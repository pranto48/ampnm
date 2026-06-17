package main

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"syscall"
	"time"
	"unicode/utf16"
	"unsafe"
)

// ─────────────────────────────────────────────────────────────────────────────
// Win32 API Constants
// ─────────────────────────────────────────────────────────────────────────────
const (
	WM_CREATE           = 0x0001
	WM_DESTROY          = 0x0002
	WM_SIZE             = 0x0005
	WM_PAINT            = 0x000F
	WM_COMMAND          = 0x0111
	WM_CLOSE            = 0x0010
	WM_TIMER            = 0x0113
	WM_SETFONT          = 0x0030
	WM_CTLCOLORSTATIC   = 0x0138 // Sent before painting a STATIC control
	WM_TRAY             = 0x8001 // Custom tray callback

	WS_OVERLAPPEDWINDOW = 0x00CF0000
	WS_OVERLAPPED       = 0x00000000
	WS_CAPTION          = 0x00C00000
	WS_SYSMENU          = 0x00080000
	WS_MINIMIZEBOX      = 0x00020000
	WS_CHILD            = 0x40000000
	WS_VISIBLE          = 0x10000000
	WS_BORDER           = 0x00800000
	WS_GROUP            = 0x00020000

	SS_LEFT             = 0x00000000
	ES_LEFT             = 0x0000
	ES_AUTOHSCROLL      = 0x0080
	BS_PUSHBUTTON       = 0x00000000
	BS_AUTOCHECKBOX     = 0x00000003
	BS_GROUPBOX         = 0x00000007 // Group box style

	SW_SHOW             = 5
	SW_HIDE             = 0
	SW_RESTORE          = 9

	COLOR_3DFACE        = 15

	NIM_ADD             = 0x00000000
	NIM_MODIFY          = 0x00000001
	NIM_DELETE          = 0x00000002
	NIF_MESSAGE         = 0x00000001
	NIF_ICON            = 0x00000002
	NIF_TIP             = 0x00000004
	NIF_INFO            = 0x00000010

	NIIF_INFO           = 0x00000001
	NIIF_WARNING        = 0x00000002
	NIIF_ERROR          = 0x00000003

	WM_LBUTTONDBLCLK    = 0x0203
	WM_RBUTTONUP        = 0x0205

	MF_STRING           = 0x00000000
	MF_CHECKED          = 0x00000008
	MF_UNCHECKED        = 0x00000000
	TPM_RETURNCMD       = 0x0100

	BST_CHECKED         = 1
	BM_GETCHECK         = 0x00F0
	BM_SETCHECK         = 0x00F1

	// GDI text alignment / background mode
	TRANSPARENT_MODE    = 1 // SetBkMode TRANSPARENT
)

// ─────────────────────────────────────────────────────────────────────────────
// Win32 DLL & Proc bindings
// ─────────────────────────────────────────────────────────────────────────────
var (
	moduser32   = syscall.NewLazyDLL("user32.dll")
	modkernel32 = syscall.NewLazyDLL("kernel32.dll")
	modgdi32    = syscall.NewLazyDLL("gdi32.dll")
	modshell32  = syscall.NewLazyDLL("shell32.dll")
	modadvapi32 = syscall.NewLazyDLL("advapi32.dll")

	procRegisterClassExW    = moduser32.NewProc("RegisterClassExW")
	procCreateWindowExW     = moduser32.NewProc("CreateWindowExW")
	procShowWindow          = moduser32.NewProc("ShowWindow")
	procUpdateWindow        = moduser32.NewProc("UpdateWindow")
	procGetMessageW         = moduser32.NewProc("GetMessageW")
	procTranslateMessage    = moduser32.NewProc("TranslateMessage")
	procDispatchMessageW    = moduser32.NewProc("DispatchMessageW")
	procPostQuitMessage     = moduser32.NewProc("PostQuitMessage")
	procDefWindowProcW      = moduser32.NewProc("DefWindowProcW")
	procSendMessageW        = moduser32.NewProc("SendMessageW")
	procPostMessageW        = moduser32.NewProc("PostMessageW") // NON-BLOCKING post
	procSetWindowTextW      = moduser32.NewProc("SetWindowTextW")
	procGetWindowTextW      = moduser32.NewProc("GetWindowTextW")
	procDestroyWindow       = moduser32.NewProc("DestroyWindow")
	procSetTimer            = moduser32.NewProc("SetTimer")
	procCreateMutexW        = modkernel32.NewProc("CreateMutexW")
	procGetLastError        = modkernel32.NewProc("GetLastError")
	procGetModuleHandleW    = modkernel32.NewProc("GetModuleHandleW")
	procShellNotifyIconW    = modshell32.NewProc("Shell_NotifyIconW")
	procCreatePopupMenu     = moduser32.NewProc("CreatePopupMenu")
	procAppendMenuW         = moduser32.NewProc("AppendMenuW")
	procTrackPopupMenu      = moduser32.NewProc("TrackPopupMenu")
	procGetCursorPos        = moduser32.NewProc("GetCursorPos")
	procSetForegroundWindow = moduser32.NewProc("SetForegroundWindow")
	procLoadIconW           = moduser32.NewProc("LoadIconW")
	procCreateFontIndirectW = modgdi32.NewProc("CreateFontIndirectW")
	procGetWindowRect       = moduser32.NewProc("GetWindowRect")

	// GDI color/brush procs
	procSetTextColor    = modgdi32.NewProc("SetTextColor")
	procSetBkMode       = modgdi32.NewProc("SetBkMode")
	procGetSysColorBrush = moduser32.NewProc("GetSysColorBrush")
	procCreateSolidBrush = modgdi32.NewProc("CreateSolidBrush")

	procRegOpenKeyExW   = modadvapi32.NewProc("RegOpenKeyExW")
	procRegSetValueExW  = modadvapi32.NewProc("RegSetValueExW")
	procRegDeleteValueW = modadvapi32.NewProc("RegDeleteValueW")
	procRegQueryValueExW = modadvapi32.NewProc("RegQueryValueExW")
	procRegCloseKey     = modadvapi32.NewProc("RegCloseKey")
)

// RGB helper — returns a COLORREF (0x00BBGGRR)
func rgb(r, g, b uint32) uintptr {
	return uintptr(r | (g << 8) | (b << 16))
}

// ─────────────────────────────────────────────────────────────────────────────
// Win32 Structs
// ─────────────────────────────────────────────────────────────────────────────
type WNDCLASSEXW struct {
	CbSize        uint32
	Style         uint32
	LpfnWndProc   uintptr
	CbClsExtra    int32
	CbWndExtra    int32
	HInstance     uintptr
	HIcon         uintptr
	HCursor       uintptr
	HbrBackground uintptr
	LpszMenuName  *uint16
	LpszClassName *uint16
	HIconSm       uintptr
}

type MSG struct {
	Hwnd    uintptr
	Message uint32
	Wparam  uintptr
	Lparam  uintptr
	Time    uint32
	Pt      POINT
}

type POINT struct {
	X int32
	Y int32
}

type RECT struct {
	Left   int32
	Top    int32
	Right  int32
	Bottom int32
}

type NOTIFYICONDATAW struct {
	CbSize           uint32
	Hwnd             uintptr
	UID              uint32
	UFlags           uint32
	UCallbackMessage uint32
	HIcon            uintptr
	SzTip            [128]uint16
	DwState          uint32
	DwStateMask      uint32
	SzInfo           [256]uint16
	TimeoutOrVersion uint32
	SzInfoTitle      [64]uint16
	DwInfoFlags      uint32
	GuidItem         GUID
	HBalloonIcon     uintptr
}

type GUID struct {
	Data1 uint32
	Data2 uint16
	Data3 uint16
	Data4 [8]byte
}

type LOGFONT struct {
	Height         int32
	Width          int32
	Escapement     int32
	Orientation    int32
	Weight         int32
	Italic         byte
	Underline      byte
	StrikeOut      byte
	CharSet        byte
	OutPrecision   byte
	ClipPrecision  byte
	Quality        byte
	PitchAndFamily byte
	FaceName       [32]uint16
}

// ─────────────────────────────────────────────────────────────────────────────
// GUI state
// ─────────────────────────────────────────────────────────────────────────────
var (
	hwndMain          uintptr
	hwndUrlEdit       uintptr
	hwndTokenEdit     uintptr
	hwndIntervalEdit  uintptr
	hwndStartupCheck  uintptr
	hwndTrayCheck     uintptr
	hwndStatusLabel   uintptr
	hwndLastSentLabel uintptr
	hwndIPLabel       uintptr
	hwndHostLabel     uintptr
	hwndCPULabel      uintptr
	hwndRAMLabel      uintptr

	defaultFontHwnd uintptr
	boldFontHwnd    uintptr
	trayIconHwnd    uintptr

	// Brushes for status color painting
	brushConnected uintptr
	brushFailed    uintptr
	brushTesting   uintptr
	brushBg        uintptr

	minToTrayEnabled = true
	isTestingConn    = false
	lastSuccessTime  = "Never"
	connectionStatus = "Unknown"
	errorMessage     = ""
	lastMetrics      MetricsPayload

	agentTickTicker *time.Ticker

	guiStopChan     chan struct{}
	guiStopOnce     sync.Once  // prevents double-close panics
)

// Menu Command IDs
const (
	ID_TRAY_OPEN           = 1001
	ID_TRAY_SEND_TEST      = 1002
	ID_TRAY_STARTUP_TOGGLE = 1003
	ID_TRAY_EXIT           = 1004

	ID_BTN_SAVE      = 2001
	ID_BTN_SEND_TEST = 2002
	ID_BTN_HIDE      = 2003
)

// ─────────────────────────────────────────────────────────────────────────────
// String helpers
// ─────────────────────────────────────────────────────────────────────────────
func stringToUTF16Ptr(s string) *uint16 {
	return &utf16.Encode([]rune(s + "\x00"))[0]
}

func getWindowText(hwnd uintptr) string {
	var buf [512]uint16
	procGetWindowTextW.Call(hwnd, uintptr(unsafe.Pointer(&buf[0])), uintptr(len(buf)))
	return strings.TrimSpace(syscall.UTF16ToString(buf[:]))
}

func setWindowText(hwnd uintptr, text string) {
	procSetWindowTextW.Call(hwnd, uintptr(unsafe.Pointer(stringToUTF16Ptr(text))))
}

func getCheckState(hwnd uintptr) bool {
	res, _, _ := procSendMessageW.Call(hwnd, BM_GETCHECK, 0, 0)
	return res == BST_CHECKED
}

func setCheckState(hwnd uintptr, checked bool) {
	state := uintptr(0)
	if checked {
		state = BST_CHECKED
	}
	procSendMessageW.Call(hwnd, BM_SETCHECK, state, 0)
}

// ─────────────────────────────────────────────────────────────────────────────
// Registry helpers for startup
// ─────────────────────────────────────────────────────────────────────────────
const RunKeyPath = `Software\Microsoft\Windows\CurrentVersion\Run`
const StartupValueName = "AMPNMAgent"

func getExecutablePath() string {
	exe, err := os.Executable()
	if err != nil {
		return ""
	}
	return exe
}

func GetStartupRegistry() bool {
	var hKey uintptr
	status, _, _ := procRegOpenKeyExW.Call(
		0x80000001,
		uintptr(unsafe.Pointer(stringToUTF16Ptr(RunKeyPath))),
		0,
		0x20019,
		uintptr(unsafe.Pointer(&hKey)),
	)
	if status != 0 {
		return false
	}
	defer procRegCloseKey.Call(hKey)

	var buf [512]uint16
	var size uint32 = uint32(len(buf) * 2)
	status, _, _ = procRegQueryValueExW.Call(
		hKey,
		uintptr(unsafe.Pointer(stringToUTF16Ptr(StartupValueName))),
		0,
		0,
		uintptr(unsafe.Pointer(&buf[0])),
		uintptr(unsafe.Pointer(&size)),
	)
	return status == 0
}

func SetStartupRegistry(enable bool) error {
	var hKey uintptr
	status, _, _ := procRegOpenKeyExW.Call(
		0x80000001,
		uintptr(unsafe.Pointer(stringToUTF16Ptr(RunKeyPath))),
		0,
		0x20006,
		uintptr(unsafe.Pointer(&hKey)),
	)
	if status != 0 {
		return fmt.Errorf("failed to open registry key: exit code %d", status)
	}
	defer procRegCloseKey.Call(hKey)

	if enable {
		exePath := getExecutablePath()
		if exePath == "" {
			return fmt.Errorf("could not get current executable path")
		}
		cmdStr := `"` + exePath + `"`
		utf16Val := utf16.Encode([]rune(cmdStr + "\x00"))
		status, _, _ = procRegSetValueExW.Call(
			hKey,
			uintptr(unsafe.Pointer(stringToUTF16Ptr(StartupValueName))),
			0,
			1, // REG_SZ
			uintptr(unsafe.Pointer(&utf16Val[0])),
			uintptr(len(utf16Val)*2),
		)
		if status != 0 {
			return fmt.Errorf("failed to set registry value: exit code %d", status)
		}
	} else {
		procRegDeleteValueW.Call(
			hKey,
			uintptr(unsafe.Pointer(stringToUTF16Ptr(StartupValueName))),
		)
	}
	return nil
}

// ─────────────────────────────────────────────────────────────────────────────
// Config I/O
// ─────────────────────────────────────────────────────────────────────────────
func saveConfig(cfg Config) error {
	configDir := filepath.Join(os.Getenv("ProgramData"), "AMPNM-Agent")
	if err := os.MkdirAll(configDir, 0755); err != nil {
		configDir = filepath.Join(os.Getenv("APPDATA"), "AMPNM-Agent")
		_ = os.MkdirAll(configDir, 0755)
	}

	configPath := filepath.Join(configDir, "config.json")
	file, err := os.Create(configPath)
	if err != nil {
		configPath = filepath.Join(os.Getenv("APPDATA"), "AMPNM-Agent", "config.json")
		file, err = os.Create(configPath)
		if err != nil {
			return err
		}
	}
	defer file.Close()

	encoder := json.NewEncoder(file)
	encoder.SetIndent("", "  ")
	return encoder.Encode(cfg)
}

// ─────────────────────────────────────────────────────────────────────────────
// Safe channel close (prevents double-close panics)
// ─────────────────────────────────────────────────────────────────────────────
func safeCloseGuiStop() {
	guiStopOnce.Do(func() {
		if guiStopChan != nil {
			close(guiStopChan)
		}
	})
}

// ─────────────────────────────────────────────────────────────────────────────
// Background polling loop
// ─────────────────────────────────────────────────────────────────────────────
func runGuiActivePolling(cfg Config) {
	if agentTickTicker != nil {
		agentTickTicker.Stop()
	}
	// Reset stop channel for the new polling goroutine
	guiStopChan = make(chan struct{})
	guiStopOnce = sync.Once{} // reset once-guard for new channel
	agentTickTicker = time.NewTicker(time.Duration(cfg.Interval) * time.Second)

	// Immediate initial check
	go func() {
		err := TestConnection(cfg.ServerUrl, cfg.AgentToken)
		if err == nil {
			connectionStatus = "Connected"
			lastSuccessTime = time.Now().Format("2006-01-02 15:04:05")
			errorMessage = ""
			metrics, errCollect := collectMetrics()
			if errCollect == nil {
				lastMetrics = metrics
				transmitActiveTelemetry(cfg, metrics)
			}
		} else {
			connectionStatus = "Failed"
			errorMessage = err.Error()
		}
		triggerGuiStatusUpdate()
	}()

	// Periodic polling loop
	go func() {
		localStop := guiStopChan
		for {
			select {
			case <-agentTickTicker.C:
				cfgData := loadConfig()
				metrics, err := collectMetrics()
				if err == nil {
					lastMetrics = metrics
					err = TestConnection(cfgData.ServerUrl, cfgData.AgentToken)
					if err == nil {
						connectionStatus = "Connected"
						lastSuccessTime = time.Now().Format("2006-01-02 15:04:05")
						errorMessage = ""
						transmitActiveTelemetry(cfgData, metrics)
					} else {
						connectionStatus = "Failed"
						errorMessage = err.Error()
					}
				} else {
					connectionStatus = "Failed"
					errorMessage = err.Error()
				}
				triggerGuiStatusUpdate()
			case <-localStop:
				return
			}
		}
	}()
}

// triggerGuiStatusUpdate uses PostMessage (non-blocking) to avoid UI thread deadlock / hang
func triggerGuiStatusUpdate() {
	if hwndMain != 0 {
		// PostMessageW is fire-and-forget — never blocks the calling goroutine
		procPostMessageW.Call(hwndMain, WM_TIMER, 1, 0)
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// System Tray helpers
// ─────────────────────────────────────────────────────────────────────────────
func setTrayIcon(hwnd uintptr, action uint32, tip string, infoTitle string, infoText string, infoFlags uint32) {
	var nid NOTIFYICONDATAW
	nid.CbSize = uint32(unsafe.Sizeof(nid))
	nid.Hwnd = hwnd
	nid.UID = 1
	nid.UFlags = NIF_MESSAGE | NIF_ICON | NIF_TIP
	nid.UCallbackMessage = WM_TRAY
	nid.HIcon = trayIconHwnd

	tipUTF16 := utf16.Encode([]rune(tip + "\x00"))
	copy(nid.SzTip[:], tipUTF16)

	if infoText != "" {
		nid.UFlags |= NIF_INFO
		infoUTF16 := utf16.Encode([]rune(infoText + "\x00"))
		copy(nid.SzInfo[:], infoUTF16)
		titleUTF16 := utf16.Encode([]rune(infoTitle + "\x00"))
		copy(nid.SzInfoTitle[:], titleUTF16)
		nid.DwInfoFlags = infoFlags
	}

	procShellNotifyIconW.Call(uintptr(action), uintptr(unsafe.Pointer(&nid)))
}

func showTrayContextMenu(hwnd uintptr) {
	hMenu, _, _ := procCreatePopupMenu.Call()
	if hMenu == 0 {
		return
	}

	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_OPEN, uintptr(unsafe.Pointer(stringToUTF16Ptr("Open Control Panel"))))
	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_SEND_TEST, uintptr(unsafe.Pointer(stringToUTF16Ptr("Send Test Telemetry Now"))))

	startupChecked := GetStartupRegistry()
	flag := uintptr(MF_STRING)
	if startupChecked {
		flag |= MF_CHECKED
	}
	procAppendMenuW.Call(hMenu, flag, ID_TRAY_STARTUP_TOGGLE, uintptr(unsafe.Pointer(stringToUTF16Ptr("Run on Windows Startup"))))

	procAppendMenuW.Call(hMenu, 0x0800, 0, 0) // MF_SEPARATOR

	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_EXIT, uintptr(unsafe.Pointer(stringToUTF16Ptr("Exit"))))

	var pt POINT
	procGetCursorPos.Call(uintptr(unsafe.Pointer(&pt)))

	procSetForegroundWindow.Call(hwnd)
	cmd, _, _ := procTrackPopupMenu.Call(hMenu, TPM_RETURNCMD, uintptr(pt.X), uintptr(pt.Y), 0, hwnd, 0)

	switch cmd {
	case ID_TRAY_OPEN:
		procShowWindow.Call(hwnd, SW_RESTORE)
		procSetForegroundWindow.Call(hwnd)
	case ID_TRAY_SEND_TEST:
		sendTestTelemetryFromGui()
	case ID_TRAY_STARTUP_TOGGLE:
		SetStartupRegistry(!startupChecked)
		if hwndStartupCheck != 0 {
			setCheckState(hwndStartupCheck, !startupChecked)
		}
		msg := "Startup registration enabled."
		if startupChecked {
			msg = "Startup registration disabled."
		}
		setTrayIcon(hwnd, NIM_MODIFY, "AMPNM Telemetry Agent", "Startup Modified", msg, NIIF_INFO)
	case ID_TRAY_EXIT:
		doExit(hwnd)
	}
}

func doExit(hwnd uintptr) {
	var nid NOTIFYICONDATAW
	nid.CbSize = uint32(unsafe.Sizeof(nid))
	nid.Hwnd = hwnd
	nid.UID = 1
	procShellNotifyIconW.Call(NIM_DELETE, uintptr(unsafe.Pointer(&nid)))
	if agentTickTicker != nil {
		agentTickTicker.Stop()
	}
	safeCloseGuiStop()
	procDestroyWindow.Call(hwnd)
}

// ─────────────────────────────────────────────────────────────────────────────
// Test telemetry from GUI
// ─────────────────────────────────────────────────────────────────────────────
func sendTestTelemetryFromGui() {
	if isTestingConn {
		return
	}
	isTestingConn = true
	connectionStatus = "Testing..."
	triggerGuiStatusUpdate()

	go func() {
		cfg := loadConfig()
		err := TestConnection(cfg.ServerUrl, cfg.AgentToken)
		isTestingConn = false
		if err == nil {
			connectionStatus = "Connected"
			lastSuccessTime = time.Now().Format("2006-01-02 15:04:05")
			errorMessage = ""
			metrics, errCollect := collectMetrics()
			if errCollect == nil {
				lastMetrics = metrics
				transmitActiveTelemetry(cfg, metrics)
			}
			setTrayIcon(hwndMain, NIM_MODIFY, "AMPNM Telemetry Agent", "Connection Successful",
				"Successfully connected and pushed telemetry data to AMPNM server.", NIIF_INFO)
		} else {
			connectionStatus = "Failed"
			errorMessage = err.Error()
			setTrayIcon(hwndMain, NIM_MODIFY, "AMPNM Telemetry Agent", "Connection Failed",
				"Error: "+err.Error(), NIIF_ERROR)
		}
		triggerGuiStatusUpdate()
	}()
}

// ─────────────────────────────────────────────────────────────────────────────
// WM_CTLCOLORSTATIC handler — returns appropriate text color based on status
// ─────────────────────────────────────────────────────────────────────────────
func handleCtlColorStatic(hdc uintptr, hwndCtrl uintptr) uintptr {
	if hwndCtrl == hwndStatusLabel {
		switch {
		case connectionStatus == "Connected":
			procSetTextColor.Call(hdc, rgb(34, 197, 94))   // green
		case connectionStatus == "Failed":
			procSetTextColor.Call(hdc, rgb(239, 68, 68))   // red
		default:
			procSetTextColor.Call(hdc, rgb(217, 119, 6))   // orange (Testing / Unknown)
		}
		procSetBkMode.Call(hdc, TRANSPARENT_MODE)
		bg, _, _ := procGetSysColorBrush.Call(COLOR_3DFACE)
		return bg
	}
	return 0
}

// ─────────────────────────────────────────────────────────────────────────────
// Window procedure — must never block!
// ─────────────────────────────────────────────────────────────────────────────
func wndProc(hwnd uintptr, msg uint32, wparam uintptr, lparam uintptr) uintptr {
	switch msg {

	// ── Window Created ────────────────────────────────────────────────────────
	case WM_CREATE:
		hwndMain = hwnd

		// Default system icon for tray
		hIcon, _, _ := procLoadIconW.Call(0, 32512) // IDI_APPLICATION
		if hIcon != 0 {
			trayIconHwnd = hIcon
		}

		// ── Title Banner ──────────────────────────────────────────────────────
		hwndTitle := createLabel(hwnd, "AMPNM Telemetry Agent", 20, 12, 420, 26)
		procSendMessageW.Call(hwndTitle, WM_SETFONT, boldFontHwnd, 1)
		createLabel(hwnd, "Windows Network Monitor Agent Control Panel", 20, 36, 420, 16)

		// ── GROUP: Connection Settings ────────────────────────────────────────
		createGroupBox(hwnd, "Connection Settings", 10, 58, 440, 120)

		createLabel(hwnd, "AMPNM Server URL:", 22, 76, 180, 16)
		hwndUrlEdit = createEdit(hwnd, "", 22, 92, 415, 24)

		createLabel(hwnd, "Agent Enrollment Token:", 22, 120, 200, 16)
		hwndTokenEdit = createEdit(hwnd, "", 22, 136, 415, 24)

		createLabel(hwnd, "Polling Interval (seconds):", 22, 164, 200, 16)
		hwndIntervalEdit = createEdit(hwnd, "", 22, 180, 80, 22)

		// ── GROUP: Application Options ────────────────────────────────────────
		createGroupBox(hwnd, "Application Options", 10, 192, 440, 64)

		hwndStartupCheck = createCheckbox(hwnd, "Start automatically on Windows boot", 22, 208, 300, 20)
		setCheckState(hwndStartupCheck, GetStartupRegistry())

		hwndTrayCheck = createCheckbox(hwnd, "Minimize to System Tray on close", 22, 230, 300, 20)
		setCheckState(hwndTrayCheck, minToTrayEnabled)

		// ── GROUP: Status ─────────────────────────────────────────────────────
		createGroupBox(hwnd, "Status", 10, 268, 440, 110)

		hwndStatusLabel   = createLabel(hwnd, "Status: Loading...", 22, 284, 415, 20)
		hwndLastSentLabel = createLabel(hwnd, "Last Update Pushed: Never", 22, 308, 415, 16)
		hwndHostLabel     = createLabel(hwnd, "Host: —", 22, 328, 210, 16)
		hwndIPLabel       = createLabel(hwnd, "IP: —", 240, 328, 200, 16)
		hwndCPULabel      = createLabel(hwnd, "CPU: —", 22, 348, 130, 16)
		hwndRAMLabel      = createLabel(hwnd, "RAM: —", 160, 348, 130, 16)

		// ── Buttons ───────────────────────────────────────────────────────────
		btnSave := createButton(hwnd, "💾  Save Settings",    16, 392, 130, 30, ID_BTN_SAVE)
		btnTest := createButton(hwnd, "🔗  Send Test Now",   154, 392, 130, 30, ID_BTN_SEND_TEST)
		btnHide := createButton(hwnd, "▼  Minimize to Tray", 292, 392, 158, 30, ID_BTN_HIDE)

		// Apply fonts to all controls
		for _, h := range []uintptr{
			hwndUrlEdit, hwndTokenEdit, hwndIntervalEdit,
			hwndStartupCheck, hwndTrayCheck,
			hwndStatusLabel, hwndLastSentLabel,
			hwndHostLabel, hwndIPLabel, hwndCPULabel, hwndRAMLabel,
			btnSave, btnTest, btnHide,
		} {
			procSendMessageW.Call(h, WM_SETFONT, defaultFontHwnd, 1)
		}
		procSendMessageW.Call(hwndTitle, WM_SETFONT, boldFontHwnd, 1)

		// Load initial config values into controls
		cfg := loadConfig()
		setWindowText(hwndUrlEdit, cfg.ServerUrl)
		setWindowText(hwndTokenEdit, cfg.AgentToken)
		setWindowText(hwndIntervalEdit, strconv.Itoa(cfg.Interval))

		// Tray icon setup
		setTrayIcon(hwnd, NIM_ADD, "AMPNM Telemetry Agent",
			"AMPNM Telemetry Agent Running",
			"Double-click the tray icon to open configuration.",
			NIIF_INFO)

		// 1-second UI refresh timer (safe — just redraws labels)
		procSetTimer.Call(hwnd, 1, 1000, 0)

		// Start background polling (fully async, no GUI-thread blocking)
		runGuiActivePolling(cfg)

	// ── Periodic UI Refresh ───────────────────────────────────────────────────
	case WM_TIMER:
		var statusText string
		if isTestingConn {
			statusText = "⏳  Status: Testing Connection..."
		} else {
			switch connectionStatus {
			case "Connected":
				statusText = "✅  Status: Connected"
			case "Failed":
				statusText = "❌  Status: Failed"
				if errorMessage != "" {
					short := errorMessage
					if len(short) > 70 {
						short = short[:70] + "..."
					}
					statusText += "  (" + short + ")"
				}
			default:
				statusText = "⚠️  Status: " + connectionStatus
			}
		}
		setWindowText(hwndStatusLabel, statusText)
		setWindowText(hwndLastSentLabel, "Last Update Pushed: "+lastSuccessTime)

		// Update metric labels if we have data
		if lastMetrics.HostName != "" {
			setWindowText(hwndHostLabel, "Host: "+lastMetrics.HostName)
			setWindowText(hwndIPLabel, "IP: "+lastMetrics.HostIP)
			setWindowText(hwndCPULabel, fmt.Sprintf("CPU: %.1f%%", lastMetrics.CPUPercent))
			setWindowText(hwndRAMLabel, fmt.Sprintf("RAM: %.1f%%", lastMetrics.MemoryPercent))
		}

		// Force status label repaint so WM_CTLCOLORSTATIC is resent
		if hwndStatusLabel != 0 {
			procPostMessageW.Call(hwndStatusLabel, WM_PAINT, 0, 0)
		}

	// ── Color the status label ────────────────────────────────────────────────
	case WM_CTLCOLORSTATIC:
		result := handleCtlColorStatic(wparam, lparam)
		if result != 0 {
			return result
		}
		// Fall through to DefWindowProc for other statics
		res, _, _ := procDefWindowProcW.Call(hwnd, uintptr(msg), wparam, lparam)
		return res

	// ── Button Commands ───────────────────────────────────────────────────────
	case WM_COMMAND:
		controlID := wparam & 0xFFFF
		switch controlID {

		case ID_BTN_SAVE:
			cfg := loadConfig()
			serverUrl := getWindowText(hwndUrlEdit)
			if serverUrl != "" && !strings.HasSuffix(serverUrl, "/") && !strings.HasSuffix(serverUrl, ".php") {
				serverUrl += "/"
			}
			setWindowText(hwndUrlEdit, serverUrl)
			cfg.ServerUrl = serverUrl
			cfg.AgentToken = getWindowText(hwndTokenEdit)
			intervalInt, err := strconv.Atoi(getWindowText(hwndIntervalEdit))
			if err == nil && intervalInt > 0 {
				cfg.Interval = intervalInt
			}
			minToTrayEnabled = getCheckState(hwndTrayCheck)
			SetStartupRegistry(getCheckState(hwndStartupCheck))

			errSave := saveConfig(cfg)
			if errSave != nil {
				// Show message in a goroutine to never block the GUI thread
				go showMessageBox("Error Saving Settings", "Could not write config.json: "+errSave.Error())
			} else {
				setTrayIcon(hwnd, NIM_MODIFY, "AMPNM Telemetry Agent",
					"Settings Saved", "Agent configuration updated successfully.", NIIF_INFO)
				// Restart polling with new settings
				safeCloseGuiStop()
				runGuiActivePolling(cfg)
			}

		case ID_BTN_SEND_TEST:
			sendTestTelemetryFromGui()

		case ID_BTN_HIDE:
			procShowWindow.Call(hwnd, SW_HIDE)
		}

	// ── Minimize to Tray ──────────────────────────────────────────────────────
	case WM_SIZE:
		if wparam == 1 { // SIZE_MINIMIZED
			if minToTrayEnabled {
				procShowWindow.Call(hwnd, SW_HIDE)
			}
		}

	// ── Tray icon events ──────────────────────────────────────────────────────
	case WM_TRAY:
		event := lparam & 0xFFFF
		if event == WM_LBUTTONDBLCLK {
			procShowWindow.Call(hwnd, SW_RESTORE)
			procSetForegroundWindow.Call(hwnd)
		} else if event == WM_RBUTTONUP {
			showTrayContextMenu(hwnd)
		}

	// ── Close button ──────────────────────────────────────────────────────────
	case WM_CLOSE:
		if minToTrayEnabled {
			procShowWindow.Call(hwnd, SW_HIDE)
		} else {
			doExit(hwnd)
		}

	// ── Window Destroyed ──────────────────────────────────────────────────────
	case WM_DESTROY:
		procPostQuitMessage.Call(0)

	default:
		res, _, _ := procDefWindowProcW.Call(hwnd, uintptr(msg), wparam, lparam)
		return res
	}
	return 0
}

// ─────────────────────────────────────────────────────────────────────────────
// Control creation helpers
// ─────────────────────────────────────────────────────────────────────────────
func createLabel(parent uintptr, text string, x, y, w, h int) uintptr {
	hwnd, _, _ := procCreateWindowExW.Call(
		0,
		uintptr(unsafe.Pointer(stringToUTF16Ptr("STATIC"))),
		uintptr(unsafe.Pointer(stringToUTF16Ptr(text))),
		WS_CHILD|WS_VISIBLE|SS_LEFT,
		uintptr(x), uintptr(y), uintptr(w), uintptr(h),
		parent, 0, 0, 0,
	)
	return hwnd
}

func createEdit(parent uintptr, text string, x, y, w, h int) uintptr {
	hwnd, _, _ := procCreateWindowExW.Call(
		0,
		uintptr(unsafe.Pointer(stringToUTF16Ptr("EDIT"))),
		uintptr(unsafe.Pointer(stringToUTF16Ptr(text))),
		WS_CHILD|WS_VISIBLE|WS_BORDER|ES_LEFT|ES_AUTOHSCROLL,
		uintptr(x), uintptr(y), uintptr(w), uintptr(h),
		parent, 0, 0, 0,
	)
	return hwnd
}

func createCheckbox(parent uintptr, text string, x, y, w, h int) uintptr {
	hwnd, _, _ := procCreateWindowExW.Call(
		0,
		uintptr(unsafe.Pointer(stringToUTF16Ptr("BUTTON"))),
		uintptr(unsafe.Pointer(stringToUTF16Ptr(text))),
		WS_CHILD|WS_VISIBLE|BS_AUTOCHECKBOX,
		uintptr(x), uintptr(y), uintptr(w), uintptr(h),
		parent, 0, 0, 0,
	)
	return hwnd
}

func createButton(parent uintptr, text string, x, y, w, h int, id uintptr) uintptr {
	hwnd, _, _ := procCreateWindowExW.Call(
		0,
		uintptr(unsafe.Pointer(stringToUTF16Ptr("BUTTON"))),
		uintptr(unsafe.Pointer(stringToUTF16Ptr(text))),
		WS_CHILD|WS_VISIBLE|BS_PUSHBUTTON,
		uintptr(x), uintptr(y), uintptr(w), uintptr(h),
		parent, id, 0, 0,
	)
	return hwnd
}

// createGroupBox draws a labeled border section (Win32 BUTTON + BS_GROUPBOX)
func createGroupBox(parent uintptr, text string, x, y, w, h int) uintptr {
	hwnd, _, _ := procCreateWindowExW.Call(
		0,
		uintptr(unsafe.Pointer(stringToUTF16Ptr("BUTTON"))),
		uintptr(unsafe.Pointer(stringToUTF16Ptr(text))),
		WS_CHILD|WS_VISIBLE|BS_GROUPBOX,
		uintptr(x), uintptr(y), uintptr(w), uintptr(h),
		parent, 0, 0, 0,
	)
	if hwnd != 0 {
		procSendMessageW.Call(hwnd, WM_SETFONT, defaultFontHwnd, 1)
	}
	return hwnd
}

// ─────────────────────────────────────────────────────────────────────────────
// Font initialisation
// ─────────────────────────────────────────────────────────────────────────────
func initGuiFonts() {
	var lf LOGFONT
	copy(lf.FaceName[:], utf16.Encode([]rune("Segoe UI\x00")))
	lf.Height = -13 // ~10pt
	lf.Weight = 400 // FW_NORMAL
	f, _, _ := procCreateFontIndirectW.Call(uintptr(unsafe.Pointer(&lf)))
	defaultFontHwnd = f

	var lfBold LOGFONT
	copy(lfBold.FaceName[:], utf16.Encode([]rune("Segoe UI\x00")))
	lfBold.Height = -18 // ~14pt
	lfBold.Weight = 700 // FW_BOLD
	fb, _, _ := procCreateFontIndirectW.Call(uintptr(unsafe.Pointer(&lfBold)))
	boldFontHwnd = fb
}

// ─────────────────────────────────────────────────────────────────────────────
// ShowGUI — main GUI entry point (called from main.go)
// ─────────────────────────────────────────────────────────────────────────────
func ShowGUI() {
	// Single-instance mutex guard
	hMutex, _, _ := procCreateMutexW.Call(0, 1, uintptr(unsafe.Pointer(stringToUTF16Ptr("AMPNMAgentGUI-Mutex"))))
	errCode, _, _ := procGetLastError.Call()
	if hMutex != 0 && errCode == 183 { // ERROR_ALREADY_EXISTS
		go showMessageBox("AMPNM Agent",
			"Control Panel is already running.\nLook for the agent icon in your system tray.")
		return
	}

	initGuiFonts()

	inst, _, _ := procGetModuleHandleW.Call(0)
	className := "AMPNMAgentGUI"

	var wc WNDCLASSEXW
	wc.CbSize = uint32(unsafe.Sizeof(wc))
	wc.LpfnWndProc = syscall.NewCallback(wndProc)
	wc.HInstance = inst
	wc.HbrBackground = COLOR_3DFACE + 1
	wc.LpszClassName = stringToUTF16Ptr(className)

	res, _, _ := procRegisterClassExW.Call(uintptr(unsafe.Pointer(&wc)))
	if res == 0 {
		return
	}

	// Centre window on screen
	procGetSystemMetrics := moduser32.NewProc("GetSystemMetrics")
	screenWidth := uintptr(1920)
	screenHeight := uintptr(1080)
	if procGetSystemMetrics.Find() == nil {
		w, _, _ := procGetSystemMetrics.Call(0)
		h, _, _ := procGetSystemMetrics.Call(1)
		if w > 0 {
			screenWidth = w
		}
		if h > 0 {
			screenHeight = h
		}
	}

	winW := uintptr(468)
	winH := uintptr(470)
	posX := (screenWidth - winW) / 2
	posY := (screenHeight - winH) / 2

	hwnd, _, _ := procCreateWindowExW.Call(
		0,
		uintptr(unsafe.Pointer(stringToUTF16Ptr(className))),
		uintptr(unsafe.Pointer(stringToUTF16Ptr("AMPNM Windows Telemetry Agent"))),
		WS_OVERLAPPED|WS_CAPTION|WS_SYSMENU|WS_MINIMIZEBOX,
		posX, posY, winW, winH,
		0, 0, inst, 0,
	)

	if hwnd == 0 {
		return
	}

	procShowWindow.Call(hwnd, SW_SHOW)
	procUpdateWindow.Call(hwnd)

	// Standard Win32 message pump — this is the ONLY code that should
	// touch GUI objects; background goroutines communicate via PostMessage only.
	var msg MSG
	for {
		r, _, _ := procGetMessageW.Call(uintptr(unsafe.Pointer(&msg)), 0, 0, 0)
		if r == 0 {
			break
		}
		procTranslateMessage.Call(uintptr(unsafe.Pointer(&msg)))
		procDispatchMessageW.Call(uintptr(unsafe.Pointer(&msg)))
	}
}

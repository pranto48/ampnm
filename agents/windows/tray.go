package main

import (
	"fmt"
	"os/exec"
	"syscall"
	"time"
	"unicode/utf16"
	"unsafe"
)

// ─────────────────────────────────────────────────────────────────────────────
// Win32 API Constants & Structs for System Tray
// ─────────────────────────────────────────────────────────────────────────────
const (
	WM_USER     = 0x0400
	WM_TRAY     = WM_USER + 1
	WM_DESTROY  = 0x0002
	WM_LBUTTONDBLCLK = 0x0203
	WM_RBUTTONUP     = 0x0205
	WM_COMMAND  = 0x0111

	NIM_ADD     = 0x00000000
	NIM_MODIFY  = 0x00000001
	NIM_DELETE  = 0x00000002
	NIF_MESSAGE = 0x00000001
	NIF_ICON    = 0x00000002
	NIF_TIP     = 0x00000004
	NIF_INFO    = 0x00000010

	WS_OVERLAPPEDWINDOW = 0x00CF0000
	CW_USEDEFAULT       = 0x80000000

	IDI_APPLICATION = 32512

	MF_STRING    = 0x00000000
	TPM_BOTTOMALIGN = 0x0020
	TPM_LEFTALIGN   = 0x0000
)

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

type POINT struct {
	X int32
	Y int32
}

type MSG struct {
	Hwnd    uintptr
	Message uint32
	Wparam  uintptr
	Lparam  uintptr
	Time    uint32
	Pt      POINT
}

var (
	user32               = syscall.NewLazyDLL("user32.dll")
	shell32              = syscall.NewLazyDLL("shell32.dll")
	procShellNotifyIconW = shell32.NewProc("Shell_NotifyIconW")

	procRegisterClassExW = user32.NewProc("RegisterClassExW")
	procCreateWindowExW  = user32.NewProc("CreateWindowExW")
	procDefWindowProcW   = user32.NewProc("DefWindowProcW")
	procGetMessageW      = user32.NewProc("GetMessageW")
	procTranslateMessage = user32.NewProc("TranslateMessage")
	procDispatchMessageW = user32.NewProc("DispatchMessageW")
	procPostQuitMessage  = user32.NewProc("PostQuitMessage")
	procLoadIconW        = user32.NewProc("LoadIconW")
	procLoadCursorW      = user32.NewProc("LoadCursorW")

	procCreatePopupMenu  = user32.NewProc("CreatePopupMenu")
	procInsertMenuW      = user32.NewProc("InsertMenuW")
	procTrackPopupMenu   = user32.NewProc("TrackPopupMenu")
	procGetCursorPos     = user32.NewProc("GetCursorPos")
	procSetForegroundWindow = user32.NewProc("SetForegroundWindow")
	procDestroyMenu      = user32.NewProc("DestroyMenu")

	kernel32         = syscall.NewLazyDLL("kernel32.dll")
	procGetModuleHandleW = kernel32.NewProc("GetModuleHandleW")
	procCreateMutexW = kernel32.NewProc("CreateMutexW")

	procShellExecuteW    = shell32.NewProc("ShellExecuteW")
)

var (
	trayHwnd uintptr
	trayIcon uintptr
)

func openBrowser(url string) {
	verbPtr, _ := syscall.UTF16PtrFromString("open")
	urlPtr, _ := syscall.UTF16PtrFromString(url)
	ret, _, _ := procShellExecuteW.Call(0, uintptr(unsafe.Pointer(verbPtr)), uintptr(unsafe.Pointer(urlPtr)), 0, 0, 1) // 1 = SW_SHOWNORMAL
	if ret > 32 {
		return
	}
	// Fallback to command line
	exec.Command("cmd", "/c", "start", url).Start()
}

func setTrayIcon(hwnd uintptr, action uint32, title, text, infoTitle, infoText string) {
	var nid NOTIFYICONDATAW
	nid.CbSize = uint32(unsafe.Sizeof(nid))
	nid.Hwnd = hwnd
	nid.UID = 1
	nid.UFlags = NIF_MESSAGE | NIF_ICON | NIF_TIP
	nid.UCallbackMessage = WM_TRAY
	nid.HIcon = trayIcon

	copy(nid.SzTip[:], utf16.Encode([]rune(text+"\x00")))

	if infoText != "" {
		nid.UFlags |= NIF_INFO
		copy(nid.SzInfo[:], utf16.Encode([]rune(infoText+"\x00")))
		copy(nid.SzInfoTitle[:], utf16.Encode([]rune(infoTitle+"\x00")))
		nid.DwInfoFlags = 1 // NIIF_INFO
	}

	procShellNotifyIconW.Call(uintptr(action), uintptr(unsafe.Pointer(&nid)))
}

func trayWndProc(hwnd uintptr, msg uint32, wParam, lParam uintptr) uintptr {
	switch msg {
	case WM_TRAY:
		switch lParam {
		case WM_LBUTTONDBLCLK:
			openBrowser("http://127.0.0.1:22660")
		case WM_RBUTTONUP:
			var pt POINT
			procGetCursorPos.Call(uintptr(unsafe.Pointer(&pt)))
			procSetForegroundWindow.Call(hwnd)
			
			hMenu, _, _ := procCreatePopupMenu.Call()
			procInsertMenuW.Call(hMenu, 0, MF_STRING, 1, uintptr(unsafe.Pointer(stringToUTF16Ptr("Open Dashboard"))))
			procInsertMenuW.Call(hMenu, 1, MF_STRING, 2, uintptr(unsafe.Pointer(stringToUTF16Ptr("Exit"))))

			procTrackPopupMenu.Call(hMenu, TPM_BOTTOMALIGN|TPM_LEFTALIGN, uintptr(pt.X), uintptr(pt.Y), 0, hwnd, 0)
			procDestroyMenu.Call(hMenu)
		}
	case WM_COMMAND:
		cmd := wParam & 0xFFFF
		if cmd == 1 {
			openBrowser("http://127.0.0.1:22660")
		} else if cmd == 2 {
			procPostQuitMessage.Call(0)
		}
	case WM_DESTROY:
		setTrayIcon(hwnd, NIM_DELETE, "", "", "", "")
		procPostQuitMessage.Call(0)
	default:
		ret, _, _ := procDefWindowProcW.Call(hwnd, uintptr(msg), wParam, lParam)
		return ret
	}
	return 0
}

func ShowGUI() {
	// Single instance check
	_, err := checkSingleInstance()
	if err != nil {
		// Another instance is already running! Open dashboard and exit.
		openBrowser("http://127.0.0.1:22660")
		return
	}

	hInstance, _, _ := procGetModuleHandleW.Call(0)

	// Try to load icon resource bundled by go-winres (resource string name "APP"), fallback to default app icon
	hIcon, _, _ := procLoadIconW.Call(hInstance, uintptr(unsafe.Pointer(stringToUTF16Ptr("APP"))))
	if hIcon == 0 {
		hIcon, _, _ = procLoadIconW.Call(0, IDI_APPLICATION)
	}
	trayIcon = hIcon

	className := stringToUTF16Ptr("AMPNMAgentTrayClass")

	var wc WNDCLASSEXW
	wc.CbSize = uint32(unsafe.Sizeof(wc))
	wc.LpfnWndProc = syscall.NewCallback(trayWndProc)
	wc.HInstance = hInstance
	wc.LpszClassName = className

	procRegisterClassExW.Call(uintptr(unsafe.Pointer(&wc)))

	hwnd, _, _ := procCreateWindowExW.Call(
		0,
		uintptr(unsafe.Pointer(className)),
		uintptr(unsafe.Pointer(stringToUTF16Ptr("AMPNM Agent"))),
		0, 0, 0, 0, 0,
		0, 0, hInstance, 0,
	)
	trayHwnd = hwnd

	setTrayIcon(hwnd, NIM_ADD, "AMPNM Agent", "AMPNM Telemetry Agent\nDouble-click to open dashboard", "", "")

	// Start local web server
	go startWebServer()

	// Automatically open dashboard in default browser on launch
	go func() {
		time.Sleep(500 * time.Millisecond)
		openBrowser("http://127.0.0.1:22660")
	}()

	// Message Loop
	var msg MSG
	for {
		ret, _, _ := procGetMessageW.Call(uintptr(unsafe.Pointer(&msg)), 0, 0, 0)
		if ret == 0 || ret == ^uintptr(0) {
			break
		}
		procTranslateMessage.Call(uintptr(unsafe.Pointer(&msg)))
		procDispatchMessageW.Call(uintptr(unsafe.Pointer(&msg)))
	}
}

func showMessageBox(title, text string) {
	titlePtr, _ := syscall.UTF16PtrFromString(title)
	textPtr, _ := syscall.UTF16PtrFromString(text)
	procMessageBoxW := user32.NewProc("MessageBoxW")
	procMessageBoxW.Call(0, uintptr(unsafe.Pointer(textPtr)), uintptr(unsafe.Pointer(titlePtr)), 0x00000030)
}

func checkSingleInstance() (uintptr, error) {
	// Use Local mutex to avoid ERROR_ACCESS_DENIED for standard users
	namePtr, err := syscall.UTF16PtrFromString("Local\\AMPNMAgentMutex")
	if err != nil {
		return 0, err
	}
	ret, _, err := procCreateMutexW.Call(0, 0, uintptr(unsafe.Pointer(namePtr)))
	if ret == 0 {
		// If CreateMutexW fails (returns 0), we return 0, nil to let the app run (non-fatal bypass)
		return 0, nil
	}
	const ERROR_ALREADY_EXISTS = 183
	if err != nil && err.(syscall.Errno) == ERROR_ALREADY_EXISTS {
		return ret, fmt.Errorf("already running")
	}
	return ret, nil
}

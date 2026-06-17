package main

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"syscall"
	"unicode/utf16"
	"unsafe"
)

// ─────────────────────────────────────────────────────────────────────────────
// Registry helpers for startup
// ─────────────────────────────────────────────────────────────────────────────
const RunKeyPath = `Software\Microsoft\Windows\CurrentVersion\Run`
const StartupValueName = "AMPNMAgent"

var (
	advapi32             = syscall.NewLazyDLL("advapi32.dll")
	procRegOpenKeyExW    = advapi32.NewProc("RegOpenKeyExW")
	procRegQueryValueExW = advapi32.NewProc("RegQueryValueExW")
	procRegSetValueExW   = advapi32.NewProc("RegSetValueExW")
	procRegDeleteValueW  = advapi32.NewProc("RegDeleteValueW")
	procRegCloseKey      = advapi32.NewProc("RegCloseKey")
)

func stringToUTF16Ptr(s string) *uint16 {
	res, err := syscall.UTF16PtrFromString(s)
	if err != nil {
		return nil
	}
	return res
}

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
		0x80000001, // HKEY_CURRENT_USER
		uintptr(unsafe.Pointer(stringToUTF16Ptr(RunKeyPath))),
		0,
		0x20019, // KEY_READ
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
		0x20006, // KEY_WRITE
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

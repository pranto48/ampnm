package main

import (
	"bytes"
	"encoding/binary"
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"syscall"
	"time"
	"unsafe"

	"github.com/shirou/gopsutil/v3/cpu"
	"github.com/shirou/gopsutil/v3/disk"
	"github.com/shirou/gopsutil/v3/host"
	"github.com/shirou/gopsutil/v3/mem"
	netutil "github.com/shirou/gopsutil/v3/net"
	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/debug"
	"golang.org/x/sys/windows/svc/eventlog"
)

const (
	ServiceName = "AMPNMAgent"
)

var (
	elog             debug.Log
	user32           = syscall.NewLazyDLL("user32.dll")
	procMessageBoxW  = user32.NewProc("MessageBoxW")
	cpuWarningCount  = 0
)

type Config struct {
	ServerUrl     string `json:"ServerUrl"`
	AgentToken    string `json:"AgentToken"`
	Interval      int    `json:"Interval"`
	TrapperServer string `json:"TrapperServer"`
	PassivePort   int    `json:"PassivePort"`
}

type MetricsPayload struct {
	HostName      string  `json:"host_name"`
	HostIP        string  `json:"host_ip"`
	CPUPercent    float64 `json:"cpu_percent"`
	MemoryPercent float64 `json:"memory_percent"`
	MemoryTotal   float64 `json:"memory_total_gb"`
	MemoryFree    float64 `json:"memory_free_gb"`
	DiskPercent   float64 `json:"disk_percent"`
	DiskTotal     float64 `json:"disk_total_gb"`
	DiskFree      float64 `json:"disk_free_gb"`
	NetworkIn     float64 `json:"network_in_mbps"`
	NetworkOut    float64 `json:"network_out_mbps"`
	OSVersion     string  `json:"os_version"`
	Platform      string  `json:"platform"`
	UptimeSeconds uint64  `json:"uptime_seconds"`
}

func showMessageBox(title, text string) {
	// MB_SERVICE_NOTIFICATION = 0x00200000, MB_ICONWARNING = 0x00000030
	titlePtr, _ := syscall.UTF16PtrFromString(title)
	textPtr, _ := syscall.UTF16PtrFromString(text)
	procMessageBoxW.Call(
		0,
		uintptr(unsafe.Pointer(textPtr)),
		uintptr(unsafe.Pointer(titlePtr)),
		uintptr(0x00200000|0x00000030),
	)
}

func loadConfig() Config {
	configPath := filepath.Join(os.Getenv("ProgramData"), "AMPNM-Agent", "config.json")
	cfg := Config{
		ServerUrl:     "http://localhost:2266/api/agent/metrics",
		AgentToken:    "",
		Interval:      60,
		TrapperServer: "localhost:10051",
		PassivePort:   10050,
	}

	file, err := os.Open(configPath)
	if err != nil {
		return cfg
	}
	defer file.Close()

	decoder := json.NewDecoder(file)
	_ = decoder.Decode(&cfg)
	return cfg
}

func getLocalIP() string {
	addrs, err := net.InterfaceAddrs()
	if err != nil {
		return "127.0.0.1"
	}
	for _, address := range addrs {
		if ipnet, ok := address.(*net.IPNet); ok && !ipnet.IP.IsLoopback() {
			if ipnet.IP.To4() != nil {
				return ipnet.IP.String()
			}
		}
	}
	return "127.0.0.1"
}

func collectMetrics() (MetricsPayload, error) {
	payload := MetricsPayload{
		Platform: "windows",
	}

	// Hostname
	hostname, err := os.Hostname()
	if err != nil {
		hostname = "unknown-windows"
	}
	payload.HostName = hostname

	// IP Address
	payload.HostIP = getLocalIP()

	// CPU
	cpuPercents, err := cpu.Percent(time.Second, false)
	if err == nil && len(cpuPercents) > 0 {
		payload.CPUPercent = cpuPercents[0]
	}

	// Memory
	vm, err := mem.VirtualMemory()
	if err == nil {
		payload.MemoryTotal = float64(vm.Total) / 1024 / 1024 / 1024
		payload.MemoryFree = float64(vm.Available) / 1024 / 1024 / 1024
		payload.MemoryPercent = vm.UsedPercent
	}

	// Disk C:
	du, err := disk.Usage("C:")
	if err == nil {
		payload.DiskTotal = float64(du.Total) / 1024 / 1024 / 1024
		payload.DiskFree = float64(du.Free) / 1024 / 1024 / 1024
		payload.DiskPercent = du.UsedPercent
	}

	// Uptime and OS Version
	info, err := host.Info()
	if err == nil {
		payload.UptimeSeconds = info.Uptime
		payload.OSVersion = fmt.Sprintf("%s %s (Build %s)", info.Platform, info.PlatformVersion, info.KernelVersion)
	}

	// Network bytes/sec
	netStats1, err := netutil.IOCounters(false)
	if err == nil && len(netStats1) > 0 {
		time.Sleep(500 * time.Millisecond)
		netStats2, err := netutil.IOCounters(false)
		if err == nil && len(netStats2) > 0 {
			rxBytesSec := float64(netStats2[0].BytesRecv - netStats1[0].BytesRecv) * 2
			txBytesSec := float64(netStats2[0].BytesSent - netStats1[0].BytesSent) * 2

			// Convert to Mbps: (bytes * 8) / 1,000,000
			payload.NetworkIn = (rxBytesSec * 8) / 1000000
			payload.NetworkOut = (txBytesSec * 8) / 1000000
		}
	}

	return payload, nil
}

func createTrapperPacket(data []byte) []byte {
	header := []byte("ZBXD\x01")
	length := uint64(len(data))
	buf := make([]byte, 5+8+len(data))
	copy(buf[0:5], header)
	binary.LittleEndian.PutUint64(buf[5:13], length)
	copy(buf[13:], data)
	return buf
}

func transmitActiveTelemetry(cfg Config, payload MetricsPayload) {
	// 1. HTTP Endpoint transmission
	if cfg.ServerUrl != "" {
		jsonData, err := json.Marshal(payload)
		if err == nil {
			req, err := http.NewRequest("POST", cfg.ServerUrl, bytes.NewBuffer(jsonData))
			if err == nil {
				req.Header.Set("Content-Type", "application/json")
				req.Header.Set("X-Agent-Token", cfg.AgentToken)
				client := &http.Client{Timeout: 10 * time.Second}
				resp, err := client.Do(req)
				if err == nil {
					resp.Body.Close()
				}
			}
		}
	}

	// 2. TCP Trapper Port transmission
	if cfg.TrapperServer != "" {
		conn, err := net.DialTimeout("tcp", cfg.TrapperServer, 5*time.Second)
		if err == nil {
			defer conn.Close()

			// Standard Trapper JSON packet wrapping payload with token
			wrap := map[string]interface{}{
				"agent_token": cfg.AgentToken,
				"metrics":     payload,
			}
			jsonData, err := json.Marshal(wrap)
			if err == nil {
				packet := createTrapperPacket(jsonData)
				_, _ = conn.Write(packet)
			}
		}
	}
}

func handlePassiveConnection(conn net.Conn) {
	defer conn.Close()
	buf := make([]byte, 1024)
	n, err := conn.Read(buf)
	if err != nil {
		return
	}

	query := strings.TrimSpace(string(buf[:n]))
	var response []byte

	if strings.Contains(query, "agent.ping") {
		response = []byte("1")
	} else if strings.Contains(query, "metrics") || query == "" {
		metrics, err := collectMetrics()
		if err == nil {
			jsonData, _ := json.Marshal(metrics)
			response = createTrapperPacket(jsonData)
		} else {
			response = []byte(fmt.Sprintf("ERROR: %s", err))
		}
	} else {
		response = []byte("UNSUPPORTED_KEY")
	}

	_, _ = conn.Write(response)
}

func startPassiveServer(port int, stopChan chan struct{}) {
	listener, err := net.Listen("tcp", fmt.Sprintf(":%d", port))
	if err != nil {
		if elog != nil {
			elog.Error(1, fmt.Sprintf("failed to start passive server: %v", err))
		}
		return
	}
	defer listener.Close()

	go func() {
		<-stopChan
		listener.Close()
	}()

	for {
		conn, err := listener.Accept()
		if err != nil {
			select {
			case <-stopChan:
				return
			default:
				continue
			}
		}
		go handlePassiveConnection(conn)
	}
}

type agentService struct {
	stopChan chan struct{}
}

func (m *agentService) Execute(args []string, r <-chan svc.ChangeRequest, changes chan<- svc.Status) (ssec bool, errno uint32) {
	const cmdsAccepted = svc.AcceptStop | svc.AcceptShutdown
	changes <- svc.Status{State: svc.StartPending}

	m.stopChan = make(chan struct{})
	changes <- svc.Status{State: svc.Running, Accepts: cmdsAccepted}

	cfg := loadConfig()
	elog.Info(1, fmt.Sprintf("AMPNM Agent running: polling interval = %d seconds", cfg.Interval))

	// Start passive server
	go startPassiveServer(cfg.PassivePort, m.stopChan)

	// Start active polling ticker
	ticker := time.NewTicker(time.Duration(cfg.Interval) * time.Second)
	defer ticker.Stop()

	go func() {
		for {
			select {
			case <-ticker.C:
				metrics, err := collectMetrics()
				if err == nil {
					// Local alerting logic
					if metrics.CPUPercent >= 98.0 {
						cpuWarningCount++
						if cpuWarningCount >= 3 {
							go showMessageBox("AMPNM Agent Warning", "CPU utilization has exceeded 98% for three consecutive polling cycles!")
							cpuWarningCount = 0 // reset trigger to prevent alert spam
						}
					} else {
						cpuWarningCount = 0
					}

					// Send telemetry
					transmitActiveTelemetry(cfg, metrics)
				} else {
					elog.Error(2, fmt.Sprintf("Metric collection failed: %v", err))
				}
			case <-m.stopChan:
				return
			}
		}
	}()

loop:
	for {
		select {
		case c := <-r:
			switch c.Cmd {
			case svc.Interrogate:
				changes <- c.CurrentStatus
			case svc.Stop, svc.Shutdown:
				elog.Info(1, "AMPNM Agent SCM Stop/Shutdown command received")
				close(m.stopChan)
				break loop
			default:
				elog.Warning(1, fmt.Sprintf("unexpected SCM control request #%d", c))
			}
		}
	}

	changes <- svc.Status{State: svc.StopPending}
	return
}

func runService(name string, isDebug bool) {
	var err error
	if isDebug {
		elog = debug.New(name)
	} else {
		elog, err = eventlog.Open(name)
		if err != nil {
			elog = debug.New(name)
		}
	}
	defer elog.Close()

	run := svc.Run
	if isDebug {
		run = debug.Run
	}
	err = run(name, &agentService{})
	if err != nil {
		elog.Error(1, fmt.Sprintf("AMPNM Agent service failed: %v", err))
		return
	}
}

// TestConnection tests the agent configuration connection to the server
func TestConnection(serverUrl, token string) error {
	// Collect metrics payload
	payload, err := collectMetrics()
	if err != nil {
		return fmt.Errorf("failed to collect system metrics: %v", err)
	}

	jsonData, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("failed to marshal JSON: %v", err)
	}

	req, err := http.NewRequest("POST", serverUrl, bytes.NewBuffer(jsonData))
	if err != nil {
		return fmt.Errorf("failed to create request: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Agent-Token", token)

	client := &http.Client{Timeout: 5 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("server unreachable: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("server error (HTTP %s)", resp.Status)
	}

	return nil
}

func main() {
	var err error
	debugFlag := flag.Bool("debug", false, "Run in debug mode (interactive console)")
	flag.Parse()

	isInt, err := svc.IsWindowsService()
	if err != nil {
		log.Fatalf("failed to determine if running as service: %v", err)
	}

	if isInt && !*debugFlag {
		runService(ServiceName, false)
		return
	}

	// GUI Mode (standard interactive run)
	if !*debugFlag {
		ShowGUI()
		return
	}

	// Interactive Console Mode (if -debug flag is passed)
	fmt.Printf("Starting AMPNM Agent in interactive console mode...\n")
	cfg := loadConfig()
	fmt.Printf("Configured Server: %s\n", cfg.ServerUrl)
	fmt.Printf("Configured Trapper: %s\n", cfg.TrapperServer)
	fmt.Printf("Configured Passive Port: %d\n", cfg.PassivePort)

	stopChan := make(chan struct{})
	go startPassiveServer(cfg.PassivePort, stopChan)

	ticker := time.NewTicker(time.Duration(cfg.Interval) * time.Second)
	defer ticker.Stop()

	// Perform immediate collection on startup in interactive mode
	fmt.Println("Performing initial collection...")
	metrics, err := collectMetrics()
	if err == nil {
		fmt.Printf("Collected stats: CPU:%.2f%%, RAM:%.2f%%, Disk:%.2f%%, NetIn:%.2fMbps, NetOut:%.2fMbps\n",
			metrics.CPUPercent, metrics.MemoryPercent, metrics.DiskPercent, metrics.NetworkIn, metrics.NetworkOut)
		transmitActiveTelemetry(cfg, metrics)
	} else {
		fmt.Printf("Initial collection failed: %v\n", err)
	}

	for {
		select {
		case <-ticker.C:
			fmt.Println("Collecting periodic metrics...")
			metrics, err := collectMetrics()
			if err == nil {
				fmt.Printf("Collected stats: CPU:%.2f%%, RAM:%.2f%%, Disk:%.2f%%, NetIn:%.2fMbps, NetOut:%.2fMbps\n",
					metrics.CPUPercent, metrics.MemoryPercent, metrics.DiskPercent, metrics.NetworkIn, metrics.NetworkOut)
				if metrics.CPUPercent >= 98.0 {
					cpuWarningCount++
					if cpuWarningCount >= 3 {
						go showMessageBox("AMPNM Agent Warning", "CPU utilization has exceeded 98% for three consecutive polling cycles!")
						cpuWarningCount = 0
					}
				} else {
					cpuWarningCount = 0
				}
				transmitActiveTelemetry(cfg, metrics)
			} else {
				fmt.Printf("Error collecting metrics: %v\n", err)
			}
		}
	}
}

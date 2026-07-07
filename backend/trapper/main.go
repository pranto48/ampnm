package main

import (
	"database/sql"
	"encoding/binary"
	"encoding/json"
	"fmt"
	"log"
	"net"
	"os"
	"strings"
	"sync"
	"time"

	_ "github.com/go-sql-driver/mysql"
	"github.com/gorilla/websocket"
)

// MetricsPayload matches standard AMPNM agent schema
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

// ZabbixData matches the array payload structure inside request = "agent data"
type ZabbixData struct {
	Host  string `json:"host"`
	Key   string `json:"key"`
	Value string `json:"value"`
	Clock int64  `json:"clock"`
}

// GeneralPayload accommodates both AMPNM agent structure and standard Zabbix requests
type GeneralPayload struct {
	Request    string       `json:"request"`
	Host       string       `json:"host"`
	AgentToken string       `json:"agent_token"`
	Metrics    *MetricsPayload `json:"metrics"`
	Session    string       `json:"session"`
	Data       []ZabbixData `json:"data"`
}

var (
	db       *sql.DB
	wsClient *websocket.Conn
	wsMutex  sync.Mutex
)

func main() {
	log.Println("Starting Trapper & Passive Poller Ingestion Stack...")

	// 1. Database Connection Pooling Setup
	dbHost := getEnv("DB_HOST", "db")
	dbUser := getEnv("DB_USER", "user")
	dbPass := getEnv("DB_PASSWORD", "password")
	dbName := getEnv("DB_NAME", "network_monitor")

	dsn := fmt.Sprintf("%s:%s@tcp(%s:3306)/%s?parseTime=true", dbUser, dbPass, dbHost, dbName)
	var err error
	for i := 0; i < 10; i++ {
		db, err = sql.Open("mysql", dsn)
		if err == nil {
			err = db.Ping()
			if err == nil {
				break
			}
		}
		log.Printf("Waiting for Database (%s)... Retrying in 3s\n", err)
		time.Sleep(3 * time.Second)
	}
	if err != nil {
		log.Fatalf("Fatal: Database connection failed: %v", err)
	}

	db.SetMaxOpenConns(50)
	db.SetMaxIdleConns(20)
	db.SetConnMaxLifetime(5 * time.Minute)
	log.Println("✓ Database connection pool established.")

	// 2. Connect to Next.js WebSocket Gateway
	go connectWebSocket()

	// 3. Start Background Passive Agent Outbound Polling
	go runPassivePollerLoop()

	// 4. Start TCP listeners on Port 10051 (Active) and Port 10050 (Passive Queries)
	var wg sync.WaitGroup
	wg.Add(2)

	go func() {
		defer wg.Done()
		startTCPServer(10051, handleActiveConnection)
	}()

	go func() {
		defer wg.Done()
		startTCPServer(10050, handlePassiveQueryConnection)
	}()

	wg.Wait()
}

// connectWebSocket establishes and maintains persistent WebSocket connection to UI Gateway
func connectWebSocket() {
	for {
		wsUrl := getEnv("WS_URL", "ws://localhost:8080")
		log.Printf("Connecting to Next.js WebSocket Gateway: %s\n", wsUrl)
		conn, _, err := websocket.DefaultDialer.Dial(wsUrl, nil)
		if err != nil {
			log.Printf("WebSocket connection failed: %v. Retrying in 5s...\n", err)
			time.Sleep(5 * time.Second)
			continue
		}
		log.Println("✓ Connected to Next.js WebSocket Gateway.")
		
		wsMutex.Lock()
		wsClient = conn
		wsMutex.Unlock()

		// Block and read messages (just keep connection alive)
		for {
			_, _, err := conn.ReadMessage()
			if err != nil {
				log.Printf("WebSocket disconnected: %v\n", err)
				break
			}
		}
		
		wsMutex.Lock()
		wsClient = nil
		wsMutex.Unlock()
		time.Sleep(5 * time.Second)
	}
}

func broadcastUpdate(deviceID int64, status string, avgTime float64, ttl float64) {
	wsMutex.Lock()
	defer wsMutex.Unlock()
	if wsClient == nil {
		return
	}

	msg := map[string]interface{}{
		"device_id":     deviceID,
		"status":        status,
		"last_avg_time": avgTime,
		"last_ttl":      ttl,
		"last_seen":     time.Now().Format("2006-01-02 15:04:05"),
	}
	jsonData, err := json.Marshal(msg)
	if err == nil {
		_ = wsClient.WriteMessage(websocket.TextMessage, jsonData)
	}
}

// startTCPServer listens on specified TCP port and spawns handles
func startTCPServer(port int, handler func(net.Conn)) {
	listener, err := net.Listen("tcp", fmt.Sprintf("0.0.0.0:%d", port))
	if err != nil {
		log.Fatalf("Failed to listen on TCP port %d: %v", port, err)
	}
	defer listener.Close()
	log.Printf("Ingestion server listening on tcp://0.0.0.0:%d...\n", port)

	for {
		conn, err := listener.Accept()
		if err != nil {
			log.Printf("TCP accept error: %v", err)
			continue
		}
		go handler(conn)
	}
}

// handleActiveConnection processes Zabbix Active agent traffic on port 10051
func handleActiveConnection(conn net.Conn) {
	defer conn.Close()
	_ = conn.SetDeadline(time.Now().Add(5 * time.Second))

	payload, err := readZabbixPacket(conn)
	if err != nil {
		log.Printf("Protocol error: %v", err)
		_, _ = conn.Write([]byte("ERROR: Invalid protocol packet\n"))
		return
	}

	var req GeneralPayload
	if err := json.Unmarshal(payload, &req); err != nil {
		log.Printf("JSON unmarshal error: %v", err)
		_, _ = conn.Write([]byte("ERROR: Invalid JSON structure\n"))
		return
	}

	// Route 1: Active Checks Request
	if req.Request == "active checks" {
		log.Printf("Active Checks requested by host: %s\n", req.Host)
		delay := 60
		// Query database for custom check interval if host exists
		var pingInterval sql.NullInt64
		err := db.QueryRow("SELECT ping_interval FROM devices WHERE name = ? OR ip = ? LIMIT 1", req.Host, req.Host).Scan(&pingInterval)
		if err == nil && pingInterval.Valid && pingInterval.Int64 > 0 {
			delay = int(pingInterval.Int64)
		}
		
		response := map[string]interface{}{
			"response": "success",
			"data": []map[string]interface{}{
				{"key": "agent.ping", "delay": delay},
				{"key": "metrics", "delay": delay},
			},
		}
		respJSON, _ := json.Marshal(response)
		_, _ = conn.Write(createZabbixPacket(respJSON))
		return
	}

	// Route 2: Zabbix active "agent data" transmission
	if req.Request == "agent data" {
		log.Printf("Ingesting agent data batch from session: %s\n", req.Session)
		for _, item := range req.Data {
			if item.Key == "metrics" {
				var metrics MetricsPayload
				if err := json.Unmarshal([]byte(item.Value), &metrics); err == nil {
					// Fallback host info from Zabbix metadata if empty
					if metrics.HostName == "" {
						metrics.HostName = item.Host
					}
					ingestMetrics(metrics, 1) // default UserID = 1
				}
			}
		}
		response := map[string]interface{}{"response": "success", "info": "Processed successfully"}
		respJSON, _ := json.Marshal(response)
		_, _ = conn.Write(createZabbixPacket(respJSON))
		return
	}

	// Route 3: Custom AMPNM agent telemetry with Token
	if req.AgentToken != "" && req.Metrics != nil {
		log.Printf("Ingesting AMPNM telemetry from host: %s\n", req.Metrics.HostName)
		userID, valid := validateToken(req.AgentToken)
		if !valid {
			_, _ = conn.Write([]byte("ERROR: Invalid or missing token\n"))
			return
		}
		ingestMetrics(*req.Metrics, userID)
		response := map[string]interface{}{"response": "success", "info": "Processed successfully"}
		respJSON, _ := json.Marshal(response)
		_, _ = conn.Write(createZabbixPacket(respJSON))
		return
	}

	_, _ = conn.Write([]byte("ERROR: Unsupported request payload\n"))
}

// handlePassiveQueryConnection processes incoming passive queries on port 10050
func handlePassiveQueryConnection(conn net.Conn) {
	defer conn.Close()
	_ = conn.SetDeadline(time.Now().Add(5 * time.Second))

	buf := make([]byte, 1024)
	n, err := conn.Read(buf)
	if err != nil {
		return
	}
	query := strings.TrimSpace(string(buf[:n]))
	
	// Support both raw queries ("agent.ping") and Zabbix standard requests
	if strings.Contains(query, "agent.ping") {
		_, _ = conn.Write([]byte("1"))
		return
	}

	// If request contains "metrics", extract latest metrics for a requested hostname/IP
	if strings.Contains(query, "metrics") {
		parts := strings.Split(query, " ")
		targetHost := ""
		if len(parts) > 1 {
			targetHost = parts[1]
		}
		
		var payload []byte
		if targetHost != "" {
			row := db.QueryRow(`
				SELECT hostname, ip_address, cpu_usage, memory_usage, memory_total, disk_usage, disk_total, network_in, network_out, uptime_seconds, os_version, platform
				FROM host_metrics WHERE hostname = ? OR ip_address = ? LIMIT 1`, targetHost, targetHost)
			
			var m MetricsPayload
			var ip, osVer, platform sql.NullString
			var cpu, mem, memTot, disk, diskTot, netIn, netOut sql.NullFloat64
			var uptime sql.NullInt64
			
			err := row.Scan(&m.HostName, &ip, &cpu, &mem, &memTot, &disk, &diskTot, &netIn, &netOut, &uptime, &osVer, &platform)
			if err == nil {
				m.HostIP = ip.String
				m.CPUPercent = cpu.Float64
				m.MemoryPercent = mem.Float64
				m.MemoryTotal = memTot.Float64
				m.DiskPercent = disk.Float64
				m.DiskTotal = diskTot.Float64
				m.NetworkIn = netIn.Float64
				m.NetworkOut = netOut.Float64
				m.UptimeSeconds = uint64(uptime.Int64)
				m.OSVersion = osVer.String
				m.Platform = platform.String
				payload, _ = json.Marshal(m)
			}
		}

		if len(payload) == 0 {
			payload = []byte(`{"error":"No metrics found for specified host"}`)
		}
		_, _ = conn.Write(createZabbixPacket(payload))
		return
	}

	_, _ = conn.Write([]byte("UNSUPPORTED_KEY"))
}

// runPassivePollerLoop actively polls devices configured for passive monitoring
func runPassivePollerLoop() {
	ticker := time.NewTicker(60 * time.Second)
	defer ticker.Stop()

	for range ticker.C {
		rows, err := db.Query("SELECT id, name, ip, user_id FROM devices WHERE ip IS NOT NULL AND ip != ''")
		if err != nil {
			log.Printf("Poller DB Error: %v\n", err)
			continue
		}

		for rows.Next() {
			var devID, userID int64
			var name, ip string
			if err := rows.Scan(&devID, &name, &ip, &userID); err != nil {
				continue
			}

			// Run polling asynchronously
			go func(dID, uID int64, devName, devIP string) {
				_ = pollPassiveAgent(dID, uID, devName, devIP)
			}(devID, userID, name, ip)
		}
		rows.Close()
	}
}

func pollPassiveAgent(deviceID, userID int64, devName, devIP string) error {
	conn, err := net.DialTimeout("tcp", fmt.Sprintf("%s:10050", devIP), 3*time.Second)
	if err != nil {
		return err
	}
	defer conn.Close()

	_ = conn.SetDeadline(time.Now().Add(5 * time.Second))
	_, err = conn.Write([]byte("metrics\n"))
	if err != nil {
		return err
	}

	payload, err := readZabbixPacket(conn)
	if err != nil {
		return err
	}

	var metrics MetricsPayload
	if err := json.Unmarshal(payload, &metrics); err != nil {
		return err
	}

	// Match metadata fallback
	if metrics.HostName == "" {
		metrics.HostName = devName
	}
	if metrics.HostIP == "" {
		metrics.HostIP = devIP
	}

	ingestMetrics(metrics, userID)
	return nil
}

// ingestMetrics inserts metrics into host_metrics (live status) and host_metrics_history
func ingestMetrics(metrics MetricsPayload, userID int64) {
	// 1. Find or Create device
	var deviceID int64
	err := db.QueryRow("SELECT id FROM devices WHERE ip = ? OR name = ? LIMIT 1", metrics.HostIP, metrics.HostName).Scan(&deviceID)
	if err != nil {
		// Create new device if missing
		res, err := db.Exec(`
			INSERT INTO devices (user_id, name, ip, monitor_method, type, status, description, show_live_ping)
			VALUES (?, ?, ?, 'ping', 'server', 'online', 'Auto-created from agent Trapper', 0)`,
			userID, metrics.HostName, metrics.HostIP)
		if err == nil {
			deviceID, _ = res.LastInsertId()
		}
	} else {
		// Touch existing device
		_, _ = db.Exec("UPDATE devices SET last_seen = NOW(), status = 'online' WHERE id = ?", deviceID)
	}

	// 2. Insert or Update host_metrics
	_, _ = db.Exec(`
		INSERT INTO host_metrics (hostname, ip_address, os_version, cpu_usage, memory_usage, memory_total, disk_usage, disk_total, network_in, network_out, uptime_seconds, status, last_seen, created_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'online', NOW(), NOW())
		ON DUPLICATE KEY UPDATE 
			ip_address = ?, os_version = ?, cpu_usage = ?, memory_usage = ?, memory_total = ?, disk_usage = ?, disk_total = ?, network_in = ?, network_out = ?, uptime_seconds = ?, last_seen = NOW()`,
		metrics.HostName, metrics.HostIP, metrics.OSVersion, metrics.CPUPercent, metrics.MemoryPercent, metrics.MemoryTotal, metrics.DiskPercent, metrics.DiskTotal, int64(metrics.NetworkIn), int64(metrics.NetworkOut), metrics.UptimeSeconds,
		metrics.HostIP, metrics.OSVersion, metrics.CPUPercent, metrics.MemoryPercent, metrics.MemoryTotal, metrics.DiskPercent, metrics.DiskTotal, int64(metrics.NetworkIn), int64(metrics.NetworkOut), metrics.UptimeSeconds,
	)

	// 3. Insert into host_metrics_history
	_, _ = db.Exec(`
		INSERT INTO host_metrics_history (hostname, cpu_usage, memory_usage, memory_total, disk_usage, disk_total, network_in, network_out, recorded_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
		metrics.HostName, metrics.CPUPercent, metrics.MemoryPercent, metrics.MemoryTotal, metrics.DiskPercent, metrics.DiskTotal, int64(metrics.NetworkIn), int64(metrics.NetworkOut),
	)

	// 4. Trigger alert checks / live updates broadcast
	broadcastUpdate(deviceID, "online", 0.0, 0.0)
}

func validateToken(token string) (int64, bool) {
	var userID int64
	var enabled bool
	err := db.QueryRow("SELECT user_id, enabled FROM agent_tokens WHERE token = ? LIMIT 1", token).Scan(&userID, &enabled)
	if err != nil || !enabled {
		return 0, false
	}
	_, _ = db.Exec("UPDATE agent_tokens SET last_used_at = NOW() WHERE token = ?", token)
	return userID, true
}

// readZabbixPacket extracts data payload from standard Zabbix frame
func readZabbixPacket(conn net.Conn) ([]byte, error) {
	header := make([]byte, 5)
	if _, err := conn.Read(header); err != nil {
		return nil, err
	}
	if string(header) != "ZBXD\x01" {
		return nil, fmt.Errorf("invalid header prefix")
	}

	lenBytes := make([]byte, 8)
	if _, err := conn.Read(lenBytes); err != nil {
		return nil, err
	}
	length := binary.LittleEndian.Uint64(lenBytes)

	if length <= 0 || length > 10*1024*1024 {
		return nil, fmt.Errorf("invalid payload length: %d", length)
	}

	data := make([]byte, length)
	readBytes := uint64(0)
	for readBytes < length {
		n, err := conn.Read(data[readBytes:])
		if err != nil {
			return nil, err
		}
		readBytes += uint64(n)
	}
	return data, nil
}

// createZabbixPacket serializes byte data into Zabbix protocol frame
func createZabbixPacket(data []byte) []byte {
	header := []byte("ZBXD\x01")
	length := uint64(len(data))
	buf := make([]byte, 5+8+len(data))
	copy(buf[0:5], header)
	binary.LittleEndian.PutUint64(buf[5:13], length)
	copy(buf[13:], data)
	return buf
}

func getEnv(key, fallback string) string {
	if value, ok := os.LookupEnv(key); ok {
		return value
	}
	return fallback
}

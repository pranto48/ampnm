package main

import (
	"encoding/json"
	"fmt"
	"net"
	"net/http"
	"sync"
	"time"
)

var (
	logBuffer []string
	logMu     sync.Mutex
	logCond   *sync.Cond
)

func init() {
	logCond = sync.NewCond(&logMu)
}

func addLog(msg string) {
	logMu.Lock()
	defer logMu.Unlock()
	entry := fmt.Sprintf("[%s] %s", time.Now().Format("15:04:05"), msg)
	logBuffer = append(logBuffer, entry)
	if len(logBuffer) > 200 {
		logBuffer = logBuffer[1:]
	}
	fmt.Println(entry) // still output to console
	logCond.Broadcast()
}

func startWebServer() {
	mux := http.NewServeMux()

	mux.HandleFunc("/", serveDashboard)
	mux.HandleFunc("/api/config", handleConfig)
	mux.HandleFunc("/api/interfaces", handleInterfaces)
	mux.HandleFunc("/api/test", handleTest)
	mux.HandleFunc("/api/logs", handleLogs)

	addLog("Local web server starting on http://127.0.0.1:22660")
	if err := http.ListenAndServe("127.0.0.1:22660", mux); err != nil {
		addLog(fmt.Sprintf("Failed to start web server: %v", err))
	}
}

func handleLogs(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/event-stream")
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Connection", "keep-alive")

	flusher, ok := w.(http.Flusher)
	if !ok {
		http.Error(w, "Streaming unsupported", http.StatusInternalServerError)
		return
	}

	logMu.Lock()
	var lastSent int = len(logBuffer)
	for _, msg := range logBuffer {
		fmt.Fprintf(w, "data: %s\n\n", msg)
	}
	logMu.Unlock()
	flusher.Flush()

	for {
		logMu.Lock()
		logCond.Wait()
		if len(logBuffer) > lastSent {
			for i := lastSent; i < len(logBuffer); i++ {
				fmt.Fprintf(w, "data: %s\n\n", logBuffer[i])
			}
			lastSent = len(logBuffer)
		}
		logMu.Unlock()
		flusher.Flush()
	}
}

func handleConfig(w http.ResponseWriter, r *http.Request) {
	if r.Method == "GET" {
		cfg := loadConfig()
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(cfg)
		return
	} else if r.Method == "POST" {
		var cfg Config
		if err := json.NewDecoder(r.Body).Decode(&cfg); err != nil {
			http.Error(w, err.Error(), http.StatusBadRequest)
			return
		}
		if err := saveConfig(cfg); err != nil {
			http.Error(w, err.Error(), http.StatusInternalServerError)
			return
		}
		addLog("Configuration saved successfully.")
		w.WriteHeader(http.StatusOK)
		return
	}
	http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
}

func handleTest(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	cfg := loadConfig()
	addLog("Testing connection to " + cfg.ServerUrl + " ...")
	err := TestConnection(cfg.ServerUrl, cfg.AgentToken, cfg)
	
	w.Header().Set("Content-Type", "application/json")
	if err != nil {
		addLog("Connection test failed: " + err.Error())
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
	} else {
		addLog("Connection test succeeded!")
		json.NewEncoder(w).Encode(map[string]interface{}{"success": true})
	}
}

func handleInterfaces(w http.ResponseWriter, r *http.Request) {
	addrs, err := net.InterfaceAddrs()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	
	var ips []string
	ips = append(ips, "auto")
	for _, address := range addrs {
		if ipnet, ok := address.(*net.IPNet); ok && !ipnet.IP.IsLoopback() {
			if ip4 := ipnet.IP.To4(); ip4 != nil {
				ips = append(ips, ip4.String())
			}
		}
	}
	
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(ips)
}

func serveDashboard(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html")
	w.Write([]byte(dashboardHTML))
}

const dashboardHTML = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AMPNM Windows Telemetry Agent</title>
    <style>
        :root {
            --bg-color: #0f172a;
            --surface: #1e293b;
            --primary: #3b82f6;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
            --error: #ef4444;
            --border: #334155;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text);
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: var(--surface);
            padding: 20px 40px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .card {
            background-color: var(--surface);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .card.full {
            grid-column: 1 / -1;
        }
        h2 {
            margin-top: 0;
            font-size: 18px;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-muted);
            font-size: 14px;
        }
        input, select {
            width: 100%;
            background-color: #0f172a;
            border: 1px solid var(--border);
            color: white;
            padding: 10px 12px;
            border-radius: 6px;
            box-sizing: border-box;
            margin-bottom: 16px;
            font-size: 14px;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }
        button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        button:hover {
            background-color: #2563eb;
        }
        button.secondary {
            background-color: transparent;
            border: 1px solid var(--border);
        }
        button.secondary:hover {
            background-color: var(--border);
        }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        #terminal {
            background-color: #000;
            color: #0f0;
            font-family: 'Consolas', monospace;
            padding: 16px;
            border-radius: 6px;
            height: 250px;
            overflow-y: auto;
            font-size: 13px;
            line-height: 1.5;
        }
        #status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--text-muted);
            margin-right: 8px;
        }
        .status-ok { background-color: var(--success) !important; }
        .status-fail { background-color: var(--error) !important; }
        .status-test { background-color: #eab308 !important; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="display:flex;align-items:center;">
            <svg style="margin-right:12px;" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
            AMPNM Telemetry Agent
        </h1>
    </div>

    <div class="container">
        <div class="card">
            <h2>Connection Settings</h2>
            <label>Server URL</label>
            <input type="text" id="ServerUrl" placeholder="http://192.168.20.5:2266/api/agent/metrics/">
            
            <label>Agent Token</label>
            <input type="text" id="AgentToken" placeholder="ampnm_...">
            
            <label>Polling Interval (seconds)</label>
            <input type="number" id="Interval" value="60">
        </div>

        <div class="card">
            <h2>Advanced & Network</h2>
            <label>LAN Interface Preference</label>
            <select id="LANInterface">
                <option value="auto">Auto-Detect (Default)</option>
            </select>

            <label>Trapper Server (Zabbix compatibility)</label>
            <input type="text" id="TrapperServer" placeholder="localhost:10051">
            
            <label>Passive Port</label>
            <input type="number" id="PassivePort" value="10050">
        </div>

        <div class="card full">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Live Console Logs</h2>
                <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
                    <span id="status-indicator"></span><span id="status-text">Disconnected</span>
                </div>
            </div>
            <div id="terminal"></div>
            
            <div class="btn-group" style="justify-content: space-between;">
                <div>
                    <button onclick="saveConfig()">Save Settings</button>
                    <button class="secondary" onclick="testConnection()">Send Test Now</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const terminal = document.getElementById('terminal');
        
        function appendLog(msg) {
            const div = document.createElement('div');
            div.textContent = msg;
            terminal.appendChild(div);
            terminal.scrollTop = terminal.scrollHeight;
        }

        // Setup SSE for live logs
        const evtSource = new EventSource("/api/logs");
        evtSource.onmessage = function(event) {
            appendLog(event.data);
        };

        // Fetch network interfaces
        fetch('/api/interfaces')
            .then(res => res.json())
            .then(ips => {
                const select = document.getElementById('LANInterface');
                ips.forEach(ip => {
                    if (ip === "auto") return; // Already there
                    const opt = document.createElement('option');
                    opt.value = ip;
                    opt.textContent = ip;
                    select.appendChild(opt);
                });
                loadConfig(); // Load config after IPs are populated to select correct one
            });

        function loadConfig() {
            fetch('/api/config')
                .then(res => res.json())
                .then(cfg => {
                    document.getElementById('ServerUrl').value = cfg.ServerUrl || '';
                    document.getElementById('AgentToken').value = cfg.AgentToken || '';
                    document.getElementById('Interval').value = cfg.Interval || 60;
                    document.getElementById('TrapperServer').value = cfg.TrapperServer || '';
                    document.getElementById('PassivePort').value = cfg.PassivePort || 10050;
                    if (cfg.LANInterface) {
                        document.getElementById('LANInterface').value = cfg.LANInterface;
                    }
                });
        }

        function saveConfig() {
            const cfg = {
                ServerUrl: document.getElementById('ServerUrl').value,
                AgentToken: document.getElementById('AgentToken').value,
                Interval: parseInt(document.getElementById('Interval').value),
                TrapperServer: document.getElementById('TrapperServer').value,
                PassivePort: parseInt(document.getElementById('PassivePort').value),
                LANInterface: document.getElementById('LANInterface').value
            };

            fetch('/api/config', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(cfg)
            }).then(res => {
                if (res.ok) {
                    setStatus('ok', 'Saved Successfully');
                } else {
                    res.text().then(err => setStatus('fail', 'Save Error: ' + err));
                }
            });
        }

        function testConnection() {
            setStatus('test', 'Testing Connection...');
            fetch('/api/test', { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        setStatus('ok', 'Connected');
                    } else {
                        setStatus('fail', data.error);
                    }
                }).catch(err => {
                    setStatus('fail', err.message);
                });
        }

        function setStatus(state, text) {
            const ind = document.getElementById('status-indicator');
            const txt = document.getElementById('status-text');
            ind.className = 'status-' + state;
            txt.textContent = text;
            txt.style.color = state === 'ok' ? 'var(--success)' : (state === 'fail' ? 'var(--error)' : '#eab308');
        }
    </script>
</body>
</html>
`

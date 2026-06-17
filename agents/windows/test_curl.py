import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('192.168.20.5', username='it', password='Interst0ff', timeout=5)

cmd = "curl -s -v -X POST http://localhost:2266/api/agent/metrics/ -H 'X-Agent-Token: ampnm_1dc3c51eb6872b8eabcd2717e0b7bcf3' -H 'Content-Type: application/json' -d '{\"host_ip\":\"192.168.9.10\"}'"
stdin, stdout, stderr = ssh.exec_command(cmd)

print("CURL OUT:", stdout.read().decode('utf-8'))
print("CURL ERR:", stderr.read().decode('utf-8'))

ssh.close()

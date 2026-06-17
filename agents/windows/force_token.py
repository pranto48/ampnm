import paramiko
import sys

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('192.168.20.5', username='it', password='Interst0ff', timeout=5)

def run(cmd):
    full_cmd = f"echo 'Interst0ff' | sudo -S docker exec ampnm-db-1 mysql -u it -pInterst0ff network_monitor -e \"{cmd}\" 2>&1"
    stdin, stdout, stderr = ssh.exec_command(full_cmd)
    out = stdout.read().decode('utf-8').strip()
    return out

print("=== SHOW TABLES ===")
print(run("SHOW TABLES;"))

print("\n=== INSERT TOKEN ===")
print(run("INSERT INTO agent_tokens (user_id, token, name, enabled) VALUES (1, 'ampnm_1dc3c51eb6872b8eabcd2717e0b7bcf3', 'Inserted Token', 1);"))

print("\n=== SELECT TOKENS ===")
print(run("SELECT id, token, enabled FROM agent_tokens;"))

ssh.close()

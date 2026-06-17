import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('192.168.20.5', username='it', password='Interst0ff', timeout=5)

# Insert the missing token manually just in case
cmd_insert = "echo 'Interst0ff' | sudo -S docker exec ampnm-db-1 mysql -u it -pInterst0ff network_monitor -e \"INSERT IGNORE INTO agent_tokens (user_id, token, name, enabled) VALUES (1, 'ampnm_1dc3c51eb6872b8eabcd2717e0b7bcf3', 'Manual Token', 1);\""
ssh.exec_command(cmd_insert)

# Now select all tokens
cmd = "echo 'Interst0ff' | sudo -S docker exec ampnm-db-1 mysql -u it -pInterst0ff network_monitor -e 'SELECT id, token, enabled FROM agent_tokens'"
stdin, stdout, stderr = ssh.exec_command(cmd)
print("DB TOKENS:\n" + stdout.read().decode('utf-8'))

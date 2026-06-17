import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('192.168.20.5', username='it', password='Interst0ff', timeout=5)

cmd = "echo 'Interst0ff' | sudo -S docker exec ampnm-db-1 mysql -u it -pInterst0ff network_monitor -e \"DELETE FROM app_settings WHERE setting_key LIKE 'integrity_%';\""
ssh.exec_command(cmd)

ssh.close()

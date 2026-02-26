# Docker AMPNM Install Troubleshooting

If `docker compose up --build -d` feels stuck, walk through these checks to confirm download progress and environment readiness.

## 1) Confirm prerequisites
- Docker Engine and Docker Compose are installed and running: `docker info` and `docker compose version`.
- At least 3 GB free disk space for images and the MySQL volume: `df -h`.
- Outbound internet access to Docker Hub: `ping registry-1.docker.io` (ICMP may be blocked; if so, try `curl -I https://registry-1.docker.io`).

## 2) Pre-pull images and raise the timeout
```bash
# Give slower networks more time to download layers
export COMPOSE_HTTP_TIMEOUT=600

# Pull images with plain progress so you can watch the layers
cd portal.itsupport.com.bd/docker-ampnm
docker compose pull --progress=plain
```

## 3) Run compose with visible progress
```bash
docker compose up --build --progress=plain -d
```
- The MySQL base image is the largest layer and may take several minutes on slow links.
- The PHP image build step downloads Debian packages the first time; subsequent builds are faster.

### If you see `unexpected EOF` or layer download failures
- This usually means the connection to Docker Hub was interrupted mid-download.
- Fixes to try:
  - Extend timeouts: `export COMPOSE_HTTP_TIMEOUT=1200` (20 minutes) then rerun `docker compose pull --progress=plain`.
  - Pre-pull the largest layer directly so it can resume: `docker pull mysql:8 --progress=plain`.
  - Resume only the database layer: `docker compose pull db --progress=plain`.
  - Clean partial layers if corruption persists: `docker system prune -af --volumes` (removes unused images/volumes; stop other containers first).
  - Confirm disk space (`df -h`) and that your network link is stable or not rate-limited.

## 4) Tail logs to verify startup
```bash
docker compose logs -f --tail=80 app db
```
- Look for `database system is ready` (MySQL health check) and `Server listening on` from Apache.
- If the app fails to connect to MySQL, ensure port 3306 is free on the host or change the mapping in `docker-compose.yml`.

## 5) Common blockers
- **Port conflict:** Another MySQL or service is already on port 3306. Stop it or edit the port mapping under the `db` service.
- **Low disk:** Free space before retrying; Docker cannot complete pulls when the disk is nearly full.
- **Stale cache:** Retry after cleaning partial layers: `docker system prune -af --volumes` (removes unused images/volumes; ensure nothing important is running).
- **Corporate proxy:** Configure Docker’s HTTP/HTTPS proxy settings so image pulls succeed.

## 6) Still stuck?
Run a quick diagnostic capture and share it with support:
```bash
docker compose ps
docker compose logs --tail=120 app db
```
Include the output when opening a support ticket so we can spot failing steps quickly.

# Internal Telemetry SLOs and Troubleshooting Runbook

## SLO Targets

| Signal | Green | Yellow | Red |
|---|---:|---:|---:|
| Queue depth | <= 20 | 21-100 | >100 |
| Ingest processing latency | <= 250 ms | 251-750 ms | >750 ms |
| Failed jobs (last hour) | 0 | 1-5 | >5 |
| DB query latency (15m avg) | <= 120 ms | 121-250 ms | >250 ms |
| Alert throughput (5m avg) | <= 2/min | 2.01-6/min | >6/min |

## Alert Conditions

- **ingestion_lag**: oldest pending queue item older than 120 seconds.
- **stale_workers**: worker/scheduler heartbeat older than 120 seconds.
- **db_slow_queries**: DB query latency average above 250 ms.
- **proxy_disconnects**: at least one proxy has stale or missing `last_seen`.

## Validation Commands

```bash
curl -s http://localhost:2266/metrics | head -n 40
curl -s http://localhost:2266/api/agent/metrics/health
curl -s http://localhost:2266/api/agent/proxy/health
```

## Troubleshooting

### 1) Ingestion lag
1. Check queue depth and oldest pending age in `/metrics`.
2. Restart worker process and confirm `/tmp/ampnm-metrics-worker-heartbeat` updates.
3. Inspect dead-letter table for malformed payloads:
   ```sql
   SELECT id, idempotency_key, error_reason, failed_at
   FROM metrics_ingest_dead_letter
   ORDER BY failed_at DESC LIMIT 50;
   ```

### 2) Stale workers
1. Verify scheduler and worker containers/process supervisors are running.
2. Confirm heartbeat files update:
   - `/tmp/ampnm-scheduler-heartbeat`
   - `/tmp/ampnm-metrics-worker-heartbeat`
3. Check logs for fatal errors around `scheduler tick failed` or worker retry loops.

### 3) DB slow queries
1. Inspect the telemetry table:
   ```sql
   SELECT component, operation, latency_ms, created_at
   FROM telemetry_db_query
   ORDER BY created_at DESC LIMIT 100;
   ```
2. Validate DB saturation (CPU/IO/locks) and ensure indexes exist for queue tables.
3. If needed, lower ingestion burst rate temporarily and scale DB resources.

### 4) Proxy disconnects
1. Query stale proxies:
   ```sql
   SELECT id, name, status, last_seen
   FROM proxies
   WHERE last_seen IS NULL OR last_seen < DATE_SUB(NOW(), INTERVAL 2 MINUTE);
   ```
2. Validate token/auth and network path from proxy agent to `/api/agent/proxy/*`.
3. Confirm proxy results-batch acknowledgments are being returned.

## Correlation IDs

- Incoming API requests can pass `X-Correlation-ID`.
- The ID is propagated into queue messages, worker processing logs, and alert emissions.
- Structured JSON logs include `event` and `correlation_id` for end-to-end tracing.

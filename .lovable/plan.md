

# Quiet Background Offline Detection for Docker Map

## Problem
The current system shows notifications on every ping check, causing frequent popup spam. The "blue/unknown" color flicker during pings is also visually noisy. Users want a calm map that only updates when a device has been confirmed offline for 5 seconds.

## Solution: Silent Background Health Check

### How it works

1. **Remove the blue "pinging" flicker** -- the map icon stays its current color during a ping check, no visual change while checking.

2. **Track the first failure timestamp** instead of counting consecutive failures. When a device first fails a ping, record `Date.now()`. On subsequent failures, check if 5 seconds have elapsed since that first failure. Only then mark the device as offline and show the notification.

3. **Online transitions remain instant** -- a single successful ping immediately marks the device online and shows the notification.

4. **No "all statuses stable" toast** -- remove the repetitive bulk-refresh success message.

5. **Same logic applies to both `pingSingleDevice` and `performBulkRefresh`**.

---

## Technical Details

### File: `docker-ampnm/assets/js/map/state.js`
- Replace `deviceFailCounts: {}` with `deviceFirstFailTime: {}` to track timestamps instead of counts.

### File: `docker-ampnm/assets/js/map/deviceManager.js`

**Remove the blue flicker (line 13):**
- Delete the line that sets the icon color to cyan (`#06b6d4`) while pinging. The device keeps its current color silently.

**Replace consecutive-count logic with time-based logic in `pingSingleDevice`:**
- Remove `OFFLINE_FAIL_THRESHOLD` constant.
- When `rawStatus === 'offline'`:
  - If no `deviceFirstFailTime[deviceId]` exists, set it to `Date.now()` and keep the old status (silent).
  - If it exists and `Date.now() - deviceFirstFailTime[deviceId] >= 5000`, allow the offline transition (show notification + update map).
  - If it exists but less than 5 seconds, keep old status (silent).
- When `rawStatus !== 'offline'`: clear `deviceFirstFailTime[deviceId]` and transition immediately.

**Same time-based logic in `performBulkRefresh`:**
- Apply identical 5-second timestamp check for each device in the bulk results.
- Remove the "All device statuses are stable" notyf message (line 143-145).

**Remove error toast on ping network failure (line 61):**
- Remove the `window.notyf.error` call when a ping API call throws an exception, to avoid spamming notifications for transient network issues. Keep the `console.error` for debugging.

### Summary of behavior changes

| Scenario | Before | After |
|----------|--------|-------|
| Device fails 1 ping | Blue flicker, counter incremented | No visual change, timestamp recorded silently |
| Device fails for less than 5s | Stays old color after 3 checks | Stays old color, no notification |
| Device offline for 5+ seconds | After 3 consecutive fails | After 5 seconds elapsed since first fail -- notification + map update |
| Device comes back online | Immediate | Immediate (unchanged) |
| Every bulk refresh | "All statuses stable" toast | No toast if nothing changed |
| Ping API network error | Error popup notification | Silent (console only) |


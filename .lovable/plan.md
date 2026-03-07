

# Custom Device Port Configuration + Used Port Filtering

## Overview

Currently, port types and counts are hardcoded per device type (e.g., Switch always = 24 GE + 4 SFP). The user wants:
1. Admins to define custom port types and quantities per device
2. Ports to be stored/displayed per those custom definitions
3. Already-used ports to be excluded from the connection modal dropdowns

## Changes

### 1. Database: Add `port_config` column to `devices` table

**File: `docker-ampnm/database_setup.php`**

Add a migration to add a `port_config` TEXT column (JSON) to the `devices` table. This stores the device's custom port layout, e.g.:
```json
[{"type":"GE","prefix":"G0/","start":1,"count":24},{"type":"SFP","prefix":"SFP","start":1,"count":4}]
```

### 2. Device Forms: Custom Port Type/Count inputs

**Files: `docker-ampnm/create-device.php`, `docker-ampnm/edit-device.php`**

Add a "Port Configuration" section inside the Network Ports fieldset with:
- A repeatable row UI: Port Type dropdown (GE, SFP, Serial, Mgmt, Console) + Prefix input (e.g., `G0/`) + Start Number + Count
- "Add Port Group" button to add more rows
- A hidden input `port_config` that serializes these rows as JSON on form submit
- The port grid visualization updates live based on these custom rows instead of hardcoded defaults
- On edit page, pre-populate rows from saved `port_config` JSON; fall back to type-based defaults if empty

### 3. Backend: Save/read `port_config`

**Files: `docker-ampnm/create-device.php` (POST handler), `docker-ampnm/edit-device.php` (POST handler), `docker-ampnm/api/handlers/device_handler.php`**

- Save `port_config` JSON string on INSERT and UPDATE
- Return `port_config` in device queries so the map JS can use it

### 4. Map Edge Modal: Filter out already-used ports

**File: `docker-ampnm/assets/js/map/ui.js`**

Refactor `_populatePortSelect` to:
1. Read `port_config` from the device's `deviceData` (if available) to generate the correct port list instead of using hardcoded `_generatePortOptions`
2. Fetch used ports for the device via `get_device_used_ports` API
3. Mark used ports as disabled in the `<select>` dropdown with "(In Use)" suffix
4. Allow the currently-selected port (the one being edited) to remain selectable

**File: `docker-ampnm/api/handlers/map_handler.php`**

Update `get_device_used_ports` to also return which edge each port belongs to, so the UI can allow re-selecting the current edge's own port.

### 5. Map Node Tooltip: Use custom port_config

**File: `docker-ampnm/assets/js/map/utils.js`**

Update `buildNodeTitle` to read `port_config` from device data instead of hardcoded type-based port lists.

## File Summary

| File | Change |
|------|--------|
| `database_setup.php` | Add `port_config` TEXT column migration |
| `create-device.php` | Add port group config UI + save `port_config` on POST |
| `edit-device.php` | Same UI + pre-populate from saved config |
| `api/handlers/device_handler.php` | Include `port_config` in queries |
| `assets/js/map/ui.js` | Read `port_config` for port list; fetch & filter used ports |
| `api/handlers/map_handler.php` | Enhance `get_device_used_ports` to return edge context |
| `assets/js/map/utils.js` | Use `port_config` for tooltip port summary |


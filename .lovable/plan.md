

# Plan: 5 Docker AMPNM Updates

## 1. VLAN Tagging Support in Port Configuration

Add an optional `vlan` field to each port group in the port configuration builder on `create-device.php` and `edit-device.php`.

**Changes:**
- **`create-device.php`** and **`edit-device.php`**: Add a VLAN input field to each port group row (text input, placeholder "e.g. 100"). Update the JS that serializes `port_config` JSON to include `"vlan"` per group. Update the port grid visualization to show VLAN badges on ports.
- **`assets/js/map/utils.js`**: Update `buildNodeTitle` to display VLAN info in port tooltips.
- **`assets/js/map/ui.js`**: Show VLAN tag in port select options when available.

Port config JSON becomes:
```json
[{"type":"GE","prefix":"G0/","start":1,"count":24,"vlan":"100"},{"type":"SFP","prefix":"SFP","start":1,"count":4,"vlan":""}]
```

No database changes needed -- `port_config` is already a TEXT/JSON field.

---

## 2. Rename CAT5 to CAT6

**Changes:**
- **`assets/js/map/config.js`**: Rename key `cat5` to `cat6` in `edgeColorMap` and `edgeLabelMap`. Update label to `'🔌 CAT6'`.
- **`map.php`**: Update the edge modal `<option value="cat5">` to `<option value="cat6">` and the connection legend text from "CAT5" to "CAT6".
- **`assets/js/map/ui.js`**: Update `updateAndAnimateEdges` fallback from `cat5` to `cat6`.
- **`database_setup.php`**: Change default `connection_type` from `'cat5'` to `'cat6'` for `device_edges` table.
- Add a migration in `database_setup.php` to update existing `cat5` rows: `UPDATE device_edges SET connection_type='cat6' WHERE connection_type='cat5'`.

---

## 3. Group Box (Rectangle Shape) on Map

Add a "box" or "group" device type that renders as a rectangular boundary on the map, allowing users to visually group devices inside it.

**Changes:**
- **`map.php`**: Add a toolbar button "Add Group Box" that creates a device with type `box`.
- **`assets/js/map/deviceManager.js`**: When building vis.js nodes, detect `type === 'box'` and render as a `shape: 'box'` node with larger dimensions, semi-transparent background, and a border. These nodes should be rendered behind other nodes (lower level).
- **`assets/js/map.js`**: Add click handler for the new "Add Group Box" button that calls `create_device` API with `type: 'box'` and a default name like "Group 1".
- **`assets/js/map/config.js`**: Add `box` to `iconMap` (use a square icon).
- The box device won't have IP/ping -- it's purely visual. The existing `type: 'box'` is already in the `check_all_devices_globally` exclusion filter (`type != 'box'`), so no ping logic changes needed.

---

## 4. Fix "Failed to copy device" on devices.php

**Root cause:** The `copy_device` handler in `device_handler.php` (line 686) INSERT is missing the `subchoice` and `port_config` columns. The `subchoice` column is `NOT NULL DEFAULT 0`, so if the source device has a non-zero subchoice, or if MySQL strict mode is on, the INSERT may fail because these columns from the source are not being copied.

**Fix in `device_handler.php`:**
- Add `subchoice` and `port_config` to the INSERT statement in the `copy_device` case (line 686-711).
- Include corresponding values from `$device['subchoice']` and `$device['port_config']`.

---

## 5. Add License Key Input on license_expired.php

Add an "Add License" form directly on the `license_expired.php` page so admins can enter a license key without needing access to the main app.

**Changes to `license_expired.php`:**
- Add a collapsible "Add License Key" section with:
  - A text input for the license key
  - A "Verify & Activate" submit button
- On form submit, POST to the same page (or a dedicated endpoint)
- The PHP handler at the top of the page will:
  1. Receive the license key
  2. Call `setAppLicenseKey()` to save it
  3. Clear cached license session data
  4. Call `verifyLicenseWithPortal(true)` to force re-verification
  5. If successful (status becomes `active` or `grace_period`), redirect to `index.php`
  6. If failed, show an error message on the same page
- Remove the `window.fetch` override that blocks all AJAX (it prevents the form from working via JS, but we'll use a standard PHP form POST)
- Keep the form submission working even with the security restrictions

## Files Modified

| File | Changes |
|------|---------|
| `create-device.php` | Add VLAN input to port group rows |
| `edit-device.php` | Add VLAN input to port group rows |
| `assets/js/map/config.js` | cat5 -> cat6, add box icon |
| `assets/js/map/ui.js` | cat5 -> cat6 fallback, VLAN in port options |
| `assets/js/map/utils.js` | VLAN in tooltip |
| `map.php` | CAT6 labels, Add Group Box button, legend update |
| `assets/js/map.js` | Group box creation handler |
| `assets/js/map/deviceManager.js` | Box node rendering as rectangle |
| `database_setup.php` | CAT6 default + migration |
| `api/handlers/device_handler.php` | Fix copy_device missing columns |
| `license_expired.php` | Add license key input form with PHP verification |


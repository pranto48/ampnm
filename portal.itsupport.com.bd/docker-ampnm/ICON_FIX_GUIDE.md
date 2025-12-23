# 🔧 Icon Rendering Fix Guide

## 🐞 Problem

When you change a device icon in the edit form and save it, the icon doesn't update on the network map. The map continues showing the old icon or a default icon.

## 🔍 Root Cause

The map's JavaScript code was not reading the `type` and `subchoice` fields from device data to determine which Font Awesome icon class to use. It was likely using a hardcoded or default icon instead.

---

## ✅ Solution Implemented

### 1. Added Icon Mapping Function

**File**: `assets/js/map/utils.js`

**What Changed**: Added `getDeviceIconClass()` function that:
- Takes device `type` (e.g., 'router', 'server')
- Takes `subchoice` (icon variant index 0-7)
- Returns correct Font Awesome class (e.g., 'fa-network-wired')

```javascript
getDeviceIconClass: (deviceType, subchoice) => {
    if (!window.deviceIconsLibrary) {
        return 'fa-circle'; // Default fallback
    }
    
    const typeData = window.deviceIconsLibrary[deviceType];
    if (!typeData || !typeData.icons) {
        return 'fa-circle';
    }
    
    const index = parseInt(subchoice, 10) || 0;
    const iconData = typeData.icons[index];
    
    return iconData?.icon || typeData.icons[0]?.icon || 'fa-circle';
}
```

### 2. Loaded Icon Library in Map

**File**: `map.php`

**What Changed**: 
- Loaded PHP icon library at the top
- Passed it to JavaScript as `window.deviceIconsLibrary`

```php
<?php
// Load device icons library
$deviceIconsLibrary = require_once 'includes/device_icons.php';
?>

<!-- At bottom of file -->
<script>
    window.deviceIconsLibrary = <?= json_encode($deviceIconsLibrary) ?>;
</script>
```

---

## 📝 Required Code Changes

### Step 3: Update Device Loading Code

**File to Edit**: `assets/js/map.js` or wherever devices are loaded

**Find**: Code that creates vis.js nodes from device data (likely in `loadMap()` or similar function)

**Replace**: The icon property creation to use the new mapping function

#### Before (Example):

```javascript
// Old code - hardcoded or default icon
const node = {
    id: device.id,
    label: device.name,
    x: device.x,
    y: device.y,
    icon: {
        face: 'FontAwesome',
        code: '\uf233', // Hardcoded icon
        size: 50,
        color: statusColor
    },
    deviceData: device
};
```

#### After (Fixed):

```javascript
// New code - dynamic icon based on type and subchoice
const iconClass = MapApp.utils.getDeviceIconClass(
    device.type || 'generic',
    device.subchoice || 0
);

const node = {
    id: device.id,
    label: device.name,
    x: device.x,
    y: device.y,
    icon: {
        face: 'FontAwesome',
        code: getFontAwesomeUnicode(iconClass), // Convert class to unicode
        size: 50,
        color: statusColor
    },
    deviceData: device
};
```

### Helper Function for Unicode Conversion

Add this helper function to handle Font Awesome icon codes:

```javascript
/**
 * Convert Font Awesome class to Unicode
 * vis.js requires Unicode codepoints, not classes
 */
function getFontAwesomeUnicode(iconClass) {
    // Font Awesome icon unicode map (add more as needed)
    const iconMap = {
        // Routers
        'fa-network-wired': '\uf6ff',
        'fa-router': '\uf8da',
        'fa-circle-nodes': '\ue4e3',
        'fa-sitemap': '\uf0e8',
        
        // WiFi
        'fa-wifi': '\uf1eb',
        'fa-tower-broadcast': '\uf519',
        'fa-signal': '\uf012',
        
        // Servers
        'fa-server': '\uf233',
        'fa-tower-cell': '\ue585',
        'fa-computer': '\ue4e5',
        'fa-microchip': '\uf2db',
        'fa-hard-drive': '\uf0a0',
        
        // Security
        'fa-shield-halved': '\uf3ed',
        'fa-shield': '\uf132',
        'fa-lock': '\uf023',
        'fa-fingerprint': '\uf577',
        
        // Network
        'fa-ethernet': '\uf796',
        
        // Cloud
        'fa-cloud': '\uf0c2',
        'fa-cloud-arrow-up': '\uf0ee',
        'fa-cloud-arrow-down': '\uf0ed',
        
        // Database
        'fa-database': '\uf1c0',
        
        // Storage
        'fa-hdd': '\uf0a0',
        
        // Devices
        'fa-laptop': '\uf109',
        'fa-laptop-code': '\uf5fc',
        'fa-desktop': '\uf390',
        'fa-mobile-screen': '\uf3cf',
        'fa-tablet-screen-button': '\uf3fa',
        
        // Peripherals
        'fa-print': '\uf02f',
        'fa-video': '\uf03d',
        'fa-camera': '\uf030',
        'fa-phone': '\uf095',
        
        // IoT
        'fa-lightbulb': '\uf0eb',
        'fa-temperature-half': '\uf2c9',
        
        // Power
        'fa-plug': '\uf1e6',
        'fa-battery-full': '\uf240',
        'fa-bolt': '\uf0e7',
        
        // Monitoring
        'fa-display': '\uf390',
        'fa-tv': '\uf26c',
        
        // Generic
        'fa-circle': '\uf111',
        'fa-square': '\uf0c8',
        'fa-box': '\uf466',
        'fa-cubes': '\uf1b3'
    };
    
    return iconMap[iconClass] || '\uf111'; // Default to circle
}
```

**💡 NOTE**: The unicode values above are examples. You need to get the correct unicode for each Font Awesome icon from:
https://fontawesome.com/icons

---

## 📦 Alternative: Use FontAwesome with vis.js

If converting to unicode is too complex, use this alternative approach:

### Option 1: HTML Icon Shapes

```javascript
const iconClass = MapApp.utils.getDeviceIconClass(
    device.type || 'generic',
    device.subchoice || 0
);

const node = {
    id: device.id,
    label: device.name,
    x: device.x,
    y: device.y,
    shape: 'icon',
    icon: {
        face: '\'FontAwesome\'',
        code: getFontAwesomeUnicode(iconClass),
        size: 50,
        color: statusColor
    },
    deviceData: device
};
```

### Option 2: Custom Image Icons

Generate icon images on-the-fly:

```javascript
const iconClass = MapApp.utils.getDeviceIconClass(
    device.type || 'generic',
    device.subchoice || 0
);

// Generate SVG icon
const iconSvg = `
    <svg width="50" height="50" xmlns="http://www.w3.org/2000/svg">
        <text x="50%" y="50%" 
              text-anchor="middle" 
              dominant-baseline="middle" 
              font-family="FontAwesome" 
              font-size="40" 
              fill="${statusColor}">
            ${getFontAwesomeCharacter(iconClass)}
        </text>
    </svg>
`;

const node = {
    id: device.id,
    label: device.name,
    x: device.x,
    y: device.y,
    shape: 'image',
    image: 'data:image/svg+xml;base64,' + btoa(iconSvg),
    deviceData: device
};
```

---

## ⚡ Quick Fix for Testing

If you want to test immediately without full implementation:

### Temporary Hardcoded Mapping

**File**: `assets/js/map.js` (in device loading function)

```javascript
// Quick test - maps device types to icon codes
function getIconCode(deviceType) {
    const typeMap = {
        'router': '\uf6ff',      // fa-network-wired
        'wifi': '\uf1eb',        // fa-wifi
        'server': '\uf233',      // fa-server
        'switch': '\uf796',      // fa-ethernet
        'firewall': '\uf3ed',    // fa-shield-halved
        'cloud': '\uf0c2',       // fa-cloud
        'database': '\uf1c0',    // fa-database
        'nas': '\uf0a0',         // fa-hdd
        'laptop': '\uf109',      // fa-laptop
        'tablet': '\uf3fa',      // fa-tablet-screen-button
        'mobile': '\uf3cf',      // fa-mobile-screen
        'printer': '\uf02f',     // fa-print
        'camera': '\uf030',      // fa-camera
        'phone': '\uf095',       // fa-phone
        'tower': '\uf519',       // fa-tower-broadcast
        'rack': '\uf1b3',        // fa-cubes
        'punch': '\uf017',       // fa-clock
        'ups': '\uf1e6',         // fa-plug
        'modem': '\uf796',       // fa-ethernet
        'loadbalancer': '\uf24e', // fa-balance-scale
        'iot': '\uf2db',         // fa-microchip
        'monitor': '\uf390',     // fa-desktop
        'keyboard': '\uf11c',    // fa-keyboard
        'box': '\uf466',         // fa-box
        'generic': '\uf111'      // fa-circle
    };
    
    return typeMap[deviceType] || '\uf111';
}

// Use it when creating nodes:
icon: {
    face: 'FontAwesome',
    code: getIconCode(device.type),
    size: 50,
    color: statusColor
}
```

---

## 📝 Complete Implementation Checklist

- [x] 1. Added `getDeviceIconClass()` to `utils.js`
- [x] 2. Loaded icon library in `map.php`
- [ ] 3. Update device node creation to use `getDeviceIconClass()`
- [ ] 4. Add Font Awesome unicode mapping function
- [ ] 5. Test icon changes on map
- [ ] 6. Update device refresh code to preserve icons
- [ ] 7. Update device placement code
- [ ] 8. Test with all 25+ device types

---

## 🧪 Testing Steps

1. **Edit a device**:
   - Go to device list
   - Click edit on any device
   - Change the device type
   - Select a different icon variant
   - Save

2. **Check the map**:
   - Go to network map
   - Find the edited device
   - **Expected**: Icon should match what you selected
   - **If not working**: Check browser console for errors

3. **Debug**:
   ```javascript
   // In browser console:
   console.log('Icon Library:', window.deviceIconsLibrary);
   console.log('Utils:', MapApp.utils);
   console.log('Get Icon Test:', MapApp.utils.getDeviceIconClass('router', 0));
   ```

---

## 💡 Font Awesome Unicode Reference

To get unicode values for icons:

1. Go to https://fontawesome.com/icons
2. Search for your icon
3. Click on it
4. Find the unicode value (e.g., `f0c2` for `fa-cloud`)
5. Convert to JavaScript: `\uf0c2`

Or use this tool:
```javascript
// Get unicode from Font Awesome
function getIconUnicode(iconClass) {
    const tempIcon = document.createElement('i');
    tempIcon.className = `fas ${iconClass}`;
    document.body.appendChild(tempIcon);
    
    const computed = window.getComputedStyle(tempIcon, ':before');
    const content = computed.getPropertyValue('content');
    
    document.body.removeChild(tempIcon);
    return content.replace(/["']/g, '');
}
```

---

## 🐞 Known Issues

### Issue 1: Icons not updating after refresh

**Solution**: Make sure `deviceManager.js` updates include icon:

```javascript
MapApp.state.nodes.update({
    id: deviceId,
    icon: {
        ...node.icon,
        code: getFontAwesomeUnicode(
            MapApp.utils.getDeviceIconClass(device.type, device.subchoice)
        )
    }
});
```

### Issue 2: Wrong icon showing

**Debug**:
```javascript
const node = MapApp.state.nodes.get(deviceId);
console.log('Device type:', node.deviceData.type);
console.log('Subchoice:', node.deviceData.subchoice);
console.log('Icon class:', MapApp.utils.getDeviceIconClass(
    node.deviceData.type, 
    node.deviceData.subchoice
));
```

### Issue 3: Database not saving type/subchoice

**Check**: Make sure `update_device` API saves these fields:

```sql
UPDATE devices 
SET type = ?, subchoice = ?, ...
WHERE id = ?
```

---

## 🌐 Summary

**What's Fixed**:
1. ✅ Icon mapping function created
2. ✅ Icon library loaded on map page

**What's Needed**:
3. ⚠️ Update device node creation code
4. ⚠️ Add Font Awesome unicode mapping
5. ⚠️ Test and verify

**Next Steps**:
1. Find where devices are added to vis.js network
2. Update icon property to use `getDeviceIconClass()`
3. Add unicode conversion helper
4. Test with multiple device types
5. Verify icon updates on edit/save

---

**Last Updated**: December 14, 2025  
**Status**: ⚠️ Partial - Needs Step 3 implementation  
**Files Modified**: 2  
**Files Remaining**: 1-2 (map loading code)

# Enhanced Device Icon Picker Guide

## Overview

The AMPNM Docker application now includes a comprehensive icon picker system that allows users to select from multiple icon variants for each device type. This feature significantly improves the visual representation of network devices on the Docker networking map.

## Features

### 🎨 Multiple Icon Variants

Each device type now offers multiple icon options:

- **Router**: Network, Classic, Nodes, Diagram
- **WiFi Router**: Signal, Tower, Radio, Bars
- **Server**: Vertical, Tower, Computer, Processor
- **Switch**: Ethernet, Branch, Group, Layers
- **Firewall**: Shield, Full Shield, Lock, Unlocked
- **Cloud**: Basic, Upload, Download, Cloudflare
- **Database**: Cylinder, Diagram, Cubes, Server
- **NAS/Storage**: Hard Disk, Drive, Folder, Archive
- **Laptop/Computer**: Closed, Code, Desktop, Monitor
- **Tablet**: Screen, Classic, Full, Rectangle
- **Mobile Phone**: Screen, Button, Classic, Square
- **Printer**: Print, Laser, Scanner, Copy
- **Camera/CCTV**: Video, Photo, Movie, Eye
- **IP Phone**: Classic, Headset, Flip, Volume
- **Radio Tower**: Broadcast, Cell, Pin, Satellite
- **Equipment Rack**: Cubes, Cube, Box, Square
- **Punch Device**: Plug, Chip, Power, Square
- **Group/Container**: Basic, Cubes, Cube, Group
- **Other Device**: Circle, Diamond, Star, Asterisk

## File Structure

### New Files Added

1. **`includes/device_icons.php`**
   - Central icon library definition
   - Contains all device types and their icon variants
   - Easily extensible for adding new device types or icons

2. **`assets/icon-picker.css`**
   - Professional styling for the icon picker interface
   - Includes animations, hover effects, and responsive design
   - Features:
     - Category tabs
     - Scrollable icon gallery
     - Search functionality
     - Selection indicators
     - Dark theme optimized

3. **`assets/icon-picker.js`**
   - Interactive JavaScript for the icon picker
   - Features:
     - Dynamic icon rendering
     - Category switching
     - Icon search/filtering
     - Selection management
     - Event delegation

### Modified Files

1. **`create-device.php`**
   - Integrated device icons library
   - Enhanced UI with improved layout
   - Added icon picker container
   - Loads CSS and JavaScript resources

2. **`edit-device.php`**
   - Same enhancements as create-device.php
   - Maintains existing device icon on edit
   - Allows icon change during device editing

## Usage

### For Users

#### Adding a New Device

1. Navigate to **Add New Device** page
2. Enter device name and basic information
3. In the **Device Type & Icon** section:
   - Select device type from dropdown
   - Browse available icon variants in the icon picker
   - Click on desired icon to select it
   - The dropdown will automatically update
4. Complete remaining fields and submit

#### Editing a Device

1. Navigate to device management
2. Click edit on desired device
3. Follow same process as adding (Device Type & Icon section)
4. Click "Save Changes"

#### Icon Search Feature

- Type in the search box to filter icons by name
- Search works across all icon variants
- Shows "No icons match your search" when no results

### For Developers

#### Adding New Icons

Edit `includes/device_icons.php`:

```php
'router' => [
    'label' => 'Router',
    'icons' => [
        ['icon' => 'fa-network-wired', 'label' => 'Router (Network)'],
        ['icon' => 'fa-router', 'label' => 'Router (Classic)'],
        ['icon' => 'fa-new-icon', 'label' => 'Router (New Variant)'], // NEW
        // ... more icons
    ]
],
```

#### Adding New Device Type

Add new entry to `includes/device_icons.php`:

```php
'new_device_type' => [
    'label' => 'New Device Type',
    'icons' => [
        ['icon' => 'fa-some-icon', 'label' => 'Variant 1'],
        ['icon' => 'fa-other-icon', 'label' => 'Variant 2'],
    ]
],
```

Update database schema if needed to support the new type.

#### Customizing Styles

Modify `assets/icon-picker.css`:

- `.icon-picker-container` - Main container styling
- `.icon-gallery-btn` - Individual icon button styling
- `.icon-gallery-btn.selected` - Selected state styling
- `.icon-category-tab` - Category tab styling
- Animation timing and effects can be adjusted via CSS transitions

#### Extending JavaScript

The `IconPicker` object in `assets/icon-picker.js` can be extended:

```javascript
window.IconPicker.customMethod = function() {
    // Custom functionality
};
```

## Technical Details

### Icon Library Data Structure

```php
[
    'device_type' => [
        'label' => 'Display Name',
        'icons' => [
            [
                'icon' => 'fa-icon-class',
                'label' => 'Icon Description'
            ],
            // ... more icons
        ]
    ]
]
```

### Font Awesome Integration

The system uses Font Awesome 6+ icons. All icons use the `fa-` prefix.

Supported icon classes:
- `fa-network-wired`
- `fa-router`
- `fa-wifi`
- `fa-server`
- `fa-shield-halved`
- And many more...

### Database

The existing `devices` table stores:
- `type` - Device type (device_type from icons library)
- `icon_url` - Optional custom icon URL
- `icon_size` - Icon display size
- `name_text_size` - Name label size

No schema changes required.

## Browser Compatibility

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile browsers (iOS Safari 14+, Chrome Mobile)

## Performance Considerations

- Icon library is loaded inline as JavaScript object
- CSS uses efficient selectors
- No external API calls
- Smooth animations with GPU acceleration via CSS transforms

## Troubleshooting

### Icons Not Showing

1. Verify Font Awesome 6+ is loaded in `header.php`
2. Check that icon class names are correct in `device_icons.php`
3. Clear browser cache

### Icon Picker Not Appearing

1. Verify `device_icons.php` is in `includes/` directory
2. Check that `icon-picker.css` is linked in create/edit-device.php
3. Verify `icon-picker.js` is loaded at bottom of page
4. Check browser console for JavaScript errors

### Icons Not Persisting

1. Verify device is being saved with correct type
2. Check database `type` field contains valid device type
3. Verify `map.php` correctly reads device type

## Future Enhancements

1. **Custom Icon Upload**: Allow users to upload custom icons
2. **Icon Categories**: Organize icons by category (networking, storage, etc.)
3. **Icon Preview**: Show selected icon preview before saving
4. **Drag-to-Reorder**: Reorder icon variants
5. **Icon Sets**: Support multiple icon themes/sets
6. **Recently Used**: Track and suggest recently used icons

## Support

For issues or feature requests related to the icon picker:

1. Check this guide first
2. Review browser console for errors
3. Contact support with:
   - Browser version
   - Steps to reproduce
   - Screenshot of issue
   - Console error messages (if any)

---

**Last Updated**: December 2025
**Version**: 1.0

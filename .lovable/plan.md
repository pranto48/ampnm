

## Floor Plan Interactive Drawing System

### Overview
Transform the current tab-based CRUD Floor Plan page into an interactive CAD-style canvas where users can upload floor plan images, drag-and-drop racks/devices, draw cable runs visually, and add text annotations -- all on an SVG canvas with pan/zoom.

### Database Changes (SQL Migration)

1. **Storage bucket** `floor-plan-files` for uploaded floor plan images (public read, authenticated upload)
2. **New table** `floor_plan_annotations` -- stores text labels and zone markers placed on the canvas:
   - `id uuid PK`, `floor_plan_id uuid`, `x numeric`, `y numeric`, `text text`, `font_size int default 14`, `color text default '#ffffff'`, `type text default 'label'` (label | zone), `width numeric`, `height numeric`, `created_at timestamptz`
   - RLS: admin ALL, authenticated SELECT
3. **Alter `rack_locations`** -- add `rotation integer default 0`, `label_visible boolean default true`

### New Components (8 files)

| Component | Purpose |
|-----------|---------|
| `src/components/floor-plan/FloorPlanCanvas.tsx` | Main SVG canvas with pan (drag empty space), zoom (scroll wheel), transform matrix state. Renders background image, racks, devices, cables, and annotations as SVG elements. Handles mouse events for all drawing modes. |
| `src/components/floor-plan/CanvasToolbar.tsx` | Vertical toolbar: Select, Add Rack, Add Device, Draw Cable, Add Label, Zoom In/Out/Reset, Upload. Active tool highlighted. |
| `src/components/floor-plan/CanvasRackNode.tsx` | SVG `<g>` rendering a rack as a labeled rectangle with rack-unit count. Draggable in select mode. Shows selection handles when active. |
| `src/components/floor-plan/CanvasDeviceNode.tsx` | SVG `<g>` rendering a device icon (circle + type icon) at x/y. Draggable. Tooltip on hover with device details. |
| `src/components/floor-plan/CanvasCableLine.tsx` | SVG `<line>` or `<path>` colored by `cable_color`, connecting source and dest equipment positions. Hover shows cable details. |
| `src/components/floor-plan/CanvasAnnotation.tsx` | SVG `<text>` or `<rect>` + `<text>` for labels/zones. Draggable in select mode. |
| `src/components/floor-plan/PropertiesPanel.tsx` | Right-side slide-out panel showing editable fields for the selected canvas item (rack, device, cable, annotation). Saves inline on change. |
| `src/components/floor-plan/FloorPlanUploader.tsx` | File input (PNG/JPG/PDF) that uploads to `floor-plan-files` bucket, returns public URL, updates `floor_plans.image_url`. |

### Modified File

**`src/pages/FloorPlanPage.tsx`** -- Restructured layout:
- Top: floor plan selector + "New Floor Plan" button (kept)
- Below: two-column layout -- left: `CanvasToolbar`, center: `FloorPlanCanvas`, right: `PropertiesPanel` (shown when item selected)
- Existing CRUD logic (savePlan, saveRack, saveCable, etc.) stays but is called from canvas interactions and properties panel instead of tab dialogs
- Tabs removed; overview stats shown as floating badges on canvas or in a collapsible summary bar
- All existing dialogs kept as fallback for complex forms (cable endpoints, port config)

### Canvas Interaction Model

- **Select mode (default)**: Click item to select (shows PropertiesPanel). Drag to move. Double-click to edit. Right-click for delete.
- **Add Rack mode**: Click canvas to place rack at coordinates. Mini-dialog for name/units, then saves to DB with x/y.
- **Add Device mode**: Shows dropdown of existing devices. Click canvas to place at position. Saves device's floor plan position.
- **Draw Cable mode**: Click first endpoint (rack/device), then click second endpoint. Draws line. Opens cable dialog for details (type, color, etc.).
- **Add Label mode**: Click canvas to place text. Inline text editing.
- **Pan**: Drag on empty canvas space (in any mode). **Zoom**: Mouse wheel.

### File Upload Flow

- `FloorPlanUploader` component handles file selection and upload to `floor-plan-files` storage bucket
- Accepted formats: `.png, .jpg, .jpeg` (direct image display)
- PDF support: uploaded to bucket, rendered via `<object>` tag as fallback; primary recommendation is image upload
- After upload, `floor_plans.image_url` is updated to the public storage URL
- Image displayed as SVG `<image>` background element on the canvas

### Key Technical Decisions

- **SVG canvas**: No new dependencies. DOM-based hit detection, React-friendly. Transform matrix for pan/zoom.
- **Existing tables reused**: `rack_locations.x/y` already exist for positioning. `cable_runs` links source/dest. Canvas is a visual layer on top of existing data.
- **Grid snapping**: Optional 20px grid snap for precise placement, toggled from toolbar.
- **Backwards compatible**: All existing data works. The old tab UI is replaced but all CRUD operations are preserved.


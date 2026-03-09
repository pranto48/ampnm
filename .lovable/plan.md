

## Floor Plan Interactive Drawing System

### Current State
The Floor Plan page (`src/pages/FloorPlanPage.tsx`) is a tabbed CRUD form interface - users manage racks, panels, ports, and cables through dialogs and lists. There is no visual canvas. Floor plan "images" are entered as external URLs. No file upload capability exists.

### What We Will Build
An interactive CAD-style drawing canvas where users:
1. **Upload a floor plan** (PDF/image) as the background layer
2. **Visually place and drag** racks, devices, and network equipment on the floor plan
3. **Draw cable runs** as lines between placed equipment
4. **Add annotations** (text labels, network zones)

### Architecture

```text
┌─────────────────────────────────────────────┐
│  FloorPlanPage                              │
│  ┌─────────────┐  ┌──────────────────────┐  │
│  │  Toolbar     │  │  Canvas (SVG)        │  │
│  │  - Select    │  │  ┌────────────────┐  │  │
│  │  - Add Rack  │  │  │ Floor Plan Img │  │  │
│  │  - Add Device│  │  │ (background)   │  │  │
│  │  - Draw Cable│  │  │                │  │  │
│  │  - Add Label │  │  │  [Rack]  [SW]  │  │  │
│  │  - Zoom/Pan  │  │  │   |---------|  │  │  │
│  │  - Upload    │  │  │  cable run     │  │  │
│  └─────────────┘  │  └────────────────┘  │  │
│                    └──────────────────────┘  │
│  ┌──────────────────────────────────────┐   │
│  │  Properties Panel (selected item)    │   │
│  └──────────────────────────────────────┘   │
└─────────────────────────────────────────────┘
```

### Implementation Steps

**1. Storage Bucket for Floor Plan Files**
- Create a `floor-plan-files` storage bucket via SQL migration
- RLS policies for authenticated users to upload/read
- Support image uploads (PNG, JPG) and PDF files

**2. Database Changes**
- Add `floor_plan_annotations` table for text labels and zone markers placed on the canvas
- Update `rack_locations` to ensure x/y coordinates are used for canvas positioning
- Add `rotation` and `label_visible` columns to rack_locations for visual placement control

**3. New Component: `FloorPlanCanvas`**
- SVG-based interactive canvas (no extra dependencies needed)
- Floor plan image rendered as `<image>` background element
- Pan (mouse drag on empty space) and zoom (scroll wheel) with transform matrix
- Grid snapping option for precise placement

**4. Drawing Tools**
- **Select/Move mode**: Click to select items, drag to reposition. Selected item shows resize handles and properties panel.
- **Add Rack**: Click canvas to place a rack icon at that position. Opens a mini-dialog for name/size, then saves with x/y coordinates.
- **Add Device**: Place monitored devices from the existing device list onto the floor plan at specific locations.
- **Draw Cable**: Click source equipment, then click destination - draws a colored line between them. Opens cable detail dialog on completion.
- **Add Label**: Click to place a text annotation on the canvas.
- **Zoom/Pan**: Mouse wheel to zoom, drag on empty space to pan. Reset view button.

**5. File Upload Flow**
- "Upload Floor Plan" button opens a file picker (accepts `.png, .jpg, .jpeg, .pdf`)
- For images: upload directly to storage bucket, set as `image_url`
- For PDFs: upload to storage bucket, render first page as canvas background using an `<object>` or `<iframe>` fallback, or convert to image via a simple edge function

**6. Interactive Canvas Features**
- Racks rendered as labeled rectangles with rack-unit indicators
- Devices rendered as icons (matching their device type icons from the network map)
- Cable runs rendered as colored SVG `<line>` or `<path>` elements with the cable color
- Hover tooltips showing equipment details
- Right-click context menu for edit/delete
- Items save their x/y position to the database on drag-end

**7. Properties Side Panel**
- When an item is selected on the canvas, a slide-out panel shows its editable properties (same fields as current dialogs)
- Changes save inline without modal dialogs

### Files to Create/Modify

| File | Action |
|------|--------|
| SQL migration | Create `floor-plan-files` bucket, add `floor_plan_annotations` table, add `rotation` to `rack_locations` |
| `src/components/floor-plan/FloorPlanCanvas.tsx` | New - Main SVG canvas with pan/zoom/interaction |
| `src/components/floor-plan/CanvasToolbar.tsx` | New - Drawing tools sidebar |
| `src/components/floor-plan/CanvasRackNode.tsx` | New - SVG rack element |
| `src/components/floor-plan/CanvasDeviceNode.tsx` | New - SVG device element |
| `src/components/floor-plan/CanvasCableLine.tsx` | New - SVG cable path |
| `src/components/floor-plan/CanvasAnnotation.tsx` | New - SVG text label |
| `src/components/floor-plan/PropertiesPanel.tsx` | New - Selected item editor |
| `src/components/floor-plan/FloorPlanUploader.tsx` | New - File upload component |
| `src/pages/FloorPlanPage.tsx` | Refactor - Replace tab-based CRUD with canvas layout, keep existing data/CRUD logic |

### Key Design Decisions
- **SVG over HTML5 Canvas**: SVG gives us DOM-based hit detection, CSS styling, and easier React integration without extra libraries
- **No new dependencies**: Using native SVG + React state for drawing. No fabric.js or konva needed for this scope.
- **Existing tables reused**: `rack_locations` already has x/y columns; `cable_runs` already links source/dest. We just visualize them on the canvas now.
- **Backwards compatible**: All existing rack/panel/port/cable data continues to work. The canvas is an enhanced view layer.


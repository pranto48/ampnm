

# Rebuild AMPNM as React App with Supabase Backend

## Overview

Replace the current simple ping monitor website with a full AMPNM (Advanced Multi-Platform Network Monitor) application -- a React rebuild of the Docker AMPNM PHP app. The Docker local installation stays untouched (it uses PHP + MySQL locally). This website becomes the cloud-hosted version using Supabase for the database.

When you change settings, devices, maps, etc. on this website, they live in the Supabase database. The Docker app continues using its own local MySQL database independently.

---

## Phase 1: Database Schema

Create Supabase tables matching the Docker app's MySQL schema:

- **profiles** -- user profiles linked to Supabase Auth (role: admin/viewer)
- **maps** -- network maps (name, type, background_color, background_image_url, public_view_enabled)
- **devices** -- monitored devices (name, ip, check_port, monitor_method, type, subchoice, status, x, y, map_id, ping_interval, icon_size, thresholds, etc.)
- **device_edges** -- connections between devices (source_id, target_id, map_id, connection_type)
- **device_status_logs** -- status change history
- **ping_results** -- ping result history (replaces existing simple table)
- **network_graphs** -- saved graph URLs
- **smtp_settings** -- email notification config
- **device_email_subscriptions** -- per-device email alert subscriptions
- **app_settings** -- key-value settings store

RLS policies will scope data by user_id, with admin users having full access and viewers having read-only access.

---

## Phase 2: Authentication

- Login page with email/password (Supabase Auth)
- Auto-create profile on signup with a database trigger
- Role-based access (admin vs viewer)
- Protected routes -- redirect to login if not authenticated
- Logout functionality

---

## Phase 3: Layout and Navigation

Recreate the dark slate theme with sidebar/topbar navigation matching the Docker app:

- **Dashboard** -- status chart, device counters, manual ping, recent activity
- **Network** submenu -- Map, Network Graphs
- **Monitoring** submenu -- Host Metrics, Windows Agent
- **Administration** submenu (admin only) -- Devices, History, Status Logs, Email Notifications, Users, License
- **Help/Documentation**
- **Logout**

Mobile-responsive with sidebar toggle.

---

## Phase 4: Core Pages

### Dashboard (/)
- Device status doughnut chart (online/warning/critical/offline)
- Status counter cards
- Manual ping test
- Recent activity feed from status logs

### Network Map (/map)
- Interactive network map (using React Flow or vis-network via CDN)
- Device nodes with icons, status colors, live ping display
- Edge connections with color-coded types (CAT5, Fiber, WiFi, Radio, LAN, Tunnel)
- Map selector, create/delete maps
- Device drag-and-drop positioning
- Map background color/image
- Public map sharing toggle
- Import/export map data

### Devices (/devices)
- Device list with status badges
- Add/edit/delete devices
- Device form with all fields (IP, port, monitor method, icon picker, thresholds, ping interval)
- Bulk device checking

### History (/history)
- Ping result history with filtering by host

### Status Logs (/status-logs)
- Device status change log

### Network Graphs (/network-graphs)
- Manage external graph URLs with iframe embedding

---

## Phase 5: Admin Features

- **Users** (/admin/users) -- manage user accounts and roles
- **Email Notifications** (/admin/notifications) -- SMTP settings, per-device subscriptions
- **License Management** (/admin/license) -- view/change license key, status display

---

## Phase 6: Edge Functions

- **ping-target** -- update existing edge function to work with the new device schema (ping by IP, return latency/packet loss)
- **verify-license** -- keep existing if present, or create for license verification

---

## Phase 7: Cleanup

- Remove all files from `docker-ampnm/` subfolder (keep as reference until migration is complete)
- Remove old ping monitor components (StatusSummary, TargetCard, TargetGrid, AddTargetDialog, NavLink)
- Remove old `useTargets` hook
- Update the existing `targets` and `ping_results` tables or replace them with the new schema

---

## Technical Details

### Files to Remove (old ping monitor)
- `src/components/AddTargetDialog.tsx`
- `src/components/NavLink.tsx`
- `src/components/StatusSummary.tsx`
- `src/components/TargetCard.tsx`
- `src/components/TargetGrid.tsx`
- `src/hooks/useTargets.ts`
- `src/pages/Index.tsx` (will be rewritten)

### New Files to Create
- `src/components/layout/AppLayout.tsx` -- main layout with nav
- `src/components/layout/Sidebar.tsx` -- navigation sidebar
- `src/pages/LoginPage.tsx`
- `src/pages/DashboardPage.tsx`
- `src/pages/MapPage.tsx`
- `src/pages/DevicesPage.tsx`
- `src/pages/HistoryPage.tsx`
- `src/pages/StatusLogsPage.tsx`
- `src/pages/NetworkGraphsPage.tsx`
- `src/pages/UsersPage.tsx`
- `src/pages/NotificationsPage.tsx`
- `src/pages/LicensePage.tsx`
- `src/pages/DocumentationPage.tsx`
- `src/pages/PublicMapPage.tsx`
- `src/hooks/useAuth.ts`
- `src/hooks/useDevices.ts`
- `src/hooks/useMaps.ts`
- Various device/map/edge components

### Styling
- Dark theme (slate-900 background, slate-800 cards) matching Docker app
- Tailwind CSS utility classes
- Cyan accent color for branding
- Font Awesome icons via lucide-react equivalents

### Implementation Order
1. Database schema migration (all tables)
2. Auth system + login page
3. Layout/navigation shell
4. Dashboard page
5. Devices CRUD
6. Network Map
7. History and Status Logs
8. Admin pages (Users, Notifications, License)
9. Network Graphs
10. Cleanup old files

This will be implemented step-by-step across multiple messages, starting with the database schema and authentication.


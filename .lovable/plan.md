

# Phase 2 + 3: Authentication System, Login Page, Layout and Navigation

## What will be built

This step removes the old simple ping monitor UI and replaces it with the full AMPNM application shell -- a login system, app layout with navigation, and a basic dashboard placeholder.

---

## Step 1: Remove old files

Delete the following files that belong to the old ping monitor:
- `src/components/AddTargetDialog.tsx`
- `src/components/NavLink.tsx`
- `src/components/StatusSummary.tsx`
- `src/components/TargetCard.tsx`
- `src/components/TargetGrid.tsx`
- `src/hooks/useTargets.ts`
- `src/pages/Index.tsx` (will be rewritten)

---

## Step 2: Authentication hook (`src/hooks/useAuth.ts`)

- Uses `supabase.auth.onAuthStateChange` to track session state
- `signIn(email, password)` and `signUp(email, password, username)` functions
- `signOut()` function
- Fetches user role from `user_roles` table
- Exposes `user`, `session`, `role`, `loading`, `isAdmin` values
- Context provider pattern (`AuthProvider` + `useAuth` hook)

---

## Step 3: Login page (`src/pages/LoginPage.tsx`)

- Matches the Docker app's dark animated gradient background login screen
- Email + password form (no username -- Supabase Auth uses email)
- Error display for invalid credentials
- AMPNM branding with shield icon
- Redirects to dashboard if already logged in
- Sign-up toggle to allow first user registration

---

## Step 4: App layout (`src/components/layout/AppLayout.tsx`)

- Wraps all authenticated pages
- Top navigation bar matching Docker app's `header.php`:
  - AMPNM logo/brand on left
  - Navigation links with dropdown submenus
  - Mobile sidebar with hamburger toggle
- Navigation structure:
  - Dashboard
  - Network (Map, Network Graphs)
  - Monitoring (Host Metrics, Windows Agent)
  - Administration (admin only): Devices, History, Status Logs, Email Notifications, Users, License
  - Help
  - Logout
- Active link highlighting
- Dark slate theme (`bg-slate-900`, `bg-slate-800/50` navbar)

---

## Step 5: Protected route wrapper (`src/components/auth/ProtectedRoute.tsx`)

- Checks authentication state
- Redirects to `/login` if not authenticated
- Optional `adminOnly` prop to restrict pages to admin role
- Shows loading spinner while checking auth

---

## Step 6: Dashboard page (`src/pages/DashboardPage.tsx`)

- Placeholder dashboard with:
  - Status counter cards (Online, Warning, Critical, Offline) reading from `devices` table
  - Device status doughnut chart (using recharts PieChart)
  - Manual ping test form
  - Recent activity feed from `device_status_logs`
- Map selector dropdown reading from `maps` table

---

## Step 7: Update routing (`src/App.tsx`)

- Add `AuthProvider` wrapper
- Routes:
  - `/login` -> LoginPage (public)
  - `/` -> DashboardPage (protected)
  - All other pages as placeholder `NotFound` for now
- Remove old Index route

---

## Step 8: Update global styles (`src/index.css`)

- Add the animated gradient background keyframes for login page
- Add status indicator CSS classes
- Set dark theme defaults (slate-900 background, Inter font)

---

## Technical details

### New files to create:
- `src/hooks/useAuth.tsx` -- Auth context provider + hook
- `src/components/auth/ProtectedRoute.tsx` -- Route guard
- `src/components/layout/AppLayout.tsx` -- Main layout with navigation
- `src/pages/LoginPage.tsx` -- Login/signup page
- `src/pages/DashboardPage.tsx` -- Main dashboard

### Files to modify:
- `src/App.tsx` -- New routing structure
- `src/index.css` -- Dark theme + animations

### Files to delete:
- `src/components/AddTargetDialog.tsx`
- `src/components/NavLink.tsx`
- `src/components/StatusSummary.tsx`
- `src/components/TargetCard.tsx`
- `src/components/TargetGrid.tsx`
- `src/hooks/useTargets.ts`
- `src/pages/Index.tsx`

### Auth flow:
```text
User visits / --> ProtectedRoute checks session
  |-- No session --> Redirect to /login
  |-- Has session --> Fetch role from user_roles table
       |-- Render AppLayout with DashboardPage
```

### Role checking:
- Uses existing `has_role()` database function
- Client-side role is fetched for UI display only (show/hide admin menu)
- RLS policies on tables enforce actual access control server-side

### First user setup:
- The existing `handle_new_user()` trigger auto-assigns `admin` role to the first registered user
- Subsequent users get `viewer` role
- Sign-up available on login page for initial setup


import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { AuthProvider } from "@/hooks/useAuth";
import { ProtectedRoute } from "@/components/auth/ProtectedRoute";
import LoginPage from "./pages/LoginPage";
import DashboardPage from "./pages/DashboardPage";
import DevicesPage from "./pages/DevicesPage";
import NetworkMapPage from "./pages/NetworkMapPage";
import HistoryPage from "./pages/HistoryPage";
import StatusLogsPage from "./pages/StatusLogsPage";
import NetworkGraphsPage from "./pages/NetworkGraphsPage";
import HostMetricsPage from "./pages/HostMetricsPage";
import WindowsAgentPage from "./pages/WindowsAgentPage";
import NotificationsPage from "./pages/NotificationsPage";
import UsersPage from "./pages/UsersPage";
import LicensePage from "./pages/LicensePage";
import HelpPage from "./pages/HelpPage";
import NotFound from "./pages/NotFound";

const queryClient = new QueryClient();

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <Toaster />
      <Sonner />
      <BrowserRouter>
        <AuthProvider>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/" element={<ProtectedRoute><DashboardPage /></ProtectedRoute>} />
            <Route path="/map" element={<ProtectedRoute><NetworkMapPage /></ProtectedRoute>} />
            <Route path="/network-graphs" element={<ProtectedRoute><NetworkGraphsPage /></ProtectedRoute>} />
            <Route path="/host-metrics" element={<ProtectedRoute><HostMetricsPage /></ProtectedRoute>} />
            <Route path="/windows-agent" element={<ProtectedRoute><WindowsAgentPage /></ProtectedRoute>} />
            <Route path="/devices" element={<ProtectedRoute adminOnly><DevicesPage /></ProtectedRoute>} />
            <Route path="/history" element={<ProtectedRoute adminOnly><HistoryPage /></ProtectedRoute>} />
            <Route path="/status-logs" element={<ProtectedRoute adminOnly><StatusLogsPage /></ProtectedRoute>} />
            <Route path="/notifications" element={<ProtectedRoute adminOnly><NotificationsPage /></ProtectedRoute>} />
            <Route path="/users" element={<ProtectedRoute adminOnly><UsersPage /></ProtectedRoute>} />
            <Route path="/license" element={<ProtectedRoute adminOnly><LicensePage /></ProtectedRoute>} />
            <Route path="/help" element={<ProtectedRoute><HelpPage /></ProtectedRoute>} />
            <Route path="*" element={<NotFound />} />
          </Routes>
        </AuthProvider>
      </BrowserRouter>
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;

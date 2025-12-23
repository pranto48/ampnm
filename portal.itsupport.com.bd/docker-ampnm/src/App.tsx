import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import Index from "./pages/Index";
import NotFound from "./pages/NotFound";
import AddDevicePage from "./pages/AddDevicePage";
import EditDevicePage from "./pages/EditDevicePage";
import LicenseManagementPage from "./pages/LicenseManagementPage";
import PublicMapPage from "./pages/PublicMapPage"; // Import new page

const queryClient = new QueryClient();

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <Toaster />
      <Sonner />
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<Index />} />
          <Route path="/add-device" element={<AddDevicePage />} />
          <Route path="/edit-device/:id" element={<EditDevicePage />} />
          <Route path="/license-management" element={<LicenseManagementPage />} />
          <Route path="/public-map/:mapId" element={<PublicMapPage />} /> {/* New route */}
          {/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
          <Route path="*" element={<NotFound />} />
        </Routes>
      </BrowserRouter>
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;
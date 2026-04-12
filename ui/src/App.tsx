import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { HashRouter, Route, Routes } from "react-router-dom";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import { AuthProvider } from "@/contexts/auth-context";
import PrivateRoute from "@/components/PrivateRoute";
import Index from "./pages/Index.tsx";
import Login from "./pages/Login.tsx";
import Register from "./pages/Register.tsx";
import NotFound from "./pages/NotFound.tsx";
import RoleRedirect from "./pages/RoleRedirect.tsx";
import DoctorDashboard from "./pages/DoctorDashboard.tsx";
import AdminDashboard from "./pages/AdminDashboard.tsx";
import RoleRoute from "@/components/RoleRoute";

const queryClient = new QueryClient();

const App = () => (
  <QueryClientProvider client={queryClient}>
    <AuthProvider>
      <TooltipProvider>
        <Toaster />
        <Sonner />
        <HashRouter>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/register" element={<Register />} />
            <Route
              path="/"
              element={
                <PrivateRoute>
                  <RoleRedirect />
                </PrivateRoute>
              }
            />
            <Route
              path="/patient"
              element={
                <PrivateRoute>
                  <RoleRoute role="patient">
                    <Index />
                  </RoleRoute>
                </PrivateRoute>
              }
            />
            <Route
              path="/doctor"
              element={
                <PrivateRoute>
                  <RoleRoute role="doctor">
                    <DoctorDashboard />
                  </RoleRoute>
                </PrivateRoute>
              }
            />
            <Route
              path="/admin"
              element={
                <PrivateRoute>
                  <RoleRoute role="admin">
                    <AdminDashboard />
                  </RoleRoute>
                </PrivateRoute>
              }
            />
            <Route path="*" element={<NotFound />} />
          </Routes>
        </HashRouter>
      </TooltipProvider>
    </AuthProvider>
  </QueryClientProvider>
);

export default App;

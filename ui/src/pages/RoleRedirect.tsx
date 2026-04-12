import { Navigate } from "react-router-dom";
import { useAuth } from "@/contexts/auth-context";

export default function RoleRedirect() {
  const { user } = useAuth();
  if (!user) {
    return <Navigate to="/login" replace />;
  }
  switch (user.role) {
    case "patient":
      return <Navigate to="/patient" replace />;
    case "doctor":
      return <Navigate to="/doctor" replace />;
    case "admin":
      return <Navigate to="/admin" replace />;
    default:
      return <Navigate to="/patient" replace />;
  }
}

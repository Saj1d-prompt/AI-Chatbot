import {
  Navigate,
  useLocation,
} from "react-router-dom";

import { useAuth } from "../../context/AuthContext";

function ProtectedRoute({
  children,
}) {
  const {
    isAuthenticated,
    isAuthLoading,
  } = useAuth();

  const location =
    useLocation();

  if (isAuthLoading) {
    return (
      <div className="auth-loading-screen">
        <div className="auth-loading-spinner" />

        <span>
          Loading your workspace...
        </span>
      </div>
    );
  }

  if (!isAuthenticated) {
    return (
      <Navigate
        to="/login"
        replace
        state={{
          from: location.pathname,
        }}
      />
    );
  }

  return children;
}

export default ProtectedRoute;
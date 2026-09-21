import {
  Navigate,
  Route,
  Routes,
} from "react-router-dom";

import ProtectedRoute from "./components/auth/ProtectedRoute";
import { useAuth } from "./context/AuthContext";
import ChatPage from "./pages/ChatPage";
import LoginPage from "./pages/LoginPage";
import RegisterPage from "./pages/RegisterPage";

function App() {
  const {
    isAuthenticated,
    isAuthLoading,
  } = useAuth();

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

  return (
    <Routes>
      <Route
        path="/login"
        element={<LoginPage />}
      />

      <Route
        path="/register"
        element={<RegisterPage />}
      />

      <Route
        path="/"
        element={
          <ProtectedRoute>
            <ChatPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="*"
        element={
          <Navigate
            to={
              isAuthenticated
                ? "/"
                : "/login"
            }
            replace
          />
        }
      />
    </Routes>
  );
}

export default App;
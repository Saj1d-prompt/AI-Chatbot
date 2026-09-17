import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
} from "react";

import {
  getCurrentUser,
  loginUser,
  logoutUser,
  registerUser,
} from "../services/api/authApi";

const AuthContext = createContext(null);

export function AuthProvider({
  children,
}) {
  const [user, setUser] = useState(null);

  const [isAuthLoading, setIsAuthLoading] =
    useState(true);

  const checkAuthentication =
    useCallback(async () => {
      try {
        const data =
          await getCurrentUser();

        if (data.success && data.user) {
          setUser(data.user);
        } else {
          setUser(null);
        }
      } catch (error) {
        if (
          error.response?.status !== 401
        ) {
          console.error(
            "Authentication check failed:",
            error
          );
        }

        setUser(null);
      } finally {
        setIsAuthLoading(false);
      }
    }, []);

  useEffect(() => {
    checkAuthentication();
  }, [checkAuthentication]);

  const register = async (
    formData
  ) => {
    const data =
      await registerUser(formData);

    if (!data.success || !data.user) {
      throw new Error(
        "Invalid registration response."
      );
    }

    setUser(data.user);

    return data;
  };

  const login = async (formData) => {
    const data =
      await loginUser(formData);

    if (!data.success || !data.user) {
      throw new Error(
        "Invalid login response."
      );
    }

    setUser(data.user);

    return data;
  };

  const logout = async () => {
    await logoutUser();

    setUser(null);
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated: Boolean(user),
        isAuthLoading,
        register,
        login,
        logout,
        refreshUser:
          checkAuthentication,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context =
    useContext(AuthContext);

  if (!context) {
    throw new Error(
      "useAuth must be used inside AuthProvider."
    );
  }

  return context;
}
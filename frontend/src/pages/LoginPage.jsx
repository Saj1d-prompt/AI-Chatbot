import { useState } from "react";

import {
  Link,
  Navigate,
  useLocation,
  useNavigate,
} from "react-router-dom";

import {
  ArrowRight,
  LoaderCircle,
} from "lucide-react";

import AuthLayout from "../components/auth/AuthLayout";
import { useAuth } from "../context/AuthContext";

function LoginPage() {
  const navigate = useNavigate();
  const location = useLocation();

  const {
    login,
    isAuthenticated,
  } = useAuth();

  const [formData, setFormData] =
    useState({
      email: "",
      password: "",
      remember: false,
    });

  const [isSubmitting, setIsSubmitting] =
    useState(false);

  const [error, setError] =
    useState("");

  if (isAuthenticated) {
    return (
      <Navigate
        to="/"
        replace
      />
    );
  }

  const handleChange = (event) => {
    const {
      name,
      value,
      type,
      checked,
    } = event.target;

    setFormData(
      (currentData) => ({
        ...currentData,

        [name]:
          type === "checkbox"
            ? checked
            : value,
      })
    );

    if (error) {
      setError("");
    }
  };

  const handleSubmit = async (
    event
  ) => {
    event.preventDefault();

    if (isSubmitting) {
      return;
    }

    setIsSubmitting(true);
    setError("");

    try {
      await login(formData);

      const destination =
        location.state?.from || "/";

      navigate(
        destination,
        {
          replace: true,
        }
      );
    } catch (requestError) {
      console.error(
        "Login failed:",
        requestError
      );

      setError(
        requestError.response?.data
          ?.message ||
          "Unable to sign in. Please try again."
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AuthLayout
      title="Welcome back"
      description="Sign in to continue to your AI workspace."
    >
      <form
        className="auth-form"
        onSubmit={handleSubmit}
      >
        {error && (
          <div className="auth-error">
            {error}
          </div>
        )}

        <label className="auth-field">
          <span>Email</span>

          <input
            type="email"
            name="email"
            value={formData.email}
            autoComplete="email"
            placeholder="you@example.com"
            required
            onChange={handleChange}
          />
        </label>

        <label className="auth-field">
          <span>Password</span>

          <input
            type="password"
            name="password"
            value={
              formData.password
            }
            autoComplete="current-password"
            placeholder="Enter your password"
            required
            onChange={handleChange}
          />
        </label>

        <label className="auth-checkbox">
          <input
            type="checkbox"
            name="remember"
            checked={
              formData.remember
            }
            onChange={handleChange}
          />

          <span>
            Keep me signed in
          </span>
        </label>

        <button
          className="auth-submit-button"
          type="submit"
          disabled={isSubmitting}
        >
          {isSubmitting ? (
            <>
              <LoaderCircle
                className="auth-spinner"
                size={17}
              />

              Signing in...
            </>
          ) : (
            <>
              Sign in

              <ArrowRight size={17} />
            </>
          )}
        </button>
      </form>

      <p className="auth-switch">
        Don't have an account?{" "}

        <Link to="/register">
          Create one
        </Link>
      </p>
    </AuthLayout>
  );
}

export default LoginPage;
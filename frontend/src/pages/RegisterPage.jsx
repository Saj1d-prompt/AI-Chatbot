import { useState } from "react";

import {
  ArrowRight,
  LoaderCircle,
} from "lucide-react";

import {
  Link,
  Navigate,
  useNavigate,
} from "react-router-dom";

import AuthLayout from "../components/auth/AuthLayout";
import { useAuth } from "../context/AuthContext";

function RegisterPage() {
  const navigate = useNavigate();

  const {
    register,
    isAuthenticated,
  } = useAuth();

  const [formData, setFormData] =
    useState({
      name: "",
      email: "",
      password: "",
      passwordConfirmation: "",
    });

  const [isSubmitting, setIsSubmitting] =
    useState(false);

  const [error, setError] =
    useState("");

  const [
    validationErrors,
    setValidationErrors,
  ] = useState({});

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
    } = event.target;

    setFormData(
      (currentData) => ({
        ...currentData,
        [name]: value,
      })
    );

    if (validationErrors[name]) {
      setValidationErrors(
        (currentErrors) => ({
          ...currentErrors,
          [name]: undefined,
        })
      );
    }

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
    setValidationErrors({});

    try {
      await register(formData);

      navigate("/", {
        replace: true,
      });
    } catch (requestError) {
      console.error(
        "Registration failed:",
        requestError
      );

      if (
        requestError.response?.status ===
        422
      ) {
        const backendErrors =
          requestError.response?.data
            ?.errors;

        if (backendErrors) {
          setValidationErrors(
            backendErrors
          );
        }
      }

      setError(
        requestError.response?.data
          ?.message ||
          "Unable to create your account."
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AuthLayout
      title="Create your account"
      description="Create an account to keep your conversations private and available across sessions."
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
          <span>Name</span>

          <input
            type="text"
            name="name"
            value={formData.name}
            autoComplete="name"
            placeholder="Your name"
            required
            onChange={handleChange}
          />

          {validationErrors.name?.[0] && (
            <small className="auth-field-error">
              {
                validationErrors
                  .name[0]
              }
            </small>
          )}
        </label>

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

          {validationErrors.email?.[0] && (
            <small className="auth-field-error">
              {
                validationErrors
                  .email[0]
              }
            </small>
          )}
        </label>

        <label className="auth-field">
          <span>Password</span>

          <input
            type="password"
            name="password"
            value={
              formData.password
            }
            autoComplete="new-password"
            placeholder="At least 8 characters"
            required
            onChange={handleChange}
          />

          {validationErrors.password?.[0] && (
            <small className="auth-field-error">
              {
                validationErrors
                  .password[0]
              }
            </small>
          )}
        </label>

        <label className="auth-field">
          <span>
            Confirm password
          </span>

          <input
            type="password"
            name="passwordConfirmation"
            value={
              formData
                .passwordConfirmation
            }
            autoComplete="new-password"
            placeholder="Repeat your password"
            required
            onChange={handleChange}
          />
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

              Creating account...
            </>
          ) : (
            <>
              Create account

              <ArrowRight size={17} />
            </>
          )}
        </button>
      </form>

      <p className="auth-switch">
        Already have an account?{" "}

        <Link to="/login">
          Sign in
        </Link>
      </p>
    </AuthLayout>
  );
}

export default RegisterPage;
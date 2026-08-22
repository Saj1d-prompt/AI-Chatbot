import { AlertCircle, X } from "lucide-react";

import "./ErrorBanner.css";

function ErrorBanner({ message, onClose }) {
  if (!message) {
    return null;
  }

  return (
    <div className="error-banner">
      <AlertCircle size={16} />

      <span>{message}</span>

      <button
        type="button"
        aria-label="Dismiss error"
        onClick={onClose}
      >
        <X size={15} />
      </button>
    </div>
  );
}

export default ErrorBanner;
import { Bot } from "lucide-react";

import "./Auth.css";

function AuthLayout({
  title,
  description,
  children,
}) {
  return (
    <main className="auth-page">
      <section className="auth-panel">
        <div className="auth-brand">
          <div className="auth-brand-icon">
            <Bot size={21} />
          </div>

          <div>
            <strong>Nexus</strong>
            <span>AI Assistant</span>
          </div>
        </div>

        <div className="auth-heading">
          <h1>{title}</h1>

          <p>{description}</p>
        </div>

        {children}
      </section>
    </main>
  );
}

export default AuthLayout;
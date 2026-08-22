import { Bot, User } from "lucide-react";

import "./MessageList.css";

function MessageList({ messages, isLoading }) {
  return (
    <section className="message-list">
      <div className="message-list-inner">
        {messages.map((message) => (
          <article
            className={`chat-message chat-message-${message.role}`}
            key={message.id}
          >
            <div className="message-avatar">
              {message.role === "assistant" ? (
                <Bot size={17} />
              ) : (
                <User size={16} />
              )}
            </div>

            <div className="message-body">
              <div className="message-meta">
                <span>
                  {message.role === "assistant"
                    ? "Nexus"
                    : "You"}
                </span>
              </div>

              <div className="message-content">
                {message.content}
              </div>
            </div>
          </article>
        ))}

        {isLoading && (
          <article className="chat-message chat-message-assistant">
            <div className="message-avatar">
              <Bot size={17} />
            </div>

            <div className="message-body">
              <div className="message-meta">
                <span>Nexus</span>
              </div>

              <div
                className="thinking-indicator"
                aria-label="AI is thinking"
              >
                <span />
                <span />
                <span />
              </div>
            </div>
          </article>
        )}
      </div>
    </section>
  );
}

export default MessageList;
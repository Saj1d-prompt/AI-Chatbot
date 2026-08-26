import { useState } from "react";

import {
  Bot,
  Check,
  Copy,
  LoaderCircle,
  User,
} from "lucide-react";

import MarkdownMessage from "./MarkdownMessage";

import "./MessageList.css";

function MessageList({
  messages,
  isLoading,
  isLoadingOlder,
}) {
  const [copiedMessageId, setCopiedMessageId] =
    useState(null);

  const handleCopy = async (message) => {
    try {
      await navigator.clipboard.writeText(
        message.content
      );

      setCopiedMessageId(message.id);

      window.setTimeout(() => {
        setCopiedMessageId((currentId) =>
          currentId === message.id
            ? null
            : currentId
        );
      }, 1800);
    } catch (error) {
      console.error(
        "Failed to copy message:",
        error
      );
    }
  };

  return (
    <section className="message-list">
      <div className="message-list-inner">
        {isLoadingOlder && (
          <div className="older-message-loader">
            <LoaderCircle size={15} />

            <span>
              Loading older messages...
            </span>
          </div>
        )}

        {messages.map((message) => {
          const isAssistant =
            message.role === "assistant";

          const isCopied =
            copiedMessageId === message.id;

          return (
            <article
              className={`chat-message chat-message-${message.role}`}
              key={message.id}
            >
              <div className="message-avatar">
                {isAssistant ? (
                  <Bot size={17} />
                ) : (
                  <User size={16} />
                )}
              </div>

              <div className="message-body">
                <div className="message-meta">
                  <span>
                    {isAssistant
                      ? "Nexus"
                      : "You"}
                  </span>
                </div>

                <div className="message-content">
                  {isAssistant ? (
                    <MarkdownMessage
                      content={message.content}
                    />
                  ) : (
                    message.content
                  )}
                </div>

                {isAssistant && (
                  <div className="message-actions">
                    <button
                      type="button"
                      className="message-action-button"
                      onClick={() =>
                        handleCopy(message)
                      }
                      aria-label="Copy response"
                      title="Copy response"
                    >
                      {isCopied ? (
                        <>
                          <Check size={14} />
                          <span>Copied</span>
                        </>
                      ) : (
                        <>
                          <Copy size={14} />
                          <span>Copy</span>
                        </>
                      )}
                    </button>
                  </div>
                )}
              </div>
            </article>
          );
        })}

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
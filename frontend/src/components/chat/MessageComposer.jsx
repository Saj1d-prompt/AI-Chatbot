import {
  ArrowUp,
  Paperclip,
  SlidersHorizontal,
} from "lucide-react";

import "./MessageComposer.css";

function MessageComposer({
  value,
  onChange,
  onSubmit,
}) {
  const handleKeyDown = (event) => {
    if (
      event.key === "Enter" &&
      !event.shiftKey
    ) {
      event.preventDefault();

      onSubmit(event);
    }
  };

  return (
    <div className="composer-wrapper">
      <form
        className="message-composer"
        onSubmit={onSubmit}
      >
        <textarea
          value={value}
          rows={1}
          placeholder="Ask Nexus anything..."
          aria-label="Message"
          onChange={(event) =>
            onChange(event.target.value)
          }
          onKeyDown={handleKeyDown}
        />

        <div className="composer-toolbar">
          <div className="composer-tools">
            <button
              type="button"
              className="composer-tool-button"
              aria-label="Attach file"
            >
              <Paperclip size={17} />
            </button>

            <button
              type="button"
              className="composer-tool-button"
              aria-label="Chat settings"
            >
              <SlidersHorizontal size={16} />
            </button>
          </div>

          <button
            className="composer-send-button"
            type="submit"
            disabled={!value.trim()}
            aria-label="Send message"
          >
            <ArrowUp size={18} strokeWidth={2.4} />
          </button>
        </div>
      </form>

      <p className="composer-disclaimer">
        AI can make mistakes. Verify important information.
      </p>
    </div>
  );
}

export default MessageComposer;
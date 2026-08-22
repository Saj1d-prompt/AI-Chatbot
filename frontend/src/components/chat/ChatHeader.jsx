import {
  ChevronDown,
  Menu,
  MoreHorizontal,
} from "lucide-react";

import "./ChatHeader.css";

function ChatHeader({ onMenuClick }) {
  return (
    <header className="chat-header">
      <div className="chat-header-left">
        <button
          className="mobile-menu-button"
          type="button"
          aria-label="Open sidebar"
          onClick={onMenuClick}
        >
          <Menu size={19} />
        </button>

        <button className="assistant-selector" type="button">
          <div>
            <span className="assistant-name">
              Nexus Assistant
            </span>

            <span className="assistant-model">
              Cloudflare Workers AI
            </span>
          </div>

          <ChevronDown size={14} />
        </button>
      </div>

      <div className="chat-header-right">
        <div className="connection-status">
          <span className="connection-dot" />
          <span>Online</span>
        </div>

        <button
          className="header-action-button"
          type="button"
          aria-label="Conversation options"
        >
          <MoreHorizontal size={19} />
        </button>
      </div>
    </header>
  );
}

export default ChatHeader;
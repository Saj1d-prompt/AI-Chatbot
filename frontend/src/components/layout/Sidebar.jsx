import {
  Bot,
  MessageSquareText,
  MoreHorizontal,
  PanelLeftClose,
  Plus,
  Search,
  Settings2,
} from "lucide-react";

import "./Sidebar.css";

const conversations = [
  {
    group: "Today",
    items: [
      {
        id: 1,
        title: "Building a Laravel API",
        active: true,
      },
      {
        id: 2,
        title: "React component architecture",
      },
    ],
  },
  {
    group: "Previous 7 days",
    items: [
      {
        id: 3,
        title: "Understanding REST APIs",
      },
      {
        id: 4,
        title: "Project architecture ideas",
      },
    ],
  },
];

function Sidebar({ isOpen, onClose, onNewChat }) {
  return (
    <aside className={`sidebar ${isOpen ? "sidebar-open" : ""}`}>
      <div className="sidebar-top">
        <div className="sidebar-brand-row">
          <div className="sidebar-brand">
            <div className="brand-symbol">
              <Bot size={19} strokeWidth={2} />
            </div>

            <div className="brand-copy">
              <span className="brand-title">Nexus</span>
              <span className="brand-subtitle">AI Assistant</span>
            </div>
          </div>

          <button
            className="sidebar-icon-button mobile-close-button"
            type="button"
            aria-label="Close sidebar"
            onClick={onClose}
          >
            <PanelLeftClose size={18} />
          </button>
        </div>

        <button
          className="new-chat-button"
          type="button"
          onClick={onNewChat}
        >
          <Plus size={17} />
          <span>New conversation</span>
        </button>

        <button className="sidebar-search" type="button">
          <Search size={16} />
          <span>Search conversations</span>

          <kbd>⌘ K</kbd>
        </button>
      </div>

      <div className="conversation-scroll">
        {conversations.map((group) => (
          <section className="conversation-group" key={group.group}>
            <p className="conversation-group-title">
              {group.group}
            </p>

            <div className="conversation-list">
              {group.items.map((conversation) => (
                <button
                  className={`conversation-item ${
                    conversation.active
                      ? "conversation-item-active"
                      : ""
                  }`}
                  type="button"
                  key={conversation.id}
                >
                  <MessageSquareText
                    className="conversation-icon"
                    size={16}
                  />

                  <span className="conversation-title">
                    {conversation.title}
                  </span>

                  <MoreHorizontal
                    className="conversation-more"
                    size={16}
                  />
                </button>
              ))}
            </div>
          </section>
        ))}
      </div>

      <div className="sidebar-footer">
        <button className="sidebar-footer-button" type="button">
          <Settings2 size={17} />

          <div>
            <span>Settings</span>
            <small>Preferences & configuration</small>
          </div>
        </button>

        <div className="sidebar-profile">
          <div className="profile-avatar">U</div>

          <div className="profile-copy">
            <span>User</span>
            <small>Local development</small>
          </div>

          <MoreHorizontal size={17} />
        </div>
      </div>
    </aside>
  );
}

export default Sidebar;
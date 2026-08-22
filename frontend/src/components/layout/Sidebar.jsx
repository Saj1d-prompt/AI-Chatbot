import {
  Bot,
  LoaderCircle,
  MessageSquareText,
  MoreHorizontal,
  PanelLeftClose,
  Plus,
  Search,
  Settings2,
} from "lucide-react";

import "./Sidebar.css";

function groupConversations(conversations) {
  const today = [];
  const earlier = [];

  const todayKey = new Date().toDateString();

  conversations.forEach((conversation) => {
    const updatedAt = conversation.updated_at
      ? new Date(conversation.updated_at)
      : null;

    if (
      updatedAt &&
      updatedAt.toDateString() === todayKey
    ) {
      today.push(conversation);
    } else {
      earlier.push(conversation);
    }
  });

  return [
    {
      label: "Today",
      items: today,
    },
    {
      label: "Earlier",
      items: earlier,
    },
  ].filter((group) => group.items.length > 0);
}

function Sidebar({
  isOpen,
  onClose,
  onNewChat,
  conversations,
  activeConversationId,
  onSelectConversation,
  isLoadingConversations,
  isCreatingConversation,
}) {
  const groups = groupConversations(conversations);

  const handleConversationClick = (conversationId) => {
    onSelectConversation(conversationId);
    onClose();
  };

  return (
    <aside
      className={`sidebar ${
        isOpen ? "sidebar-open" : ""
      }`}
    >
      <div className="sidebar-top">
        <div className="sidebar-brand-row">
          <div className="sidebar-brand">
            <div className="brand-symbol">
              <Bot size={19} strokeWidth={2} />
            </div>

            <div className="brand-copy">
              <span className="brand-title">
                Nexus
              </span>

              <span className="brand-subtitle">
                AI Assistant
              </span>
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
          disabled={isCreatingConversation}
          onClick={onNewChat}
        >
          {isCreatingConversation ? (
            <LoaderCircle
              className="sidebar-loader"
              size={17}
            />
          ) : (
            <Plus size={17} />
          )}

          <span>
            {isCreatingConversation
              ? "Creating..."
              : "New conversation"}
          </span>
        </button>

        <button
          className="sidebar-search"
          type="button"
        >
          <Search size={16} />

          <span>Search conversations</span>
        </button>
      </div>

      <div className="conversation-scroll">
        {isLoadingConversations ? (
          <div className="sidebar-loading-state">
            <LoaderCircle
              className="sidebar-loader"
              size={17}
            />

            <span>Loading conversations...</span>
          </div>
        ) : groups.length === 0 ? (
          <div className="sidebar-empty-state">
            <MessageSquareText size={20} />

            <span>No conversations yet</span>

            <small>
              Start a new conversation to begin.
            </small>
          </div>
        ) : (
          groups.map((group) => (
            <section
              className="conversation-group"
              key={group.label}
            >
              <p className="conversation-group-title">
                {group.label}
              </p>

              <div className="conversation-list">
                {group.items.map((conversation) => (
                  <button
                    className={`conversation-item ${
                      conversation.id ===
                      activeConversationId
                        ? "conversation-item-active"
                        : ""
                    }`}
                    type="button"
                    key={conversation.id}
                    onClick={() =>
                      handleConversationClick(
                        conversation.id
                      )
                    }
                  >
                    <MessageSquareText
                      className="conversation-icon"
                      size={16}
                    />

                    <span className="conversation-title">
                      {conversation.title ||
                        "New conversation"}
                    </span>

                    <MoreHorizontal
                      className="conversation-more"
                      size={16}
                    />
                  </button>
                ))}
              </div>
            </section>
          ))
        )}
      </div>

      <div className="sidebar-footer">
        <button
          className="sidebar-footer-button"
          type="button"
        >
          <Settings2 size={17} />

          <div>
            <span>Settings</span>

            <small>
              Preferences & configuration
            </small>
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
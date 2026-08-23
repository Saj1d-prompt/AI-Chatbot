import { useState } from "react";

import {
  Bot,
  Check,
  LoaderCircle,
  MessageSquareText,
  MoreHorizontal,
  PanelLeftClose,
  Pencil,
  Plus,
  Search,
  Settings2,
  Trash2,
  X,
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
  onRenameConversation,
  onDeleteConversation,
  isLoadingConversations,
  isCreatingConversation,
}) {
  const [menuConversationId, setMenuConversationId] =
    useState(null);

  const [editingConversationId, setEditingConversationId] =
    useState(null);

  const [editingTitle, setEditingTitle] =
    useState("");

  const groups =
    groupConversations(conversations);

  const handleConversationClick = (
    conversationId
  ) => {
    if (
      editingConversationId === conversationId
    ) {
      return;
    }

    setMenuConversationId(null);

    onSelectConversation(conversationId);

    onClose();
  };

  const handleMenuClick = (
    event,
    conversationId
  ) => {
    event.stopPropagation();

    setMenuConversationId(
      (currentId) =>
        currentId === conversationId
          ? null
          : conversationId
    );
  };

  const startRename = (
    event,
    conversation
  ) => {
    event.stopPropagation();

    setMenuConversationId(null);

    setEditingConversationId(
      conversation.id
    );

    setEditingTitle(
      conversation.title || ""
    );
  };

  const cancelRename = (event) => {
    event?.stopPropagation();

    setEditingConversationId(null);
    setEditingTitle("");
  };

  const submitRename = async (
    event,
    conversationId
  ) => {
    event.preventDefault();
    event.stopPropagation();

    const title = editingTitle.trim();

    if (!title) {
      return;
    }

    const success =
      await onRenameConversation(
        conversationId,
        title
      );

    if (success) {
      setEditingConversationId(null);
      setEditingTitle("");
    }
  };

  const handleDelete = async (
    event,
    conversationId
  ) => {
    event.stopPropagation();

    setMenuConversationId(null);

    await onDeleteConversation(
      conversationId
    );
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
              <Bot
                size={19}
                strokeWidth={2}
              />
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

          <span>
            Search conversations
          </span>
        </button>
      </div>

      <div className="conversation-scroll">
        {isLoadingConversations ? (
          <div className="sidebar-loading-state">
            <LoaderCircle
              className="sidebar-loader"
              size={17}
            />

            <span>
              Loading conversations...
            </span>
          </div>
        ) : groups.length === 0 ? (
          <div className="sidebar-empty-state">
            <MessageSquareText size={20} />

            <span>
              No conversations yet
            </span>

            <small>
              Start a new conversation
              to begin.
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
                {group.items.map(
                  (conversation) => {
                    const isEditing =
                      editingConversationId ===
                      conversation.id;

                    const menuOpen =
                      menuConversationId ===
                      conversation.id;

                    return (
                      <div
                        className="conversation-row"
                        key={conversation.id}
                      >
                        {isEditing ? (
                          <form
                            className="conversation-rename-form"
                            onSubmit={(event) =>
                              submitRename(
                                event,
                                conversation.id
                              )
                            }
                          >
                            <input
                              autoFocus
                              value={editingTitle}
                              maxLength={120}
                              onChange={(event) =>
                                setEditingTitle(
                                  event.target.value
                                )
                              }
                              onKeyDown={(event) => {
                                if (
                                  event.key ===
                                  "Escape"
                                ) {
                                  cancelRename(
                                    event
                                  );
                                }
                              }}
                            />

                            <button
                              type="submit"
                              aria-label="Save conversation title"
                              disabled={
                                !editingTitle.trim()
                              }
                            >
                              <Check size={14} />
                            </button>

                            <button
                              type="button"
                              aria-label="Cancel rename"
                              onClick={
                                cancelRename
                              }
                            >
                              <X size={14} />
                            </button>
                          </form>
                        ) : (
                          <>
                            <button
                              className={`conversation-item ${
                                conversation.id ===
                                activeConversationId
                                  ? "conversation-item-active"
                                  : ""
                              }`}
                              type="button"
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
                            </button>

                            <button
                              className="conversation-menu-button"
                              type="button"
                              aria-label="Conversation options"
                              onClick={(event) =>
                                handleMenuClick(
                                  event,
                                  conversation.id
                                )
                              }
                            >
                              <MoreHorizontal
                                size={16}
                              />
                            </button>

                            {menuOpen && (
                              <div className="conversation-menu">
                                <button
                                  type="button"
                                  onClick={(
                                    event
                                  ) =>
                                    startRename(
                                      event,
                                      conversation
                                    )
                                  }
                                >
                                  <Pencil
                                    size={14}
                                  />
                                  Rename
                                </button>

                                <button
                                  className="conversation-menu-delete"
                                  type="button"
                                  onClick={(
                                    event
                                  ) =>
                                    handleDelete(
                                      event,
                                      conversation.id
                                    )
                                  }
                                >
                                  <Trash2
                                    size={14}
                                  />
                                  Delete
                                </button>
                              </div>
                            )}
                          </>
                        )}
                      </div>
                    );
                  }
                )}
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
          <div className="profile-avatar">
            U
          </div>

          <div className="profile-copy">
            <span>User</span>

            <small>
              Local development
            </small>
          </div>

          <MoreHorizontal size={17} />
        </div>
      </div>
    </aside>
  );
}

export default Sidebar;
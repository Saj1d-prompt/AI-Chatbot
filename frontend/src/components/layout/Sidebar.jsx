import { useState } from "react";

import {
  Bot,
  Check,
  LoaderCircle,
  LogOut,
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
  user,
  onLogout,
}) {
  const [
    menuConversationId,
    setMenuConversationId,
  ] = useState(null);

  const [
    editingConversationId,
    setEditingConversationId,
  ] = useState(null);

  const [
    editingTitle,
    setEditingTitle,
  ] = useState("");

  const [
    isSearchOpen,
    setIsSearchOpen,
  ] = useState(false);

  const [
    searchQuery,
    setSearchQuery,
  ] = useState("");

  /*
   * Search is case-insensitive and currently
   * searches conversation titles.
   */
  const normalizedSearch =
    searchQuery
      .trim()
      .toLowerCase();

  const filteredConversations =
    normalizedSearch
      ? conversations.filter(
          (conversation) => {
            const title =
              conversation.title ||
              "New conversation";

            return title
              .toLowerCase()
              .includes(
                normalizedSearch
              );
          }
        )
      : conversations;

  const groups =
    groupConversations(
      filteredConversations
    );

  const handleConversationClick = (
    conversationId
  ) => {
    if (
      editingConversationId ===
      conversationId
    ) {
      return;
    }

    setMenuConversationId(null);

    onSelectConversation(
      conversationId
    );

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

    const title =
      editingTitle.trim();

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

  const openSearch = () => {
    setIsSearchOpen(true);
    setMenuConversationId(null);
  };

  const closeSearch = () => {
    setSearchQuery("");
    setIsSearchOpen(false);
  };

  const handleSearchKeyDown = (
    event
  ) => {
    if (event.key === "Escape") {
      closeSearch();
    }
  };

  const userInitial =
    user?.name
      ?.trim()
      ?.charAt(0)
      ?.toUpperCase() || "U";

  const hasConversations =
    conversations.length > 0;

  const hasSearchResults =
    filteredConversations.length >
    0;

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
            <PanelLeftClose
              size={18}
            />
          </button>
        </div>

        <button
          className="new-chat-button"
          type="button"
          disabled={
            isCreatingConversation
          }
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

        {isSearchOpen ? (
          <div className="sidebar-search-box">
            <Search
              className="sidebar-search-icon"
              size={16}
            />

            <input
              className="sidebar-search-input"
              type="text"
              value={searchQuery}
              placeholder="Search conversations..."
              aria-label="Search conversations"
              autoFocus
              onChange={(event) =>
                setSearchQuery(
                  event.target.value
                )
              }
              onKeyDown={
                handleSearchKeyDown
              }
            />

            <button
              className="sidebar-search-close"
              type="button"
              aria-label="Close conversation search"
              title="Close search"
              onClick={closeSearch}
            >
              <X size={15} />
            </button>
          </div>
        ) : (
          <button
            className="sidebar-search"
            type="button"
            onClick={openSearch}
          >
            <Search size={16} />

            <span>
              Search conversations
            </span>
          </button>
        )}
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
        ) : !hasConversations ? (
          <div className="sidebar-empty-state">
            <MessageSquareText
              size={20}
            />

            <span>
              No conversations yet
            </span>

            <small>
              Start a new conversation
              to begin.
            </small>
          </div>
        ) : (
          isSearchOpen &&
          !hasSearchResults
        ) ? (
          <div className="sidebar-search-empty">
            <Search size={20} />

            <span>
              No conversations found
            </span>

            <small>
              No titles match
              {searchQuery.trim()
                ? ` "${searchQuery.trim()}"`
                : " your search"}
              .
            </small>

            <button
              type="button"
              onClick={() =>
                setSearchQuery("")
              }
            >
              Clear search
            </button>
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
                        key={
                          conversation.id
                        }
                      >
                        {isEditing ? (
                          <form
                            className="conversation-rename-form"
                            onSubmit={(
                              event
                            ) =>
                              submitRename(
                                event,
                                conversation.id
                              )
                            }
                          >
                            <input
                              autoFocus
                              value={
                                editingTitle
                              }
                              maxLength={
                                120
                              }
                              onChange={(
                                event
                              ) =>
                                setEditingTitle(
                                  event
                                    .target
                                    .value
                                )
                              }
                              onKeyDown={(
                                event
                              ) => {
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
                              <Check
                                size={14}
                              />
                            </button>

                            <button
                              type="button"
                              aria-label="Cancel rename"
                              onClick={
                                cancelRename
                              }
                            >
                              <X
                                size={14}
                              />
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
                              onClick={(
                                event
                              ) =>
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
            <span>
              Settings
            </span>

            <small>
              Preferences & configuration
            </small>
          </div>
        </button>

        <div className="sidebar-profile">
          <div className="profile-avatar">
            {userInitial}
          </div>

          <div className="profile-copy">
            <span>
              {user?.name || "User"}
            </span>

            <small>
              {user?.email || ""}
            </small>
          </div>

          <button
            className="profile-logout-button"
            type="button"
            aria-label="Log out"
            title="Log out"
            onClick={onLogout}
          >
            <LogOut size={16} />
          </button>
        </div>
      </div>
    </aside>
  );
}

export default Sidebar;
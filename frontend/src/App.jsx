import {
  useCallback,
  useEffect,
  useLayoutEffect,
  useRef,
  useState,
} from "react";

import Sidebar from "./components/layout/Sidebar";
import ChatHeader from "./components/chat/ChatHeader";
import EmptyState from "./components/chat/EmptyState";
import MessageList from "./components/chat/MessageList";
import MessageComposer from "./components/chat/MessageComposer";
import ErrorBanner from "./components/common/ErrorBanner";

import {
  createConversation,
  getConversationMessages,
  getConversations,
  sendConversationMessage,
} from "./services/api/conversationApi";

import "./App.css";

const starterPrompts = [
  {
    title: "Explain something",
    prompt: "Explain REST APIs in simple terms.",
  },
  {
    title: "Help me code",
    prompt:
      "Help me build a reusable Laravel service class.",
  },
  {
    title: "Brainstorm",
    prompt:
      "Give me ideas for a professional SaaS project.",
  },
  {
    title: "Improve writing",
    prompt:
      "Help me improve a professional project description.",
  },
];

function App() {
  const [sidebarOpen, setSidebarOpen] =
    useState(false);

  const [draft, setDraft] = useState("");

  const [messages, setMessages] = useState([]);

  const [conversations, setConversations] =
    useState([]);

  const [
    activeConversationId,
    setActiveConversationId,
  ] = useState(null);

  const [pagination, setPagination] = useState({
    has_more: false,
    next_before_id: null,
  });

  const [isLoading, setIsLoading] =
    useState(false);

  const [
    isLoadingConversation,
    setIsLoadingConversation,
  ] = useState(false);

  const [
    isLoadingConversations,
    setIsLoadingConversations,
  ] = useState(true);

  const [
    isCreatingConversation,
    setIsCreatingConversation,
  ] = useState(false);

  const [
    isLoadingOlder,
    setIsLoadingOlder,
  ] = useState(false);

  const [error, setError] = useState("");

  const chatContentRef = useRef(null);

  const shouldScrollToBottomRef =
    useRef(false);

  const restoreScrollRef = useRef(null);

  /*
   * Prevent stale conversation responses from replacing
   * the currently selected conversation.
   */
  const conversationRequestRef = useRef(0);

  const handleSuggestionSelect = (prompt) => {
    setDraft(prompt);
  };

  /*
   * Move a conversation to the top of the sidebar and
   * refresh its latest metadata.
   */
  const updateConversationInList = useCallback(
    (updatedConversation) => {
      setConversations((currentConversations) => {
        const remaining =
          currentConversations.filter(
            (conversation) =>
              conversation.id !==
              updatedConversation.id
          );

        return [
          updatedConversation,
          ...remaining,
        ];
      });
    },
    []
  );

  /*
   * Load the newest message batch for one conversation.
   */
  const openConversation = useCallback(
    async (conversationId) => {
      const requestId =
        conversationRequestRef.current + 1;

      conversationRequestRef.current =
        requestId;

      setActiveConversationId(
        conversationId
      );

      setMessages([]);

      setPagination({
        has_more: false,
        next_before_id: null,
      });

      setError("");

      setIsLoadingConversation(true);

      try {
        const data =
          await getConversationMessages(
            conversationId
          );

        /*
         * Ignore the response if the user selected another
         * conversation while this request was running.
         */
        if (
          requestId !==
          conversationRequestRef.current
        ) {
          return;
        }

        if (
          !data.success ||
          !Array.isArray(data.messages)
        ) {
          throw new Error(
            "Invalid conversation response."
          );
        }

        shouldScrollToBottomRef.current =
          true;

        setMessages(data.messages);

        setPagination(
          data.pagination || {
            has_more: false,
            next_before_id: null,
          }
        );
      } catch (requestError) {
        console.error(
          "Failed to load conversation:",
          requestError
        );

        setError(
          requestError.response?.data
            ?.message ||
            "Unable to load this conversation."
        );
      } finally {
        if (
          requestId ===
          conversationRequestRef.current
        ) {
          setIsLoadingConversation(false);
        }
      }
    },
    []
  );

  /*
   * Initial application bootstrap.
   */
  useEffect(() => {
    const bootstrap = async () => {
      setIsLoadingConversations(true);

      try {
        const data =
          await getConversations();

        if (
          !data.success ||
          !Array.isArray(
            data.conversations
          )
        ) {
          throw new Error(
            "Invalid conversations response."
          );
        }

        setConversations(
          data.conversations
        );

        /*
         * Automatically open the most recently active
         * conversation.
         */
        if (
          data.conversations.length > 0
        ) {
          await openConversation(
            data.conversations[0].id
          );
        }
      } catch (requestError) {
        console.error(
          "Failed to load conversations:",
          requestError
        );

        setError(
          requestError.response?.data
            ?.message ||
            "Unable to load conversations."
        );
      } finally {
        setIsLoadingConversations(false);
      }
    };

    bootstrap();
  }, [openConversation]);

  /*
   * Create a real database conversation.
   */
  const handleNewChat = async () => {
    if (isCreatingConversation) {
      return;
    }

    setIsCreatingConversation(true);
    setError("");

    try {
      const data =
        await createConversation();

      if (
        !data.success ||
        !data.conversation
      ) {
        throw new Error(
          "Invalid conversation creation response."
        );
      }

      const conversation =
        data.conversation;

      setConversations(
        (currentConversations) => [
          conversation,
          ...currentConversations,
        ]
      );

      setActiveConversationId(
        conversation.id
      );

      setMessages([]);

      setPagination({
        has_more: false,
        next_before_id: null,
      });

      setDraft("");

      setSidebarOpen(false);
    } catch (requestError) {
      console.error(
        "Failed to create conversation:",
        requestError
      );

      setError(
        requestError.response?.data
          ?.message ||
          "Unable to create a new conversation."
      );
    } finally {
      setIsCreatingConversation(false);
    }
  };

  /*
   * Load older history when the user scrolls upward.
   */
  const loadOlderMessages =
    useCallback(async () => {
      if (
        !activeConversationId ||
        !pagination.has_more ||
        !pagination.next_before_id ||
        isLoadingOlder
      ) {
        return;
      }

      const container =
        chatContentRef.current;

      if (!container) {
        return;
      }

      setIsLoadingOlder(true);

      /*
       * Save the current viewport position before older
       * messages are inserted above it.
       */
      restoreScrollRef.current = {
        previousScrollHeight:
          container.scrollHeight,

        previousScrollTop:
          container.scrollTop,
      };

      try {
        const data =
          await getConversationMessages(
            activeConversationId,
            pagination.next_before_id
          );

        if (
          !data.success ||
          !Array.isArray(data.messages)
        ) {
          throw new Error(
            "Invalid message history response."
          );
        }

        setMessages(
          (currentMessages) => [
            ...data.messages,
            ...currentMessages,
          ]
        );

        setPagination(
          data.pagination || {
            has_more: false,
            next_before_id: null,
          }
        );
      } catch (requestError) {
        restoreScrollRef.current = null;

        console.error(
          "Failed to load older messages:",
          requestError
        );

        setError(
          requestError.response?.data
            ?.message ||
            "Unable to load older messages."
        );
      } finally {
        setIsLoadingOlder(false);
      }
    }, [
      activeConversationId,
      pagination,
      isLoadingOlder,
    ]);

  /*
   * Detect when the user scrolls near the top.
   */
  const handleChatScroll = () => {
    const container =
      chatContentRef.current;

    if (!container) {
      return;
    }

    if (
      container.scrollTop <= 80 &&
      pagination.has_more &&
      !isLoadingOlder
    ) {
      loadOlderMessages();
    }
  };

  /*
   * Send a message inside the current persisted conversation.
   */
  const handleSubmit = async (event) => {
    event.preventDefault();

    const content = draft.trim();

    if (!content || isLoading) {
      return;
    }

    let conversationId =
      activeConversationId;

    setError("");

    /*
     * Safety fallback:
     * if no conversation exists, create one automatically.
     */
    if (!conversationId) {
      try {
        const creationData =
          await createConversation();

        if (
          !creationData.success ||
          !creationData.conversation
        ) {
          throw new Error(
            "Unable to create conversation."
          );
        }

        const newConversation =
          creationData.conversation;

        conversationId =
          newConversation.id;

        setActiveConversationId(
          conversationId
        );

        setConversations(
          (currentConversations) => [
            newConversation,
            ...currentConversations,
          ]
        );
      } catch (requestError) {
        console.error(
          "Conversation creation failed:",
          requestError
        );

        setError(
          "Unable to create a conversation."
        );

        return;
      }
    }

    /*
     * Display the user message immediately while Laravel
     * performs the AI request.
     */
    const temporaryMessage = {
      id: `temporary-${crypto.randomUUID()}`,
      conversation_id: conversationId,
      role: "user",
      content,
      token_usage: null,
      created_at:
        new Date().toISOString(),
      updated_at:
        new Date().toISOString(),
    };

    shouldScrollToBottomRef.current =
      true;

    setMessages((currentMessages) => [
      ...currentMessages,
      temporaryMessage,
    ]);

    setDraft("");
    setIsLoading(true);

    try {
      const data =
        await sendConversationMessage(
          conversationId,
          content
        );

      if (
        !data.success ||
        !data.user_message ||
        !data.assistant_message
      ) {
        throw new Error(
          "Invalid AI response."
        );
      }

      /*
       * Replace the temporary local message with the real
       * database message, then append the assistant reply.
       */
      shouldScrollToBottomRef.current =
        true;

      setMessages(
        (currentMessages) => {
          const withoutTemporary =
            currentMessages.filter(
              (message) =>
                message.id !==
                temporaryMessage.id
            );

          return [
            ...withoutTemporary,
            data.user_message,
            data.assistant_message,
          ];
        }
      );

      /*
       * The backend may have generated the initial title and
       * updated the conversation timestamp.
       */
      if (data.conversation) {
        updateConversationInList(
          data.conversation
        );
      }
    } catch (requestError) {
      console.error(
        "Chat request failed:",
        requestError
      );

      setError(
        requestError.response?.data
          ?.message ||
          "Unable to reach the AI assistant. Please try again."
      );
    } finally {
      setIsLoading(false);
    }
  };

  /*
   * Restore the viewport after prepending older messages,
   * or scroll to the bottom after normal chat activity.
   */
  useLayoutEffect(() => {
    const container =
      chatContentRef.current;

    if (!container) {
      return;
    }

    if (restoreScrollRef.current) {
      const {
        previousScrollHeight,
        previousScrollTop,
      } = restoreScrollRef.current;

      const newScrollHeight =
        container.scrollHeight;

      container.scrollTop =
        newScrollHeight -
        previousScrollHeight +
        previousScrollTop;

      restoreScrollRef.current = null;

      return;
    }

    if (
      shouldScrollToBottomRef.current
    ) {
      container.scrollTop =
        container.scrollHeight;

      shouldScrollToBottomRef.current =
        false;
    }
  }, [
    messages,
    isLoading,
    isLoadingConversation,
    isLoadingOlder,
  ]);

  const activeConversation =
    conversations.find(
      (conversation) =>
        conversation.id ===
        activeConversationId
    ) || null;

  return (
    <div className="chat-app">
      <Sidebar
        isOpen={sidebarOpen}
        onClose={() =>
          setSidebarOpen(false)
        }
        onNewChat={handleNewChat}
        conversations={conversations}
        activeConversationId={
          activeConversationId
        }
        onSelectConversation={
          openConversation
        }
        isLoadingConversations={
          isLoadingConversations
        }
        isCreatingConversation={
          isCreatingConversation
        }
      />

      {sidebarOpen && (
        <button
          className="sidebar-overlay"
          type="button"
          aria-label="Close sidebar"
          onClick={() =>
            setSidebarOpen(false)
          }
        />
      )}

      <section className="chat-workspace">
        <ChatHeader
          onMenuClick={() =>
            setSidebarOpen(true)
          }
          conversation={
            activeConversation
          }
        />

        <ErrorBanner
          message={error}
          onClose={() =>
            setError("")
          }
        />

        <main
          className="chat-content"
          ref={chatContentRef}
          onScroll={handleChatScroll}
        >
          {isLoadingConversation ? (
            <div className="conversation-loading-screen">
              <div className="conversation-loading-spinner" />

              <span>
                Loading conversation...
              </span>
            </div>
          ) : messages.length === 0 ? (
            <EmptyState
              suggestions={starterPrompts}
              onSuggestionSelect={
                handleSuggestionSelect
              }
            />
          ) : (
            <MessageList
              messages={messages}
              isLoading={isLoading}
              isLoadingOlder={
                isLoadingOlder
              }
            />
          )}
        </main>

        <MessageComposer
          value={draft}
          onChange={setDraft}
          onSubmit={handleSubmit}
          isLoading={isLoading}
        />
      </section>
    </div>
  );
}

export default App;
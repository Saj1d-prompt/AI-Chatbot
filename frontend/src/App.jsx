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
  deleteConversation,
  getConversationMessages,
  getConversations,
  regenerateConversationResponse,
  renameConversation,
  sendConversationMessage,
} from "./services/api/conversationApi";

import "./App.css";

const starterPrompts = [
  {
    title: "Explain something",
    prompt:
      "Explain REST APIs in simple terms.",
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

  const [draft, setDraft] =
    useState("");

  const [messages, setMessages] =
    useState([]);

  const [
    conversations,
    setConversations,
  ] = useState([]);

  const [
    activeConversationId,
    setActiveConversationId,
  ] = useState(null);

  const [
    pagination,
    setPagination,
  ] = useState({
    has_more: false,
    next_before_id: null,
  });

  const [isLoading, setIsLoading] =
    useState(false);

  const [
    isRegenerating,
    setIsRegenerating,
  ] = useState(false);

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

  const [error, setError] =
    useState("");

  const chatContentRef = useRef(null);

  const shouldScrollToBottomRef =
    useRef(false);

  const restoreScrollRef =
    useRef(null);

  const conversationRequestRef =
    useRef(0);

  const handleSuggestionSelect = (
    prompt
  ) => {
    setDraft(prompt);
  };

  const updateConversationInList =
    useCallback(
      (updatedConversation) => {
        setConversations(
          (currentConversations) => {
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
          }
        );
      },
      []
    );

  const openConversation =
    useCallback(
      async (conversationId) => {
        const requestId =
          conversationRequestRef.current +
          1;

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

          if (
            requestId !==
            conversationRequestRef.current
          ) {
            return;
          }

          if (
            !data.success ||
            !Array.isArray(
              data.messages
            )
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
            requestError.response
              ?.data?.message ||
              "Unable to load this conversation."
          );
        } finally {
          if (
            requestId ===
            conversationRequestRef.current
          ) {
            setIsLoadingConversation(
              false
            );
          }
        }
      },
      []
    );

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

  const handleNewChat = async () => {
    if (
      isCreatingConversation ||
      isLoading ||
      isRegenerating
    ) {
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
          !Array.isArray(
            data.messages
          )
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
        restoreScrollRef.current =
          null;

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

  const handleSubmit = async (
    event
  ) => {
    event.preventDefault();

    const content =
      draft.trim();

    if (
      !content ||
      isLoading ||
      isRegenerating
    ) {
      return;
    }

    let conversationId =
      activeConversationId;

    setError("");

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

    const temporaryMessage = {
      id: `temporary-${crypto.randomUUID()}`,
      conversation_id:
        conversationId,
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

    setMessages(
      (currentMessages) => [
        ...currentMessages,
        temporaryMessage,
      ]
    );

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

      const status =
        requestError.response?.status;

      /*
       * A 502 from our Laravel endpoint means the user
       * message was normally stored before the AI call
       * failed. Keep the message visible so Retry can
       * regenerate the missing response.
       *
       * For a network/client error, remove the temporary
       * message and restore the draft.
       */
      if (status !== 502) {
        setMessages(
          (currentMessages) =>
            currentMessages.filter(
              (message) =>
                message.id !==
                temporaryMessage.id
            )
        );

        setDraft(content);
      }

      setError(
        requestError.response?.data
          ?.message ||
          "Unable to reach the AI assistant. Please try again."
      );
    } finally {
      setIsLoading(false);
    }
  };

  const handleRegenerateResponse =
    async () => {
      if (
        !activeConversationId ||
        isLoading ||
        isRegenerating
      ) {
        return;
      }

      setError("");
      setIsRegenerating(true);

      try {
        const data =
          await regenerateConversationResponse(
            activeConversationId
          );

        if (
          !data.success ||
          !data.user_message ||
          !data.assistant_message
        ) {
          throw new Error(
            "Invalid regeneration response."
          );
        }

        shouldScrollToBottomRef.current =
          true;

        setMessages(
          (currentMessages) => {
            /*
             * Remove any temporary user message that may
             * remain after a failed AI request.
             */
            let nextMessages =
              currentMessages.filter(
                (message) =>
                  !String(
                    message.id
                  ).startsWith(
                    "temporary-"
                  )
              );

            const realUserExists =
              nextMessages.some(
                (message) =>
                  message.id ===
                  data.user_message.id
              );

            if (!realUserExists) {
              nextMessages = [
                ...nextMessages,
                data.user_message,
              ];
            }

            const assistantIndex =
              nextMessages.findIndex(
                (message) =>
                  message.id ===
                  data.assistant_message.id
              );

            if (
              assistantIndex >= 0
            ) {
              nextMessages =
                [...nextMessages];

              nextMessages[
                assistantIndex
              ] =
                data.assistant_message;
            } else {
              nextMessages = [
                ...nextMessages,
                data.assistant_message,
              ];
            }

            return nextMessages;
          }
        );

        if (data.conversation) {
          updateConversationInList(
            data.conversation
          );
        }
      } catch (requestError) {
        console.error(
          "Response regeneration failed:",
          requestError
        );

        setError(
          requestError.response?.data
            ?.message ||
            "Unable to regenerate the response."
        );
      } finally {
        setIsRegenerating(false);
      }
    };

  const handleRenameConversation =
    async (
      conversationId,
      title
    ) => {
      try {
        const data =
          await renameConversation(
            conversationId,
            title
          );

        if (
          !data.success ||
          !data.conversation
        ) {
          throw new Error(
            "Invalid rename response."
          );
        }

        setConversations(
          (currentConversations) =>
            currentConversations.map(
              (conversation) =>
                conversation.id ===
                conversationId
                  ? data.conversation
                  : conversation
            )
        );

        return true;
      } catch (requestError) {
        console.error(
          "Failed to rename conversation:",
          requestError
        );

        setError(
          requestError.response?.data
            ?.message ||
            "Unable to rename the conversation."
        );

        return false;
      }
    };

  const handleDeleteConversation =
    async (conversationId) => {
      const confirmed =
        window.confirm(
          "Delete this conversation? This cannot be undone."
        );

      if (!confirmed) {
        return;
      }

      try {
        const data =
          await deleteConversation(
            conversationId
          );

        if (!data.success) {
          throw new Error(
            "Invalid delete response."
          );
        }

        const remainingConversations =
          conversations.filter(
            (conversation) =>
              conversation.id !==
              conversationId
          );

        setConversations(
          remainingConversations
        );

        if (
          activeConversationId ===
          conversationId
        ) {
          if (
            remainingConversations.length >
            0
          ) {
            await openConversation(
              remainingConversations[0]
                .id
            );
          } else {
            setActiveConversationId(
              null
            );

            setMessages([]);

            setPagination({
              has_more: false,
              next_before_id: null,
            });
          }
        }
      } catch (requestError) {
        console.error(
          "Failed to delete conversation:",
          requestError
        );

        setError(
          requestError.response?.data
            ?.message ||
            "Unable to delete the conversation."
        );
      }
    };

  useLayoutEffect(() => {
    const container =
      chatContentRef.current;

    if (!container) {
      return;
    }

    if (
      restoreScrollRef.current
    ) {
      const {
        previousScrollHeight,
        previousScrollTop,
      } =
        restoreScrollRef.current;

      const newScrollHeight =
        container.scrollHeight;

      container.scrollTop =
        newScrollHeight -
        previousScrollHeight +
        previousScrollTop;

      restoreScrollRef.current =
        null;

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
    isRegenerating,
    isLoadingConversation,
    isLoadingOlder,
  ]);

  const activeConversation =
    conversations.find(
      (conversation) =>
        conversation.id ===
        activeConversationId
    ) || null;

  const lastMessage =
    messages.length > 0
      ? messages[
          messages.length - 1
        ]
      : null;

  const showThinkingIndicator =
    isLoading ||
    (
      isRegenerating &&
      lastMessage?.role ===
        "user"
    );

  return (
    <div className="chat-app">
      <Sidebar
        isOpen={sidebarOpen}
        onClose={() =>
          setSidebarOpen(false)
        }
        onNewChat={handleNewChat}
        conversations={
          conversations
        }
        activeConversationId={
          activeConversationId
        }
        onSelectConversation={
          openConversation
        }
        onRenameConversation={
          handleRenameConversation
        }
        onDeleteConversation={
          handleDeleteConversation
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
          onScroll={
            handleChatScroll
          }
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
              suggestions={
                starterPrompts
              }
              onSuggestionSelect={
                handleSuggestionSelect
              }
            />
          ) : (
            <MessageList
              messages={messages}
              isLoading={
                showThinkingIndicator
              }
              isLoadingOlder={
                isLoadingOlder
              }
              isRegenerating={
                isRegenerating
              }
              onRegenerate={
                handleRegenerateResponse
              }
            />
          )}
        </main>

        <MessageComposer
          value={draft}
          onChange={setDraft}
          onSubmit={handleSubmit}
          isLoading={
            isLoading ||
            isRegenerating
          }
        />
      </section>
    </div>
  );
}

export default App;
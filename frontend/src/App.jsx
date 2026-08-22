import { useEffect, useRef, useState } from "react";

import Sidebar from "./components/layout/Sidebar";
import ChatHeader from "./components/chat/ChatHeader";
import EmptyState from "./components/chat/EmptyState";
import MessageList from "./components/chat/MessageList";
import MessageComposer from "./components/chat/MessageComposer";
import ErrorBanner from "./components/common/ErrorBanner";

import { sendChatMessage } from "./services/api/chatApi";

import "./App.css";

const starterPrompts = [
  {
    title: "Explain something",
    prompt: "Explain REST APIs in simple terms.",
  },
  {
    title: "Help me code",
    prompt: "Help me build a reusable Laravel service class.",
  },
  {
    title: "Brainstorm",
    prompt: "Give me ideas for a professional SaaS project.",
  },
  {
    title: "Improve writing",
    prompt: "Help me improve a professional project description.",
  },
];

function createMessage(role, content) {
  return {
    id: crypto.randomUUID(),
    role,
    content,
  };
}

function App() {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const [draft, setDraft] = useState("");

  const [messages, setMessages] = useState([]);

  const [isLoading, setIsLoading] = useState(false);

  const [error, setError] = useState("");

  const chatContentRef = useRef(null);

  const handleSuggestionSelect = (prompt) => {
    setDraft(prompt);
  };

  const handleNewChat = () => {
    setDraft("");
    setMessages([]);
    setError("");
    setSidebarOpen(false);
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    const message = draft.trim();

    if (!message || isLoading) {
      return;
    }

    const userMessage = createMessage(
      "user",
      message
    );

    setMessages((currentMessages) => [
      ...currentMessages,
      userMessage,
    ]);

    setDraft("");
    setError("");
    setIsLoading(true);

    try {
      const data = await sendChatMessage(message);

      if (
        !data.success ||
        typeof data.reply !== "string"
      ) {
        throw new Error(
          "The server returned an invalid AI response."
        );
      }

      const assistantMessage = createMessage(
        "assistant",
        data.reply
      );

      setMessages((currentMessages) => [
        ...currentMessages,
        assistantMessage,
      ]);
    } catch (requestError) {
      console.error(
        "Chat request failed:",
        requestError
      );

      const serverMessage =
        requestError.response?.data?.message;

      setError(
        serverMessage ||
          "Unable to reach the AI assistant. Please try again."
      );
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    const container = chatContentRef.current;

    if (!container) {
      return;
    }

    container.scrollTo({
      top: container.scrollHeight,
      behavior: "smooth",
    });
  }, [messages, isLoading]);

  return (
    <div className="chat-app">
      <Sidebar
        isOpen={sidebarOpen}
        onClose={() => setSidebarOpen(false)}
        onNewChat={handleNewChat}
      />

      {sidebarOpen && (
        <button
          className="sidebar-overlay"
          type="button"
          aria-label="Close sidebar"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      <section className="chat-workspace">
        <ChatHeader
          onMenuClick={() => setSidebarOpen(true)}
        />

        <ErrorBanner
          message={error}
          onClose={() => setError("")}
        />

        <main
          className="chat-content"
          ref={chatContentRef}
        >
          {messages.length === 0 ? (
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
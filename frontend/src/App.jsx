import { useState } from "react";

import Sidebar from "./components/layout/Sidebar";
import ChatHeader from "./components/chat/ChatHeader";
import EmptyState from "./components/chat/EmptyState";
import MessageComposer from "./components/chat/MessageComposer";

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

function App() {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [draft, setDraft] = useState("");

  const handleSuggestionSelect = (prompt) => {
    setDraft(prompt);
  };

  const handleNewChat = () => {
    setDraft("");
    setSidebarOpen(false);
  };

  const handleSubmit = (event) => {
    event.preventDefault();

    if (!draft.trim()) {
      return;
    }

    /*
     * The Laravel API connection will be added in the next step.
     * For now, we only verify the composer UI and input behavior.
     */
  };

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
        <ChatHeader onMenuClick={() => setSidebarOpen(true)} />

        <main className="chat-content">
          <EmptyState
            suggestions={starterPrompts}
            onSuggestionSelect={handleSuggestionSelect}
          />
        </main>

        <MessageComposer
          value={draft}
          onChange={setDraft}
          onSubmit={handleSubmit}
        />
      </section>
    </div>
  );
}

export default App;
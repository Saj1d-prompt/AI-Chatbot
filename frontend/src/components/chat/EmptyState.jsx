import {
  ArrowUpRight,
  Braces,
  Lightbulb,
  PenLine,
  Sparkles,
} from "lucide-react";

import "./EmptyState.css";

const icons = [
  Sparkles,
  Braces,
  Lightbulb,
  PenLine,
];

function EmptyState({
  suggestions,
  onSuggestionSelect,
}) {
  return (
    <section className="empty-state">
      <div className="empty-brand">
        <div className="empty-brand-mark">
          <span>N</span>
        </div>

        <span className="empty-brand-line" />
      </div>

      <div className="empty-heading">
        <p className="empty-eyebrow">
          AI KNOWLEDGE ASSISTANT
        </p>

        <h1>
          What can we
          <span> explore today?</span>
        </h1>

        <p>
          Ask a question, solve a problem, explore an idea,
          or work through code with your AI assistant.
        </p>
      </div>

      <div className="suggestion-grid">
        {suggestions.map((suggestion, index) => {
          const Icon = icons[index % icons.length];

          return (
            <button
              className="suggestion-card"
              type="button"
              key={suggestion.title}
              onClick={() =>
                onSuggestionSelect(suggestion.prompt)
              }
            >
              <div className="suggestion-card-top">
                <div className="suggestion-icon">
                  <Icon size={16} />
                </div>

                <ArrowUpRight
                  className="suggestion-arrow"
                  size={15}
                />
              </div>

              <div>
                <span className="suggestion-title">
                  {suggestion.title}
                </span>

                <p>{suggestion.prompt}</p>
              </div>
            </button>
          );
        })}
      </div>
    </section>
  );
}

export default EmptyState;
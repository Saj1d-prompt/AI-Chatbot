import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import {
  Check,
  ChevronDown,
  Database,
  LoaderCircle,
} from "lucide-react";

import { knowledgeBaseApi } from "../../services/api/knowledgeBaseApi";

import {
  getConversation,
  updateConversationKnowledgeBases,
} from "../../services/api/conversationApi";

import "./KnowledgeBaseSelector.css";

function getErrorMessage(error) {
  const validationErrors = error?.response?.data?.errors;

  if (validationErrors) {
    const messages = Object.values(validationErrors).flat();

    if (messages.length > 0) {
      return messages.join(" ");
    }
  }

  return (
    error?.response?.data?.message ||
    error?.message ||
    "Unable to update knowledge bases."
  );
}

export default function KnowledgeBaseSelector({
  conversationId,
}) {
  const [knowledgeBases, setKnowledgeBases] = useState([]);
  const [selectedIds, setSelectedIds] = useState([]);
  const [savedIds, setSavedIds] = useState([]);

  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  useEffect(() => {
    let cancelled = false;

    if (!conversationId) {
      return;
    }

    async function load() {
      setIsLoading(true);
      setError("");
      setSuccess("");

      try {
        const [available, conversationData] =
          await Promise.all([
            knowledgeBaseApi.getAll(),
            getConversation(conversationId),
          ]);

        if (cancelled) {
          return;
        }

        if (
          !Array.isArray(available) ||
          !conversationData.success ||
          !Array.isArray(
            conversationData.knowledge_base_ids
          )
        ) {
          throw new Error(
            "Invalid knowledge base information."
          );
        }

        const attachedIds =
          conversationData.knowledge_base_ids.map(
            Number
          );

        setKnowledgeBases(available);
        setSelectedIds(attachedIds);
        setSavedIds(attachedIds);
      } catch (err) {
        if (!cancelled) {
          setError(getErrorMessage(err));
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    }

    load();

    return () => {
      cancelled = true;
    };
  }, [conversationId]);

  function toggleKnowledgeBase(id) {
    if (isSaving) {
      return;
    }

    setError("");
    setSuccess("");

    setSelectedIds((current) =>
      current.includes(id)
        ? current.filter((item) => item !== id)
        : [...current, id]
    );
  }

  function handleCancel() {
    setSelectedIds([...savedIds]);
    setError("");
    setSuccess("");
    setIsOpen(false);
  }

  const hasChanges =
    selectedIds.length !== savedIds.length ||
    selectedIds.some((id) => !savedIds.includes(id));

  async function handleSave() {
    if (
      !conversationId ||
      isLoading ||
      isSaving ||
      !hasChanges
    ) {
      return;
    }

    setIsSaving(true);
    setError("");
    setSuccess("");

    try {
      const result = await updateConversationKnowledgeBases(
        conversationId,
        selectedIds
      );

      if (
        !result.success ||
        !Array.isArray(result.knowledge_base_ids)
      ) {
        throw new Error("Invalid attachment response.");
      }

      const ids = result.knowledge_base_ids.map(Number);

      setSavedIds(ids);
      setSelectedIds(ids);

      setSuccess(
        ids.length === 0
          ? "All knowledge bases detached."
          : "Knowledge bases saved successfully."
      );

      setIsOpen(false);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setIsSaving(false);
    }
  }

  if (!conversationId) {
    return (
      <div className="kb-chat-bar">
        <Database size={15} />
        <span>
          Create a conversation to attach documents.
        </span>

        <Link
          className="kb-chat-manage"
          to="/knowledge-bases"
        >
          Manage Documents
        </Link>
      </div>
    );
  }

  return (
    <div className="kb-chat-bar">
      <div className="kb-chat-trigger-wrap">
        <button
          type="button"
          className="kb-chat-trigger"
          onClick={() => {
            setIsOpen((current) => !current);
          }}
          disabled={isLoading || isSaving}
          aria-expanded={isOpen}
          aria-controls="kb-chat-selection-panel"
        >
          {isLoading ? (
            <LoaderCircle
              size={15}
              className="kb-chat-spinner"
            />
          ) : (
            <Database size={15} />
          )}

          <span>
            Knowledge Bases
            {" · "}
            {savedIds.length} selected
          </span>

          <ChevronDown size={14} />
        </button>

        {isOpen && (
          <div
            id="kb-chat-selection-panel"
            className="kb-chat-panel"
          >
            <div className="kb-chat-panel-heading">
              <strong>Use knowledge bases</strong>
              <span>
                Select documents for this conversation.
              </span>
            </div>

            {knowledgeBases.length === 0 ? (
              <p className="kb-chat-empty">
                No knowledge bases available.
                Create one and upload your documents first.
              </p>
            ) : (
              <div className="kb-chat-options">
                {knowledgeBases.map((kb) => {
                  const id = Number(kb.id);
                  const selected = selectedIds.includes(id);

                  return (
                    <label
                      className="kb-chat-option"
                      key={id}
                    >
                      <input
                        type="checkbox"
                        checked={selected}
                        onChange={() => toggleKnowledgeBase(id)}
                        disabled={isSaving}
                      />

                      <span className="kb-chat-option-info">
                        <strong>{kb.name}</strong>
                        <small>
                          {kb.documents_count ?? 0}
                          {" documents"}
                        </small>
                      </span>

                      {selected && (
                        <Check
                          size={15}
                          className="kb-chat-check"
                        />
                      )}
                    </label>
                  );
                })}
              </div>
            )}

            {error && (
              <p className="kb-chat-error" role="alert">
                {error}
              </p>
            )}

            <div className="kb-chat-panel-actions">
              <Link
                to="/knowledge-bases"
                className="kb-chat-manage"
              >
                Manage Documents
              </Link>

              <div className="kb-chat-buttons">
                <button
                  type="button"
                  className="kb-chat-cancel"
                  onClick={handleCancel}
                  disabled={isSaving}
                >
                  Cancel
                </button>

                <button
                  type="button"
                  className="kb-chat-save"
                  onClick={handleSave}
                  disabled={!hasChanges || isSaving}
                >
                  {isSaving ? "Saving..." : "Save"}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>

      {success && (
        <span
          className="kb-chat-success"
          role="status"
        >
          {success}
        </span>
      )}

      {!isOpen && error && (
        <span
          className="kb-chat-error-inline"
          role="alert"
        >
          {error}
        </span>
      )}

      <Link
        className="kb-chat-manage kb-chat-manage-right"
        to="/knowledge-bases"
      >
        Manage Documents
      </Link>
    </div>
  );
}


import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { knowledgeBaseApi } from "../services/api/knowledgeBaseApi";
import "./KnowledgeBasesPage.css";

function getErrorMessage(error) {
  return (
    error?.response?.data?.message ||
    "Something went wrong. Please try again."
  );
}

export default function KnowledgeBasesPage() {
  const [knowledgeBases, setKnowledgeBases] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [busyId, setBusyId] = useState(null);

  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [editingId, setEditingId] = useState(null);

  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const loadKnowledgeBases = useCallback(async () => {
    try {
      setLoading(true);
      setError("");

      const data = await knowledgeBaseApi.getAll();

      setKnowledgeBases(Array.isArray(data) ? data : []);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadKnowledgeBases();
  }, [loadKnowledgeBases]);

  function resetForm() {
    setName("");
    setDescription("");
    setEditingId(null);
  }

  function startEditing(kb) {
    setEditingId(kb.id);
    setName(kb.name);
    setDescription(kb.description || "");
    setError("");
    setSuccess("");
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  async function handleSubmit(event) {
    event.preventDefault();

    if (!name.trim()) {
      setError("Please enter a knowledge base name.");
      return;
    }

    try {
      setSaving(true);
      setError("");
      setSuccess("");

      const payload = {
        name: name.trim(),
        description: description.trim(),
      };

      if (editingId !== null) {
        const updated = await knowledgeBaseApi.update(
          editingId,
          payload
        );

        setKnowledgeBases((current) =>
          current.map((kb) =>
            kb.id === editingId ? updated : kb
          )
        );

        setSuccess("Knowledge base updated successfully.");
      } else {
        const created = await knowledgeBaseApi.create(payload);

        setKnowledgeBases((current) => [
          created,
          ...current,
        ]);

        setSuccess("Knowledge base created successfully.");
      }

      resetForm();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(kb) {
    const confirmed = window.confirm(
      `Delete "${kb.name}"?\n\nThis may also delete its documents and associated data. This action cannot be undone.`
    );

    if (!confirmed) return;

    try {
      setBusyId(kb.id);
      setError("");
      setSuccess("");

      await knowledgeBaseApi.remove(kb.id);

      setKnowledgeBases((current) =>
        current.filter((item) => item.id !== kb.id)
      );

      if (editingId === kb.id) {
        resetForm();
      }

      setSuccess("Knowledge base deleted successfully.");
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusyId(null);
    }
  }

  return (
    <main className="kb-page">
      <div className="kb-container">
        <header className="kb-header">
          <div>
            <p className="kb-eyebrow">YOUR WORKSPACE</p>
            <h1>Knowledge Bases</h1>
            <p>
              Organize documents that your AI assistant can
              use to answer questions.
            </p>
          </div>

          <Link className="kb-back-link" to="/">
            ← Back to Chat
          </Link>
        </header>

        {error && (
          <div className="kb-alert kb-alert-error" role="alert">
            {error}
          </div>
        )}

        {success && (
          <div className="kb-alert kb-alert-success" role="status">
            {success}
          </div>
        )}

        <section className="kb-form-card">
          <h2>
            {editingId !== null
              ? "Edit Knowledge Base"
              : "Create Knowledge Base"}
          </h2>

          <form onSubmit={handleSubmit}>
            <label htmlFor="kb-name">Name</label>

            <input
              id="kb-name"
              type="text"
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder="e.g. Company Policies"
              maxLength={255}
              required
              disabled={saving}
            />

            <label htmlFor="kb-description">
              Description
            </label>

            <textarea
              id="kb-description"
              value={description}
              onChange={(event) =>
                setDescription(event.target.value)
              }
              placeholder="What kind of documents will this contain?"
              rows={3}
              disabled={saving}
            />

            <div className="kb-form-actions">
              <button
                type="submit"
                className="kb-primary-button"
                disabled={saving}
              >
                {saving
                  ? "Saving..."
                  : editingId !== null
                    ? "Save Changes"
                    : "Create Knowledge Base"}
              </button>

              {editingId !== null && (
                <button
                  type="button"
                  className="kb-secondary-button"
                  onClick={resetForm}
                  disabled={saving}
                >
                  Cancel
                </button>
              )}
            </div>
          </form>
        </section>

        <section className="kb-list-section">
          <div className="kb-section-heading">
            <h2>Your Knowledge Bases</h2>
            <span>{knowledgeBases.length} total</span>
          </div>

          {loading ? (
            <p className="kb-state">Loading knowledge bases...</p>
          ) : knowledgeBases.length === 0 ? (
            <div className="kb-empty">
              <div className="kb-empty-icon">📚</div>
              <h3>No knowledge bases yet</h3>
              <p>
                Create your first knowledge base to start
                organizing information for your AI assistant.
              </p>
            </div>
          ) : (
            <div className="kb-grid">
              {knowledgeBases.map((kb) => (
                <article className="kb-card" key={kb.id}>
                  <div className="kb-card-icon">📁</div>

                  <h3>{kb.name}</h3>

                  <p>
                    {kb.description ||
                      "No description provided."}
                  </p>

                  <span className="kb-document-count">
                    {kb.documents_count ?? 0} documents
                  </span>

                  <div className="kb-card-actions">
                    <button
                      type="button"
                      className="kb-secondary-button"
                      onClick={() => startEditing(kb)}
                      disabled={busyId !== null || saving}
                    >
                      Edit
                    </button>

                    <button
                      type="button"
                      className="kb-danger-button"
                      onClick={() => handleDelete(kb)}
                      disabled={busyId !== null || saving}
                    >
                      {busyId === kb.id
                        ? "Deleting..."
                        : "Delete"}
                    </button>
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>
      </div>
    </main>
  );
}

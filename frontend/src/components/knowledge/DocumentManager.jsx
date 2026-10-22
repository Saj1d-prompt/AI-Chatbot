import { useCallback, useEffect, useRef, useState } from "react";
import { knowledgeBaseApi } from "../../services/api/knowledgeBaseApi";
import "./DocumentManager.css";

const MAX_FILE_SIZE = 2 * 1024 * 1024;
const POLLING_INTERVAL = 3000;

function getErrorMessage(error) {
  const validationErrors = error?.response?.data?.errors;

  if (validationErrors) {
    const messages = Object.values(validationErrors).flat();

    if (messages.length > 0) {
      return messages.join(" ");
    }
  }

  if (error?.code === "ECONNABORTED") {
    return (
      "The request timed out. Refresh the document list " +
      "to check the latest processing status."
    );
  }

  return (
    error?.response?.data?.message ||
    error?.message ||
    "Something went wrong. Please try again."
  );
}

function formatFileSize(bytes) {
  const size = Number(bytes);

  if (!Number.isFinite(size) || size < 0) {
    return "Unknown size";
  }

  if (size < 1024) {
    return `${size} B`;
  }

  return `${(size / 1024).toFixed(1)} KB`;
}

function formatStatus(status) {
  switch (status) {
    case "uploaded":
      return "Queued";
    case "processing":
      return "Processing";
    case "ready":
    case "completed":
      return "Ready";
    case "failed":
      return "Failed";
    default:
      return status || "Unknown";
  }
}

function isPending(document) {
  return (
    document.status === "uploaded" ||
    document.status === "processing"
  );
}

export default function DocumentManager({
  knowledgeBaseId,
  onDocumentUploaded,
}) {
  const [documents, setDocuments] = useState([]);
  const [selectedFile, setSelectedFile] = useState(null);

  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [processingId, setProcessingId] = useState(null);

  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const fileInputRef = useRef(null);
  const refreshInProgress = useRef(false);
  const requestVersion = useRef(0);

  const isBusy = uploading || processingId !== null;

  const hasPendingDocuments = documents.some(isPending);

  const loadDocuments = useCallback(
    async ({ silent = false } = {}) => {
      if (refreshInProgress.current) {
        return;
      }

      refreshInProgress.current = true;
      const version = requestVersion.current;

      if (!silent) {
        setLoading(true);
      }

      try {
        const result = await knowledgeBaseApi.getDocuments(
          knowledgeBaseId
        );

        if (!Array.isArray(result)) {
          throw new Error("Invalid document list response.");
        }

        if (version !== requestVersion.current) {
          return;
        }

        setDocuments(result);

        if (silent) {
          setError("");
        }
      } catch (err) {
        if (version === requestVersion.current) {
          setError(getErrorMessage(err));
        }
      } finally {
        if (version === requestVersion.current) {
          setLoading(false);
          refreshInProgress.current = false;
        }
      }
    },
    [knowledgeBaseId]
  );

  /*
   * Load documents when the manager opens
   * or the knowledge base changes.
   */
  useEffect(() => {
    requestVersion.current += 1;
    refreshInProgress.current = false;

    setDocuments([]);
    setError("");
    setSuccess("");
    setLoading(true);

    loadDocuments();

    return () => {
      requestVersion.current += 1;
    };
  }, [loadDocuments]);

  /*
   * Poll only while at least one document
   * is queued or processing.
   *
   * Polling stops automatically when every
   * document is ready or failed.
   */
  useEffect(() => {
    if (!hasPendingDocuments || isBusy || loading) {
      return;
    }

    const intervalId = setInterval(() => {
      loadDocuments({ silent: true });
    }, POLLING_INTERVAL);

    return () => clearInterval(intervalId);
  }, [
    hasPendingDocuments,
    isBusy,
    loading,
    loadDocuments,
  ]);

  function handleFileChange(event) {
    setError("");
    setSuccess("");

    const file = event.target.files?.[0];

    if (!file) {
      setSelectedFile(null);
      return;
    }

    if (!file.name.toLowerCase().endsWith(".txt")) {
      setError("Only TXT files are supported.");
      setSelectedFile(null);
      event.target.value = "";
      return;
    }

    if (file.size > MAX_FILE_SIZE) {
      setError("File size must not exceed 2 MB.");
      setSelectedFile(null);
      event.target.value = "";
      return;
    }

    setSelectedFile(file);
  }

  async function handleUpload(event) {
    event.preventDefault();

    if (!selectedFile || isBusy) {
      return;
    }

    try {
      setUploading(true);
      setError("");
      setSuccess("");

      const uploadedDocument =
        await knowledgeBaseApi.uploadDocument(
          knowledgeBaseId,
          selectedFile
        );

      setSelectedFile(null);

      if (fileInputRef.current) {
        fileInputRef.current.value = "";
      }

      setSuccess(
        `"${uploadedDocument.original_name}" uploaded successfully. ` +
        "Automatic processing has been queued."
      );

      await loadDocuments();

      onDocumentUploaded?.();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setUploading(false);
    }
  }

  /*
   * Manual retries are only offered when
   * automatic processing has failed.
   */
  async function handleRetry(document) {
    if (isBusy || loading || document.status !== "failed") {
      return;
    }

    try {
      setProcessingId(document.id);
      setError("");
      setSuccess("");

      setDocuments((current) =>
        current.map((item) =>
          item.id === document.id
            ? { ...item, status: "processing" }
            : item
        )
      );

      const processed =
        await knowledgeBaseApi.processDocument(
          knowledgeBaseId,
          document.id
        );

      setSuccess(
        `"${document.original_name}" processed successfully. ` +
        `${processed.chunks_count} chunks created.`
      );
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      await loadDocuments({ silent: true });
      setProcessingId(null);
    }
  }

  async function handleRefresh() {
    setError("");
    await loadDocuments({ silent: true });
  }

  return (
    <section className="dm-container">
      <div className="dm-header">
        <div>
          <h4>Manage Documents</h4>

          <p>
            Upload TXT files. Documents are automatically
            processed and made searchable.
          </p>
        </div>
      </div>

      {error && (
        <div className="dm-alert dm-error" role="alert">
          {error}
        </div>
      )}

      {success && (
        <div className="dm-alert dm-success" role="status">
          {success}
        </div>
      )}

      <form
        className="dm-upload-form"
        onSubmit={handleUpload}
      >
        <label htmlFor={`dm-file-${knowledgeBaseId}`}>
          Select a TXT document
        </label>

        <input
          id={`dm-file-${knowledgeBaseId}`}
          ref={fileInputRef}
          type="file"
          accept=".txt,text/plain"
          onChange={handleFileChange}
          disabled={isBusy}
        />

        <p className="dm-help">
          Supported format: TXT. Maximum file size: 2 MB.
        </p>

        {selectedFile && (
          <p className="dm-selected">
            Selected: {selectedFile.name} (
            {formatFileSize(selectedFile.size)})
          </p>
        )}

        <button
          className="dm-upload-button"
          type="submit"
          disabled={!selectedFile || isBusy}
        >
          {uploading ? "Uploading..." : "Upload Document"}
        </button>
      </form>

      <div className="dm-list-header">
        <h4>Documents ({documents.length})</h4>

        <button
          type="button"
          className="dm-refresh-button"
          onClick={handleRefresh}
          disabled={loading || isBusy}
        >
          {loading ? "Loading..." : "Refresh"}
        </button>
      </div>

      {hasPendingDocuments && !isBusy && (
        <p className="dm-processing-message" role="status">
          Automatic document processing is in progress.
          Status updates every 3 seconds.
        </p>
      )}

      {loading && (
        <p className="dm-empty-text">
          Loading documents...
        </p>
      )}

      {!loading && documents.length === 0 && (
        <p className="dm-empty-text">
          No documents uploaded yet.
        </p>
      )}

      {!loading && documents.length > 0 && (
        <div className="dm-document-list">
          {documents.map((document) => {
            const isRetrying =
              processingId === document.id;

            return (
              <div
                className="dm-document"
                key={document.id}
              >
                <div className="dm-document-main">
                  <div className="dm-document-name">
                    {document.original_name}
                  </div>

                  <div className="dm-document-meta">
                    {formatFileSize(document.file_size)}
                    {" · "}
                    {document.chunks_count ?? 0} chunks
                  </div>

                  {document.status === "failed" &&
                    document.error_message && (
                      <p className="dm-document-error">
                        {document.error_message}
                      </p>
                    )}

                  {isPending(document) && (
                    <p className="dm-processing-message">
                      {document.status === "uploaded"
                        ? "Waiting for the queue worker..."
                        : "Extracting text and generating embeddings..."}
                    </p>
                  )}

                  {document.status === "failed" && (
                    <button
                      type="button"
                      className="dm-process-button"
                      onClick={() => handleRetry(document)}
                      disabled={isBusy || loading}
                    >
                      {isRetrying
                        ? "Retrying..."
                        : "Retry Processing"}
                    </button>
                  )}
                </div>

                <span
                  className={`dm-status dm-status-${
                    document.status || "unknown"
                  }`}
                >
                  {formatStatus(document.status)}
                </span>
              </div>
            );
          })}
        </div>
      )}

      <p className="dm-note">
        Files are processed automatically after upload.
        Ready documents can be retrieved by your AI
        assistant when their knowledge base is attached
        to a conversation.
      </p>
    </section>
  );
}

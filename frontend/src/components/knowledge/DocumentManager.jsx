
import { useCallback, useEffect, useRef, useState } from "react";
import { knowledgeBaseApi } from "../../services/api/knowledgeBaseApi";
import "./DocumentManager.css";

const MAX_FILE_SIZE = 2 * 1024 * 1024;

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
      "to check whether processing completed before retrying."
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
      return "Uploaded";
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

  const isBusy = uploading || processingId !== null;

  const loadDocuments = useCallback(async () => {
    setLoading(true);

    try {
      const result = await knowledgeBaseApi.getDocuments(
        knowledgeBaseId
      );

      if (!Array.isArray(result)) {
        throw new Error("Invalid document list response.");
      }

      setDocuments(result);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [knowledgeBaseId]);

  useEffect(() => {
    loadDocuments();
  }, [loadDocuments]);

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

      setSuccess(
        `"${uploadedDocument.original_name}" uploaded successfully. ` +
        "Click Process Document to make it searchable."
      );

      setSelectedFile(null);

      if (fileInputRef.current) {
        fileInputRef.current.value = "";
      }

      await loadDocuments();

      onDocumentUploaded?.();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setUploading(false);
    }
  }

  async function handleProcess(document) {
    if (isBusy || loading) {
      return;
    }

    try {
      setProcessingId(document.id);
      setError("");
      setSuccess("");

      // Show an immediate visual processing state.
      setDocuments((current) =>
        current.map((item) =>
          item.id === document.id
            ? { ...item, status: "processing" }
            : item
        )
      );

      const processedDocument =
        await knowledgeBaseApi.processDocument(
          knowledgeBaseId,
          document.id
        );

      setSuccess(
        `"${document.original_name}" processed successfully. ` +
        `${processedDocument.chunks_count} chunks created.`
      );
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      // Reload the authoritative status from Laravel.
      await loadDocuments();
      setProcessingId(null);
    }
  }

  async function handleRefresh() {
    setError("");
    setSuccess("");

    await loadDocuments();
  }

  function canProcess(document) {
    return (
      document.status === "uploaded" ||
      document.status === "failed"
    );
  }

  return (
    <section className="dm-container">
      <div className="dm-header">
        <div>
          <h4>Manage Documents</h4>
          <p>
            Upload and process TXT files to make them
            searchable by your AI assistant.
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
            const isProcessing =
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

                  {isProcessing && (
                    <p
                      className="dm-processing-message"
                      role="status"
                    >
                      Generating embeddings. Please keep
                      this page open until the request finishes.
                    </p>
                  )}

                  {canProcess(document) && (
                    <button
                      type="button"
                      className="dm-process-button"
                      onClick={() => handleProcess(document)}
                      disabled={isBusy || loading}
                    >
                      {document.status === "failed"
                        ? "Retry Processing"
                        : "Process Document"}
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
        Only documents with Ready status have completed
        text extraction, chunking, and embedding generation.
        To use them in chat, the knowledge base must also
        be attached to the conversation.
      </p>
    </section>
  );
}

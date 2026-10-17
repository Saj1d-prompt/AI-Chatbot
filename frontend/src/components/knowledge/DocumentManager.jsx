
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

  return (
    error?.response?.data?.message ||
    error?.message ||
    "Something went wrong. Please try again."
  );
}

function formatFileSize(bytes) {
  if (!Number.isFinite(Number(bytes))) {
    return "Unknown size";
  }

  const size = Number(bytes);

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
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const fileInputRef = useRef(null);

  const loadDocuments = useCallback(async () => {
    setLoading(true);
    setError("");

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

    if (!selectedFile || uploading) {
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
        `"${uploadedDocument.original_name}" uploaded successfully. Processing is not started yet.`
      );

      setSelectedFile(null);

      if (fileInputRef.current) {
        fileInputRef.current.value = "";
      }

      await loadDocuments();

      if (onDocumentUploaded) {
        onDocumentUploaded();
      }
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setUploading(false);
    }
  }

  return (
    <section className="dm-container">
      <div className="dm-header">
        <div>
          <h4>Manage Documents</h4>
          <p>
            Upload text files to this knowledge base.
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

      <form className="dm-upload-form" onSubmit={handleUpload}>
        <label htmlFor={`dm-file-${knowledgeBaseId}`}>
          Select a TXT document
        </label>

        <input
          id={`dm-file-${knowledgeBaseId}`}
          ref={fileInputRef}
          type="file"
          accept=".txt,text/plain"
          onChange={handleFileChange}
          disabled={uploading}
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
          disabled={!selectedFile || uploading}
        >
          {uploading ? "Uploading..." : "Upload Document"}
        </button>
      </form>

      <div className="dm-list-header">
        <h4>Documents ({documents.length})</h4>

        <button
          type="button"
          className="dm-refresh-button"
          onClick={loadDocuments}
          disabled={loading || uploading}
        >
          {loading ? "Loading..." : "Refresh"}
        </button>
      </div>

      {loading ? (
        <p className="dm-empty-text">
          Loading documents...
        </p>
      ) : documents.length === 0 ? (
        <p className="dm-empty-text">
          No documents uploaded yet.
        </p>
      ) : (
        <div className="dm-document-list">
          {documents.map((document) => (
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
              </div>

              <span
                className={`dm-status dm-status-${
                  document.status || "unknown"
                }`}
              >
                {formatStatus(document.status)}
              </span>
            </div>
          ))}
        </div>
      )}

      <p className="dm-note">
        Uploaded documents are not searchable until
        text extraction, chunking and embedding
        generation have completed successfully.
      </p>
    </section>
  );
}

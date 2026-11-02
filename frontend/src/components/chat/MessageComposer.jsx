import {
  useCallback,
  useEffect,
  useRef,
  useState,
} from "react";

import {
  ArrowUp,
  CheckCircle2,
  CircleAlert,
  FileText,
  LoaderCircle,
  Paperclip,
  RefreshCw,
  SlidersHorizontal,
  X,
} from "lucide-react";

import {
  createConversation,
  deleteConversationAttachment,
  getConversationAttachments,
  retryConversationAttachment,
  uploadConversationAttachment,
} from "../../services/api/conversationApi";

import "./MessageComposer.css";

const MAX_TEXTAREA_HEIGHT = 180;
const MAX_FILE_SIZE = 2 * 1024 * 1024;
const POLLING_INTERVAL = 3000;

/*
|--------------------------------------------------------------------------
| Attachment Helpers
|--------------------------------------------------------------------------
*/

function isPendingAttachment(attachment) {
  return (
    attachment.status === "uploaded" ||
    attachment.status === "processing"
  );
}

function isRemovableAttachment(attachment) {
  return (
    attachment.status === "ready" ||
    attachment.status === "failed"
  );
}

function getAttachmentStatusText(status) {
  switch (status) {
    case "uploaded":
      return "Queued";

    case "processing":
      return "Processing";

    case "ready":
      return "Ready";

    case "failed":
      return "Failed";

    default:
      return "Unknown";
  }
}

function getErrorMessage(error) {
  const errors = error?.response?.data?.errors;

  if (errors) {
    const messages = Object.values(errors).flat();

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

/*
|--------------------------------------------------------------------------
| Message Composer
|--------------------------------------------------------------------------
*/

function MessageComposer({
  value,
  onChange,
  onSubmit,
  isLoading,
  conversationId,
  onConversationCreated,
}) {
  /*
  |--------------------------------------------------------------------------
  | Refs
  |--------------------------------------------------------------------------
  */

  const textareaRef = useRef(null);
  const fileInputRef = useRef(null);

  const requestVersionRef = useRef(0);
  const activeConversationRef = useRef(conversationId);

  const refreshInProgressRef = useRef(false);
  const uploadInProgressRef = useRef(false);
  const retryInProgressRef = useRef(false);
  const deleteInProgressRef = useRef(false);

  /*
   * Prevent a refresh that started before deletion
   * from restoring an attachment already removed.
   */
  const deletedAttachmentIdsRef = useRef(new Set());

  /*
  |--------------------------------------------------------------------------
  | State
  |--------------------------------------------------------------------------
  */

  const [attachments, setAttachments] = useState([]);

  const [isUploading, setIsUploading] = useState(false);

  const [
    isLoadingAttachments,
    setIsLoadingAttachments,
  ] = useState(false);

  const [retryingId, setRetryingId] = useState(null);
  const [deletingId, setDeletingId] = useState(null);

  const [attachmentError, setAttachmentError] =
    useState("");

  const [attachmentNotice, setAttachmentNotice] =
    useState("");

  /*
  |--------------------------------------------------------------------------
  | Derived State
  |--------------------------------------------------------------------------
  */

  const hasPendingAttachments =
    attachments.some(isPendingAttachment);

  const isAttachmentBusy =
    isUploading ||
    retryingId !== null ||
    deletingId !== null;

  const isSendDisabled =
    !value.trim() ||
    isLoading ||
    isAttachmentBusy ||
    isLoadingAttachments ||
    hasPendingAttachments;

  /*
  |--------------------------------------------------------------------------
  | Textarea Auto Resize
  |--------------------------------------------------------------------------
  */

  const resizeTextarea = () => {
    const textarea = textareaRef.current;

    if (!textarea) {
      return;
    }

    textarea.style.height = "auto";

    const nextHeight = Math.min(
      textarea.scrollHeight,
      MAX_TEXTAREA_HEIGHT
    );

    textarea.style.height = `${nextHeight}px`;

    textarea.style.overflowY =
      textarea.scrollHeight > MAX_TEXTAREA_HEIGHT
        ? "auto"
        : "hidden";
  };

  useEffect(() => {
    resizeTextarea();
  }, [value]);

  /*
  |--------------------------------------------------------------------------
  | Refresh Attachment Statuses
  |--------------------------------------------------------------------------
  */

  const refreshAttachments = useCallback(
    async (
      targetConversationId,
      showLoading = false
    ) => {
      if (!targetConversationId) {
        return;
      }

      if (refreshInProgressRef.current) {
        return;
      }

      const requestVersion =
        requestVersionRef.current;

      refreshInProgressRef.current = true;

      if (showLoading) {
        setIsLoadingAttachments(true);
      }

      try {
        const result =
          await getConversationAttachments(
            targetConversationId
          );

        if (
          !result.success ||
          !Array.isArray(result.data)
        ) {
          throw new Error(
            "Invalid attachment status response."
          );
        }

        /*
         * Ignore stale responses after switching
         * to a different conversation.
         */
        if (
          requestVersion !==
            requestVersionRef.current ||
          activeConversationRef.current !==
            targetConversationId
        ) {
          return;
        }

        /*
         * A previous refresh must not reinsert
         * an attachment that was just deleted.
         */
        setAttachments(
          result.data.filter(
            (attachment) =>
              !deletedAttachmentIdsRef.current.has(
                attachment.id
              )
          )
        );
      } catch (error) {
        if (
          requestVersion ===
            requestVersionRef.current &&
          activeConversationRef.current ===
            targetConversationId
        ) {
          setAttachmentError(
            getErrorMessage(error)
          );
        }
      } finally {
        if (
          requestVersion ===
          requestVersionRef.current
        ) {
          refreshInProgressRef.current = false;
          setIsLoadingAttachments(false);
        }
      }
    },
    []
  );

  /*
  |--------------------------------------------------------------------------
  | Conversation Switching
  |--------------------------------------------------------------------------
  */

  useEffect(() => {
    requestVersionRef.current += 1;

    activeConversationRef.current = conversationId;

    refreshInProgressRef.current = false;
    deletedAttachmentIdsRef.current = new Set();

    setAttachments([]);
    setAttachmentError("");
    setAttachmentNotice("");

    setRetryingId(null);
    setDeletingId(null);

    if (!conversationId) {
      setIsLoadingAttachments(false);
      return;
    }

    refreshAttachments(
      conversationId,
      true
    );

    return () => {
      requestVersionRef.current += 1;
    };
  }, [
    conversationId,
    refreshAttachments,
  ]);

  /*
  |--------------------------------------------------------------------------
  | Automatic Status Polling
  |--------------------------------------------------------------------------
  */

  useEffect(() => {
    if (
      !conversationId ||
      !hasPendingAttachments ||
      isUploading ||
      isLoadingAttachments
    ) {
      return;
    }

    const intervalId = window.setInterval(() => {
      refreshAttachments(conversationId);
    }, POLLING_INTERVAL);

    return () => {
      window.clearInterval(intervalId);
    };
  }, [
    conversationId,
    hasPendingAttachments,
    isUploading,
    isLoadingAttachments,
    refreshAttachments,
  ]);

  /*
  |--------------------------------------------------------------------------
  | Form Submission
  |--------------------------------------------------------------------------
  */

  const handleKeyDown = (event) => {
    if (
      event.key === "Enter" &&
      !event.shiftKey
    ) {
      event.preventDefault();

      if (!isSendDisabled) {
        onSubmit(event);
      }
    }
  };

  const handleFormSubmit = (event) => {
    event.preventDefault();

    if (isSendDisabled) {
      return;
    }

    onSubmit(event);
  };

  /*
  |--------------------------------------------------------------------------
  | Automatically Create Conversation
  |--------------------------------------------------------------------------
  */

  const ensureConversation = async () => {
    if (activeConversationRef.current) {
      return activeConversationRef.current;
    }

    if (
      typeof onConversationCreated !== "function"
    ) {
      throw new Error(
        "Unable to create a conversation for this attachment."
      );
    }

    const startingVersion =
      requestVersionRef.current;

    const result = await createConversation();

    if (
      !result.success ||
      !result.conversation?.id
    ) {
      throw new Error(
        "Unable to create a conversation."
      );
    }

    const conversation = result.conversation;

    /*
     * Cancel attaching a file if the user
     * switched conversations while the new
     * conversation was being created.
     */
    if (
      startingVersion !==
      requestVersionRef.current
    ) {
      throw new Error(
        "The active conversation changed. Please select the file again."
      );
    }

    activeConversationRef.current =
      conversation.id;

    onConversationCreated(conversation);

    return conversation.id;
  };

  /*
  |--------------------------------------------------------------------------
  | Upload TXT Attachment
  |--------------------------------------------------------------------------
  */

  const handleFileChange = async (event) => {
    const file = event.target.files?.[0];

    /*
     * Allow the same file to be selected again
     * if an upload fails.
     */
    event.target.value = "";

    if (!file) {
      return;
    }

    setAttachmentError("");
    setAttachmentNotice("");

    if (
      !file.name.toLowerCase().endsWith(".txt")
    ) {
      setAttachmentError(
        "Only TXT documents are supported."
      );
      return;
    }

    if (file.size > MAX_FILE_SIZE) {
      setAttachmentError(
        "The document must be 2 MB or smaller."
      );
      return;
    }

    if (file.size === 0) {
      setAttachmentError(
        "The selected document is empty."
      );
      return;
    }

    if (
      uploadInProgressRef.current ||
      retryInProgressRef.current ||
      deleteInProgressRef.current ||
      isLoading
    ) {
      return;
    }

    uploadInProgressRef.current = true;
    setIsUploading(true);

    let targetConversationId = null;

    try {
      targetConversationId =
        await ensureConversation();

      const result =
        await uploadConversationAttachment(
          targetConversationId,
          file
        );

      if (
        !result.success ||
        !result.data?.id
      ) {
        throw new Error(
          "The attachment upload was not confirmed."
        );
      }

      if (
        activeConversationRef.current ===
        targetConversationId
      ) {
        /*
         * Show the queued attachment immediately
         * while the processing job runs.
         */
        setAttachments((current) => [
          ...current.filter(
            (item) =>
              item.id !== result.data.id
          ),
          {
            ...result.data,
            status:
              result.data.status ||
              "uploaded",
            chunks_count: 0,
          },
        ]);

        setAttachmentNotice(
          `"${file.name}" uploaded. Processing queued.`
        );

        await refreshAttachments(
          targetConversationId
        );
      }
    } catch (error) {
      if (
        !targetConversationId ||
        activeConversationRef.current ===
          targetConversationId
      ) {
        setAttachmentError(
          getErrorMessage(error)
        );
      }
    } finally {
      uploadInProgressRef.current = false;
      setIsUploading(false);
    }
  };

  /*
  |--------------------------------------------------------------------------
  | Retry Failed Attachment
  |--------------------------------------------------------------------------
  */

  const handleRetryAttachment = async (
    attachment
  ) => {
    const targetConversationId =
      activeConversationRef.current;

    if (
      !targetConversationId ||
      attachment.status !== "failed" ||
      uploadInProgressRef.current ||
      retryInProgressRef.current ||
      deleteInProgressRef.current ||
      isLoading
    ) {
      return;
    }

    retryInProgressRef.current = true;

    const requestVersion =
      requestVersionRef.current;

    setRetryingId(attachment.id);
    setAttachmentError("");
    setAttachmentNotice("");

    try {
      const result =
        await retryConversationAttachment(
          targetConversationId,
          attachment.id
        );

      if (
        !result.success ||
        result.data?.id !== attachment.id
      ) {
        throw new Error(
          "The retry request was not confirmed."
        );
      }

      if (
        requestVersion !==
          requestVersionRef.current ||
        activeConversationRef.current !==
          targetConversationId
      ) {
        return;
      }

      /*
       * Display Queued immediately.
       * Polling will update the status automatically.
       */
      setAttachments((current) =>
        current.map((item) =>
          item.id === attachment.id
            ? {
                ...item,
                status: "uploaded",
                error_message: null,
              }
            : item
        )
      );

      setAttachmentNotice(
        `"${attachment.original_name}" queued for retry.`
      );

      await refreshAttachments(
        targetConversationId
      );
    } catch (error) {
      if (
        requestVersion ===
          requestVersionRef.current &&
        activeConversationRef.current ===
          targetConversationId
      ) {
        setAttachmentError(
          getErrorMessage(error)
        );

        await refreshAttachments(
          targetConversationId
        );
      }
    } finally {
      retryInProgressRef.current = false;

      if (
        requestVersion ===
          requestVersionRef.current
      ) {
        setRetryingId(null);
      }
    }
  };

  /*
  |--------------------------------------------------------------------------
  | Delete Chat Attachment
  |--------------------------------------------------------------------------
  */

  const handleDeleteAttachment = async (
    attachment
  ) => {
    const targetConversationId =
      activeConversationRef.current;

    /*
     * The backend only allows removal of
     * Ready or Failed attachments.
     */
    if (
      !targetConversationId ||
      !isRemovableAttachment(attachment) ||
      uploadInProgressRef.current ||
      retryInProgressRef.current ||
      deleteInProgressRef.current ||
      isLoading
    ) {
      return;
    }

    /*
     * Ask for confirmation before permanently
     * removing the document.
     */
    const confirmed = window.confirm(
      `Permanently remove "${attachment.original_name}"?\n\n` +
      "Nexus will no longer retrieve information " +
      "from this document in future responses."
    );

    if (!confirmed) {
      return;
    }

    deleteInProgressRef.current = true;

    const requestVersion =
      requestVersionRef.current;

    setDeletingId(attachment.id);
    setAttachmentError("");
    setAttachmentNotice("");

    try {
      const result =
        await deleteConversationAttachment(
          targetConversationId,
          attachment.id
        );

      if (!result.success) {
        throw new Error(
          "The attachment deletion was not confirmed."
        );
      }

      /*
       * Ignore the result if the active
       * conversation has changed.
       */
      if (
        requestVersion !==
          requestVersionRef.current ||
        activeConversationRef.current !==
          targetConversationId
      ) {
        return;
      }

      /*
       * Keep stale status responses from bringing
       * a deleted attachment back into the UI.
       */
      deletedAttachmentIdsRef.current.add(
        attachment.id
      );

      /*
       * Remove the attachment from the screen.
       */
      setAttachments((current) =>
        current.filter(
          (item) =>
            item.id !== attachment.id
        )
      );

      setAttachmentNotice(
        `"${attachment.original_name}" was removed.`
      );
    } catch (error) {
      if (
        requestVersion ===
          requestVersionRef.current &&
        activeConversationRef.current ===
          targetConversationId
      ) {
        setAttachmentError(
          getErrorMessage(error)
        );

        await refreshAttachments(
          targetConversationId
        );
      }
    } finally {
      deleteInProgressRef.current = false;

      if (
        requestVersion ===
          requestVersionRef.current
      ) {
        setDeletingId(null);
      }
    }
  };

  /*
  |--------------------------------------------------------------------------
  | Manual Attachment Refresh
  |--------------------------------------------------------------------------
  */

  const handleManualRefresh = () => {
    if (!conversationId) {
      return;
    }

    setAttachmentError("");

    refreshAttachments(
      conversationId,
      true
    );
  };

  /*
  |--------------------------------------------------------------------------
  | Render
  |--------------------------------------------------------------------------
  */

  return (
    <div className="composer-wrapper">
      {/* Hidden file input */}
      <input
        ref={fileInputRef}
        type="file"
        accept=".txt,text/plain"
        className="composer-hidden-file-input"
        onChange={handleFileChange}
        aria-label="Choose a TXT document"
      />

      {/* Attachment list */}
      {attachments.length > 0 && (
        <div className="composer-attachments">
          <div className="composer-attachments-header">
            <span>
              Chat attachments ({attachments.length})
            </span>

            <button
              type="button"
              className="composer-attachment-refresh"
              onClick={handleManualRefresh}
              disabled={isLoadingAttachments}
              aria-label="Refresh attachment statuses"
              title="Refresh attachment statuses"
            >
              <RefreshCw
                size={14}
                className={
                  isLoadingAttachments
                    ? "composer-icon-spin"
                    : ""
                }
              />
            </button>
          </div>

          <div className="composer-attachment-list">
            {attachments.map((attachment) => {
              const isRetrying =
                retryingId === attachment.id;

              const isDeleting =
                deletingId === attachment.id;

              return (
                <div
                  className="composer-attachment-item"
                  key={attachment.id}
                >
                  <FileText
                    size={17}
                    className="composer-attachment-file-icon"
                  />

                  <span className="composer-attachment-name">
                    {attachment.original_name}
                  </span>

                  {/* Processing status */}
                  <span
                    className={
                      "composer-attachment-status " +
                      `composer-attachment-${attachment.status}`
                    }
                  >
                    {isPendingAttachment(attachment) ? (
                      <LoaderCircle
                        size={13}
                        className="composer-icon-spin"
                      />
                    ) : attachment.status === "ready" ? (
                      <CheckCircle2 size={13} />
                    ) : (
                      <CircleAlert size={13} />
                    )}

                    {getAttachmentStatusText(
                      attachment.status
                    )}
                  </span>

                  {/* Retry button */}
                  {attachment.status === "failed" && (
                    <button
                      type="button"
                      className="composer-attachment-retry"
                      onClick={() =>
                        handleRetryAttachment(
                          attachment
                        )
                      }
                      disabled={
                        isAttachmentBusy ||
                        isLoading
                      }
                      title="Retry processing"
                      aria-label={
                        `Retry processing ${attachment.original_name}`
                      }
                    >
                      {isRetrying ? (
                        <LoaderCircle
                          size={14}
                          className="composer-icon-spin"
                        />
                      ) : (
                        <RefreshCw size={14} />
                      )}

                      <span>
                        {isRetrying
                          ? "Retrying..."
                          : "Retry"}
                      </span>
                    </button>
                  )}

                  {/* Remove button */}
                  {isRemovableAttachment(
                    attachment
                  ) && (
                    <button
                      type="button"
                      className="composer-attachment-delete"
                      onClick={() =>
                        handleDeleteAttachment(
                          attachment
                        )
                      }
                      disabled={
                        isAttachmentBusy ||
                        isLoading
                      }
                      title={
                        `Remove ${attachment.original_name}`
                      }
                      aria-label={
                        `Remove ${attachment.original_name}`
                      }
                    >
                      {isDeleting ? (
                        <LoaderCircle
                          size={15}
                          className="composer-icon-spin"
                        />
                      ) : (
                        <X size={15} />
                      )}
                    </button>
                  )}
                </div>
              );
            })}
          </div>

          {/* Processing failure notice */}
          {attachments.some(
            (attachment) =>
              attachment.status === "failed"
          ) && (
            <p className="composer-attachment-warning">
              One or more documents failed processing.
              Use Retry to process an existing file
              again, or remove the failed attachment.
            </p>
          )}
        </div>
      )}

      {/* Upload progress */}
      {isUploading && (
        <div
          className="composer-attachment-notice"
          role="status"
        >
          <LoaderCircle
            size={14}
            className="composer-icon-spin"
          />
          <span>Uploading document...</span>
        </div>
      )}

      {/* Success notice */}
      {!isUploading && attachmentNotice && (
        <div
          className="composer-attachment-notice"
          role="status"
        >
          {attachmentNotice}
        </div>
      )}

      {/* Error message */}
      {attachmentError && (
        <div
          className="composer-attachment-error"
          role="alert"
        >
          <CircleAlert size={15} />

          <span>
            {attachmentError}
          </span>
        </div>
      )}

      {/* Message input */}
      <form
        className="message-composer"
        onSubmit={handleFormSubmit}
      >
        <textarea
          ref={textareaRef}
          value={value}
          rows={1}
          placeholder={
            hasPendingAttachments
              ? "Preparing your documents..."
              : "Ask Nexus anything..."
          }
          aria-label="Message"
          disabled={isLoading}
          onChange={(event) =>
            onChange(event.target.value)
          }
          onKeyDown={handleKeyDown}
        />

        <div className="composer-toolbar">
          <div className="composer-tools">
            {/* Attach file */}
            <button
              type="button"
              className="composer-tool-button"
              aria-label="Attach TXT document"
              title="Attach TXT document"
              disabled={
                isLoading ||
                isAttachmentBusy
              }
              onClick={() =>
                fileInputRef.current?.click()
              }
            >
              <Paperclip size={17} />
            </button>

            {/* Existing chat settings control */}
            <button
              type="button"
              className="composer-tool-button"
              aria-label="Chat settings"
              disabled={isLoading}
            >
              <SlidersHorizontal size={16} />
            </button>
          </div>

          {/* Send message */}
          <button
            className="composer-send-button"
            type="submit"
            disabled={isSendDisabled}
            aria-label="Send message"
            title={
              hasPendingAttachments
                ? "Wait for documents to finish processing"
                : "Send message"
            }
          >
            {isLoading ? (
              <LoaderCircle
                className="send-loader"
                size={17}
              />
            ) : (
              <ArrowUp
                size={18}
                strokeWidth={2.4}
              />
            )}
          </button>
        </div>
      </form>

      {/* Footer */}
      <div className="composer-footer">
        <span>
          Shift + Enter for new line
        </span>

        <span className="composer-footer-separator">
          •
        </span>

        <span>
          AI can make mistakes. Verify important information.
        </span>
      </div>
    </div>
  );
}

export default MessageComposer;

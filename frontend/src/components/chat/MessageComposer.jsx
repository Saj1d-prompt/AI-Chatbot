
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
} from "lucide-react";

import {
  createConversation,
  getConversationAttachments,
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
  const validationErrors =
    error?.response?.data?.errors;

  if (validationErrors) {
    const messages =
      Object.values(validationErrors).flat();

    if (messages.length > 0) {
      return messages.join(" ");
    }
  }

  return (
    error?.response?.data?.message ||
    error?.message ||
    "Unable to upload the document."
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
   * DOM references.
   */
  const textareaRef = useRef(null);
  const fileInputRef = useRef(null);

  /*
   * Prevent stale API responses from one
   * conversation affecting another.
   */
  const requestVersionRef = useRef(0);

  const activeConversationRef = useRef(
    conversationId
  );

  /*
   * Prevent overlapping status requests
   * and duplicate uploads.
   */
  const refreshInProgressRef = useRef(false);
  const uploadInProgressRef = useRef(false);

  /*
   * Attachment state.
   */
  const [
    attachments,
    setAttachments,
  ] = useState([]);

  const [
    isUploading,
    setIsUploading,
  ] = useState(false);

  const [
    isLoadingAttachments,
    setIsLoadingAttachments,
  ] = useState(false);

  const [
    attachmentError,
    setAttachmentError,
  ] = useState("");

  const [
    attachmentNotice,
    setAttachmentNotice,
  ] = useState("");

  /*
   * Detect unfinished document processing.
   */
  const hasPendingAttachments =
    attachments.some(isPendingAttachment);

  /*
   * Prevent messages from being sent while
   * uploaded documents are still processing.
   */
  const isSendDisabled =
    !value.trim() ||
    isLoading ||
    isUploading ||
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

    textarea.style.height =
      `${nextHeight}px`;

    textarea.style.overflowY =
      textarea.scrollHeight >
      MAX_TEXTAREA_HEIGHT
        ? "auto"
        : "hidden";
  };

  useEffect(() => {
    resizeTextarea();
  }, [value]);

  /*
  |--------------------------------------------------------------------------
  | Attachment Status API
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
         * Ignore responses belonging to
         * a previous conversation.
         */
        if (
          requestVersion !==
            requestVersionRef.current ||
          activeConversationRef.current !==
            targetConversationId
        ) {
          return;
        }

        setAttachments(result.data);

        setAttachmentError("");
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
    /*
     * Invalidate requests from the
     * previously selected conversation.
     */
    requestVersionRef.current += 1;

    activeConversationRef.current =
      conversationId;

    refreshInProgressRef.current = false;

    setAttachments([]);
    setAttachmentError("");
    setAttachmentNotice("");

    if (!conversationId) {
      setIsLoadingAttachments(false);
      return;
    }

    /*
     * Load files belonging only to
     * the selected conversation.
     */
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

    const intervalId = window.setInterval(
      () => {
        refreshAttachments(
          conversationId
        );
      },
      POLLING_INTERVAL
    );

    return () => {
      window.clearInterval(
        intervalId
      );
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
  | Keyboard and Form Submission
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
  | Create Conversation Before Upload
  |--------------------------------------------------------------------------
  */

  const ensureConversation = async () => {
    /*
     * Existing conversation:
     * upload into that conversation.
     */
    if (activeConversationRef.current) {
      return activeConversationRef.current;
    }

    if (
      typeof onConversationCreated !==
      "function"
    ) {
      throw new Error(
        "Unable to create a conversation for this attachment."
      );
    }

    /*
     * STEP 5 RELIABILITY FIX:
     *
     * Record the current request version
     * before making the API request.
     */
    const startingVersion =
      requestVersionRef.current;

    const result =
      await createConversation();

    if (
      !result.success ||
      !result.conversation?.id
    ) {
      throw new Error(
        "Unable to create a new conversation."
      );
    }

    const conversation =
      result.conversation;

    /*
     * STEP 5 RELIABILITY FIX:
     *
     * If the user switched conversations
     * while this request was pending,
     * do not replace their selected chat.
     */
    if (
      startingVersion !==
      requestVersionRef.current
    ) {
      throw new Error(
        "The active conversation changed. Please select the file again."
      );
    }

    /*
     * Register the new conversation
     * with ChatPage.
     */
    activeConversationRef.current =
      conversation.id;

    onConversationCreated(
      conversation
    );

    return conversation.id;
  };

  /*
  |--------------------------------------------------------------------------
  | File Upload
  |--------------------------------------------------------------------------
  */

  const handleFileChange = async (
    event
  ) => {
    const file =
      event.target.files?.[0];

    /*
     * Allow the same file to be
     * selected again later.
     */
    event.target.value = "";

    if (!file) {
      return;
    }

    setAttachmentError("");
    setAttachmentNotice("");

    /*
     * Frontend validation.
     */
    if (
      !file.name
        .toLowerCase()
        .endsWith(".txt")
    ) {
      setAttachmentError(
        "Only TXT documents are supported."
      );

      return;
    }

    if (
      file.size > MAX_FILE_SIZE
    ) {
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

    /*
     * Avoid duplicate uploads.
     */
    if (
      uploadInProgressRef.current ||
      isLoading
    ) {
      return;
    }

    uploadInProgressRef.current = true;
    setIsUploading(true);

    let targetConversationId = null;

    try {
      /*
       * Automatically create a conversation
       * when attaching a file from a new chat.
       */
      targetConversationId =
        await ensureConversation();

      /*
       * Send multipart/form-data
       * to the Laravel backend.
       */
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

      /*
       * Only update the current chat's UI.
       */
      if (
        activeConversationRef.current ===
        targetConversationId
      ) {
        /*
         * STEP 5 RELIABILITY FIX:
         *
         * Display the new document
         * immediately as Queued.
         *
         * We do not need to wait for
         * the first status API request.
         */
        setAttachments((current) => [
          ...current.filter(
            (attachment) =>
              attachment.id !==
              result.data.id
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
          `"${file.name}" uploaded. ` +
          "Nexus is preparing the document."
        );

        /*
         * Synchronize status with
         * the Laravel database.
         */
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
  | Manual Attachment Refresh
  |--------------------------------------------------------------------------
  */

  const handleManualRefresh = () => {
    if (!conversationId) {
      return;
    }

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
      {/*
        Hidden file picker.
        Opened by the paperclip button.
      */}
      <input
        ref={fileInputRef}
        type="file"
        accept=".txt,text/plain"
        className="composer-hidden-file-input"
        onChange={handleFileChange}
        aria-label="Choose a TXT document"
      />

      {/*
        Conversation attachment list.
      */}
      {attachments.length > 0 && (
        <div className="composer-attachments">
          <div className="composer-attachments-header">
            <span>
              Chat attachments (
              {attachments.length})
            </span>

            <button
              type="button"
              className="composer-attachment-refresh"
              onClick={
                handleManualRefresh
              }
              disabled={
                isLoadingAttachments
              }
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
            {attachments.map(
              (attachment) => (
                <div
                  className="composer-attachment-item"
                  key={attachment.id}
                >
                  <FileText size={17} />

                  <span className="composer-attachment-name">
                    {attachment.original_name}
                  </span>

                  <span
                    className={
                      "composer-attachment-status " +
                      `composer-attachment-${attachment.status}`
                    }
                  >
                    {isPendingAttachment(
                      attachment
                    ) ? (
                      <LoaderCircle
                        size={13}
                        className="composer-icon-spin"
                      />
                    ) : attachment.status ===
                      "ready" ? (
                      <CheckCircle2
                        size={13}
                      />
                    ) : (
                      <CircleAlert
                        size={13}
                      />
                    )}

                    {getAttachmentStatusText(
                      attachment.status
                    )}
                  </span>
                </div>
              )
            )}
          </div>

          {attachments.some(
            (attachment) =>
              attachment.status ===
              "failed"
          ) && (
            <p className="composer-attachment-warning">
              One or more documents
              failed processing.
              Reupload the affected
              file to try again.
            </p>
          )}
        </div>
      )}

      {/*
        Uploading indicator.
      */}
      {isUploading && (
        <div
          className="composer-attachment-notice"
          role="status"
        >
          <LoaderCircle
            size={14}
            className="composer-icon-spin"
          />

          Uploading document...
        </div>
      )}

      {/*
        Upload success notification.
      */}
      {!isUploading &&
        attachmentNotice && (
          <div
            className="composer-attachment-notice"
            role="status"
          >
            {attachmentNotice}
          </div>
        )}

      {/*
        Upload or status error.
      */}
      {attachmentError && (
        <div
          className="composer-attachment-error"
          role="alert"
        >
          <CircleAlert
            size={15}
          />

          <span>
            {attachmentError}
          </span>
        </div>
      )}

      {/*
        Existing message composer.
      */}
      <form
        className="message-composer"
        onSubmit={
          handleFormSubmit
        }
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
          disabled={
            isLoading
          }
          onChange={(event) =>
            onChange(
              event.target.value
            )
          }
          onKeyDown={
            handleKeyDown
          }
        />

        <div className="composer-toolbar">
          <div className="composer-tools">
            {/*
              Functional attachment button.
            */}
            <button
              type="button"
              className="composer-tool-button"
              aria-label="Attach TXT document"
              title="Attach TXT document"
              disabled={
                isLoading ||
                isUploading
              }
              onClick={() =>
                fileInputRef.current?.click()
              }
            >
              <Paperclip
                size={17}
              />
            </button>

            {/*
              Existing chat settings button.
            */}
            <button
              type="button"
              className="composer-tool-button"
              aria-label="Chat settings"
              disabled={isLoading}
            >
              <SlidersHorizontal
                size={16}
              />
            </button>
          </div>

          {/*
            Send button.
            Disabled until pending
            attachments are ready.
          */}
          <button
            className="composer-send-button"
            type="submit"
            disabled={
              isSendDisabled
            }
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

      {/*
        Existing composer footer.
      */}
      <div className="composer-footer">
        <span>
          Shift + Enter for new line
        </span>

        <span className="composer-footer-separator">
          •
        </span>

        <span>
          AI can make mistakes.
          Verify important information.
        </span>
      </div>
    </div>
  );
}

export default MessageComposer;

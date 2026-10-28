
import apiClient from "./apiClient";

export async function getConversations() {
  const response = await apiClient.get("/conversations");
  return response.data;
}

export async function getConversation(conversationId) {
  const response = await apiClient.get(
    `/conversations/${conversationId}`
  );

  return response.data;
}

export async function createConversation(title = null) {
  const payload = {};

  if (title) {
    payload.title = title;
  }

  const response = await apiClient.post(
    "/conversations",
    payload
  );

  return response.data;
}

export async function getConversationMessages(
  conversationId,
  beforeId = null
) {
  const params = {};

  if (beforeId) {
    params.before_id = beforeId;
  }

  const response = await apiClient.get(
    `/conversations/${conversationId}/messages`,
    { params }
  );

  return response.data;
}

export async function sendConversationMessage(
  conversationId,
  message
) {
  const response = await apiClient.post(
    `/conversations/${conversationId}/messages`,
    { message }
  );

  return response.data;
}

export async function regenerateConversationResponse(
  conversationId
) {
  const response = await apiClient.post(
    `/conversations/${conversationId}/regenerate`
  );

  return response.data;
}

export async function renameConversation(
  conversationId,
  title
) {
  const response = await apiClient.patch(
    `/conversations/${conversationId}`,
    { title }
  );

  return response.data;
}

export async function deleteConversation(conversationId) {
  const response = await apiClient.delete(
    `/conversations/${conversationId}`
  );

  return response.data;
}

export async function updateConversationKnowledgeBases(
  conversationId,
  knowledgeBaseIds
) {
  const response = await apiClient.put(
    `/conversations/${conversationId}/knowledge-bases`,
    {
      knowledge_base_ids: knowledgeBaseIds,
    }
  );

  return response.data;
}

/*
|--------------------------------------------------------------------------
| Conversation Attachments
|--------------------------------------------------------------------------
*/

/**
 * Upload a TXT file directly into a conversation.
 */
export async function uploadConversationAttachment(
  conversationId,
  file
) {
  const formData = new FormData();

  formData.append("file", file);

  const response = await apiClient.post(
    `/conversations/${conversationId}/attachments`,
    formData,
    {
      headers: {
        "Content-Type": undefined,
      },
    }
  );

  return response.data;
}

/**
 * Fetch the current attachment list and processing statuses.
 */
export async function getConversationAttachments(
  conversationId
) {
  const response = await apiClient.get(
    `/conversations/${conversationId}/attachments`
  );

  return response.data;
}

/**
 * Retry processing an existing failed attachment.
 *
 * Reuses the original document record and stored file.
 */
export async function retryConversationAttachment(
  conversationId,
  documentId
) {
  const response = await apiClient.post(
    `/conversations/${conversationId}/attachments/${documentId}/retry`
  );

  return response.data;
}

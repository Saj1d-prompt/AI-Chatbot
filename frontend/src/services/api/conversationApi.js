import apiClient from "./apiClient";

export async function getConversations() {
  const response = await apiClient.get("/conversations");

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
    {
      params,
    }
  );

  return response.data;
}

export async function sendConversationMessage(
  conversationId,
  message
) {
  const response = await apiClient.post(
    `/conversations/${conversationId}/messages`,
    {
      message,
    }
  );

  return response.data;
}

export async function deleteConversation(
  conversationId
) {
  const response = await apiClient.delete(
    `/conversations/${conversationId}`
  );

  return response.data;
}
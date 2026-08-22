import apiClient from "./apiClient";

export async function sendChatMessage(message) {
  const response = await apiClient.post("/chat", {
    message,
  });

  return response.data;
}
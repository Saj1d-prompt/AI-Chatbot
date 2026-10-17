
import apiClient from "./apiClient";

export const knowledgeBaseApi = {
  async getAll() {
    const response = await apiClient.get("/knowledge-bases");
    return response.data.data;
  },

  async getById(knowledgeBaseId) {
    const response = await apiClient.get(
      `/knowledge-bases/${knowledgeBaseId}`
    );
    return response.data.data;
  },

  async create({ name, description = "" }) {
    const response = await apiClient.post("/knowledge-bases", {
      name,
      description,
    });

    return response.data.data;
  },

  async update(knowledgeBaseId, data) {
    const response = await apiClient.patch(
      `/knowledge-bases/${knowledgeBaseId}`,
      data
    );

    return response.data.data;
  },

  async remove(knowledgeBaseId) {
    const response = await apiClient.delete(
      `/knowledge-bases/${knowledgeBaseId}`
    );

    return response.data;
  },

  async getDocuments(knowledgeBaseId) {
    const response = await apiClient.get(
      `/knowledge-bases/${knowledgeBaseId}/documents`
    );

    return response.data.data;
  },

  async uploadDocument(knowledgeBaseId, file) {
    const formData = new FormData();

    formData.append("file", file);

    const response = await apiClient.post(
      `/knowledge-bases/${knowledgeBaseId}/documents`,
      formData,
      {
        headers: {
          "Content-Type": undefined,
        },
      }
    );

    return response.data.data;
  },
};

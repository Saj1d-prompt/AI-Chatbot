import axios from "axios";

const apiClient = axios.create({
  baseURL: "/api",

  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },

  timeout: 70000,
});

export default apiClient;
import axios from "axios";

import apiClient from "./apiClient";

const csrfClient = axios.create({
  withCredentials: true,
  withXSRFToken: true,

  headers: {
    Accept: "application/json",
  },
});

export async function initializeCsrf() {
  await csrfClient.get(
    "/sanctum/csrf-cookie"
  );
}

export async function registerUser({
  name,
  email,
  password,
  passwordConfirmation,
}) {
  await initializeCsrf();

  const response = await apiClient.post(
    "/auth/register",
    {
      name,
      email,
      password,

      password_confirmation:
        passwordConfirmation,
    }
  );

  return response.data;
}

export async function loginUser({
  email,
  password,
  remember = false,
}) {
  await initializeCsrf();

  const response = await apiClient.post(
    "/auth/login",
    {
      email,
      password,
      remember,
    }
  );

  return response.data;
}

export async function getCurrentUser() {
  const response = await apiClient.get(
    "/auth/user"
  );

  return response.data;
}

export async function logoutUser() {
  const response = await apiClient.post(
    "/auth/logout"
  );

  return response.data;
}
import { instance, authInstance } from "./client";

// Every endpoint returns { success, message, data }; these return `data`.

export const login = (credentials) => instance.post("auth/login", credentials).then((r) => r.data.data);

export const registerHomecare = (payload) => instance.post("auth/register", payload).then((r) => r.data);

export const me = () => authInstance.get("auth/me").then((r) => r.data.data);

export const logout = () => authInstance.post("auth/logout");

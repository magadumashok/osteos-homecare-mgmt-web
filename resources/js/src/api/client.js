import axios from "axios";
import { getToken, setToken, clearSession } from "../services/AuthService";

const baseConfig = {
    baseURL: "/api/v1/",
    headers: { Accept: "application/json" },
};

/** Public endpoints (login, registration, reference data). */
export const instance = axios.create(baseConfig);

/** Authenticated endpoints. Always sends the latest token. */
export const authInstance = axios.create(baseConfig);

authInstance.interceptors.request.use((config) => {
    const token = getToken();
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

// ── Expired token: refresh once, retry, otherwise end the session ─────────────
let refreshing = null;

const refreshToken = () => {
    refreshing ??= instance
        .post("auth/refresh", null, { headers: { Authorization: `Bearer ${getToken()}` } })
        .then(({ data }) => {
            setToken(data.data.access_token, data.data.expires_in);
            return data.data.access_token;
        })
        .finally(() => {
            refreshing = null;
        });
    return refreshing;
};

authInstance.interceptors.response.use(
    (response) => response,
    async (error) => {
        const { config, response } = error;

        if (response?.status === 401 && !config._retried && getToken()) {
            config._retried = true;
            try {
                const token = await refreshToken();
                config.headers.Authorization = `Bearer ${token}`;
                return authInstance(config);
            } catch {
                clearSession();
                window.location.assign("/?expired=1");
            }
        }

        return Promise.reject(error);
    }
);

/** The server's `message`, or a readable fallback. */
export const errorMessage = (error, fallback = "Something went wrong. Please try again.") => {
    const data = error?.response?.data;
    if (data?.errors) return Object.values(data.errors).flat()[0] ?? fallback;
    return data?.message || fallback;
};

/** Field errors from a 422 response, keyed by field name. */
export const fieldErrors = (error) => error?.response?.status === 422 ? error.response.data.errors ?? {} : {};

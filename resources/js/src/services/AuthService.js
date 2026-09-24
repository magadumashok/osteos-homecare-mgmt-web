// Session persistence in localStorage. Same helper names as the EMR's
// AuthService, plus token-expiry tracking (the legacy app never checked it).

const KEYS = {
    token: "ACCESS_TOKEN",
    validity: "validity",
    user: "user",
    homecare: "homecare",
    permissions: "permissions",
};

const readJson = (key, fallback) => {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch {
        return fallback;
    }
};

// ── Token ─────────────────────────────────────────────────────────────────────
export const getToken = () => localStorage.getItem(KEYS.token);

export const setToken = (token, expiresInSeconds) => {
    localStorage.setItem(KEYS.token, token);
    if (expiresInSeconds) {
        localStorage.setItem(KEYS.validity, new Date(Date.now() + expiresInSeconds * 1000).toISOString());
    }
};

export const getValidity = () => {
    const v = localStorage.getItem(KEYS.validity);
    return v ? new Date(v) : null;
};

export const isTokenValid = () => {
    const v = getValidity();
    return !!v && v > new Date();
};

export const isLoggedIn = () => !!getToken() && isTokenValid();

// ── Session ───────────────────────────────────────────────────────────────────
export const setSession = ({ access_token, expires_in, user, homecare, permissions }) => {
    setToken(access_token, expires_in);
    localStorage.setItem(KEYS.user, JSON.stringify(user ?? null));
    localStorage.setItem(KEYS.homecare, JSON.stringify(homecare ?? null));
    setPermissions(permissions);
};

export const updateSessionProfile = ({ user, homecare, permissions }) => {
    localStorage.setItem(KEYS.user, JSON.stringify(user ?? null));
    localStorage.setItem(KEYS.homecare, JSON.stringify(homecare ?? null));
    setPermissions(permissions);
};

export const getSession = () => ({
    token: getToken(),
    user: readJson(KEYS.user, null),
    homecare: readJson(KEYS.homecare, null),
    permissions: getPermissions(),
});

export const clearSession = () => {
    Object.values(KEYS).forEach((k) => localStorage.removeItem(k));
};

// ── Generic key/value ─────────────────────────────────────────────────────────
export const setItem = (name, data) => localStorage.setItem(name, data);
export const getKey = (key) => localStorage.getItem(key);

// ── Permissions ───────────────────────────────────────────────────────────────
export const setPermissions = (permissions) => {
    const arr = Array.isArray(permissions) ? permissions : Array.from(permissions ?? []);
    localStorage.setItem(KEYS.permissions, JSON.stringify(arr));
};

export const getPermissions = () => {
    const parsed = readJson(KEYS.permissions, []);
    return Array.isArray(parsed) ? parsed : [];
};

export const hasPermission = (permission) => {
    if (!permission) return true;
    return getPermissions().includes(permission);
};

export const hasAnyPermission = (permissionList = []) => {
    if (!permissionList.length) return true;
    const stored = getPermissions();
    return permissionList.some((p) => stored.includes(p));
};

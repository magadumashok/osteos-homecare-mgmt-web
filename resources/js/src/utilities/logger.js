/**
 * devLog - Development-only logger
 *
 * Replaces bare console.log calls across the codebase.
 * Logs are silenced in production builds automatically.
 *
 * Usage:
 *   import { devLog, devWarn, devError } from "../utilities/logger";
 *   devLog("patients loaded", data);
 */

const isDev = import.meta.env.DEV;

export const devLog = (...args) => {
    if (isDev) console.log(...args);
};

export const devWarn = (...args) => {
    if (isDev) console.warn(...args);
};

export const devError = (...args) => {
    if (isDev) console.error(...args);
};

export default devLog;

import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import * as authApi from "../api/auth";
import { errorMessage } from "../api/client";
import { setSession, updateSessionProfile, clearSession, getSession, isLoggedIn } from "../services/AuthService";

const fromStorage = () => {
    const s = isLoggedIn() ? getSession() : { user: null, homecare: null, permissions: [] };
    return {
        isAuthenticated: isLoggedIn(),
        user: s.user,
        homecare: s.homecare,
        permissions: s.permissions,
        status: "idle",
    };
};

export const login = createAsyncThunk("auth/login", async (credentials, { rejectWithValue }) => {
    try {
        const data = await authApi.login(credentials);
        setSession(data);
        return data;
    } catch (error) {
        return rejectWithValue(errorMessage(error, "Could not sign in. Please try again."));
    }
});

/** Reload user, homecare and permissions (e.g. after roles change). */
export const refreshProfile = createAsyncThunk("auth/refreshProfile", async () => {
    const data = await authApi.me();
    updateSessionProfile(data);
    return data;
});

export const logout = createAsyncThunk("auth/logout", async () => {
    try {
        await authApi.logout();
    } catch {
        // The token may already be invalid; the local session ends regardless.
    }
    clearSession();
});

const authSlice = createSlice({
    name: "auth",
    initialState: fromStorage(),
    reducers: {},
    extraReducers: (builder) => {
        builder
            .addCase(login.pending, (state) => {
                state.status = "loading";
            })
            .addCase(login.fulfilled, (state, { payload }) => {
                state.status = "idle";
                state.isAuthenticated = true;
                state.user = payload.user;
                state.homecare = payload.homecare;
                state.permissions = payload.permissions ?? [];
            })
            .addCase(login.rejected, (state) => {
                state.status = "idle";
            })
            .addCase(refreshProfile.fulfilled, (state, { payload }) => {
                state.user = payload.user;
                state.homecare = payload.homecare;
                state.permissions = payload.permissions ?? [];
            })
            .addCase(logout.fulfilled, (state) => {
                state.isAuthenticated = false;
                state.user = null;
                state.homecare = null;
                state.permissions = [];
            });
    },
});

export default authSlice.reducer;

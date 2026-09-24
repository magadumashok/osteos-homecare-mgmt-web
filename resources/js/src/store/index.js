import { configureStore } from "@reduxjs/toolkit";
import auth from "./authSlice";

// One slice per module (createSlice); add new ones here as modules are ported.
const store = configureStore({
    reducer: {
        auth,
    },
    devTools: import.meta.env.DEV,
});

export default store;

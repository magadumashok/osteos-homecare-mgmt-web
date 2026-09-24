import React from "react";
import { Provider } from "react-redux";
import { BrowserRouter } from "react-router-dom";
import { ThemeProvider, CssBaseline } from "@mui/material";
import { LocalizationProvider } from "@mui/x-date-pickers";
import { AdapterDateFns } from "@mui/x-date-pickers/AdapterDateFns";
import { ToastContainer } from "react-toastify";
import store from "./store";
import muiTheme from "./theme/muiTheme";
import AppRoutes from "./components/AppRoutes.jsx";
import "react-toastify/dist/ReactToastify.min.css";
// Design system shared with the EMR — auth pages, shell, cards, buttons, forms
import "./assets/css/theme.css";

function App() {
    return (
        <Provider store={store}>
            <ThemeProvider theme={muiTheme}>
                <CssBaseline />
                <LocalizationProvider dateAdapter={AdapterDateFns}>
                    <BrowserRouter>
                        <AppRoutes />
                    </BrowserRouter>
                </LocalizationProvider>
                <ToastContainer position="top-center" hideProgressBar newestOnTop autoClose={6000} />
            </ThemeProvider>
        </Provider>
    );
}

export default App;

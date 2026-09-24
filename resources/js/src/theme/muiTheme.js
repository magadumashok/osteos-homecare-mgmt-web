import { createTheme } from "@mui/material/styles";

/**
 * MUI theme matching the EMR design tokens in assets/css/theme.css,
 * so MUI components and the shared CSS classes look like one system.
 */
const muiTheme = createTheme({
    palette: {
        primary: { main: "#15698a", dark: "#0d5068", light: "#1a8fa8", contrastText: "#ffffff" },
        secondary: { main: "#2193b0", contrastText: "#ffffff" },
        background: { default: "#f0f4f8", paper: "#ffffff" },
        text: { primary: "#333333", secondary: "#8a9baa" },
    },
    shape: { borderRadius: 10 },
    typography: {
        fontFamily: "'DM Sans', sans-serif",
        h1: { fontFamily: "'Playfair Display', serif" },
        h2: { fontFamily: "'Playfair Display', serif" },
        h3: { fontFamily: "'Playfair Display', serif" },
        h4: { fontFamily: "'Playfair Display', serif" },
        button: { textTransform: "none", fontWeight: 600 },
    },
    components: {
        MuiButton: { styleOverrides: { root: { borderRadius: 25 } } },
        MuiCard: { styleOverrides: { root: { borderRadius: 12 } } },
    },
});

export default muiTheme;

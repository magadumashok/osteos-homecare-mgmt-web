import React, { useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { useLocation, useNavigate, useSearchParams } from "react-router-dom";
import { useForm } from "react-hook-form";
import { yupResolver } from "@hookform/resolvers/yup";
import { CircularProgress } from "@mui/material";
import { toast } from "react-toastify";
import RHFTextField from "../forms/RHFTextField";
import AuthLayout from "./AuthLayout";
import { login } from "../../store/authSlice";
import { loginSchema } from "../../validations/authSchemas";

const LoginComponent = () => {
    const dispatch = useDispatch();
    const navigate = useNavigate();
    const location = useLocation();
    const [params] = useSearchParams();
    const isLoading = useSelector((s) => s.auth.status === "loading");

    const { handleSubmit, control } = useForm({
        resolver: yupResolver(loginSchema),
        defaultValues: { mobile: "", password: "" },
    });

    useEffect(() => {
        if (params.get("expired")) {
            toast.info("Your session ended. Please sign in again.", { toastId: "expired" });
        }
    }, [params]);

    const onSubmit = async (values) => {
        const result = await dispatch(login(values));
        if (login.fulfilled.match(result)) {
            navigate(location.state?.from ?? "/dashboard", { replace: true });
        } else {
            toast.error(result.payload);
        }
    };

    return (
        <AuthLayout>
            <p className="auth-eyebrow">Welcome back</p>
            <h2 className="auth-title">Sign in</h2>
            <p className="auth-subtitle">Use the mobile number registered with your homecare center.</p>

            <form onSubmit={handleSubmit(onSubmit)} noValidate>
                <RHFTextField
                    name="mobile"
                    control={control}
                    label="Mobile number"
                    autoComplete="username"
                    inputProps={{ inputMode: "numeric" }}
                    autoFocus
                />
                <RHFTextField
                    name="password"
                    control={control}
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                />

                <p className="auth-forgot" style={{ cursor: "default" }}>
                    Forgot your password? Ask your homecare administrator to reset it.
                </p>

                <button type="submit" className="auth-btn-primary full" disabled={isLoading}>
                    {isLoading ? (
                        <><CircularProgress size={16} color="inherit" /> Signing in…</>
                    ) : (
                        <>Sign in <span>→</span></>
                    )}
                </button>
            </form>

            <div className="auth-divider">or</div>

            <button type="button" className="auth-btn-secondary full" onClick={() => navigate("/register")}>
                Register your homecare center
            </button>
        </AuthLayout>
    );
};

export default LoginComponent;

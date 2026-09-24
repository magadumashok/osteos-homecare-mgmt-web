import * as yup from "yup";

// Mirrors App\Http\Requests\Auth\* on the server.

const mobile = yup
    .string()
    .trim()
    .required("Enter your mobile number")
    .matches(/^[0-9]{6,15}$/, "Enter the mobile number as digits only, without the country code");

export const loginSchema = yup.object({
    mobile,
    password: yup.string().required("Enter your password"),
});

export const registerHomecareSchema = yup.object({
    name: yup.string().trim().required("Enter the homecare center's name").max(120),
    owner: yup.string().trim().required("Enter the owner's name").max(120),
    email: yup.string().trim().required("Enter an email address").email("Enter a valid email address"),
    mobile,
    password: yup.string().required("Choose a password").min(8, "Use at least 8 characters"),
    password_confirmation: yup
        .string()
        .required("Repeat the password")
        .oneOf([yup.ref("password")], "The passwords don't match"),
    country: yup.string().required("Select a country"),
    region: yup.string().nullable(),
    city: yup.string().required("Select a city"),
    address: yup.string().trim().max(500).nullable(),
    center_id: yup.string().trim().max(60).nullable(),
});

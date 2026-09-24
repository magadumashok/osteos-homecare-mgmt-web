// src/utils/validationRules.js

export const required = (message = "This field is required") => ({
  required: { value: true, message },
});

export const minLength = (length, message) => ({
  minLength: { value: length, message: message || `Minimum ${length} characters required` },
});

export const maxLength = (length, message) => ({
  maxLength: { value: length, message: message || `Maximum ${length} characters allowed` },
});

export const email = (message = "Invalid email address") => ({
  pattern: {
    value: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    message,
  },
});

export const mobileNumber = (message = "Invalid mobile number") => ({
  pattern: {
    value: /^[0-9]{10}$/, // Adjust as per your region
    message,
  },
});

// src/components/forms/RHFTextField.js
import { Controller } from "react-hook-form";
import TextField from "@mui/material/TextField";

// Extra props (autoComplete, inputProps, placeholder, disabled…) pass through to TextField.
const RHFTextField = ({ name, control, label, type = "text", rules, ...rest }) => (
  <Controller
    name={name}
    control={control}
    rules={rules}
    render={({ field, fieldState: { error } }) => (
      <TextField
        {...field}
        {...rest}
        value={field.value ?? ""}
        type={type}
        label={label}
        fullWidth
        variant="outlined"
        margin="normal"
        error={!!error}
        helperText={error?.message}
        InputProps={{
        classes: {
        notchedOutline: "input-modern label-modern"
        }
        }}
      />
    )}
  />
);

export default RHFTextField;

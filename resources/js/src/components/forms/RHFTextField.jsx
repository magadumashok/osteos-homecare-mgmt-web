// src/components/forms/RHFTextField.js
import { Controller } from "react-hook-form";
import TextField from "@mui/material/TextField";

// Extra props (autoComplete, inputProps, placeholder, disabled…) pass through to TextField.
// A `helperText` shows until the field has an error. Date and time inputs keep their label raised.
const RHFTextField = ({ name, control, label, type = "text", rules, helperText, ...rest }) => (
  <Controller
    name={name}
    control={control}
    rules={rules}
    render={({ field: { ref, ...field }, fieldState: { error } }) => (
      <TextField
        {...field}
        {...rest}
        inputRef={ref} // lets the form focus the first invalid field
        value={field.value ?? ""}
        type={type}
        label={label}
        fullWidth
        variant="outlined"
        margin="normal"
        error={!!error}
        helperText={error?.message ?? helperText}
        InputLabelProps={["date", "time", "datetime-local"].includes(type) ? { shrink: true } : rest.InputLabelProps}
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

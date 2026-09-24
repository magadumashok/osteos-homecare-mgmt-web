// src/components/forms/RHFTextField.js
import { Controller } from "react-hook-form";
import TextField from "@mui/material/TextField";

const RHFTextFieldSimple = ({ name, control, label, type = "text", rules }) => (
  <Controller
    name={name}
    control={control}
    rules={rules}
    render={({ field, fieldState: { error } }) => (
      <TextField
        {...field}
        type={type}
        // label={label}
        fullWidth
        variant="standard"
        margin="normal"
        error={!!error}
        helperText={error?.message}
        // InputProps={{
        // classes: {
        // notchedOutline: "input-modern label-modern"
        // }
        // }}
      />
    )}
  />
);

export default RHFTextFieldSimple;

// src/components/forms/RHFTextarea.js
import { Controller } from "react-hook-form";
import TextField from "@mui/material/TextField";

const RHFTextarea = ({ name, control, label, rows = 3, rules }) => (
  <Controller
    name={name}
    control={control}
    rules={rules}
    render={({ field, fieldState: { error } }) => (
      <TextField
        {...field}
        label={label}
        fullWidth
        variant="outlined"
        margin="normal"
        multiline
        rows={rows}
        error={!!error}
        helperText={error?.message}
        InputProps={{
          classes: {
            notchedOutline: "input-modern label-modern",
          },
        }}
      />
    )}
  />
);

export default RHFTextarea;

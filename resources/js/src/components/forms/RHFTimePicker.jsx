// src/components/forms/RHFTimePicker.js
import { Controller } from "react-hook-form";
import { TimePicker } from "@mui/x-date-pickers/TimePicker";
import { TextField } from "@mui/material";

const RHFTimePicker = ({ name, control, label, rules }) => (
  <Controller
    name={name}
    control={control}
    rules={rules}
    render={({ field, fieldState: { error } }) => (
      <TimePicker
        value={field.value || null}
        onChange={(newValue) => field.onChange(newValue)}
        label={label}
        slotProps={{
          textField: {
            fullWidth: true,
            variant: "outlined",
            margin: "normal",
            error: !!error,
            helperText: error?.message,
            InputProps: {
              classes: { notchedOutline: "input-modern label-modern" },
            },
          },
        }}
      />
    )}
  />
);

export default RHFTimePicker;

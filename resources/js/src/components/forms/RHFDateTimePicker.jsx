// src/components/forms/RHFDateTimePicker.js
import { Controller } from "react-hook-form";
import { DateTimePicker } from "@mui/x-date-pickers/DateTimePicker";

const RHFDateTimePicker = ({ name, control, label, rules }) => (
  <Controller
    name={name}
    control={control}
    rules={rules}
    render={({ field, fieldState: { error } }) => (
      <DateTimePicker
        value={field.value || null}
        onChange={(newValue) => field.onChange(newValue)}
        label={label}
        slotProps={{
          textField: {
            fullWidth: true,
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

export default RHFDateTimePicker;

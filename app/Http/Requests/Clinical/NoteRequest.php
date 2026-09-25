<?php

namespace App\Http\Requests\Clinical;

use App\Http\Requests\Rules;
use Illuminate\Foundation\Http\FormRequest;

/** A text note, a base64 audio note, or an image note (`image`). */
class NoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:10000', 'required_without_all:audio,image'],
            'audio' => ['nullable', 'string'],
            'image' => ['nullable', 'image', Rules::upload()],
            'date' => ['nullable', Rules::DATE],
            'time' => ['nullable', Rules::TIME],
        ];
    }
}

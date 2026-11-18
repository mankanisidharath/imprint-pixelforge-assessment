<?php

namespace App\Http\Requests;

use App\Features\Pixelforge\GenerationOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:'.GenerationOptions::MAX_PROMPT_LENGTH],
            'size' => ['required', Rule::in(GenerationOptions::SIZES)],
            'quality' => ['required', Rule::in(GenerationOptions::QUALITIES)],
            'reference_images' => ['nullable', 'array', 'max:'.GenerationOptions::MAX_REFERENCE_IMAGES],
            'reference_images.*' => ['required', 'url:http,https'],
        ];
    }
}

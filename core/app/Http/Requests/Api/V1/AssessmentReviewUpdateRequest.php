<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AssessmentReviewUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::contractRules();
    }

    /**
     * @return array<string, mixed>
     */
    public static function contractRules(): array
    {
        return [
            'result' => ['required', 'string'],
            'test_notes' => ['nullable', 'string', 'max:5000'],
            'conclusion' => ['nullable', 'string', 'max:5000'],
            'reviewed_on' => ['nullable', 'date'],
        ];
    }
}

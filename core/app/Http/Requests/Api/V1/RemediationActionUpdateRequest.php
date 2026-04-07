<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RemediationActionUpdateRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:140'],
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'due_on' => ['nullable', 'date'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}

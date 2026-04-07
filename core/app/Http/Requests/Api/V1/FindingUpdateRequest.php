<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class FindingUpdateRequest extends FormRequest
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
            'organization_id' => ['nullable', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:140'],
            'severity' => ['required', 'string'],
            'description' => ['required', 'string', 'max:1000'],
            'linked_control_id' => ['nullable', 'string', 'max:120'],
            'linked_risk_id' => ['nullable', 'string', 'max:120'],
            'due_on' => ['nullable', 'date'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RiskCreateRequest extends FormRequest
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
            'organization_id' => ['required', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:140'],
            'category' => ['required', 'string'],
            'inherent_score' => ['required', 'integer', 'min:0', 'max:100'],
            'residual_score' => ['required', 'integer', 'min:0', 'max:100'],
            'linked_asset_id' => ['nullable', 'string', 'max:120'],
            'linked_control_id' => ['nullable', 'string', 'max:120'],
            'treatment' => ['required', 'string', 'max:800'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}

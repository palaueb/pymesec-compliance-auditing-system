<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AssetUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'string'],
            'criticality' => ['required', 'string'],
            'classification' => ['required', 'string'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}

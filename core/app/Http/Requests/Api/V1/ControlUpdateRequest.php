<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ControlUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'framework_id' => ['nullable', 'string', 'max:64'],
            'framework' => ['nullable', 'string', 'max:80'],
            'domain' => ['required', 'string', 'max:80'],
            'evidence' => ['required', 'string', 'max:500'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AssessmentCreateRequest extends FormRequest
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
            'framework_id' => ['nullable', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:500'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'status' => ['nullable', 'string'],
            'control_ids' => ['nullable', 'array'],
            'control_ids.*' => ['string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}

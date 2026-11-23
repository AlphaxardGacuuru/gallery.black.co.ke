<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    public const PHONE_LOCKED_MESSAGE = 'Your phone number can\'t be changed here. Use "Request a change" to ask the support team.';

    protected function prepareForValidation(): void
    {
        $this->normalizePhoneInput();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // A locked number (see User::canChangePhone()) may only be sent
        // back unchanged, not cleared or replaced.
        return collect($this->profileRules($this->user()->id))
            ->when(
                ! $this->user()->canChangePhone(),
                fn ($rules) => $rules->put('phone', ['sometimes', 'required', Rule::in([$this->user()->phone])]),
            )
            ->all();
    }

    public function messages(): array
    {
        return [
            'phone.required' => self::PHONE_LOCKED_MESSAGE,
            'phone.in' => self::PHONE_LOCKED_MESSAGE,
        ];
    }
}

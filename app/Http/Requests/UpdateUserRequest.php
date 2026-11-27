<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\VerificationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhoneInput();
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $rules = [
            'name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                'unique:users,email,'.$id,
            ],
            'phone' => $this->phoneRules($id),
            'verified' => ['sometimes', 'boolean'],
            'reason' => [
                'nullable',
                'required_if_accepted:verified',
                Rule::enum(VerificationReason::class)
                    ->only(VerificationReason::adminChoices()),
            ],
        ];
        if ($this->filled('password')) {
            $rules['password'] = $this->passwordRules();
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'Another account already uses this phone number.',
        ];
    }
}

<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

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
        // A number can be changed but not cleared, since payouts need one.
        return collect($this->profileRules($this->user()->id))
            ->when(
                $this->user()->phone,
                fn ($rules) => $rules->put('phone', ['sometimes', 'required', ...collect($rules->get('phone'))->reject('nullable')->all()]),
            )
            ->all();
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Your M-Pesa phone number can\'t be removed, since prizes and payouts are sent to it. You can change it to a new one.',
        ];
    }
}

<?php

namespace App\Concerns;

use App\Models\User;
use App\Rules\SafaricomPhoneNumber;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function profileRules(int|string|null $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'phone' => $this->phoneRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function emailRules(int|string|null $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate a user's M-Pesa phone number.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function phoneRules(int|string|null $userId = null): array
    {
        return [
            'nullable',
            new SafaricomPhoneNumber,
            $userId === null
                ? Rule::unique(User::class, 'phone')
                : Rule::unique(User::class, 'phone')->ignore($userId),
        ];
    }

    /**
     * Rewrite a submitted phone number (07.., 254.., +254.., with spaces)
     * to the "07XXXXXXXX" format users are stored with, so the unique rule
     * compares like with like. Call from prepareForValidation().
     */
    protected function normalizePhoneInput(): void
    {
        $this->whenFilled('phone', fn (string $phone) => $this->merge([
            'phone' => SafaricomPhoneNumber::normalize($phone),
        ]));
    }
}

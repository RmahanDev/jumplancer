<?php

namespace App\Http\Requests\Concerns;

use App\Support\PersianText;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Shared normalization and rules for name / username / email / mobile fields, so accounts
 * created from sign-up, the profile page and the admin panel follow the same format.
 */
trait NormalizesAccountFields
{
    /**
     * Persian digits to Latin, lower-case username and email, mobile as "09xxxxxxxxx".
     */
    protected function normalizeAccountFields(): void
    {
        $phone = $this->string('phone')->trim()->toString();

        $this->merge(array_filter([
            'name' => $this->has('name') ? PersianText::normalize($this->string('name')->toString()) : null,
            'username' => $this->has('username') ? mb_strtolower(PersianText::toLatinDigits($this->string('username')->trim()->toString())) : null,
            'email' => $this->has('email') ? mb_strtolower($this->string('email')->trim()->toString()) : null,
        ], fn (?string $value): bool => $value !== null) + [
            'phone' => $phone === '' ? null : (PersianText::normalizeMobile($phone) ?? $phone),
        ]);
    }

    /**
     * @param  mixed  $ignore  The user being edited, if any.
     * @return array<string, array<int, mixed>>
     */
    protected function accountRules(mixed $ignore = null): array
    {
        $unique = fn (): Unique => Rule::unique('users')->ignore($ignore);

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z][a-z0-9._-]*$/', $unique()],
            'email' => ['required', 'string', 'email', 'max:150', $unique()],
            'phone' => ['nullable', 'string', 'regex:/^09\d{9}$/', $unique()],
        ];
    }
}

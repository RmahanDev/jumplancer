<?php

namespace App\Http\Requests\Wallet;

use App\Support\BankCard;
use App\Support\PersianText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A payout card: a valid card number, in the account holder's own name, registered with the
 * mobile number of the account. Staff check the details again before paying.
 */
class StoreBankCardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'card_number' => BankCard::normalize($this->input('card_number')),
            'holder_name' => PersianText::normalize($this->input('holder_name')),
            'phone' => PersianText::normalizeMobile($this->string('phone')->toString()) ?? $this->input('phone'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'card_number' => [
                'required',
                'digits:16',
                Rule::unique('bank_cards')->where('user_id', $this->user()->id),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! BankCard::isValid((string) $value)) {
                        $fail(__('This card number is not valid.'));
                    }
                },
            ],
            'holder_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'confirm_owner' => ['accepted'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if ($user->phone === null) {
                    $validator->errors()->add('phone', __('Add your mobile number in your profile first; the card must be registered with it.'));

                    return;
                }

                if (! $validator->errors()->has('phone') && $this->input('phone') !== $user->phone) {
                    $validator->errors()->add('phone', __('The card must be registered with the mobile number of your account (:phone).', ['phone' => $user->phone]));
                }

                if (! $validator->errors()->has('holder_name') && $this->input('holder_name') !== PersianText::normalize($user->name)) {
                    $validator->errors()->add('holder_name', __('The card must be in your own name (:name).', ['name' => $user->name]));
                }

                if ($user->bankCards()->count() >= 5) {
                    $validator->errors()->add('card_number', __('You can register up to five cards. Remove one first.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card_number.digits' => __('A card number has 16 digits.'),
            'card_number.unique' => __('This card is already registered.'),
            'phone.regex' => __('Enter a mobile number like 09123456789.'),
            'confirm_owner.accepted' => __('Confirm that the card belongs to you.'),
        ];
    }
}

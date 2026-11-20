<?php

namespace App\Http\Requests\Auth;

use App\Domain\Identity\Actions\SendEmailVerificationCodeAction;
use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $rawCode = $this->input('code');

        if (is_string($rawCode)) {
            $this->merge([
                'code' => preg_replace('/\s+/', '', trim($rawCode)) ?? '',
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $digits = SendEmailVerificationCodeAction::CODE_LENGTH;

        return [
            'code' => ['required', 'string', 'digits:'.$digits],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Please enter the 6-digit verification code sent to your email.',
            'code.digits' => 'The verification code must be a 6-digit number.',
        ];
    }
}

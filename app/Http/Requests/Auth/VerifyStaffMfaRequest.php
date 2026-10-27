<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyStaffMfaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Either a 6-digit TOTP code or an XXXXX-XXXXX recovery code.
            'code' => ['required', 'string', 'max:20'],
        ];
    }
}

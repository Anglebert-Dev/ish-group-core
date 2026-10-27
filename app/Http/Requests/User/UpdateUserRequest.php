<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'role' => ['nullable', 'integer', $this->roleRule()],
        ];
    }

    private function roleRule()
    {
        // Admin-role restrictions are enforced in UserService.
        return Rule::exists('roles', 'id')
            ->where('guard_name', 'staff')
            ->where('business_id', Auth::guard('staff')->user()?->business_id);
    }
}

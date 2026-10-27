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
            'role' => ['nullable', 'string', $this->roleRule()],
        ];
    }

    private function roleRule()
    {
        $acting = Auth::guard('staff')->user();

        return Rule::exists('roles', 'name')->where(function ($query) use ($acting) {
            $query->where('guard_name', 'staff');

            if (! $acting?->hasRole('SuperAdmin')) {
                $query->where('name', '!=', 'SuperAdmin');
            }
        });
    }
}

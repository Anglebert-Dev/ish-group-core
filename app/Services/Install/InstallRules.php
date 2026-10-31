<?php

namespace App\Services\Install;

use Illuminate\Validation\Rules\Password;

/**
 * Validation for each installer section, shared by the web wizard and
 * core:setup so both accept exactly the same input.
 */
class InstallRules
{
    public static function database(): array
    {
        return [
            'driver' => ['required', 'in:mysql,pgsql'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'username' => ['required', 'string', 'max:128'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function app(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:255'],
            'timezone' => ['required', 'timezone:all'],
            'queue_via_scheduler' => ['boolean'],
        ];
    }

    public static function mail(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'scheme' => ['nullable', 'in:smtp,smtps'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function admin(): array
    {
        return [
            'business' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }
}

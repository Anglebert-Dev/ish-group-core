<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Core Access Modules & Actions
    |--------------------------------------------------------------------------
    | Permission name format: "{action} {module}.{controller}"
    |
    | Examples:
    | "list users.user"
    | "update settings.setting"
    |
    | Only the core lives here. A code module adds its own permissions by
    | returning the same shape from its DataController::user_permissions()
    | hook; App\Services\Permission\AccessCatalogue merges both.
    |
    | A business's Admin#<id> role passes every check through Gate::before,
    | so it never needs permissions attached.
    */

    'access_modules' => [

        'dashboard' => [
            'dashboard',
        ],

        'users' => [
            'user',
            'role',
        ],

        'settings' => [
            'setting',
        ],

        'notifications' => [
            'notification',
        ],

        'activity' => [
            'activity_log',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Access Actions
    |--------------------------------------------------------------------------
    | Combined with every module/controller above (and every module's own).
    */

    'access_actions' => [
        'list',
        'read',
        'create',
        'update',
        'delete',
        'manage',
        'approve',
        'assign',
        'upload',
        'download',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extra Permissions
    |--------------------------------------------------------------------------
    | Permissions that do not follow "{action} {module}.{controller}".
    */

    'extra_permissions' => [
        'export lists',
    ],

    /*
    |--------------------------------------------------------------------------
    | Display Labels (Roles UI)
    |--------------------------------------------------------------------------
    | Missing keys fall back to Str::headline().
    */

    'module_labels' => [
        'dashboard' => 'Dashboard',
        'users' => 'Users & Roles',
        'settings' => 'Settings',
        'notifications' => 'Notifications',
        'activity' => 'Activity',
    ],

    'controller_labels' => [
        'dashboard' => 'Dashboard',
        'user' => 'Users',
        'role' => 'Roles',
        'setting' => 'Settings',
        'notification' => 'Notifications',
        'activity_log' => 'Audit Logs',
    ],

];

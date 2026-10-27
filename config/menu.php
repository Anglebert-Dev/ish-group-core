<?php

/*
|--------------------------------------------------------------------------
| Admin sidebar (core items)
|--------------------------------------------------------------------------
| Modules add items with DataController::modifyAdminMenu(), returning a list
| of arrays in the same shape. Item keys:
|
|   label       text, or a translation key such as "body.users"
|   route       named route
|   active      route pattern for highlighting (default: "<route>*")
|   permission  "list users.user" (pipe = OR); null = any signed-in staff
|   ability     platform ability instead, e.g. "superadmin"
|   group       sidebar section; null = top, no heading
|   order       sort within its group (default 50)
|   icon        SVG path data (24x24 outline)
|
| Groups render in the order below; unknown groups (from modules) render
| just before "Management", in alphabetical order. "Platform" only has
| superadmin items, so other users never see it.
*/

return [

    'groups' => [
        'Platform',
        null,
        'Management',
        'System',
    ],

    'items' => [
        [
            'label' => 'body.dashboard',
            'route' => 'dashboard',
            'permission' => null,
            'group' => null,
            'order' => 0,
            'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        ],
        [
            'label' => 'body.users',
            'route' => 'users.index',
            'active' => 'users.*',
            'permission' => 'list users.user',
            'group' => 'Management',
            'order' => 10,
            'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        ],
        [
            'label' => 'body.roles',
            'route' => 'roles.index',
            'active' => 'roles.*',
            'permission' => 'list users.role',
            'group' => 'Management',
            'order' => 20,
            'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        ],
        [
            'label' => 'body.overview',
            'route' => 'superadmin.dashboard',
            'active' => 'superadmin.dashboard',
            'ability' => 'superadmin',
            'group' => 'Platform',
            'order' => 10,
            'icon' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
        ],
        [
            'label' => 'body.businesses',
            'route' => 'superadmin.businesses.index',
            'active' => 'superadmin.businesses.*',
            'ability' => 'superadmin',
            'group' => 'Platform',
            'order' => 20,
            'icon' => 'M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h1m-1 4h1m-1 4h1',
        ],
        [
            'label' => 'body.modules',
            'route' => 'superadmin.modules.index',
            'active' => 'superadmin.modules.*',
            'ability' => 'superadmin',
            'group' => 'Platform',
            'order' => 30,
            'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        ],
        [
            'label' => 'body.platform_settings',
            'route' => 'superadmin.settings.index',
            'active' => 'superadmin.settings.*',
            'ability' => 'superadmin',
            'group' => 'Platform',
            'order' => 40,
            'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4',
        ],
        [
            'label' => 'body.notifications',
            'route' => 'notifications.index',
            'active' => 'notifications.*',
            'permission' => 'list notifications.notification',
            'group' => 'System',
            'order' => 10,
            'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        ],
        [
            'label' => 'body.settings',
            'route' => 'settings.index',
            'active' => 'settings.*',
            'permission' => 'list settings.setting',
            'group' => 'System',
            'order' => 20,
            'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
        ],
        [
            'label' => 'body.audit_logs',
            'route' => 'activity-logs.index',
            'active' => 'activity-logs.*',
            'permission' => 'list activity.activity_log',
            'group' => 'System',
            'order' => 30,
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        ],
        [
            'label' => 'body.profile',
            'route' => 'staff.profile',
            'active' => 'staff.profile*',
            'permission' => null,
            'group' => 'System',
            'order' => 40,
            'icon' => 'M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
    ],

];

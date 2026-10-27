<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Access Modules & Actions
    |--------------------------------------------------------------------------
    | Permission name format: "{action} {module}.{controller}"
    |
    | Examples:
    | "create fleet.vehicle"
    | "update bookings.booking"
    | "delete finance.invoice"
    |
    | Add modules/controllers here, then call:
    | PermissionService::initPermissions(true)
    |
    | SuperAdmin automatically receives all permissions.
    */

    'access_modules' => [

        /*
        |--------------------------------------------------------------------------
        | Platform & Administration
        |--------------------------------------------------------------------------
        */
        'dashboard' => [
            'dashboard',
        ],

        'institutions' => [
            'institution',
        ],

        'users' => [
            'user',
            'role',
        ],

        'settings' => [
            'setting',
        ],

        /*
        |--------------------------------------------------------------------------
        | Stations
        |--------------------------------------------------------------------------
        */
        'stations' => [
            'station',
        ],

        /*
        |--------------------------------------------------------------------------
        | Fleet Management
        |--------------------------------------------------------------------------
        */
        'fleet' => [
            'vehicle_class',
            'vehicle_category',
            'vehicle',
            'vehicle_owner',
            'vehicle_assignment',
            'vehicle_maintenance',
            'fuel_log',
            'vehicle_document',
        ],

        /*
        |--------------------------------------------------------------------------
        | Customers
        |--------------------------------------------------------------------------
        */
        'customers' => [
            'customer',
            'corporate_customer',
        ],

        /*
        |--------------------------------------------------------------------------
        | Drivers & Chauffeur Operations
        |--------------------------------------------------------------------------
        */
        'drivers' => [
            'driver',
            'chauffeur_job',
        ],

        /*
        |--------------------------------------------------------------------------
        | Bookings
        |--------------------------------------------------------------------------
        */
        'bookings' => [
            'booking',
            'booking_extra',
            'availability',
        ],

        /*
        |--------------------------------------------------------------------------
        | Rental Operations
        |--------------------------------------------------------------------------
        */
        'operations' => [
            'rental',
            'inspection',
        ],

        /*
        |--------------------------------------------------------------------------
        | Finance
        |--------------------------------------------------------------------------
        */
        'finance' => [
            'invoice',
            'payment',
            'expense',
            'supplier',
            'chart_of_account',
            'general_ledger',
            'financial_statement',
            'deposit',
            'payment_order',
        ],

        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */
        'reports' => [
            'report',
        ],

        /*
        |--------------------------------------------------------------------------
        | Supporting Modules
        |--------------------------------------------------------------------------
        */
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
    |
    | These actions are combined with every module/controller.
    |
    | Example:
    |
    | create fleet.vehicle
    | update fleet.vehicle
    | delete fleet.vehicle
    |
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

        'dispatch',

        'upload',

        'download',

    ],

    /*
    |--------------------------------------------------------------------------
    | Extra Permissions
    |--------------------------------------------------------------------------
    |
    | Permissions that do not follow the standard:
    |
    | "{action} {module}.{controller}"
    |
    */

    'extra_permissions' => [

        /*
        | Platform
        */
        'access all institutions',

        /*
        | Institutions
        */
        'manage institution users',

        /*
        | Fleet
        */
        'view all fleet vehicles',

        /*
        | Finance
        */
        'view financial reports',
        'approve payment orders',

        /*
        | Reports
        */
        'export reports',

    ],

    /*
    |--------------------------------------------------------------------------
    | Display Labels (Roles UI)
    |--------------------------------------------------------------------------
    |
    | Optional human labels for permission groups. Missing keys fall back to
    | Str::headline() in the Roles screens.
    |
    */

    'module_labels' => [
        'dashboard' => 'Dashboard',
        'institutions' => 'Institutions',
        'users' => 'Users & Roles',
        'settings' => 'Settings',
        'stations' => 'Stations',
        'fleet' => 'Fleet',
        'customers' => 'Customers',
        'drivers' => 'Drivers',
        'bookings' => 'Bookings',
        'operations' => 'Operations',
        'finance' => 'Finance',
        'reports' => 'Reports',
        'notifications' => 'Notifications',
        'activity' => 'Activity',
    ],

    'controller_labels' => [
        'dashboard' => 'Dashboard',
        'institution' => 'Institution',
        'user' => 'Users',
        'role' => 'Roles',
        'setting' => 'Settings',
        'station' => 'Stations',
        'vehicle_class' => 'Vehicle Classes',
        'vehicle_category' => 'Vehicle Categories',
        'vehicle' => 'Vehicles',
        'vehicle_owner' => 'Vehicle Owners',
        'vehicle_assignment' => 'Vehicle Assignments',
        'vehicle_maintenance' => 'Maintenance',
        'fuel_log' => 'Fuel Logs',
        'vehicle_document' => 'Vehicle Documents',
        'customer' => 'Customers',
        'corporate_customer' => 'Corporate Customers',
        'driver' => 'Drivers',
        'chauffeur_job' => 'Chauffeur Jobs',
        'booking' => 'Bookings',
        'booking_extra' => 'Extras',
        'availability' => 'Availability',
        'rental' => 'Rentals',
        'inspection' => 'Inspections',
        'invoice' => 'Invoices',
        'payment' => 'Payments',
        'expense' => 'Expenses',
        'supplier' => 'Suppliers',
        'chart_of_account' => 'Chart of Accounts',
        'general_ledger' => 'General Ledger',
        'financial_statement' => 'Financial Statements',
        'deposit' => 'Deposits',
        'payment_order' => 'Payment Orders',
        'report' => 'Reports',
        'notification' => 'Notifications',
        'activity_log' => 'Audit Logs',
    ],

];

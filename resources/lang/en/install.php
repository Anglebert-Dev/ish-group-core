<?php

// Installer (web /install and core:setup). docs/self-hosting-build-guide.md §5.1

return [

    'title' => 'Install',
    'subtitle' => 'Set up this server in a few steps.',
    'continue' => 'Continue',
    'back' => 'Back',
    'skip' => 'Skip',

    // Steps
    'step_code' => 'Install code',
    'step_requirements' => 'Server check',
    'step_database' => 'Database',
    'step_app' => 'Website',
    'step_mail' => 'Email',
    'step_admin' => 'Your account',
    'step_review' => 'Install',
    'step_env' => 'Saving settings',
    'step_migrate' => 'Creating tables',
    'step_platform' => 'Creating your account',
    'step_modules' => 'Installing modules',
    'step_site' => 'Linking to updates',
    'step_storage' => 'Preparing file storage',
    'step_finish' => 'Finishing',

    // Code
    'code_intro' => 'Enter the install code you received with this package.',
    'code_label' => 'Install code',
    'code_wrong' => 'That code is not right. Check the code you received with this package.',

    // Requirements
    'req_intro' => 'Everything marked "needed" must be green before you can continue.',
    'req_php' => 'PHP :version or newer',
    'req_extension' => 'PHP extension: :name',
    'req_db_driver' => 'MySQL or PostgreSQL support (pdo_mysql or pdo_pgsql)',
    'req_writable' => 'Can write to :path',
    'req_modules_writable_hint' => 'Needed to install module updates later.',
    'req_symlink' => 'Symbolic links allowed',
    'req_symlink_hint' => 'Without it, uploaded files are stored in public/storage instead.',
    'req_needed' => 'needed',
    'req_optional' => 'optional',
    'req_fix_first' => 'Fix the items marked "needed", then reload this page.',
    'recheck' => 'Check again',

    // Database
    'db_intro' => 'Create an empty database on your server first, then enter its details.',
    'db_driver' => 'Database type',
    'db_host' => 'Host',
    'db_port' => 'Port',
    'db_database' => 'Database name',
    'db_username' => 'Username',
    'db_password' => 'Password',
    'db_ok' => 'Connected.',
    'db_failed' => 'Could not connect: :error',
    'db_has_users' => 'This database already holds an installed copy. Use an empty database.',

    // App
    'app_intro' => 'How this website is reached and what it is called.',
    'app_name' => 'Name',
    'app_url' => 'Address (URL)',
    'app_url_hint' => 'The address people type to open this site, e.g. https://finance.example.com',
    'app_timezone' => 'Time zone',
    'app_queue_via_scheduler' => 'My hosting only has cron (shared hosting, no Supervisor)',
    'app_queue_via_scheduler_hint' => 'Emails are then sent by the every-minute cron job. Untick if your server runs "php artisan queue:work" permanently.',

    // Mail
    'mail_intro' => 'The email account used to send notifications and password resets.',
    'mail_host' => 'SMTP server',
    'mail_port' => 'Port',
    'mail_scheme' => 'Encryption',
    'mail_scheme_auto' => 'Automatic (STARTTLS, usually port 587)',
    'mail_scheme_smtps' => 'SSL (usually port 465)',
    'mail_username' => 'Username',
    'mail_password' => 'Password',
    'mail_from_address' => 'Send from (email)',
    'mail_from_address_hint' => 'Usually the same as the SMTP account.',
    'mail_from_name' => 'Send from (name)',
    'mail_test_to' => 'Send a test email to',
    'mail_send_test' => 'Send test',
    'mail_skip_hint' => 'You can skip this; emails are then only written to the log until you set it in .env.',
    'mail_test_subject' => 'Test email',
    'mail_test_body' => 'This is a test from the installer. Email works.',
    'mail_sent' => 'Test email sent to :email.',
    'mail_failed' => 'Could not send: :error',

    // Admin
    'admin_intro' => 'Your business and the first account. This account manages everything on this server.',
    'admin_business' => 'Business name',
    'admin_name' => 'Your full name',
    'admin_email' => 'Your email',
    'admin_password' => 'Password',
    'admin_password_hint' => 'At least 12 characters, with upper and lower case, a number and a symbol.',
    'admin_password_confirmation' => 'Repeat password',

    // Review / run
    'review_intro' => 'Ready. This creates the tables, your account and the modules below. It can take a minute.',
    'review_modules' => 'Modules',
    'review_no_modules' => 'None (add them later in Super Admin → Modules).',
    'install_now' => 'Install now',
    'retry' => 'Try again',
    'resuming' => 'Carrying on with the installation that stopped earlier.',

    // Finish
    'done' => 'Installed.',
    'finish_title' => 'All set',
    'finish_intro' => 'This server is installed. Two jobs keep it running:',
    'finish_cron' => 'Add this cron job (every minute). It runs daily tasks, alerts and update checks:',
    'finish_queue' => 'Keep this running with Supervisor or systemd so emails are sent:',
    'finish_queue_cron' => 'Emails are sent by the cron job above (shared hosting mode).',
    'finish_login' => 'Go to sign in',

    // Errors
    'already_installed' => 'This server is already installed.',
    'already_running' => 'An installation is already running. Wait a minute and try again.',
    'step_failed' => ':step failed: :error',
    'env_not_writable' => 'Could not write the .env file. Make it writable by the web server.',
    'env_value_newline' => 'Values cannot contain line breaks.',
    'session_expired' => 'Your session ended. Please start again.',

];

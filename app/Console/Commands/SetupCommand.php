<?php

namespace App\Console\Commands;

use App\Services\Install\DatabaseProbe;
use App\Services\Install\InstallationService;
use App\Services\Install\InstallRules;
use App\Services\Install\MailProbe;
use App\Services\Install\RequirementsChecker;
use App\Support\Installer\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Terminal twin of the web installer (docs/self-hosting-build-guide.md §5.1).
 * Asks for whatever isn't given as an option; with --no-interaction every
 * value must come from options.
 *
 *   php artisan core:setup
 *   php artisan core:setup --no-interaction --db-driver=pgsql --db-host=127.0.0.1 \
 *       --db-database=finance --db-username=finance --db-password=secret \
 *       --app-url=https://finance.example.com --business="Jane Doe" \
 *       --admin-name="Jane Doe" --admin-email=jane@example.com --admin-password='...'
 *   php artisan core:setup --skip-env ...   # Docker: environment set from outside
 */
class SetupCommand extends Command
{
    protected $signature = 'core:setup
        {--db-driver= : mysql or pgsql}
        {--db-host=}
        {--db-port=}
        {--db-database=}
        {--db-username=}
        {--db-password=}
        {--app-name=}
        {--app-url=}
        {--timezone=}
        {--queue-via-scheduler : Shared hosting: send queued emails from the cron job}
        {--mail-host= : Leave out to skip email (written to the log)}
        {--mail-port=}
        {--mail-scheme= : smtps for SSL (port 465); empty for STARTTLS}
        {--mail-username=}
        {--mail-password=}
        {--mail-from=}
        {--mail-from-name=}
        {--mail-test= : Send a test email to this address first}
        {--business= : Name of the first business}
        {--admin-name=}
        {--admin-email=}
        {--admin-password=}
        {--modules= : Comma-separated modules to install (default: from site.json)}
        {--skip-env : Do not write .env; use the environment as it is (Docker)}';

    protected $description = 'Install this copy from the terminal: database, email, first business and admin, modules';

    public function handle(InstallationService $installer, RequirementsChecker $requirements, DatabaseProbe $probe, MailProbe $mailProbe): int
    {
        if (Installer::isInstalled()) {
            $this->components->info(__('install.already_installed'));

            return self::SUCCESS;
        }

        foreach ($requirements->check() as $check) {
            if (! $check['ok']) {
                $check['required']
                    ? $this->components->error($check['label'])
                    : $this->components->warn($check['label'].' — '.$check['detail']);
            }
        }

        if (! $requirements->passes()) {
            return self::FAILURE;
        }

        $skipEnv = (bool) $this->option('skip-env');
        $data = ['skip_env' => $skipEnv];

        if (! $skipEnv) {
            $data['database'] = $this->section(InstallRules::database(), $this->askDatabase());

            if ($data['database'] === null) {
                return self::FAILURE;
            }

            $test = $probe->test($data['database']);

            if (! $test['ok'] && ! ($test['has_users'] && $installer->resuming())) {
                $this->components->error($test['message']);

                return self::FAILURE;
            }

            $this->components->info($test['ok'] ? $test['message'] : __('install.resuming'));

            $data['app'] = $this->section(InstallRules::app(), $this->askApp());
            $data['mail'] = $this->askMail();

            if ($data['app'] === null || $data['mail'] === false) {
                return self::FAILURE;
            }

            if ($data['mail'] && ($to = $this->option('mail-test'))) {
                $sent = $mailProbe->send($data['mail'], $to);
                $sent['ok'] ? $this->components->info($sent['message']) : $this->components->error($sent['message']);

                if (! $sent['ok']) {
                    return self::FAILURE;
                }
            }
        }

        $data['admin'] = $this->section(InstallRules::admin(), $this->askAdmin());

        if ($data['admin'] === null) {
            return self::FAILURE;
        }

        if ($modules = $this->option('modules')) {
            $data['modules'] = array_values(array_filter(array_map('trim', explode(',', $modules))));
        }

        $result = $installer->run($data, fn (string $step) => $this->components->task(__('install.step_'.$step)));

        if (! $result['status']) {
            $this->components->error($result['message']);

            return self::FAILURE;
        }

        $this->components->info(__('install.done').' '.__('install.finish_cron'));
        $this->line('  * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');

        if (! ($data['app']['queue_via_scheduler'] ?? false)) {
            $this->newLine();
            $this->line('  '.__('install.finish_queue'));
            $this->line('  cd '.base_path().' && php artisan queue:work --tries=3');
        }

        $this->newLine();
        $this->components->info(__('install.finish_login').': '.rtrim((string) config('app.url'), '/').'/app/login');

        return self::SUCCESS;
    }

    private function askDatabase(): array
    {
        $driver = $this->value('db-driver', fn () => select(__('install.db_driver'), ['mysql' => 'MySQL / MariaDB', 'pgsql' => 'PostgreSQL'], 'mysql'));

        return [
            'driver' => $driver,
            'host' => $this->value('db-host', fn () => text(__('install.db_host'), default: '127.0.0.1')),
            'port' => $this->value('db-port', fn () => text(__('install.db_port'), default: $driver === 'pgsql' ? '5432' : '3306'), $driver === 'pgsql' ? '5432' : '3306'),
            'database' => $this->value('db-database', fn () => text(__('install.db_database'), required: true)),
            'username' => $this->value('db-username', fn () => text(__('install.db_username'), required: true)),
            'password' => $this->value('db-password', fn () => password(__('install.db_password')), ''),
        ];
    }

    private function askApp(): array
    {
        return [
            'name' => $this->value('app-name', fn () => text(__('install.app_name'), default: (string) config('app.name')), (string) config('app.name')),
            'url' => $this->value('app-url', fn () => text(__('install.app_url'), required: true, hint: __('install.app_url_hint'))),
            'timezone' => $this->value('timezone', fn () => text(__('install.app_timezone'), default: (string) config('app.timezone')), (string) config('app.timezone')),
            'queue_via_scheduler' => $this->option('queue-via-scheduler')
                || ($this->input->isInteractive() && confirm(__('install.app_queue_via_scheduler'), default: false, hint: __('install.app_queue_via_scheduler_hint'))),
        ];
    }

    /**
     * @return array<string, mixed>|null|false  null = skipped, false = invalid
     */
    private function askMail(): array|null|false
    {
        $host = $this->option('mail-host');

        if (! $host && $this->input->isInteractive() && confirm(__('install.step_mail').'?', default: true, hint: __('install.mail_skip_hint'))) {
            $host = text(__('install.mail_host'), required: true);
        }

        if (! $host) {
            return null;
        }

        $mail = [
            'host' => $host,
            'port' => $this->value('mail-port', fn () => text(__('install.mail_port'), default: '587'), '587'),
            'scheme' => $this->value('mail-scheme', fn () => select(__('install.mail_scheme'), ['' => __('install.mail_scheme_auto'), 'smtps' => __('install.mail_scheme_smtps')]), '') ?: null,
            'username' => $this->value('mail-username', fn () => text(__('install.mail_username')), ''),
            'password' => $this->value('mail-password', fn () => password(__('install.mail_password')), ''),
            'from_address' => $this->value('mail-from', fn () => text(__('install.mail_from_address'), required: true, hint: __('install.mail_from_address_hint'))),
            'from_name' => $this->value('mail-from-name', fn () => text(__('install.mail_from_name')), ''),
        ];

        return $this->section(InstallRules::mail(), $mail) ?? false;
    }

    private function askAdmin(): array
    {
        return [
            'business' => $this->value('business', fn () => text(__('install.admin_business'), required: true)),
            'name' => $this->value('admin-name', fn () => text(__('install.admin_name'), required: true)),
            'email' => $this->value('admin-email', fn () => text(__('install.admin_email'), required: true)),
            'password' => $this->value('admin-password', fn () => password(__('install.admin_password'), required: true, hint: __('install.admin_password_hint'))),
        ];
    }

    /**
     * An option's value, else ask (interactive), else the default.
     */
    private function value(string $option, callable $ask, ?string $default = null): ?string
    {
        $given = $this->option($option);

        if ($given !== null && $given !== '') {
            return (string) $given;
        }

        return $this->input->isInteractive() ? (string) $ask() : $default;
    }

    /**
     * Validate one section; print the problems and return null when invalid.
     */
    private function section(array $rules, array $values): ?array
    {
        $validator = Validator::make($values, $rules);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return null;
        }

        return $validator->validated() + array_intersect_key($values, $rules);
    }
}

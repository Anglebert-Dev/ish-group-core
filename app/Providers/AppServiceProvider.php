<?php

namespace App\Providers;

use App\Contracts\Entitlements;
use App\Listeners\AuditAuthEvents;
use App\Models\Business\Business;
use App\Models\User;
use App\Support\Mail\BrandedMail;
use App\Services\Module\ModuleManager;
use App\Services\Notification\NotificationService;
use App\Services\Setting\SettingService;
use App\Support\Menu\AdminMenu;
use App\Support\Entitlements\Quota;
use App\Support\Entitlements\UnlimitedEntitlements;
use App\Support\Settings\BusinessSettings;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Abilities only a platform superadmin holds; a business Admin never does.
     */
    public const PLATFORM_ABILITIES = ['superadmin', 'manage_modules', 'manage_businesses'];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Per-request state (current tenant, memoised hooks/branding): scoped,
        // and flushed after every request so it never leaks into the next one
        // (long-running workers, or several requests in one test).
        $this->app->scoped(SettingService::class);
        $this->app->scoped(CurrentBusiness::class);
        $this->app->scoped(ModuleManager::class);
        $this->app->scoped(BusinessSettings::class);

        // A billing module rebinds Entitlements; Quota counters are global.
        $this->app->bindIf(Entitlements::class, UnlimitedEntitlements::class);
        $this->app->singleton(Quota::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set((string) config('app.timezone'));

        $this->registerGate();

        Event::listen(RequestHandled::class, fn () => $this->app->forgetScopedInstances());
        Event::subscribe(AuditAuthEvents::class);

        Password::defaults(function () {
            $rule = Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols();

            if (! $this->app->runningUnitTests()) {
                $rule = $rule->uncompromised();
            }

            return $rule;
        });

        RateLimiter::for('login', function (Request $request) {
            $login = strtolower((string) ($request->input('login') ?: $request->input('email')));

            return Limit::perMinute(5)->by($login.'|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(3)->by($email.'|'.$request->ip()),
                Limit::perMinute(5)->by($request->ip()),
            ];
        });

        // Reset links: a model from another broker (e.g. the Frontend module's
        // Customer) supplies its own URL via passwordResetUrl(); staff is the default.
        ResetPassword::createUrlUsing(function (object $user, string $token) {
            if (method_exists($user, 'passwordResetUrl')) {
                return $user->passwordResetUrl($token);
            }

            return url(route('staff.password.reset', [
                'token' => $token,
                'email' => $user instanceof User ? $user->getEmailForPasswordReset() : '',
            ], false));
        });

        // Reset emails are sent in the name of the user's business.
        ResetPassword::toMailUsing(function (object $user, string $token) {
            $business = $user->business ?? null;
            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return BrandedMail::make(
                $business instanceof Business ? $business : null,
                __('body.email_reset_subject'),
                [__('body.email_reset_line', ['name' => $user->name ?? ''])],
                __('body.email_reset_action'),
                call_user_func(ResetPassword::$createUrlCallback, $user, $token),
                [__('body.email_reset_expiry', ['minutes' => $minutes]), __('body.email_reset_ignore')],
            );
        });

        View::composer('*', function ($view) {
            $branding = app(SettingService::class)->current();
            $view->with('branding', $branding);
            $view->with('company', $branding->companyName());
            $view->with('appTimezone', config('app.timezone'));
        });

        View::composer('components.dashboard.sidebar', function ($view) {
            $view->with('menuGroups', app(AdminMenu::class)->visibleGroups());
        });

        View::composer('components.dashboard.header', function ($view) {
            $user = auth('staff')->user();
            $notifications = app(NotificationService::class);

            $view->with('headerNotifications', $notifications->recentForHeader($user));
            $view->with('headerUnreadCount', $notifications->unreadCount($user));
        });

        Blade::directive('datetime', function ($expression) {
            $expression = trim($expression) !== '' ? $expression : 'null';

            return "<?php echo \\App\\Libraries\\GeneralLibrary::formatDateTime($expression); ?>";
        });
    }

    /**
     * Two branches (reference doc §2):
     *  - platform abilities: only users flagged is_superadmin
     *  - everything else: true for the business's Admin#<id> role,
     *    otherwise fall through to spatie permissions
     */
    private function registerGate(): void
    {
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            if (in_array($ability, self::PLATFORM_ABILITIES, true)) {
                return $user->isSuperAdmin() ? true : false;
            }

            return $user->isBusinessAdmin() ? true : null;
        });
    }
}

<?php

namespace App\Providers;

use App\Services\Extra\ExtraService;
use App\Services\Notification\NotificationService;
use App\Services\Setting\SettingService;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set((string) config('app.timezone'));

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

        ResetPassword::createUrlUsing(function (object $user, string $token) {
            if ($user instanceof Customer) {
                return url(route('customer.password.reset', [
                    'token' => $token,
                    'email' => $user->getEmailForPasswordReset(),
                ], false));
            }

            return url(route('staff.password.reset', [
                'token' => $token,
                'email' => $user instanceof User ? $user->getEmailForPasswordReset() : '',
            ], false));
        });

        View::composer('*', function ($view) {
            $branding = app(SettingService::class)->current();
            $view->with('branding', $branding);
            $view->with('company', $branding->companyName());
            $view->with('appTimezone', config('app.timezone'));

            $chauffeurExtra = app(ExtraService::class)->chauffeurExtra();
            $view->with('chauffeurExtra', $chauffeurExtra);
            $view->with('chauffeurDailyFee', $chauffeurExtra?->price ?? '0.00');
        });

        View::composer('components.dashboard.header', function ($view) {
            $user = auth('staff')->user();
            $notifications = app(NotificationService::class);

            $view->with('headerNotifications', $notifications->recentForHeader($user));
            $view->with('headerUnreadCount', $notifications->unreadCount($user));
        });

        Blade::directive('kigali', function ($expression) {
            $expression = trim($expression) !== '' ? $expression : 'null';

            return "<?php echo \\App\\Libraries\\GeneralLibrary::formatDateTime($expression); ?>";
        });
    }
}

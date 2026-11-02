<?php

namespace Tests\Feature;

use App\Models\System\System;
use App\Services\Updates\UpdateClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Running behind a tunnel or proxy that ends HTTPS (e.g. ngrok): links and
 * secure cookies follow X-Forwarded-Proto only when TRUSTED_PROXIES says so.
 */
class TrustedProxiesTest extends TestCase
{
    public function test_forwarded_https_is_ignored_unless_proxies_are_trusted(): void
    {
        Route::get('/_proxy-probe', fn (Request $request) => $request->isSecure() ? 'https' : 'http');

        // Plain http on purpose: test requests otherwise follow APP_URL, which may be https.
        $this->get('http://localhost/_proxy-probe', ['X-Forwarded-Proto' => 'https'])->assertSee('http')->assertDontSee('https');

        $this->refreshWithProxies('*');
        Route::get('/_proxy-probe', fn (Request $request) => $request->isSecure() ? 'https' : 'http');
        $this->get('http://localhost/_proxy-probe', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'demo.ngrok-free.app'])->assertSee('https');
    }

    public function test_update_calls_skip_the_ngrok_warning_page(): void
    {
        $method = new \ReflectionMethod(UpdateClient::class, 'request');
        System::addProperty('distribution_token', Crypt::encryptString('t'));
        $request = $method->invoke(app(UpdateClient::class));

        $this->assertSame('1', $request->getOptions()['headers']['ngrok-skip-browser-warning']);
    }

    private function refreshWithProxies(string $value): void
    {
        putenv("TRUSTED_PROXIES={$value}");
        $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = $value;
        $this->refreshApplication();
        putenv('TRUSTED_PROXIES');
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
    }
}

<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\View\View;
use Modules\Billing\Models\Package;

/**
 * Public pricing page: packages anyone can buy, each linking to sign-up
 * with that package pre-selected.
 */
class PricingController extends Controller
{
    public function index(AuthService $auth): View
    {
        return view('billing::public.pricing', [
            'packages' => self::publicPackages(),
            'registrationOpen' => $auth->registrationIsOpen(),
        ]);
    }

    /**
     * Active, not private, not limited to specific businesses.
     */
    public static function publicPackages()
    {
        return Package::query()
            ->where('is_active', true)
            ->where('is_private', false)
            ->ordered()
            ->get()
            ->filter(fn (Package $package) => empty($package->business_ids))
            ->values();
    }
}

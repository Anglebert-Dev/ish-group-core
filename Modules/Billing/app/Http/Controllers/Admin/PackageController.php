<?php

namespace Modules\Billing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business\Business;
use App\Services\Module\ModuleManager;
use App\Support\Entitlements\Quota;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Http\Requests\SavePackageRequest;
use Modules\Billing\Models\Package;
use Modules\Billing\Models\Subscription;

class PackageController extends Controller
{
    public function index(): View
    {
        $counts = Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->selectRaw('package_id, count(*) as total')->groupBy('package_id')->pluck('total', 'package_id');

        return view('billing::admin.packages.index', [
            'packages' => Package::query()->ordered()->get(),
            'subscriberCounts' => $counts,
        ]);
    }

    public function create(ModuleManager $modules, Quota $quota): View
    {
        return view('billing::admin.packages.form', $this->formData(new Package([
            'interval' => 'month', 'interval_count' => 1, 'is_active' => true, 'sort_order' => Package::query()->max('sort_order') + 1,
        ]), $modules, $quota));
    }

    public function store(SavePackageRequest $request): RedirectResponse
    {
        Package::query()->create($request->packageData());

        return redirect()->route('superadmin.billing.packages.index')->with('success', __('billing::billing.package_saved'));
    }

    public function edit(Package $package, ModuleManager $modules, Quota $quota): View
    {
        return view('billing::admin.packages.form', $this->formData($package, $modules, $quota));
    }

    public function update(SavePackageRequest $request, Package $package): RedirectResponse
    {
        // Existing subscribers keep their frozen terms until they renew/change.
        $package->update($request->packageData());

        return redirect()->route('superadmin.billing.packages.index')->with('success', __('billing::billing.package_saved'));
    }

    public function destroy(Package $package): RedirectResponse
    {
        $inUse = Subscription::query()->where('package_id', $package->id)
            ->whereIn('status', array_map(fn ($s) => $s->value, SubscriptionStatus::current()))->exists();

        if ($inUse) {
            return back()->with('error', __('billing::billing.package_in_use'));
        }

        $package->delete();

        return redirect()->route('superadmin.billing.packages.index')->with('success', __('billing::billing.package_deleted'));
    }

    private function formData(Package $package, ModuleManager $modules, Quota $quota): array
    {
        return [
            'package' => $package,
            'modules' => $modules->catalogue()->where('active', true)->reject(fn ($m) => $modules->isBillingExempt($m['name']))->values(),
            'limitKeys' => $quota->keys(),
            'businesses' => Business::query()->orderBy('name')->pluck('name', 'id'),
        ];
    }
}

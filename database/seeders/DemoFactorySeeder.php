<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Business\Business;
use App\Models\Customer\Customer;
use App\Models\Role\Role;
use App\Models\User;
use App\Services\Business\BusinessSetupService;
use App\Services\Customer\CustomerService;
use App\Services\Module\ModuleManager;
use App\Services\Permission\PermissionService;
use App\Support\Settings\BusinessSettings;
use App\Support\Tenancy\CurrentBusiness;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Billing\Models\Invoice as BillingInvoice;
use Modules\Billing\Models\Package;
use Modules\Billing\Services\PaymentService;
use Modules\Billing\Services\SubscriptionService;
use Modules\Billing\Support\BillingSettings;
use Modules\Delivery\Services\DeliveryService;
use Modules\Frontend\Services\SiteService;
use Modules\GarmentPack\Models\Measurement;
use Modules\GarmentPack\Models\Style;
use Modules\GarmentPack\Models\Worker;
use Modules\GarmentPack\Services\StyleService;
use Modules\GarmentPack\Services\WorkshopService;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Variant;
use Modules\Inventory\Services\ItemService;
use Modules\Inventory\Services\StockService;
use Modules\Inventory\Services\StoreService;
use Modules\Invoicing\Services\InvoiceService;
use Modules\Production\Services\WorkOrderService;
use Modules\Purchasing\Models\Supplier;
use Modules\Purchasing\Services\PurchasingService;
use Modules\Quotations\Enums\SampleStatus;
use Modules\Quotations\Services\QuotationService;
use Modules\SalesOrders\Enums\OrderStatus;
use Modules\SalesOrders\Models\SalesOrder;
use Modules\SalesOrders\Services\OrderService;
use RuntimeException;

/**
 * A complete demo garment factory for testers:
 *
 *   php artisan db:seed --class=DemoFactorySeeder
 *
 * Everything goes through the real services (stage rules, stock checks,
 * numbering, audit), spread over the last five weeks so reports have
 * history. Run it once; it skips when the demo business already exists.
 *
 * Logins (password for all: Demo-Factory-2026!):
 *   owner@kigalisun.test    business admin
 *   sales@kigalisun.test    sales (orders, quotes, invoices, customers)
 *   floor@kigalisun.test    production supervisor
 *   store@kigalisun.test    store keeper (stock, purchasing)
 */
class DemoFactorySeeder extends Seeder
{
    public const PASSWORD = 'Demo-Factory-2026!';

    public const OWNER_EMAIL = 'owner@kigalisun.test';

    private const MODULES = ['Frontend', 'Billing', 'Inventory', 'SalesOrders', 'Production', 'Invoicing', 'Quotations', 'Delivery', 'Purchasing', 'GarmentPack', 'Reports'];

    private Business $business;

    private User $owner;

    private Store $main;

    private Store $shop;

    /** @var array<string, Variant> */
    private array $v = [];

    /** @var array<string, Customer> */
    private array $customers = [];

    public function run(): void
    {
        if (User::query()->where('email', self::OWNER_EMAIL)->exists()) {
            $this->command?->warn('Demo factory already seeded ('.self::OWNER_EMAIL.').');

            return;
        }

        $start = now();

        try {
            $this->modules();
            $this->platform();
            $this->day(35, fn () => $this->business());
            $this->day(34, fn () => $this->catalogue());
            $this->day(33, fn () => $this->purchasing());
            $this->day(30, fn () => $this->styles());
            $this->day(28, fn () => $this->quotations());
            $this->day(25, fn () => $this->orders());
            $this->production();
            $this->day(6, fn () => $this->invoicesAndDeliveries());
            $this->day(2, fn () => $this->extras());
        } finally {
            Carbon::setTestNow();
            Auth::forgetGuards();
        }

        $this->command?->info('Demo factory "Kigali Sun Garments" seeded in '.$start->diffInSeconds(now()).'s.');
        $this->command?->table(['Login', 'Role'], [
            [self::OWNER_EMAIL, 'Owner (admin)'],
            ['sales@kigalisun.test', 'Sales'],
            ['floor@kigalisun.test', 'Production supervisor'],
            ['store@kigalisun.test', 'Store keeper'],
        ]);
        $this->command?->info('Password for all: '.self::PASSWORD);
    }

    /**
     * Run $step as if it were $daysAgo days ago (so history has dates).
     */
    private function day(int $daysAgo, callable $step): void
    {
        Carbon::setTestNow(now()->startOfDay()->subDays($daysAgo)->setTime(9, 0));
        $step();
        Carbon::setTestNow();
    }

    private function ok(array $result): mixed
    {
        if (! $result['status']) {
            throw new RuntimeException('Demo seeding failed: '.$result['message']);
        }

        return $result['data'];
    }

    private function modules(): void
    {
        $modules = app(ModuleManager::class);

        foreach (self::MODULES as $name) {
            if (! $modules->isInstalled($name)) {
                $modules->install($name);
            } elseif (! $modules->isActive($name)) {
                $modules->toggle($name, true);
            }
        }

        app(PermissionService::class)->initPermissions(true);
    }

    /**
     * Billing: currency, packages the platform sells.
     */
    private function platform(): void
    {
        // Only fill settings the platform has not set yet.
        BillingSettings::set(array_filter(
            ['currency' => 'RWF', 'tax_rate' => '0', 'grace_days' => '7', 'reminder_days' => '7', 'due_days' => '7'],
            fn ($value, $key) => blank(BillingSettings::get($key)),
            ARRAY_FILTER_USE_BOTH,
        ));

        $packages = [
            ['name' => 'Website', 'price' => 15000, 'modules' => ['Frontend'], 'limits' => ['users' => 2], 'sort_order' => 1,
                'description' => 'A website for your business.', 'features' => ['Business website', 'Contact form']],
            ['name' => 'Shop', 'price' => 45000, 'modules' => ['Frontend', 'Inventory', 'Invoicing', 'Reports'], 'limits' => ['users' => 5, 'inventory.stores' => 2], 'sort_order' => 2,
                'description' => 'Stock, invoices and payments.', 'features' => ['Stock in 2 stores', 'Invoices and payments', 'Reports']],
            ['name' => 'Garment Factory', 'price' => 120000, 'modules' => self::MODULES, 'limits' => ['users' => 20, 'inventory.stores' => 5], 'sort_order' => 3, 'is_popular' => true, 'trial_days' => 0,
                'description' => 'Everything for a garment factory.', 'features' => ['Orders, quotes, deliveries', 'Production and quality control', 'Styles, costing, piece-rate pay', 'Purchasing and reports']],
        ];

        foreach ($packages as $data) {
            Package::query()->firstOrCreate(['name' => $data['name']], $data + [
                'interval' => 'month', 'interval_count' => 1, 'trial_days' => 14, 'is_active' => true,
            ]);
        }
    }

    /**
     * The client business: subscribed to "Garment Factory" and paid (approved by the superadmin).
     */
    private function business(): void
    {
        $result = app(BusinessSetupService::class)->createWithOwner(
            ['name' => 'Kigali Sun Garments', 'is_active' => true],
            ['name' => 'Claudine Uwase', 'email' => self::OWNER_EMAIL, 'password' => self::PASSWORD],
        );
        $this->business = $result['data']['business'];
        $this->owner = $result['data']['owner']->fresh();
        $this->business->forceFill(['brand_color' => '#C2410C', 'timezone' => 'Africa/Kigali'])->save();

        $package = Package::query()->where('name', 'Garment Factory')->firstOrFail();
        $subscriptions = app(SubscriptionService::class);
        $subscription = $subscriptions->current($this->business);
        if (! $subscription || $subscription->package_id !== $package->id) {
            $this->ok($subscriptions->subscribe($this->business, $package, null, true));
        }

        // Pay the first invoice the way a client does: proof submitted, superadmin approves.
        $invoice = BillingInvoice::query()->where('business_id', $this->business->id)->where('status', 'open')->latest('id')->first();
        $superadmin = User::query()->where('is_superadmin', true)->first();
        if ($invoice && $superadmin) {
            $payment = $this->ok(app(PaymentService::class)->submit($invoice->fresh(), $this->owner, [
                'amount' => $invoice->total, 'method' => 'mobile_money', 'reference' => 'MP2610001', 'paid_on' => now()->toDateString(),
            ]));
            if ($payment) {
                app(PaymentService::class)->approve($payment, $superadmin);
            }
        }

        app(CurrentBusiness::class)->set($this->business);
        Auth::shouldUse('staff');
        Auth::setUser($this->owner);

        $this->staff();
        $this->settings();
    }

    private function staff(): void
    {
        app(PermissionService::class)->initPermissions(true);
        $actions = fn (string $resource, array $verbs) => array_map(fn ($verb) => "{$verb} {$resource}", $verbs);
        $all = ['list', 'read', 'create', 'update', 'delete', 'approve'];

        $roles = [
            'Sales' => [
                ...$actions('customers.customer', ['list', 'read', 'create', 'update']),
                ...$actions('sales.order', $all), ...$actions('quotations.quote', $all), ...$actions('quotations.deposit', ['list', 'create']),
                ...$actions('invoicing.invoice', ['list', 'read', 'create']), ...$actions('invoicing.payment', ['list', 'create']),
                ...$actions('delivery.note', ['list', 'read', 'create']), ...$actions('inventory.item', ['list', 'read']),
            ],
            'Production supervisor' => [
                ...$actions('production.work_order', $all), ...$actions('production.entry', ['list', 'create']),
                ...$actions('garments.style', ['list', 'read']), ...$actions('garments.worker', ['list', 'read', 'create', 'update']),
                ...$actions('garments.cmt', ['list', 'read', 'create', 'update']), ...$actions('inventory.item', ['list', 'read']),
                ...$actions('inventory.movement', ['list', 'create']), ...$actions('sales.order', ['list', 'read']),
            ],
            'Store keeper' => [
                ...$actions('inventory.item', $all), ...$actions('inventory.store', ['list', 'read']), ...$actions('inventory.movement', ['list', 'create']),
                ...$actions('purchasing.supplier', ['list', 'read', 'create', 'update']), ...$actions('purchasing.order', ['list', 'read', 'create', 'update']),
                ...$actions('purchasing.receipt', ['list', 'create']), ...$actions('delivery.note', ['list', 'read', 'create']),
            ],
        ];
        $known = \Spatie\Permission\Models\Permission::query()->where('guard_name', 'staff')->pluck('name')->all();

        $people = ['Sales' => ['Eric Habimana', 'sales'], 'Production supervisor' => ['Diane Mukamana', 'floor'], 'Store keeper' => ['Patrick Niyonzima', 'store']];
        foreach ($roles as $name => $permissions) {
            $role = Role::query()->create(['name' => Role::scopedName($name, $this->business->id), 'guard_name' => 'staff', 'business_id' => $this->business->id]);
            $role->syncPermissions(array_values(array_intersect($permissions, $known)));

            [$fullName, $login] = $people[$name];
            $user = new User(['name' => $fullName, 'email' => "{$login}@kigalisun.test", 'password' => Hash::make(self::PASSWORD)]);
            $user->business_id = $this->business->id;
            $user->status = UserStatus::Active;
            $user->save();
            $user->assignRole($role);
        }
    }

    private function settings(): void
    {
        $settings = app(BusinessSettings::class);
        $settings->set('invoicing.tax_rate', 18);
        $settings->set('invoicing.tax_id', '102345678');
        $settings->set('invoicing.address', 'KK 15 Rd, Kicukiro, Kigali');
        $settings->set('invoicing.phone', '+250 788 300 400');
        $settings->set('invoicing.footer', "MoMo pay: 077 123 · Bank of Kigali 000400012345\nThank you for your business.");
        $settings->set('production.stages', ['Cutting', 'Sewing', 'Finishing', 'Packing']);

        // Public website with customer login on.
        $site = app(SiteService::class)->forBusiness($this->business);
        $site->forceFill([
            'is_published' => true,
            'content' => ['general' => ['allow_login' => true, 'allow_registration' => true], 'contact' => ['email' => 'hello@kigalisun.test', 'phone' => '+250 788 300 400', 'address' => 'Kicukiro, Kigali']],
        ])->save();
    }

    private function catalogue(): void
    {
        $stores = app(StoreService::class);
        $this->main = $stores->default($this->business);
        $this->main->forceFill(['name' => 'Factory store'])->save();
        $this->shop = $this->ok($stores->create(['name' => 'Kimironko shop', 'code' => 'KIM', 'address' => 'Kimironko market']));

        $items = app(ItemService::class);
        $add = function (array $data, array $variants) use ($items) {
            $item = $this->ok($items->create($data, $variants));
            foreach ($item->variants as $variant) {
                $this->v[$item->sku.($variant->optionLabel() ? ':'.$variant->optionLabel() : '')] = $variant;
            }

            return $item;
        };

        // Materials (opening stock goes to the factory store).
        $add(['type' => 'material', 'name' => 'Cotton poplin', 'sku' => 'FAB-COT', 'category' => 'Fabric', 'unit' => 'm', 'reorder_level' => 150, 'option1_name' => 'Colour'], [
            ['option1' => 'White', 'cost_price' => 2200, 'opening' => 260],
            ['option1' => 'Sky blue', 'cost_price' => 2300, 'opening' => 180],
        ]);
        $add(['type' => 'material', 'name' => 'Grey twill', 'sku' => 'FAB-TWL', 'category' => 'Fabric', 'unit' => 'm', 'reorder_level' => 100], [['cost_price' => 3100, 'opening' => 90]]);
        $add(['type' => 'material', 'name' => 'Buttons 11mm', 'sku' => 'TRM-BTN', 'category' => 'Trims', 'unit' => 'pcs', 'reorder_level' => 1000], [['cost_price' => 25, 'opening' => 3000]]);
        $add(['type' => 'material', 'name' => 'Thread', 'sku' => 'TRM-THR', 'category' => 'Trims', 'unit' => 'roll', 'reorder_level' => 20], [['cost_price' => 1500, 'opening' => 15]]);
        $add(['type' => 'material', 'name' => 'Zip 18cm', 'sku' => 'TRM-ZIP', 'category' => 'Trims', 'unit' => 'pcs', 'reorder_level' => 200], [['cost_price' => 150, 'opening' => 120]]);

        // Finished goods.
        $sizes = fn (array $prices, array $colours = [null]) => collect($prices)->flatMap(fn ($price, $size) => collect($colours)->map(fn ($colour) => [
            'option1' => $size, 'option2' => $colour, 'sale_price' => $price, 'cost_price' => (int) round($price * 0.62), 'reorder_level' => 10,
            'opening' => $colour === 'White' || $colour === null ? 12 : 0,
        ]))->values()->all();

        $add(['type' => 'finished', 'name' => 'School shirt', 'sku' => 'SHT', 'category' => 'Uniforms', 'unit' => 'pcs', 'option1_name' => 'Size', 'option2_name' => 'Colour'],
            $sizes(['S' => 5500, 'M' => 6000, 'L' => 6500, 'XL' => 7000], ['White', 'Sky blue']));
        $add(['type' => 'finished', 'name' => 'School trousers', 'sku' => 'TRS', 'category' => 'Uniforms', 'unit' => 'pcs', 'option1_name' => 'Size'],
            $sizes(['28' => 8000, '30' => 8500, '32' => 9000, '34' => 9500]));
        $add(['type' => 'finished', 'name' => 'Work overall', 'sku' => 'OVR', 'category' => 'Workwear', 'unit' => 'pcs', 'option1_name' => 'Size'],
            $sizes(['M' => 18000, 'L' => 19000, 'XL' => 20000]));
        $add(['type' => 'finished', 'name' => 'Branded T-shirt', 'sku' => 'TEE', 'category' => 'Promotional', 'unit' => 'pcs', 'option1_name' => 'Size'],
            $sizes(['M' => 4500, 'L' => 4500]));

        // Some stock in the shop.
        app(StockService::class)->transfer($this->v['SHT:M / White'], $this->main, $this->shop, 6, 'Shop display');

        // Customers.
        $customers = app(CustomerService::class);
        foreach ([
            ['key' => 'school', 'type' => 'company', 'name' => 'Bursar', 'company_name' => 'Green Hills Academy', 'contact_person' => 'Jean Bosco (Bursar)', 'phone' => '+250 788 111 222', 'email' => 'bursar@greenhills.test', 'tax_id' => '101987654', 'address' => 'Nyarutarama, Kigali', 'payment_terms_days' => 30],
            ['key' => 'hotel', 'type' => 'company', 'name' => 'Lake Hotel', 'company_name' => 'Lake Kivu Serena', 'contact_person' => 'Housekeeping', 'phone' => '+250 788 222 333', 'email' => 'housekeeping@lakehotel.test', 'address' => 'Rubavu', 'payment_terms_days' => 15],
            ['key' => 'construct', 'type' => 'company', 'name' => 'BuildRw', 'company_name' => 'BuildRw Construction Ltd', 'contact_person' => 'Site manager', 'phone' => '+250 788 333 444', 'address' => 'Gikondo', 'payment_terms_days' => 30],
            ['key' => 'grace', 'type' => 'person', 'name' => 'Grace Ingabire', 'phone' => '+250 788 444 555', 'email' => 'grace@demo.test'],
            ['key' => 'walkin', 'type' => 'person', 'name' => 'Walk-in customer'],
        ] as $data) {
            $key = $data['key'];
            unset($data['key']);
            $this->customers[$key] = $this->ok($customers->create($data));
        }
    }

    private function lines(array $spec): array
    {
        return collect($spec)->map(fn ($row) => [
            'variant' => $this->v[$row[0]],
            'description' => $row[3] ?? null,
            'quantity' => (float) $row[1],
            'unit_price' => (float) $row[2],
            'line_total' => round($row[1] * $row[2], 2),
        ])->all();
    }

    private function purchasing(): void
    {
        $purchasing = app(PurchasingService::class);
        $suppliers = [];
        foreach ([
            ['name' => 'Kigali Textiles Ltd', 'contact_person' => 'Alice', 'phone' => '+250 788 555 666', 'tax_id' => '100223344', 'payment_terms_days' => 30],
            ['name' => 'Trims & Buttons Co', 'contact_person' => 'Samuel', 'phone' => '+250 788 666 777'],
        ] as $data) {
            $supplier = new Supplier($data);
            $supplier->is_active = true;
            $supplier->save();
            $suppliers[] = $supplier;
        }

        // Fabric: ordered, received in two parts, part paid.
        $po = $this->ok($purchasing->createOrder(['supplier_id' => $suppliers[0]->id, 'store_id' => $this->main->id, 'order_date' => now()->toDateString(), 'expected_date' => now()->addDays(7)->toDateString()],
            $this->lines([['FAB-COT:White', 300, 2200], ['FAB-TWL', 150, 3100]])));
        $this->ok($purchasing->markOrdered($po));
        $lines = $po->lines()->get();
        $this->ok($purchasing->receive($po, [$lines[0]->id => 200, $lines[1]->id => 150], ['received_on' => now()->toDateString(), 'supplier_reference' => 'KT-DN-881']));
        Carbon::setTestNow(now()->addDays(5));
        $this->ok($purchasing->receive($po->fresh(), [$lines[0]->id => 100], ['received_on' => now()->toDateString(), 'supplier_reference' => 'KT-DN-902']));
        $this->ok($purchasing->pay($suppliers[0], ['amount' => 500000, 'paid_on' => now()->toDateString(), 'method' => 'bank', 'reference' => 'BK-77120']));

        // Trims: ordered, still awaiting goods.
        $po2 = $this->ok($purchasing->createOrder(['supplier_id' => $suppliers[1]->id, 'store_id' => $this->main->id, 'order_date' => now()->toDateString(), 'expected_date' => now()->addDays(10)->toDateString()],
            $this->lines([['TRM-ZIP', 500, 150], ['TRM-THR', 30, 1500]])));
        $this->ok($purchasing->markOrdered($po2));
    }

    private function styles(): void
    {
        $styles = app(StyleService::class);
        $bom = fn (array $rows) => collect($rows)->map(fn ($r) => ['variant' => $this->v[$r[0]], 'quantity' => $r[1], 'waste_percent' => $r[2], 'note' => $r[3] ?? null])->all();

        $this->ok($styles->save(new Style, [
            'code' => 'SH-01', 'name' => 'School shirt, short sleeve', 'item_id' => Item::query()->where('sku', 'SHT')->value('id'),
            'labour_cost' => 900, 'overhead_cost' => 300, 'is_active' => true,
            'piece_rates' => ['Cutting' => 80, 'Sewing' => 450, 'Finishing' => 150, 'Packing' => 50],
            'description' => 'Short sleeve, one chest pocket, school logo embroidered on pocket.',
        ], $bom([['FAB-COT:White', 1.2, 8, 'Body and sleeves'], ['TRM-BTN', 7, 3], ['TRM-THR', 0.02, 0]])));

        $this->ok($styles->save(new Style, [
            'code' => 'TR-01', 'name' => 'School trousers', 'item_id' => Item::query()->where('sku', 'TRS')->value('id'),
            'labour_cost' => 1200, 'overhead_cost' => 400, 'is_active' => true,
            'piece_rates' => ['Cutting' => 100, 'Sewing' => 600, 'Finishing' => 200, 'Packing' => 50],
        ], $bom([['FAB-TWL', 1.1, 10], ['TRM-ZIP', 1, 0], ['TRM-BTN', 1, 0], ['TRM-THR', 0.03, 0]])));

        foreach ([['Aline Uwimana', 'Line A'], ['Jean Paul Mugisha', 'Line A'], ['Solange Iradukunda', 'Line B'], ['Emmanuel Nkurunziza', 'Cutting']] as [$name, $line]) {
            $worker = new Worker(['name' => $name, 'line' => $line, 'phone' => '+250 78'.random_int(1000000, 9999999)]);
            $worker->is_active = true;
            $worker->save();
        }
    }

    private function quotations(): void
    {
        $quotes = app(QuotationService::class);

        // Hotel: quote with a deposit, converted to an order later.
        $quote = $this->ok($quotes->create([
            'customer_id' => $this->customers['hotel']->id, 'store_id' => $this->main->id, 'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(), 'follow_up_date' => now()->addDays(3)->toDateString(), 'discount' => 0,
        ], $this->lines([['TEE:M', 60, 4500, 'Hotel logo print'], ['TEE:L', 40, 4500, 'Hotel logo print']])));
        $this->ok($quotes->addDeposit($quote, ['paid_on' => now()->toDateString(), 'amount' => 150000, 'method' => 'mobile_money', 'reference' => 'MP2610188']));
        $this->ok($quotes->setStatus($quote, \Modules\Quotations\Enums\QuoteStatus::Accepted));
        $this->ok($quotes->convert($quote));

        // Construction company: sample sent and approved.
        $sample = $this->ok($quotes->create([
            'customer_id' => $this->customers['construct']->id, 'store_id' => $this->main->id, 'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(21)->toDateString(), 'follow_up_date' => now()->addDays(5)->toDateString(), 'is_sample' => true, 'discount' => 0,
        ], $this->lines([['OVR:L', 1, 0, 'Sample, reflective tape']])));
        $this->ok($quotes->setSampleStatus($sample, SampleStatus::Sent));
        $this->ok($quotes->setSampleStatus($sample->fresh(), SampleStatus::Approved));

        // Open quote waiting for follow-up (due today).
        $this->ok($quotes->create([
            'customer_id' => $this->customers['construct']->id, 'store_id' => $this->main->id, 'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(40)->toDateString(), 'follow_up_date' => now()->addDays(28)->toDateString(), 'discount' => 50000,
        ], $this->lines([['OVR:M', 40, 18000], ['OVR:L', 60, 19000], ['OVR:XL', 20, 20000]])));
    }

    private function orders(): void
    {
        $orders = app(OrderService::class);
        $base = ['store_id' => $this->main->id, 'order_date' => now()->toDateString(), 'discount' => 0];

        // School: big uniform order, made in production.
        $school = $this->ok($orders->create($base + ['customer_id' => $this->customers['school']->id, 'due_date' => now()->addDays(28)->toDateString(), 'customer_reference' => 'GHA-PO-2026-14'],
            $this->lines([['SHT:M / White', 80, 6000], ['SHT:L / White', 60, 6500], ['TRS:30', 50, 8500]])));
        $this->ok($orders->confirm($school));

        // Grace: small order, still pending (lines editable).
        $this->ok($orders->create($base + ['customer_id' => $this->customers['grace']->id, 'due_date' => now()->addDays(40)->toDateString()],
            $this->lines([['SHT:S / Sky blue', 2, 5500], ['TRS:28', 2, 8000]])));

        // Hotel order (from the quote) confirmed.
        $hotel = SalesOrder::query()->where('customer_id', $this->customers['hotel']->id)->firstOrFail();
        $this->ok($orders->confirm($hotel));

        // An overdue order (due a week ago).
        $late = $this->ok($orders->create($base + ['customer_id' => $this->customers['construct']->id, 'due_date' => now()->addDays(18)->toDateString()],
            $this->lines([['OVR:M', 5, 18000]])));
        $this->ok($orders->confirm($late));
    }

    private function production(): void
    {
        $workOrders = app(WorkOrderService::class);
        $workshop = app(WorkshopService::class);
        $school = SalesOrder::query()->where('customer_id', $this->customers['school']->id)->firstOrFail();
        $hotel = SalesOrder::query()->where('customer_id', $this->customers['hotel']->id)->firstOrFail();
        [$aline, $paul, $solange, $emmanuel] = Worker::query()->orderBy('id')->get()->all();

        $wo = [];
        $this->day(24, function () use (&$wo, $workOrders, $school, $hotel) {
            $make = fn (string $variant, float $qty, ?SalesOrder $order, string $line, float $target) => $this->ok($workOrders->create([
                'variant_id' => $this->v[$variant]->id, 'store_id' => $this->main->id, 'sales_order_id' => $order?->id,
                'quantity' => $qty, 'line' => $line, 'daily_target' => $target, 'start_date' => now()->toDateString(), 'due_date' => now()->addDays(20)->toDateString(),
            ]));
            $wo['shirt_m'] = $make('SHT:M / White', 80, $school, 'Line A', 25);
            $wo['shirt_l'] = $make('SHT:L / White', 60, $school, 'Line A', 25);
            $wo['trousers'] = $make('TRS:30', 50, $school, 'Line B', 15);
            $wo['tees'] = $make('TEE:M', 60, $hotel, 'Line B', 30);
        });

        // Materials from the bill of materials.
        $this->day(23, function () use ($wo) {
            foreach (['shirt_m', 'shirt_l', 'trousers'] as $key) {
                $style = Style::query()->where('item_id', $wo[$key]->variant->item_id)->first();
                $this->ok(app(StyleService::class)->issueRemaining($style, $wo[$key]->fresh(), $this->main));
            }
        });

        // Daily work over three weeks: piece work by workers, the rest as plain counts.
        $plan = [
            22 => [['shirt_m', 0, 80, $emmanuel], ['shirt_l', 0, 60, $emmanuel], ['trousers', 0, 50, $emmanuel]],
            20 => [['shirt_m', 1, 25, $aline], ['shirt_l', 1, 20, $paul], ['trousers', 1, 15, $solange]],
            18 => [['shirt_m', 1, 30, $aline], ['shirt_l', 1, 25, $paul], ['trousers', 1, 15, $solange], ['shirt_m', 2, 40, null]],
            15 => [['shirt_m', 1, 25, $paul], ['shirt_l', 1, 15, $aline], ['trousers', 1, 20, $solange], ['shirt_m', 2, 40, null], ['shirt_l', 2, 30, null]],
            12 => [['shirt_m', 3, 70, null], ['shirt_l', 2, 25, null], ['trousers', 2, 30, null]],
            9 => [['shirt_l', 3, 50, null], ['trousers', 2, 20, null]],
            7 => [['trousers', 3, 40, null], ['tees', 0, 60, null]],
        ];

        // Cut shirts go round the floor in bundles of 20.
        $plan[21] = [];
        ksort($plan);
        $plan = array_reverse($plan, true);

        foreach ($plan as $daysAgo => $entries) {
            if ($daysAgo === 21) {
                $this->day(21, fn () => $this->ok($workshop->makeBundles($wo['shirt_m']->fresh(), 20)));

                continue;
            }

            $this->day($daysAgo, function () use ($entries, $wo, $workOrders, $workshop) {
                foreach ($entries as [$key, $stage, $qty, $worker]) {
                    $order = $wo[$key]->fresh();
                    $worker
                        ? $this->ok($workshop->recordPieceWork($worker, $order, $stage, $qty))
                        : $this->ok($workOrders->record($order, $stage, $qty));
                }
            });
        }

        // Quality control: rejects that never reach stock.
        $this->day(12, fn () => $this->ok($workOrders->reject($wo['shirt_m']->fresh(), 5, 'Oil stains on the collar')));
        $this->day(9, fn () => $this->ok($workOrders->reject($wo['shirt_l']->fresh(), 3, 'Uneven hem')));
        $this->day(7, fn () => $this->ok($workOrders->reject($wo['trousers']->fresh(), 2, 'Broken zip')));
        $this->day(7, function () use ($wo) {
            // The last shirts (M) finish after the rejects: 80 − 70 − 5 = 5 more.
            $this->ok(app(WorkOrderService::class)->record($wo['shirt_m']->fresh(), 3, 5));
            $this->ok(app(WorkOrderService::class)->record($wo['shirt_l']->fresh(), 3, 2));
            $this->ok(app(WorkOrderService::class)->record($wo['trousers']->fresh(), 3, 8));
        });

        // T-shirt printing done by a subcontractor (CMT), part back.
        $this->day(6, function () use ($wo, $workshop) {
            $job = $this->ok($workshop->sendCmt($wo['tees']->fresh(), [
                'subcontractor' => 'Print House Remera', 'phone' => '+250 788 777 888', 'stage' => 1,
                'sent_quantity' => 60, 'rate' => 700, 'sent_on' => now()->toDateString(), 'due_on' => now()->addDays(7)->toDateString(),
            ]));
            Carbon::setTestNow(now()->addDays(3));
            $this->ok($workshop->returnCmt($job, 40));
        });

        // Pay the workers for the first two weeks.
        $this->day(10, function () use ($workshop) {
            foreach (Worker::query()->get() as $worker) {
                $workshop->markPaid($worker, now()->subDays(20)->toDateString(), now()->subDays(14)->toDateString());
            }
        });
    }

    private function invoicesAndDeliveries(): void
    {
        $invoices = app(InvoiceService::class);
        $orders = app(OrderService::class);
        $school = SalesOrder::query()->where('customer_id', $this->customers['school']->id)->firstOrFail();
        $late = SalesOrder::query()->where('customer_id', $this->customers['construct']->id)->first();

        // School order: delivered in two parts, then invoiced, part paid.
        $deliveries = app(DeliveryService::class);
        $lines = $school->lines()->get()->keyBy(fn ($l) => $l->variant_id);
        $this->ok($deliveries->create($school->fresh(), [
            $lines[$this->v['SHT:M / White']->id]->id => 60,
            $lines[$this->v['SHT:L / White']->id]->id => 40,
        ], ['delivered_on' => now()->toDateString(), 'receiver_name' => 'Jean Bosco', 'receiver_phone' => '+250 788 111 222', 'vehicle' => 'RAD 123 B']));

        Carbon::setTestNow(now()->addDays(2));
        $invoice = $this->ok($invoices->create([
            'customer_id' => $this->customers['school']->id, 'store_id' => $this->main->id, 'sales_order_id' => $school->id,
            'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'discount' => 0,
        ], $school->lines()->get()->map(fn ($l) => [
            'variant' => $l->variant, 'description' => null, 'quantity' => $l->quantity, 'unit_price' => (float) $l->unit_price, 'line_total' => (float) $l->line_total,
        ])->all()));
        $this->ok($invoices->addPayment($invoice, ['amount' => 600000, 'paid_on' => now()->toDateString(), 'method' => 'bank', 'reference' => 'BK-GHA-0091']));

        // Walk-in sales from the shop (direct invoices), paid in cash; one cancelled.
        foreach ([[['SHT:M / White', 2, 6000]], [['SHT:S / White', 1, 5500], ['TRS:28', 1, 8000]], [['SHT:M / White', 1, 6000]]] as $i => $spec) {
            $sale = $this->ok($invoices->create([
                'customer_id' => $this->customers['walkin']->id, 'store_id' => $i === 0 || $i === 2 ? $this->shop->id : $this->main->id,
                'invoice_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'discount' => 0,
            ], $this->lines($spec)));
            if ($i < 2) {
                $this->ok($invoices->addPayment($sale, ['amount' => (float) $sale->total, 'paid_on' => now()->toDateString(), 'method' => 'cash']));
            } else {
                $this->ok($invoices->cancel($sale, 'Customer changed size', true));
            }
        }

        // Overdue: the construction order is ready but not delivered or invoiced.
        if ($late) {
            $orders->progress($late, OrderStatus::InProduction);
        }
    }

    private function extras(): void
    {
        // Tailoring measurements for a private client.
        $measurement = new Measurement([
            'customer_id' => $this->customers['grace']->id, 'name' => 'Wedding dress',
            'values' => ['Chest' => '88', 'Waist' => '68', 'Hips' => '94', 'Shoulder' => '38', 'Length' => '152'],
            'taken_on' => now()->toDateString(), 'notes' => 'Fitting on Saturday',
        ]);
        $measurement->save();

        // A stock count correction and a damaged write-off.
        $stock = app(StockService::class);
        $stock->adjustTo($this->v['TRM-BTN'], $this->main, $stock->onHand($this->v['TRM-BTN'], $this->main) - 40, 'Monthly count');
        $stock->issue($this->v['FAB-COT:Sky blue'], $this->main, 6, 'Water damage');
    }
}

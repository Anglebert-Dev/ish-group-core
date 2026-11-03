<?php

namespace Modules\InjongePay\Jobs;

use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\MerchantService;

class RegisterMerchant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $walletId)
    {
    }

    public function handle(MerchantService $merchants): void
    {
        $wallet = Wallet::query()->withoutGlobalScopes()->find($this->walletId);

        if (! $wallet || $wallet->bank_status !== Wallet::BANK_NOT_REGISTERED) {
            return;
        }

        app(CurrentBusiness::class)->set($wallet->business_id);
        $merchants->register($wallet);
    }
}

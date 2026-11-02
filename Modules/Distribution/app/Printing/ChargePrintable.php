<?php

namespace Modules\Distribution\Printing;

use App\Models\Business\Business;
use App\Models\System\System;
use App\Support\Printing\Document;
use App\Support\Printing\Printable;
use Illuminate\Database\Eloquent\Model;
use Modules\Distribution\Enums\ChargeStatus;
use Modules\Distribution\Models\Charge;

/**
 * A licence or support invoice to a self-hosting customer, branded as us.
 */
class ChargePrintable extends Printable
{
    public function label(): string
    {
        return __('distribution::distribution.print_charge');
    }

    public function permission(): string
    {
        return 'read settings.setting';
    }

    /** Our own records: superadmins only, whatever their role says. */
    public function find(string $key): Model
    {
        abort_unless(auth('staff')->user()?->isSuperAdmin(), 404);

        return $this->lookup(Charge::class, $key, ['site']);
    }

    /**
     * @param  Charge  $charge
     */
    public function document(Model $charge): Document
    {
        $site = $charge->site;
        $period = $charge->period_until
            ? "\n".__('distribution::distribution.print_period', ['from' => $charge->period_from?->format('d M Y'), 'until' => $charge->period_until->format('d M Y')])
            : '';

        $document = Document::make('distribution_charge', __('distribution::distribution.print_charge'))
            ->number($charge->number)
            ->meta(__('distribution::distribution.print_issued'), $charge->issued_on->format('d M Y'))
            ->meta(__('distribution::distribution.print_due'), $charge->due_on?->format('d M Y'))
            ->meta(__('distribution::distribution.print_site'), $site->name)
            ->party(__('distribution::distribution.print_bill_to'), $site->customer_name ?: $site->name, array_values(array_filter([$site->contact_email, $site->app_url])))
            ->table(
                [[__('distribution::distribution.print_description')], [__('distribution::distribution.print_amount'), 'right']],
                [[$charge->description.$period, money($charge->amount)]],
            )
            ->total(__('distribution::distribution.print_total'), money($charge->amount), strong: true);

        if ($charge->status === ChargeStatus::Paid) {
            $document->total(__('distribution::distribution.print_paid_on', ['date' => $charge->paid_on?->format('d M Y')]), money($charge->amount))
                ->watermark(__('distribution::distribution.print_watermark_paid'));
        } elseif ($charge->status === ChargeStatus::Cancelled) {
            $document->watermark(__('distribution::distribution.print_watermark_cancelled'));
        } else {
            $document->withPayment();
        }

        return $document->notes(__('distribution::distribution.print_support_note'))->withSignature();
    }

    public function business(Model $record): Business
    {
        $id = (int) System::getProperty('primary_business_id');

        return ($id ? Business::query()->find($id) : null) ?? parent::business($record);
    }
}

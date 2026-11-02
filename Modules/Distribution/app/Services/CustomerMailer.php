<?php

namespace Modules\Distribution\Services;

use App\Models\Business\Business;
use App\Models\System\System;
use App\Services\BaseService;
use App\Services\Printing\PrintService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Distribution\Models\Build;
use Modules\Distribution\Models\Charge;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Notifications\CustomerEmail;
use Modules\Distribution\Printing\ChargePrintable;
use Modules\Distribution\Support\PlatformAudit;
use Modules\Distribution\Support\ServerDetails;

/**
 * The two emails a self-hosting customer gets (docs/self-hosting-build-guide.md §6):
 *
 *  1. "Prepare your server": the setup guide (link) and the server form (CSV attached).
 *  2. "Your package is ready": download link, install code, guide, steps.
 *
 * Links are signed, so the customer opens them without an account.
 */
class CustomerMailer extends BaseService
{
    /** How long guide and form links in emails work. */
    public const LINK_DAYS = 30;

    public function sendPreparation(Site $site, string $email): array
    {
        $replace = ['site' => $site->name, 'provider' => $this->provider()];

        $this->send($email, new CustomerEmail(
            $this->businessId(),
            __('distribution::distribution.email_prepare_subject', $replace),
            [
                __('distribution::distribution.email_hello', ['name' => $site->customer_name ?: '']),
                __('distribution::distribution.email_prepare_intro', $replace),
                __('distribution::distribution.email_prepare_form'),
                __('distribution::distribution.email_prepare_no_passwords'),
            ],
            __('distribution::distribution.email_open_guide'),
            $this->guideUrl($site),
            [__('distribution::distribution.email_reply_questions')],
            ['server-details-'.(Str::slug($site->name) ?: 'site').'.csv' => ServerDetails::csvTemplate((array) $site->server_details)],
        ));

        PlatformAudit::log('distribution_preparation_emailed', "Setup guide and form emailed to {$email} for site {$site->name}", $site, ['email' => $email]);

        return $this->success(null, __('distribution::distribution.email_prepare_sent', ['email' => $email]));
    }

    public function sendPackage(Build $build, string $email): array
    {
        if (! $build->isDownloadable()) {
            return $this->failure(__('distribution::distribution.email_build_not_ready'));
        }

        if (! $build->install_code) {
            return $this->failure(__('distribution::distribution.email_no_code'));
        }

        $site = $build->site;
        $details = (array) $site->server_details;
        $replace = [
            'site' => $site->name,
            'provider' => $this->provider(),
            'date' => $build->expires_at->format('d M Y'),
        ];

        $this->send($email, new CustomerEmail(
            $this->businessId(),
            __('distribution::distribution.email_package_subject', $replace),
            [
                __('distribution::distribution.email_hello', ['name' => $site->customer_name ?: '']),
                __('distribution::distribution.email_package_intro', $replace),
                __('distribution::distribution.email_package_code', ['code' => $build->install_code]),
                __('distribution::distribution.email_package_step_upload', ['folder' => $details['install_path'] ?? __('distribution::distribution.email_your_app_folder')]),
                __('distribution::distribution.email_package_step_open', ['url' => $details['app_url'] ?? $site->app_url ?? __('distribution::distribution.email_your_address')]),
                __('distribution::distribution.email_package_step_cron', ['cron' => ServerDetails::cronLine($details)]),
                __('distribution::distribution.email_package_guide', ['url' => $this->guideUrl($site)]),
            ],
            __('distribution::distribution.email_download'),
            URL::temporarySignedRoute('distribution.customer.download', $build->expires_at, ['build' => $build]),
            [
                __('distribution::distribution.email_package_expires', $replace),
                __('distribution::distribution.email_package_keep_code'),
                __('distribution::distribution.email_reply_questions'),
            ],
        ));

        $build->forceFill(['emailed_to' => $email, 'emailed_at' => now()])->save();
        PlatformAudit::log('distribution_package_emailed', "Zip and install code emailed to {$email} for site {$site->name}", $build, ['email' => $email, 'file' => $build->filename]);

        return $this->success(null, __('distribution::distribution.email_package_sent', ['email' => $email]));
    }

    public function sendLinkKey(Site $site, string $key, string $email): array
    {
        $updatesPage = ($site->app_url ?: ($site->server_details['app_url'] ?? null));

        $this->send($email, new CustomerEmail(
            $this->businessId(),
            __('distribution::distribution.email_link_subject', ['site' => $site->name]),
            [
                __('distribution::distribution.email_hello', ['name' => $site->customer_name ?: '']),
                __('distribution::distribution.email_link_intro', ['site' => $site->name, 'provider' => $this->provider()]),
                __('distribution::distribution.email_link_key', ['key' => $key]),
                __('distribution::distribution.email_link_steps'),
            ],
            $updatesPage ? __('distribution::distribution.email_link_open') : null,
            $updatesPage ? rtrim($updatesPage, '/').'/app/super-admin/updates' : null,
            [__('distribution::distribution.email_link_keep_key'), __('distribution::distribution.email_reply_questions')],
        ));

        PlatformAudit::log('distribution_link_key_emailed', "Link key emailed to {$email} for site {$site->name}", $site, ['email' => $email]);

        return $this->success(null, __('distribution::distribution.email_link_sent', ['email' => $email]));
    }

    /**
     * A licence or support invoice, with its PDF.
     */
    public function sendCharge(Charge $charge, string $email): array
    {
        $printable = new ChargePrintable;
        $pdf = app(PrintService::class)->pdfBytes($printable->document($charge), $printable->business($charge));
        $site = $charge->site;

        $this->send($email, new CustomerEmail(
            $this->businessId(),
            __('distribution::distribution.email_charge_subject', ['number' => $charge->number, 'provider' => $this->provider()]),
            [
                __('distribution::distribution.email_hello', ['name' => $site->customer_name ?: '']),
                __('distribution::distribution.email_charge_intro', ['number' => $charge->number, 'amount' => money($charge->amount), 'due' => $charge->due_on?->format('d M Y') ?? '—']),
                $charge->description,
            ],
            null,
            null,
            [__('distribution::distribution.email_charge_pay'), __('distribution::distribution.email_reply_questions')],
            [$charge->number.'.pdf' => $pdf],
        ));

        $charge->forceFill(['emailed_at' => now()])->saveQuietly();
        PlatformAudit::log('distribution_charge_emailed', "Charge {$charge->number} emailed to {$email}", $charge, ['email' => $email]);

        return $this->success(null, __('distribution::distribution.email_charge_sent', ['email' => $email]));
    }

    /**
     * Support is ending (or has ended): what that means and how to renew.
     */
    public function sendSupportReminder(Site $site, string $email, ?Charge $renewal = null): void
    {
        $date = $site->support_until->format('d M Y');
        $ended = ! $site->hasSupport();

        $this->send($email, new CustomerEmail(
            $this->businessId(),
            $ended
                ? __('distribution::distribution.email_support_ended_subject', ['site' => $site->name])
                : __('distribution::distribution.email_support_ending_subject', ['site' => $site->name, 'date' => $date]),
            array_values(array_filter([
                __('distribution::distribution.email_hello', ['name' => $site->customer_name ?: '']),
                $ended
                    ? __('distribution::distribution.email_support_ended', ['site' => $site->name, 'date' => $date])
                    : __('distribution::distribution.email_support_ending', ['site' => $site->name, 'date' => $date, 'days' => max(0, (int) $site->supportDaysLeft())]),
                __('distribution::distribution.email_support_keeps_working'),
                $renewal ? __('distribution::distribution.email_support_invoice', ['number' => $renewal->number, 'amount' => money($renewal->amount)]) : __('distribution::distribution.email_support_how'),
            ])),
            null,
            null,
            [__('distribution::distribution.email_reply_questions')],
            $renewal ? [$renewal->number.'.pdf' => app(PrintService::class)->pdfBytes((new ChargePrintable)->document($renewal), (new ChargePrintable)->business($renewal))] : [],
        ));
    }

    public function guideUrl(Site $site): string
    {
        return URL::temporarySignedRoute('distribution.customer.guide', now()->addDays(self::LINK_DAYS), ['site' => $site]);
    }

    private function send(string $email, CustomerEmail $message): void
    {
        Notification::route('mail', $email)->notify($message);
    }

    /** Our business: emails go out in its name, replies come back to it. */
    private function businessId(): ?int
    {
        return (int) System::getProperty('primary_business_id') ?: null;
    }

    private function provider(): string
    {
        $business = $this->businessId() ? Business::query()->find($this->businessId()) : null;

        return $business?->companyName() ?: (string) config('app.name');
    }
}

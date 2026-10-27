<?php

namespace App\Libraries;

use App\Enums\DocumentAlertLevel;
use Carbon\CarbonInterface;

class DocumentExpiryLibrary
{
    /** UI “expiring soon” window (Atlas yellow). */
    public const YELLOW_DAYS = 30;

    /** UI urgent window including expired (Atlas red). */
    public const RED_DAYS = 15;

    public static function daysUntil(?CarbonInterface $expiry): ?int
    {
        if (! $expiry) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false);
    }

    public static function level(?CarbonInterface $expiry): DocumentAlertLevel
    {
        $days = self::daysUntil($expiry);

        if ($days === null) {
            return DocumentAlertLevel::Unknown;
        }

        if ($days <= self::RED_DAYS) {
            return DocumentAlertLevel::Red;
        }

        if ($days <= self::YELLOW_DAYS) {
            return DocumentAlertLevel::Yellow;
        }

        return DocumentAlertLevel::Green;
    }

    public static function summary(?CarbonInterface $expiry): string
    {
        $days = self::daysUntil($expiry);
        $level = self::level($expiry);

        if ($days === null) {
            return $level->label();
        }

        if ($days < 0) {
            return __('body.document_expired_days_ago', ['days' => abs($days)]);
        }

        if ($days === 0) {
            return __('body.document_expires_today');
        }

        if ($level === DocumentAlertLevel::Red || $level === DocumentAlertLevel::Yellow) {
            return __('body.document_expires_in_days', ['days' => $days]);
        }

        return __('body.document_valid_until', ['date' => $expiry->format('d M Y')]);
    }
}

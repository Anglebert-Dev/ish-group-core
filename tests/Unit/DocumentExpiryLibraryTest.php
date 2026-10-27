<?php

namespace Tests\Unit;

use App\Enums\DocumentAlertLevel;
use App\Libraries\DocumentExpiryLibrary;
use Tests\TestCase;

class DocumentExpiryLibraryTest extends TestCase
{
    public function test_alert_levels_match_atlas_windows(): void
    {
        $this->assertSame(DocumentAlertLevel::Unknown, DocumentExpiryLibrary::level(null));
        $this->assertSame(DocumentAlertLevel::Green, DocumentExpiryLibrary::level(now()->addDays(45)));
        $this->assertSame(DocumentAlertLevel::Yellow, DocumentExpiryLibrary::level(now()->addDays(30)));
        $this->assertSame(DocumentAlertLevel::Yellow, DocumentExpiryLibrary::level(now()->addDays(16)));
        $this->assertSame(DocumentAlertLevel::Red, DocumentExpiryLibrary::level(now()->addDays(15)));
        $this->assertSame(DocumentAlertLevel::Red, DocumentExpiryLibrary::level(now()));
        $this->assertSame(DocumentAlertLevel::Red, DocumentExpiryLibrary::level(now()->subDay()));
    }

    public function test_summary_for_expired_and_expiring_documents(): void
    {
        $this->assertSame(__('body.document_alert_missing'), DocumentExpiryLibrary::summary(null));
        $this->assertSame(__('body.document_expires_today'), DocumentExpiryLibrary::summary(now()));
        $this->assertSame(
            __('body.document_expires_in_days', ['days' => 10]),
            DocumentExpiryLibrary::summary(now()->addDays(10))
        );
        $this->assertSame(
            __('body.document_expired_days_ago', ['days' => 3]),
            DocumentExpiryLibrary::summary(now()->subDays(3))
        );
    }
}

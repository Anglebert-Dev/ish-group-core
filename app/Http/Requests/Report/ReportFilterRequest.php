<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    /**
     * Allow-list of report slugs — also used to reject an unknown
     * `{report}` export segment with a 404 rather than silently
     * matching nothing.
     */
    public const REPORTS = [
        'bookings-summary',
        'fleet-performance',
        'driver-performance',
        'vehicle-owner-revenue',
        'compliance-status',
        'customer-activity',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    public function from(): ?string
    {
        return $this->validated('from');
    }

    public function to(): ?string
    {
        return $this->validated('to');
    }
}

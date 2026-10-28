<?php

namespace Modules\Billing\Http\Requests;

use App\Services\Module\ModuleManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\BillingInterval;

class SavePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
            'interval_count' => ['required', 'integer', 'min:1', 'max:120'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'max:100'],
            'limits' => ['nullable', 'array'],
            'limits.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'features' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['boolean'],
            'is_private' => ['boolean'],
            'once_per_business' => ['boolean'],
            'is_popular' => ['boolean'],
            'business_ids' => ['nullable', 'array'],
            'business_ids.*' => ['integer', 'exists:businesses,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'apply_to_subscribers' => ['nullable', 'in:none,terms,all'],
        ];
    }

    /**
     * Normalised attributes for the model.
     */
    public function packageData(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'interval' => $data['interval'],
            'interval_count' => (int) $data['interval_count'],
            'trial_days' => (int) ($data['trial_days'] ?? 0),
            // A module comes with the modules it requires.
            'modules' => app(ModuleManager::class)->withRequirements(array_values($data['modules'] ?? [])),
            'limits' => array_map('intval', array_filter($data['limits'] ?? [], fn ($v) => $v !== null && $v !== '')),
            'features' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['features'] ?? ''))))),
            'is_active' => $this->boolean('is_active'),
            'is_private' => $this->boolean('is_private'),
            'once_per_business' => $this->boolean('once_per_business'),
            'is_popular' => $this->boolean('is_popular'),
            'business_ids' => array_values(array_map('intval', $data['business_ids'] ?? [])) ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}

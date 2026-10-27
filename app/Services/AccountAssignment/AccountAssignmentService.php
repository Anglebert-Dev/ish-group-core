<?php

namespace App\Services\AccountAssignment;

use App\Libraries\ListExport;
use App\Models\AccountAssignment\AccountAssignment;
use App\Models\ChartOfAccount\ChartOfAccount;
use App\Repositories\AccountAssignment\AccountAssignmentRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class AccountAssignmentService extends BaseService
{
    public function __construct(protected AccountAssignmentRepository $assignments)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->assignments->query()
            ->with('account')
            ->orderBy('action')
            ->orderBy('slot')
            ->paginate(50);
    }

    public function exportList(): ListExport
    {
        $rows = $this->assignments->query()
            ->with('account')
            ->orderBy('action')
            ->orderBy('slot')
            ->get()
            ->map(fn (AccountAssignment $assignment) => [
                $assignment->action,
                $assignment->slot,
                $assignment->account?->label() ?? '—',
            ])
            ->all();

        return ListExport::make(__('body.account_assignments'), 'account-assignments', [
            __('body.action'),
            __('body.slot'),
            __('body.account'),
        ], $rows);
    }

    public function accountFor(string $action, string $slot): ?ChartOfAccount
    {
        if (! Schema::hasTable('account_assignments')) {
            return null;
        }

        $assignment = $this->assignments->findByActionSlot($action, $slot);

        return $assignment?->account;
    }

    public function requireAccount(string $action, string $slot): ChartOfAccount
    {
        $account = $this->accountFor($action, $slot);

        if (! $account) {
            throw new \RuntimeException(__('body.account_assignment_missing', [
                'action' => $action,
                'slot' => $slot,
            ]));
        }

        return $account;
    }

    public function update(AccountAssignment $assignment, array $data): array
    {
        $assignment->fill($data);
        $this->assignments->save($assignment);

        return $this->success($assignment->fresh('account'), __('body.account_assignment_updated'));
    }
}

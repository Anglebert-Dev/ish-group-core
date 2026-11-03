<?php

namespace Modules\InjongePay\Http\Controllers\Business;

use App\Helpers\AuthHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\Approval;
use Modules\InjongePay\Services\Approvals;
use Modules\InjongePay\Services\Executors;

class ApprovalController extends Controller
{
    public function index(Approvals $approvals): View
    {
        $approvals->expireOverdue();

        return view('injongepay::business.approvals.index', [
            'approvals' => Approval::query()->with(['wallet', 'requester'])->latest('id')->paginate(30),
        ]);
    }

    public function approve(Request $request, Approval $approval, Approvals $approvals, Executors $executors): RedirectResponse
    {
        abort_unless(AuthHelper::can('approve injongepay.'.$approval->operation), 403);
        $data = $request->validate(['pin' => ['required', 'string']]);

        try {
            $executors->run($approvals->approve($approval, $request->user('staff'), $data['pin']));
        } catch (InjongePayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('injongepay::injongepay.approved'));
    }

    public function reject(Request $request, Approval $approval, Approvals $approvals): RedirectResponse
    {
        abort_unless(AuthHelper::can('approve injongepay.'.$approval->operation), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $approvals->reject($approval, $request->user('staff'), $data['reason']);
        } catch (InjongePayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('injongepay::injongepay.rejected'));
    }
}

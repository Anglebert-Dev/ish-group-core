<?php

namespace Modules\InjongePay\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\System\System;
use App\Support\Health\BackgroundJobs as Health;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\InjongePay\Services\BackgroundJobs;

class BackgroundJobController extends Controller
{
    public function index(BackgroundJobs $jobs): View
    {
        $beat = System::getProperty(Health::HEARTBEAT);

        return view('injongepay::platform.background-jobs', [
            'jobs' => $jobs->all(),
            'problems' => Health::problems(),
            'schedulerSeen' => $beat ? Carbon::parse($beat) : null,
            'graceMinutes' => Health::GRACE_MINUTES,
            'logDays' => BackgroundJobs::logDays(),
            'minLogDays' => BackgroundJobs::MIN_LOG_DAYS,
        ]);
    }

    public function update(Request $request, BackgroundJobs $jobs): RedirectResponse
    {
        $rules = ['log_days' => ['required', 'integer', 'min:'.BackgroundJobs::MIN_LOG_DAYS, 'max:3650']];
        foreach (BackgroundJobs::JOBS as $key => $job) {
            $rules["jobs.{$key}.enabled"] = ['nullable', 'boolean'];
            $rules["jobs.{$key}.timing"] = isset($job['options'])
                ? ['required', Rule::in(array_map('strval', $job['options']))]
                : ['required', 'date_format:H:i'];
        }

        $data = $request->validate($rules);
        $jobs->update($data['jobs']);
        $jobs->setLogDays((int) $data['log_days']);

        return back()->with('success', __('injongepay::injongepay.background_jobs_saved'));
    }
}

<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\PlatformService;
use Illuminate\View\View;

class SuperAdminDashboardController extends Controller
{
    public function __construct(protected PlatformService $platform)
    {
    }

    public function index(): View
    {
        return view('app.super-admin.dashboard', $this->platform->overview());
    }
}

<?php

namespace App\Http\Controllers\Web\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard)
    {
    }

    public function index(): View
    {
        return view('app.dashboard.index', $this->dashboard->payload());
    }
}

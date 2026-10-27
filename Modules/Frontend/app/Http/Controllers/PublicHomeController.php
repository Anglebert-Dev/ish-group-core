<?php

namespace Modules\Frontend\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PublicHomeController extends Controller
{
    public function index(): View
    {
        return view('frontend::pages.home');
    }
}

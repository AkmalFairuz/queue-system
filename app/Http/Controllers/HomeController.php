<?php

namespace App\Http\Controllers;

use App\Services\DashboardDataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request, DashboardDataService $dashboardData): View
    {
        return view('home.index', [
            'data' => $dashboardData->forHome($request->user()),
        ]);
    }
}

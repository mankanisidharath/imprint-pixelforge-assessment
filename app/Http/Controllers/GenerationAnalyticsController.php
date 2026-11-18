<?php

namespace App\Http\Controllers;

use App\Features\GenerationAnalytics\GenerationAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationAnalyticsController extends Controller
{
    public function __invoke(Request $request, GenerationAnalytics $analytics): Response
    {
        return Inertia::render('generations/Analytics', [
            'analytics' => $analytics->forUser($request->user()),
        ]);
    }
}

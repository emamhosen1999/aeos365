<?php

declare(strict_types=1);

namespace Aero\Platform\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicPageController extends Controller
{
    public function home(Request $request): Response
    {
        return Inertia::render('Platform/Public/Home', [
            'title' => 'Home',
        ]);
    }

    public function pricing(Request $request): Response
    {
        return Inertia::render('Platform/Public/Pricing', [
            'title' => 'Pricing',
        ]);
    }

    public function features(Request $request): Response
    {
        return Inertia::render('Platform/Public/Features', [
            'title' => 'Features',
        ]);
    }

    public function enterprise(Request $request): Response
    {
        return Inertia::render('Platform/Public/Enterprise', [
            'title' => 'Enterprise',
        ]);
    }

    public function about(Request $request): Response
    {
        return Inertia::render('Platform/Public/About', [
            'title' => 'About',
        ]);
    }

    public function docs(Request $request): Response
    {
        return Inertia::render('Platform/Public/Docs', [
            'title' => 'Documentation',
        ]);
    }
}

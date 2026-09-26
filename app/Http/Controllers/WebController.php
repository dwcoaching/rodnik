<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Spring;
use App\Support\DuoUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class WebController extends Controller
{
    public function index(Request $request, DuoUrl $duoUrl): View|RedirectResponse
    {
        $resource = $duoUrl->resolve($request);
        $page = $resource['page'];

        $resourcePath = $duoUrl->relative($page, canonical: true);
        $legacyQuery = array_intersect(['page', 'view', 's', 'u', 'spring', 'spring_id', 'locating'], array_keys($request->query()));

        if ($request->getPathInfo() !== $resourcePath || $legacyQuery !== []) {
            return redirect($duoUrl->absolute($page, query: $request->query()), 301);
        }

        return view('duo', $resource);
    }

    public function legacySection(Request $request, DuoUrl $duoUrl, string $springId, string $section): RedirectResponse
    {
        $spring = Spring::query()->findOrFail($duoUrl->identifier($springId));

        return redirect(localized_route('springs.'.$section, [...$request->query(), 'spring' => $spring]), 301);
    }
}

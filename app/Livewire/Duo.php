<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Http\Controllers\WebController;
use App\Models\Spring;
use App\Models\User;
use App\Support\DuoUrl;
use App\Support\PageMetadata;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class Duo extends Component
{
    /** @var array{spring: ?int, user: ?int, location: ?int} */
    #[Locked]
    public array $page = [];

    public bool $firstRender = true;

    #[Locked]
    public bool $isSharedMap = false;

    #[Locked]
    public int $navigationRevision = 0;

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>|null  $sharedState
     */
    public function mount(array $page = [], ?array $sharedState = null): void
    {
        $this->page = array_merge(config('duo.url_defaults'), $page);

        if ($sharedState !== null) {
            $this->isSharedMap = true;
            $this->page = array_merge(config('duo.url_defaults'), $sharedState['page']);

            if ($this->page['spring'] && ! Spring::query()->whereKey($this->page['spring'])->exists()) {
                $this->page['spring'] = null;
            }

            if ($this->page['user'] && ! User::query()->whereKey($this->page['user'])->exists()) {
                $this->page['user'] = null;
            }
        }
    }

    /**
     * @return array{page: array{spring: ?int, user: ?int, location: ?int}, coordinates: array<int, float>, url: string, metadata: array<string, mixed>}
     */
    public function navigateTo(string $href, DuoUrl $duoUrl, Router $router, PageMetadata $metadata): array
    {
        abort_unless(str_starts_with($href, '/') && ! str_starts_with($href, '//') && ! str_contains($href, '\\'), 404);

        $request = Request::create(url('/').$href);
        $route = $router->getRoutes()->match($request);
        abort_unless($route->getActionName() === WebController::class.'@index', 404);
        $request->setRouteResolver(fn () => $route);

        $locale = $request->is('ru', 'ru/*') ? 'ru' : config('localization.default');
        abort_unless($locale === app()->getLocale(), 404);

        $resource = $duoUrl->resolve($request);
        $this->page = $resource['page'];
        $this->isSharedMap = false;
        $this->navigationRevision++;

        return [
            'page' => $this->page,
            'coordinates' => $resource['coordinates'],
            'url' => $duoUrl->relative($this->page, $locale, $request->query()),
            'metadata' => $metadata->forRequest($request),
        ];
    }

    public function render(): View
    {
        $coordinates = [];

        if ($this->page['spring']) {
            $spring = Spring::query()->findOrFail($this->page['spring']);
            $coordinates = [(float) $spring->longitude, (float) $spring->latitude];
        }

        return view('livewire.duo', compact('coordinates'));
    }
}

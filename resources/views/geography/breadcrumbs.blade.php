@php
    $crumbs = [['label' => __('geography.docs'), 'url' => url(localized_public_path('/docs'))]];
    if (isset($type)) {
        $crumbs[] = ['label' => __('geography.all_countries'), 'url' => localized_route('geography.countries.index')];
    }
    if (isset($country)) {
        $crumbs[] = ['label' => $country->displayName(), 'url' => $countryHasSources ? localized_route('geography.countries.show', ['country' => $country->slug]) : null];
    }
    if (isset($region)) {
        $crumbs[] = ['label' => $region->displayName(), 'url' => $regionHasSources ? localized_route('geography.regions.show', ['region' => $region->slug]) : null];
    }
@endphp
<nav aria-label="{{ __('geography.breadcrumbs') }}" class="text-sm text-zinc-600">
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
        @foreach ($crumbs as $crumb)
            <li class="flex items-center gap-2">
                @unless ($loop->first)
                    <span aria-hidden="true" class="text-zinc-400">›</span>
                @endunless
                @if ($crumb['url'])
                    <a href="{{ $crumb['url'] }}" data-rodnik-navigate class="text-blue-700 hover:underline">{{ $crumb['label'] }}</a>
                @else
                    <span>{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

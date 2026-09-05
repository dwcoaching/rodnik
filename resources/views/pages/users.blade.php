<?php

use App\Library\UserRanking;
use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('docs.users');

render(fn (View $view): View => $view->with('users', app(UserRanking::class)->paginate()));

?>

@extends('folio.index')

@section('title', __('pages.users.title').' — Rodnik.today')
@section('description', __('pages.users.description'))

@section('content')
    <div class="max-w-4xl">
        <h1 class="text-2xl font-black">{{ __('pages.users.title') }}</h1>
        <p class="mt-3 max-w-prose text-gray-600">{{ __('pages.users.description') }}</p>
        <p class="mt-2 text-sm text-gray-500">{{ __('pages.users.map_hint') }}</p>

        <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <td class="px-3 py-3"></td>
                        <th scope="col" class="px-3 py-3 text-left font-semibold">{{ __('pages.users.user') }}</th>
                        <th scope="col" aria-sort="descending" class="px-3 py-3 text-right font-semibold">{{ __('pages.users.reports') }} ↓</th>
                        <th scope="col" class="px-3 py-3 text-right font-semibold">{{ __('pages.users.springs') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        <tr class="hover:bg-blue-50/50">
                            <td class="px-3 py-4 text-right tabular-nums text-gray-500">{{ $users->firstItem() + $loop->index }}</td>
                            <th scope="row" class="px-3 py-4 text-left font-semibold">
                                <a href="{{ duo_route(['user' => $user->id]) }}" class="wrap-anywhere text-blue-600 hover:text-blue-800 hover:underline">{{ $user->name }}</a>
                            </th>
                            <td class="px-3 py-4 text-right font-semibold tabular-nums">{{ number_format($user->reports_count) }}</td>
                            <td class="px-3 py-4 text-right tabular-nums">{{ number_format($user->springs_count) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8 text-center text-gray-500">{{ __('pages.users.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="mt-6">{{ $users->links() }}</div>
        @endif
    </div>
@endsection

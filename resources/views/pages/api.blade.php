<?php

use function Laravel\Folio\name;

name('docs.api');

?>

{{-- Shared by /docs/api and /ru/docs/api; text lives in resources/lang/{en,ru}/api.php. --}}

@extends('folio.index')

@section('title', __('api.title').' — Rodnik.today')
@section('description', __('api.seo_description'))

@section('content')
    @php
        $apiBaseUrl = rtrim(config('app.url'), '/').'/api/v1';
        $siteUrl = rtrim(config('app.url'), '/');
        $endpoints = [
            ['POST', '/auth/register', 'no', '201'],
            ['POST', '/auth/token', 'no', '200'],
            ['DELETE', '/auth/token', 'required', '204'],
            ['GET', '/me', 'required', '200'],
            ['DELETE', '/me', 'required', '204'],
            ['GET', '/springs/{spring}', 'no', '200'],
            ['POST', '/springs', 'required', '201'],
            ['GET', '/users/{user}/reports', 'optional', '200'],
            ['POST', '/reports', 'required', '201'],
            ['PATCH', '/reports/{report}', 'owner', '200'],
            ['DELETE', '/reports/{report}', 'owner', '204'],
            ['POST', '/reports/{report}/photos', 'owner', '201'],
            ['DELETE', '/photos/{photo}', 'owner', '204'],
        ];
    @endphp

    <article class="prose max-w-4xl break-words prose-pre:max-w-full prose-pre:overflow-x-auto">
        <h1>{{ __('api.heading') }}</h1>
        <p>{{ __('api.lead') }}</p>

        <h2 id="contract">{{ __('api.contract.title') }}</h2>
        <ul>
            <li>{!! __('api.contract.base_url') !!} <code>{{ $apiBaseUrl }}</code></li>
            <li>{!! __('api.contract.envelope') !!}</li>
            <li>{!! __('api.contract.dates') !!}</li>
            <li>{!! __('api.contract.auth') !!}</li>
            <li>{!! __('api.contract.lifetime') !!}</li>
            <li>{!! __('api.contract.content_type') !!}</li>
            <li>{!! __('api.contract.cors') !!}</li>
            <li>{!! __('api.contract.license', ['about' => e(localized_public_path('/docs/about'))]) !!}</li>
        </ul>

        <h2 id="endpoints">{{ __('api.endpoints.title') }}</h2>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr><th>{{ __('api.endpoints.method') }}</th><th>{{ __('api.endpoints.path') }}</th><th>{{ __('api.endpoints.auth') }}</th><th>{{ __('api.endpoints.success') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($endpoints as [$method, $path, $auth, $status])
                        <tr><td><code>{{ $method }}</code></td><td><code>{{ $path }}</code></td><td>{{ __('api.endpoints.'.$auth) }}</td><td><code>{{ $status }}</code></td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <h2 id="rate-limits">{{ __('api.rate_limits.title') }}</h2>
        <p>{!! __('api.rate_limits.intro') !!}</p>
        <div class="overflow-x-auto">
            <table>
                <thead><tr><th>{{ __('api.rate_limits.endpoint') }}</th><th>{{ __('api.rate_limits.limit') }}</th></tr></thead>
                <tbody>
                    <tr><td>{{ __('api.rate_limits.general') }}</td><td>{{ __('api.rate_limits.general_limit') }}</td></tr>
                    <tr><td><code>POST /auth/token</code></td><td>{{ __('api.rate_limits.token_limit') }}</td></tr>
                    <tr><td><code>POST /auth/register</code></td><td>{{ __('api.rate_limits.register_limit') }}</td></tr>
                    <tr><td><code>POST /springs</code></td><td>{{ __('api.rate_limits.springs_limit') }}</td></tr>
                    <tr><td><code>POST /reports</code></td><td>{{ __('api.rate_limits.reports_limit') }}</td></tr>
                </tbody>
            </table>
        </div>

        <h2 id="recommended-save-workflow">{{ __('api.workflow.title') }}</h2>
        <p>{{ __('api.workflow.intro') }}</p>
        <ol>
            @foreach (__('api.workflow.steps') as $step)
                <li>{!! $step !!}</li>
            @endforeach
        </ol>

        <h3 id="client-state-model">{{ __('api.workflow.state_title') }}</h3>
        <div class="overflow-x-auto">
            <table>
                <thead><tr><th>{{ __('api.workflow.state') }}</th><th>{{ __('api.workflow.stored') }}</th><th>{{ __('api.workflow.next') }}</th></tr></thead>
                <tbody>
                    @foreach (__('api.workflow.states') as $state => [$stored, $next])
                        <tr><td><code>{{ $state }}</code></td><td>{{ $stored }}</td><td>{!! $next !!}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p>{!! __('api.workflow.state_note') !!}</p>

        <h3 id="partial-success">{{ __('api.workflow.recovery_title') }}</h3>
        <ul>
            @foreach (__('api.workflow.recovery') as $item)
                <li>{!! $item !!}</li>
            @endforeach
        </ul>
        <p>{!! __('api.workflow.idempotency') !!}</p>

        <h2 id="authentication">{{ __('api.auth.title') }}</h2>

        <h3 id="register">{{ __('api.auth.register_title') }}</h3>
        <pre><code>curl -X POST '{{ $apiBaseUrl }}/auth/register' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "Example User",
    "email": "user@example.com",
    "password": "example-password",
    "device_name": "Example phone",
    "locale": "en"
  }'</code></pre>
        <p>{!! __('api.auth.register_intro') !!}</p>
        <p>{!! __('api.auth.register_response') !!}</p>

        <h3 id="create-token">{{ __('api.auth.token_title') }}</h3>
        <pre><code>curl -X POST '{{ $apiBaseUrl }}/auth/token' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "user@example.com",
    "password": "example-password",
    "device_name": "Example phone"
  }'</code></pre>
        <pre><code>{
  "data": {
    "token": "1|plain-text-token-returned-once",
    "token_type": "Bearer",
    "user": {
      "id": 42,
      "name": "Example User",
      "email": "user@example.com",
      "locale": "en",
      "rating": 12,
      "profile_photo_url": "https://example.test/profile-photo.jpg"
    }
  }
}</code></pre>
        <p>{{ __('api.auth.token_storage') }}</p>

        <h3 id="current-user">{{ __('api.auth.me_title') }}</h3>
        <pre><code>curl '{{ $apiBaseUrl }}/me' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer TOKEN'</code></pre>
        <p>{!! __('api.auth.me_note') !!}</p>

        <h3 id="revoke-token">{{ __('api.auth.revoke_title') }}</h3>
        <pre><code>curl -X DELETE '{{ $apiBaseUrl }}/auth/token' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer TOKEN'</code></pre>
        <p>{{ __('api.auth.revoke_note') }}</p>

        <h3 id="delete-account">{{ __('api.auth.delete_title') }}</h3>
        <pre><code>curl -X DELETE '{{ $apiBaseUrl }}/me' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer TOKEN' \
  -d '{"password": "example-password"}'</code></pre>
        <p>{!! __('api.auth.delete_note') !!}</p>

        <h2 id="spring">{{ __('api.spring.title') }}</h2>

        <h3 id="get-spring">{{ __('api.spring.show_title') }}</h3>
        <pre><code>GET /springs/{spring}</code></pre>
        <p>{{ __('api.spring.show_intro') }}</p>
        <pre><code>{
  "data": {
    "id": 123,
    "name": "Forest spring",
    "type": "Spring",
    "latitude": 55.7558,
    "longitude": 37.6173,
    "intermittent": "no",
    "water_score": 1,
    "not_found": false,
    "reports_count": 1,
    "reports": [
      {
        "id": 456,
        "spring_id": 123,
        "visited_at": "2026-08-15",
        "state": "running",
        "quality": "good",
        "access_limited": false,
        "littered": false,
        "broken": false,
        "comment": "Clear and flowing.",
        "author": {
          "id": 42,
          "name": "Example User",
          "profile_photo_url": "https://example.test/profile-photo.jpg"
        },
        "created_at": "2026-08-15T10:00:00.000000Z",
        "updated_at": "2026-08-15T10:00:00.000000Z",
        "photos": [
          {
            "id": 789,
            "url": "https://example.test/photos/789.jpg",
            "width": 1280,
            "height": 960
          }
        ]
      }
    ]
  }
}</code></pre>
        <p>{!! __('api.spring.enums') !!}</p>
        <p>{!! __('api.spring.hidden') !!}</p>

        <h3 id="create-spring">{{ __('api.spring.create_title') }}</h3>
        <pre><code>curl -X POST '{{ $apiBaseUrl }}/springs' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer TOKEN' \
  -d '{
    "latitude": 55.7558,
    "longitude": 37.6173,
    "name": "Forest spring",
    "type": "Spring"
  }'</code></pre>
        <p>{!! __('api.spring.create_intro') !!} {!! collect(\App\Models\Spring::TYPES)->map(fn (string $type): string => '<code>'.e($type).'</code>')->implode(', ') !!}.</p>
        <p>{!! __('api.spring.create_response') !!}</p>
        <p>{{ __('api.spring.create_duplicates') }}</p>

        <h3 id="map-discovery">{{ __('api.spring.map_title') }}</h3>
        <p>{!! __('api.spring.map', ['tiles' => e($siteUrl.'/tiles/{z}/{x}/{y}.json')]) !!}</p>

        <h2 id="reports">{{ __('api.reports.title') }}</h2>

        <h3 id="user-reports">{{ __('api.reports.list_title') }}</h3>
        <pre><code>curl '{{ $apiBaseUrl }}/users/42/reports?page=1' \
  -H 'Accept: application/json'</code></pre>
        <pre><code>{
  "data": [
    {
      "id": 456,
      "spring_id": 123,
      "spring": {
        "id": 123,
        "name": "Forest spring",
        "type": "Spring",
        "latitude": 55.7558,
        "longitude": 37.6173
      },
      "visited_at": "2026-08-15",
      "state": "running",
      "quality": "good",
      "...": "the same report fields as above",
      "photos": []
    }
  ],
  "links": {
    "first": "{{ $apiBaseUrl }}/users/42/reports?page=1",
    "last": "{{ $apiBaseUrl }}/users/42/reports?page=3",
    "prev": null,
    "next": "{{ $apiBaseUrl }}/users/42/reports?page=2"
  },
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 50,
    "total": 128
  }
}</code></pre>
        <p>{!! __('api.reports.list_intro') !!}</p>
        <p>{!! __('api.reports.list_hidden') !!}</p>

        <h3 id="create-report">{{ __('api.reports.create_title') }}</h3>
        <pre><code>curl -X POST '{{ $apiBaseUrl }}/reports' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer TOKEN' \
  -d '{
    "spring_id": 123,
    "visited_at": "2026-08-15",
    "timezone": "Europe/Moscow",
    "state": "running",
    "quality": "good",
    "access_limited": false,
    "littered": false,
    "broken": false,
    "comment": "Clear and flowing."
  }'</code></pre>
        <p>{!! __('api.reports.create_response') !!}</p>

        <h3 id="update-report">{{ __('api.reports.update_title') }}</h3>
        <pre><code>PATCH /reports/{report}</code></pre>
        <p>{!! __('api.reports.update') !!}</p>

        <h3 id="delete-report">{{ __('api.reports.delete_title') }}</h3>
        <pre><code>DELETE /reports/{report}</code></pre>
        <p>{!! __('api.reports.delete') !!}</p>

        <h3 id="normalization">{{ __('api.reports.normalization_title') }}</h3>
        <ul>
            @foreach (__('api.reports.normalization') as $item)
                <li>{!! $item !!}</li>
            @endforeach
        </ul>

        <h2 id="photos">{{ __('api.photos.title') }}</h2>
        <p>{!! __('api.photos.intro') !!}</p>
        <pre><code>curl -X POST '{{ $apiBaseUrl }}/reports/456/photos' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer TOKEN' \
  -F 'photo=@spring.jpg' \
  -F 'latitude=55.7558' \
  -F 'longitude=37.6173'</code></pre>
        <p>{!! __('api.photos.upload') !!}</p>
        <pre><code>DELETE /photos/{photo}</code></pre>
        <p>{{ __('api.photos.delete') }}</p>

        <h2 id="errors">{{ __('api.errors.title') }}</h2>
        <div class="overflow-x-auto">
            <table>
                <thead><tr><th>{{ __('api.errors.status') }}</th><th>{{ __('api.errors.meaning') }}</th></tr></thead>
                <tbody>
                    @foreach (__('api.errors.codes') as $code => $meaning)
                        <tr><td><code>{{ $code }}</code></td><td>{{ $meaning }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <pre><code>{
  "message": "The given data was invalid.",
  "errors": {
    "visited_at": ["The visit date cannot be in the future."]
  }
}</code></pre>
        <p>{{ __('api.errors.note') }}</p>

        <h2 id="client-best-practices">{{ __('api.practices.title') }}</h2>
        <ul>
            @foreach (__('api.practices.items') as $item)
                <li>{!! str_replace(':exports', e(localized_public_path('/docs/exports')), $item) !!}</li>
            @endforeach
        </ul>
    </article>
@endsection

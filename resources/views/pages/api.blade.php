<?php

use function Laravel\Folio\{middleware, name};

middleware(['auth']);
name('docs.api');

?>

@extends('folio.index')

@section('title', 'Mobile API — Rodnik.today')
@section('description', 'Rodnik.today mobile API v1 contract, authentication, resources, examples, and errors.')

@section('content')
    @php($apiBaseUrl = rtrim(config('app.url'), '/').'/api/v1')

    <article class="prose max-w-4xl break-words prose-pre:max-w-full prose-pre:overflow-x-auto">
        <h1>Rodnik.today Mobile API v1</h1>
        <p>
            This page is the authoritative contract for the first-party Rodnik.today mobile application.
            The API uses JSON, Laravel API Resources, and Sanctum bearer tokens.
        </p>

        <h2 id="contract">Contract summary</h2>
        <ul>
            <li><strong>Base URL:</strong> <code>{{ $apiBaseUrl }}</code></li>
            <li><strong>Success envelope:</strong> a top-level <code>data</code> property, except for <code>204 No Content</code>.</li>
            <li><strong>Dates:</strong> <code>YYYY-MM-DD</code>. Timestamps use ISO 8601 in UTC.</li>
            <li><strong>Authentication:</strong> <code>Authorization: Bearer TOKEN</code>.</li>
            <li><strong>Token lifetime:</strong> tokens do not expire automatically; logout revokes the current token.</li>
            <li><strong>Content type:</strong> send <code>Accept: application/json</code>; send <code>Content-Type: application/json</code> for JSON bodies.</li>
        </ul>

        <h2 id="endpoints">Endpoints</h2>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr><th>Method</th><th>Path</th><th>Authentication</th><th>Success</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>POST</code></td><td><code>/auth/token</code></td><td>No</td><td><code>200</code></td></tr>
                    <tr><td><code>DELETE</code></td><td><code>/auth/token</code></td><td>Required</td><td><code>204</code></td></tr>
                    <tr><td><code>GET</code></td><td><code>/me</code></td><td>Required</td><td><code>200</code></td></tr>
                    <tr><td><code>GET</code></td><td><code>/springs/{spring}</code></td><td>No</td><td><code>200</code></td></tr>
                    <tr><td><code>POST</code></td><td><code>/reports</code></td><td>Required</td><td><code>201</code></td></tr>
                    <tr><td><code>PATCH</code></td><td><code>/reports/{report}</code></td><td>Owner</td><td><code>200</code></td></tr>
                    <tr><td><code>DELETE</code></td><td><code>/reports/{report}</code></td><td>Owner</td><td><code>204</code></td></tr>
                    <tr><td><code>POST</code></td><td><code>/reports/{report}/photos</code></td><td>Owner</td><td><code>201</code></td></tr>
                    <tr><td><code>DELETE</code></td><td><code>/photos/{photo}</code></td><td>Owner</td><td><code>204</code></td></tr>
                </tbody>
            </table>
        </div>

        <h2 id="authentication">Authentication</h2>
        <h3 id="create-token">Create a token</h3>
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
        <p>Store the token in the operating system Keychain or Keystore. Never log it or place it in a URL.</p>

        <h3 id="current-user">Current user</h3>
        <pre><code>curl '{{ $apiBaseUrl }}/me' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer TOKEN'</code></pre>
        <p>The response is the same user object under <code>data</code>.</p>

        <h3 id="revoke-token">Revoke the current token</h3>
        <pre><code>curl -X DELETE '{{ $apiBaseUrl }}/auth/token' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer TOKEN'</code></pre>
        <p>This revokes only the token used by the request. Other devices remain signed in.</p>

        <h2 id="spring">Get a spring</h2>
        <pre><code>GET /springs/{spring}</code></pre>
        <p>
            This public endpoint returns the spring, all visible user reports, and each report's photos in one request.
            Hidden and OpenStreetMap-imported reports are omitted. Reports are newest first by visit date; photos use their saved order.
        </p>
        <pre><code>{
  "data": {
    "id": 123,
    "name": "Forest spring",
    "type": "Spring",
    "latitude": 55.7558,
    "longitude": 37.6173,
    "intermittent": "no",
    "water_score": 1,
    "water_confirmed": true,
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
        <p>
            <code>state</code> is <code>running</code>, <code>dripping</code>, <code>dry</code>, <code>notfound</code>, or <code>null</code>.
            <code>quality</code> is <code>good</code>, <code>uncertain</code>, <code>bad</code>, or <code>null</code>.
            Historical anonymous reports have <code>author: null</code>.
        </p>
        <p>A hidden spring returns <code>404</code>. A merged spring returns <code>308</code> with a <code>Location</code> header for the canonical spring.</p>

        <h3 id="map-discovery">Map discovery</h3>
        <p>
            The mobile map may reuse the public vector-style JSON tile feed at
            <code>{{ rtrim(config('app.url'), '/') }}/tiles/{z}/{x}/{y}.json</code>, then request this spring endpoint when a user selects a point.
            There is no separate paginated spring-list endpoint in v1.
        </p>

        <h2 id="reports">Reports</h2>
        <h3 id="create-report">Create</h3>
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
        <p>The <code>201</code> response contains the created report under <code>data</code>, initially with <code>photos: []</code>.</p>

        <h3 id="update-report">Update</h3>
        <pre><code>PATCH /reports/{report}</code></pre>
        <p>
            Send any subset of <code>visited_at</code>, <code>timezone</code>, <code>state</code>, <code>quality</code>,
            <code>access_limited</code>, <code>littered</code>, <code>broken</code>, and <code>comment</code>.
            Ownership, spring, visibility, and OpenStreetMap fields cannot be changed.
        </p>

        <h3 id="delete-report">Hide</h3>
        <pre><code>DELETE /reports/{report}</code></pre>
        <p>
            Deleting a report is a soft hide. The report and photos are retained but disappear from public spring responses.
            Repeating the request is safe and returns <code>204</code>.
        </p>

        <h3 id="normalization">Normalization</h3>
        <ul>
            <li><code>dry</code> and <code>notfound</code> always clear <code>quality</code>.</li>
            <li><code>notfound</code> also clears <code>access_limited</code>, <code>littered</code>, and <code>broken</code>.</li>
            <li><code>visited_at</code> cannot be later than the current date in the supplied IANA <code>timezone</code>.</li>
        </ul>

        <h2 id="photos">Photos</h2>
        <p>Create the report first, then upload each photo separately. This allows independent progress and retries and prevents orphaned temporary uploads.</p>
        <pre><code>curl -X POST '{{ $apiBaseUrl }}/reports/456/photos' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer TOKEN' \
  -F 'photo=@spring.jpg' \
  -F 'latitude=55.7558' \
  -F 'longitude=37.6173'</code></pre>
        <p>
            One JPEG, PNG, or WebP image is accepted per request, up to 10 MB. It is oriented, resized to fit within 1280 × 1280,
            converted to JPEG, attached immediately, and returned under <code>data</code> with <code>id</code>, <code>url</code>, <code>width</code>, and <code>height</code>.
        </p>
        <pre><code>DELETE /photos/{photo}</code></pre>
        <p>Photo deletion removes both the database record and stored file. Only the report owner may upload or delete its photos.</p>

        <h2 id="errors">Errors</h2>
        <div class="overflow-x-auto">
            <table>
                <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
                <tbody>
                    <tr><td><code>401</code></td><td>Bearer token is missing, invalid, or revoked.</td></tr>
                    <tr><td><code>403</code></td><td>The authenticated user does not own the report or photo.</td></tr>
                    <tr><td><code>404</code></td><td>The resource does not exist or is not publicly available.</td></tr>
                    <tr><td><code>422</code></td><td>Validation or credentials failed.</td></tr>
                    <tr><td><code>429</code></td><td>The request rate limit was exceeded.</td></tr>
                </tbody>
            </table>
        </div>
        <pre><code>{
  "message": "The given data was invalid.",
  "errors": {
    "visited_at": ["The visit date cannot be in the future."]
  }
}</code></pre>
        <p>Clients should use the HTTP status and field keys, not parse human-readable message text.</p>
    </article>
@endsection

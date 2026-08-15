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

        <h2 id="recommended-save-workflow">Recommended save workflow</h2>
        <p>
            Create the report before uploading photos. A report is the owning server resource; photos cannot exist in the mobile API without its ID.
            Keep the form and selected images as a local draft until the report request succeeds.
        </p>
        <ol>
            <li>
                <strong>Prepare a local draft.</strong>
                Store the report fields and local image references on the device. Do not upload temporary photos and do not include images in <code>POST /reports</code>.
            </li>
            <li>
                <strong>Create the report.</strong>
                Send <code>POST /reports</code> as JSON. Disable duplicate submit actions while this request is in progress.
            </li>
            <li>
                <strong>Persist the returned report ID.</strong>
                After <code>201 Created</code>, immediately save <code>data.id</code> in the local draft before starting any upload.
                From this point onward, resume the existing server report; do not create another report when a photo fails.
            </li>
            <li>
                <strong>Upload photos sequentially in the desired display order.</strong>
                Send one multipart request to <code>POST /reports/{report}/photos</code> at a time.
                The server assigns photo order by successful arrival, so parallel uploads may produce a different order.
            </li>
            <li>
                <strong>Persist each returned photo ID.</strong>
                Mark a local image as uploaded only after its <code>201 Created</code> response and store <code>data.id</code> for deletion or reconciliation.
            </li>
            <li>
                <strong>Complete and refresh.</strong>
                When no local photos remain pending, request <code>GET /springs/{spring}</code> and replace local server-derived data with the response.
            </li>
        </ol>

        <h3 id="client-state-model">Client state model</h3>
        <div class="overflow-x-auto">
            <table>
                <thead><tr><th>State</th><th>Stored locally</th><th>Next action</th></tr></thead>
                <tbody>
                    <tr><td><code>local_draft</code></td><td>Fields and local image references</td><td><code>POST /reports</code></td></tr>
                    <tr><td><code>report_created</code></td><td>Report ID and pending images</td><td>Upload the first pending photo</td></tr>
                    <tr><td><code>uploading_photos</code></td><td>Report ID, uploaded photo IDs, pending images</td><td>Continue sequential uploads</td></tr>
                    <tr><td><code>needs_attention</code></td><td>All identifiers and the last error</td><td>Correct, reconcile, or retry</td></tr>
                    <tr><td><code>complete</code></td><td>Report ID and photo IDs</td><td>Refresh the spring and clear temporary files</td></tr>
                </tbody>
            </table>
        </div>
        <p>
            Persist this state across app restarts. If the app closes after the report is created, resume photo uploads using the saved report ID.
            If the user cancels after creation, call <code>DELETE /reports/{report}</code> to hide the incomplete report.
        </p>

        <h3 id="partial-success">Partial success and recovery</h3>
        <ul>
            <li>A failed report request leaves the local draft unchanged.</li>
            <li>A failed photo request does not roll back the report or photos that were already uploaded.</li>
            <li>For <code>422</code>, correct the indicated fields or image; repeating the same request will fail again.</li>
            <li>For <code>401</code>, stop the queue, remove the rejected local token, and ask the user to log in again.</li>
            <li>For <code>403</code> or <code>404</code>, stop automatic retries and refresh the spring because ownership or visibility may have changed.</li>
            <li>For <code>429</code>, honor the <code>Retry-After</code> header before continuing the queue.</li>
            <li>For an offline error, retain the draft and pending queue and resume when connectivity returns.</li>
        </ul>
        <p>
            Version 1 has no idempotency key. If a connection drops after a <code>POST</code> may have reached the server, do not retry it blindly.
            First refresh <code>GET /springs/{spring}</code> and reconcile the report or photo with server state; otherwise a retry may create a duplicate.
        </p>

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
        <p>
            Follow the <a href="#recommended-save-workflow">recommended save workflow</a>: create the report first, persist its ID,
            then upload each photo separately and sequentially. This provides independent progress and recovery without temporary server uploads.
        </p>
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

        <h2 id="client-best-practices">API client best practices</h2>
        <ul>
            <li><strong>Secure tokens:</strong> keep tokens only in Keychain or Keystore, never application logs, analytics, URLs, or ordinary preferences.</li>
            <li><strong>One token per installation:</strong> use a recognizable <code>device_name</code>; revoke the current token during logout before deleting it locally.</li>
            <li><strong>Explicit JSON:</strong> always send <code>Accept: application/json</code> and use the HTTP status plus stable field keys for decisions.</li>
            <li><strong>Server authority:</strong> after a successful mutation, use the returned resource and later spring response instead of assuming the server stored the submitted values unchanged.</li>
            <li><strong>Normalization:</strong> expect the server to clear incompatible quality and problem fields for <code>dry</code> and <code>notfound</code>.</li>
            <li><strong>Dates:</strong> send the user's current IANA timezone with <code>visited_at</code> so date validation follows the user's local day.</li>
            <li><strong>Images:</strong> resize or compress large images before upload to reduce mobile bandwidth, but still handle server-side conversion and returned dimensions.</li>
            <li><strong>Local cleanup:</strong> delete temporary local image copies only after their photo IDs have been persisted or the user deliberately discards the draft.</li>
            <li><strong>Redirects:</strong> when a spring response is <code>308</code>, persist and use the canonical spring ID from the <code>Location</code> URL.</li>
        </ul>
    </article>
@endsection

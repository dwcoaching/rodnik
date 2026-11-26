<?php

declare(strict_types=1);

/*
 * Text of the public API documentation page (resources/views/pages/api.blade.php).
 * Values may contain inline HTML (<code>, <strong>, <a>) and are rendered unescaped.
 * Keep the Russian file in sync.
 */

return [
    'title' => 'Rodnik.today API',
    'seo_description' => 'Rodnik.today API v1: authentication, sources, reports, photos, accounts, examples and errors.',
    'heading' => 'Rodnik.today API v1',
    'lead' => 'An open JSON API for the Rodnik.today mobile app and for anyone who builds apps, scripts or research on top of Rodnik data. It uses Laravel API Resources and Sanctum bearer tokens. Reading is public; contributing requires an account.',

    'contract' => [
        'title' => 'Contract summary',
        'base_url' => '<strong>Base URL:</strong>',
        'envelope' => '<strong>Success envelope:</strong> a top-level <code>data</code> property, except for <code>204 No Content</code>. Paginated lists add <code>links</code> and <code>meta</code>.',
        'dates' => '<strong>Dates:</strong> <code>YYYY-MM-DD</code>. Timestamps use ISO 8601 in UTC.',
        'auth' => '<strong>Authentication:</strong> <code>Authorization: Bearer TOKEN</code>.',
        'lifetime' => '<strong>Token lifetime:</strong> tokens do not expire automatically; logout revokes the current token.',
        'content_type' => '<strong>Content type:</strong> send <code>Accept: application/json</code>; send <code>Content-Type: application/json</code> for JSON bodies.',
        'cors' => '<strong>CORS:</strong> requests from any origin are allowed, so the API can also be used from a browser.',
        'license' => '<strong>Data:</strong> user reports are in the public domain; source locations largely come from OpenStreetMap (ODbL). See <a href=":about">licensing details</a>.',
    ],

    'endpoints' => [
        'title' => 'Endpoints',
        'method' => 'Method',
        'path' => 'Path',
        'auth' => 'Authentication',
        'success' => 'Success',
        'no' => 'No',
        'optional' => 'Optional',
        'required' => 'Required',
        'owner' => 'Owner',
    ],

    'rate_limits' => [
        'title' => 'Rate limits',
        'intro' => 'Limits protect the map from spam. When one is exceeded the API responds with <code>429</code> and a <code>Retry-After</code> header in seconds.',
        'endpoint' => 'Endpoint',
        'limit' => 'Limit',
        'general' => 'All endpoints',
        'general_limit' => '600 requests per minute per user or IP address',
        'token_limit' => '5 attempts per minute per email and IP address',
        'register_limit' => '3 new accounts per hour per IP address; failed attempts only count towards 10 per minute',
        'springs_limit' => '30 new sources per hour per user',
        'reports_limit' => '30 new reports per hour per user',
    ],

    'workflow' => [
        'title' => 'Recommended save workflow',
        'intro' => 'Create the report before uploading photos. A report is the owning server resource; photos cannot exist in the API without its ID. Keep the form and selected images as a local draft until the report request succeeds.',
        'steps' => [
            '<strong>Prepare a local draft.</strong> Store the report fields and local image references on the device. Do not upload temporary photos and do not include images in <code>POST /reports</code>.',
            '<strong>Create the report.</strong> Send <code>POST /reports</code> as JSON. Disable duplicate submit actions while this request is in progress.',
            '<strong>Persist the returned report ID.</strong> After <code>201 Created</code>, immediately save <code>data.id</code> in the local draft before starting any upload. From this point onward, resume the existing server report; do not create another report when a photo fails.',
            '<strong>Upload photos sequentially in the desired display order.</strong> Send one multipart request to <code>POST /reports/{report}/photos</code> at a time. The server assigns photo order by successful arrival, so parallel uploads may produce a different order.',
            '<strong>Persist each returned photo ID.</strong> Mark a local image as uploaded only after its <code>201 Created</code> response and store <code>data.id</code> for deletion or reconciliation.',
            '<strong>Complete and refresh.</strong> When no local photos remain pending, request <code>GET /springs/{spring}</code> and replace local server-derived data with the response.',
        ],
        'state_title' => 'Client state model',
        'state' => 'State',
        'stored' => 'Stored locally',
        'next' => 'Next action',
        'states' => [
            'local_draft' => ['Fields and local image references', '<code>POST /reports</code>'],
            'report_created' => ['Report ID and pending images', 'Upload the first pending photo'],
            'uploading_photos' => ['Report ID, uploaded photo IDs, pending images', 'Continue sequential uploads'],
            'needs_attention' => ['All identifiers and the last error', 'Correct, reconcile, or retry'],
            'complete' => ['Report ID and photo IDs', 'Refresh the spring and clear temporary files'],
        ],
        'state_note' => 'Persist this state across app restarts. If the app closes after the report is created, resume photo uploads using the saved report ID. If the user cancels after creation, call <code>DELETE /reports/{report}</code> to hide the incomplete report.',
        'recovery_title' => 'Partial success and recovery',
        'recovery' => [
            'A failed report request leaves the local draft unchanged.',
            'A failed photo request does not roll back the report or photos that were already uploaded.',
            'For <code>422</code>, correct the indicated fields or image; repeating the same request will fail again.',
            'For <code>401</code>, stop the queue, remove the rejected local token, and ask the user to log in again.',
            'For <code>403</code> or <code>404</code>, stop automatic retries and refresh the spring because ownership or visibility may have changed.',
            'For <code>429</code>, honor the <code>Retry-After</code> header before continuing the queue.',
            'For an offline error, retain the draft and pending queue and resume when connectivity returns.',
        ],
        'idempotency' => 'Version 1 has no idempotency key. If a connection drops after a <code>POST</code> may have reached the server, do not retry it blindly. First refresh <code>GET /springs/{spring}</code> and reconcile the report or photo with server state; otherwise a retry may create a duplicate.',
    ],

    'auth' => [
        'title' => 'Accounts and authentication',
        'register_title' => 'Register',
        'register_intro' => 'Creates an account and signs it in on this device. The rules for <code>name</code>, <code>email</code> and <code>password</code> are the same as on the website. <code>locale</code> is optional (<code>en</code> or <code>ru</code>) and sets the language of emails and notifications.',
        'register_response' => 'The <code>201</code> response has the same shape as <code>POST /auth/token</code> below, so the app is signed in right away. All invalid fields are reported together in one <code>422</code> response.',
        'token_title' => 'Create a token',
        'token_storage' => 'Store the token in the operating system Keychain or Keystore. Never log it or place it in a URL.',
        'me_title' => 'Current user',
        'me_note' => 'The response is the same user object under <code>data</code>.',
        'revoke_title' => 'Revoke the current token',
        'revoke_note' => 'This revokes only the token used by the request. Other devices remain signed in.',
        'delete_title' => 'Delete the account',
        'delete_note' => 'Permanently deletes the account after checking the current password, exactly like the profile page on the website: all tokens are revoked, maps and tracks are deleted, and reports and photos stay on the map without the author\'s name. This cannot be undone. A wrong password returns <code>422</code> with an error for <code>password</code>.',
    ],

    'spring' => [
        'title' => 'Sources',
        'show_title' => 'Get a source',
        'show_intro' => 'This public endpoint returns the source (internally called a “spring”), all visible user reports, and each report\'s photos in one request. Hidden and OpenStreetMap-imported reports are omitted. Reports are newest first by visit date; photos use their saved order.',
        'enums' => '<code>state</code> is <code>running</code>, <code>dripping</code>, <code>dry</code>, <code>notfound</code>, or <code>null</code>. <code>quality</code> is <code>good</code>, <code>uncertain</code>, <code>bad</code>, or <code>null</code>. Historical anonymous reports have <code>author: null</code>.',
        'hidden' => 'A hidden source returns <code>404</code>. A merged source returns <code>308</code> with a <code>Location</code> header for the canonical source.',
        'create_title' => 'Add a source',
        'create_intro' => 'Adds a new water source at the given coordinates. Only <code>latitude</code> and <code>longitude</code> are required. <code>name</code> is free text up to 255 characters; <code>type</code> is one of:',
        'create_response' => 'The <code>201</code> response contains the new source in the same format as <code>GET /springs/{spring}</code>, with country, region and nearest city filled in, but without reports. Use its <code>id</code> as <code>spring_id</code> to add the first report.',
        'create_duplicates' => 'Before adding, check the map for an existing source nearby: the API does not detect duplicates.',
        'map_title' => 'Map discovery',
        'map' => 'A map may reuse the public vector-style JSON tile feed at <code>:tiles</code>, then request the source endpoint when a user selects a point. There is no separate paginated source-list endpoint in v1.',
    ],

    'reports' => [
        'title' => 'Reports',
        'list_title' => 'List a user\'s reports',
        'list_intro' => 'This public endpoint returns a user\'s visible reports, newest first, 50 per page. Pass <code>?page=N</code> for the next pages. Each report also has a short <code>spring</code> block with the source\'s <code>id</code>, <code>name</code>, <code>type</code> and coordinates.',
        'list_hidden' => 'Reports on hidden sources are only included when the request carries the token of that user or of a moderator. An unknown user returns <code>404</code>.',
        'create_title' => 'Create',
        'create_response' => 'The <code>201</code> response contains the created report under <code>data</code>, initially with <code>photos: []</code>.',
        'update_title' => 'Update',
        'update' => 'Send any subset of <code>visited_at</code>, <code>timezone</code>, <code>state</code>, <code>quality</code>, <code>access_limited</code>, <code>littered</code>, <code>broken</code>, and <code>comment</code>. Ownership, source, visibility, and OpenStreetMap fields cannot be changed.',
        'delete_title' => 'Hide',
        'delete' => 'Deleting a report is a soft hide. The report and photos are retained but disappear from public responses. Repeating the request is safe and returns <code>204</code>.',
        'normalization_title' => 'Normalization',
        'normalization' => [
            '<code>dry</code> and <code>notfound</code> always clear <code>quality</code>.',
            '<code>notfound</code> also clears <code>access_limited</code>, <code>littered</code>, and <code>broken</code>.',
            '<code>visited_at</code> cannot be later than the current date in the supplied IANA <code>timezone</code>.',
        ],
    ],

    'photos' => [
        'title' => 'Photos',
        'intro' => 'Follow the <a href="#recommended-save-workflow">recommended save workflow</a>: create the report first, persist its ID, then upload each photo separately and sequentially. This provides independent progress and recovery without temporary server uploads.',
        'upload' => 'One JPEG, PNG, or WebP image is accepted per request, up to 10 MB. It is oriented, resized to fit within 1280 × 1280, converted to JPEG, attached immediately, and returned under <code>data</code> with <code>id</code>, <code>url</code>, <code>width</code>, and <code>height</code>.',
        'delete' => 'Photo deletion removes both the database record and stored file. Only the report owner may upload or delete its photos.',
    ],

    'errors' => [
        'title' => 'Errors',
        'status' => 'Status',
        'meaning' => 'Meaning',
        'codes' => [
            '401' => 'Bearer token is missing, invalid, or revoked.',
            '403' => 'The authenticated user does not own the report or photo.',
            '404' => 'The resource does not exist or is not publicly available.',
            '422' => 'Validation or credentials failed.',
            '429' => 'The request rate limit was exceeded.',
        ],
        'note' => 'Clients should use the HTTP status and field keys, not parse human-readable message text. Messages follow the server\'s default language.',
    ],

    'practices' => [
        'title' => 'API client best practices',
        'items' => [
            '<strong>Secure tokens:</strong> keep tokens only in Keychain or Keystore, never application logs, analytics, URLs, or ordinary preferences.',
            '<strong>One token per installation:</strong> use a recognizable <code>device_name</code>; revoke the current token during logout before deleting it locally.',
            '<strong>Explicit JSON:</strong> always send <code>Accept: application/json</code> and use the HTTP status plus stable field keys for decisions.',
            '<strong>Server authority:</strong> after a successful mutation, use the returned resource and later source response instead of assuming the server stored the submitted values unchanged.',
            '<strong>Normalization:</strong> expect the server to clear incompatible quality and problem fields for <code>dry</code> and <code>notfound</code>.',
            '<strong>Dates:</strong> send the user\'s current IANA timezone with <code>visited_at</code> so date validation follows the user\'s local day.',
            '<strong>Images:</strong> resize or compress large images before upload to reduce mobile bandwidth, but still handle server-side conversion and returned dimensions.',
            '<strong>Local cleanup:</strong> delete temporary local image copies only after their photo IDs have been persisted or the user deliberately discards the draft.',
            '<strong>Redirects:</strong> when a source response is <code>308</code>, persist and use the canonical source ID from the <code>Location</code> URL.',
            '<strong>Be gentle:</strong> cache responses where you can and do not crawl every source; use the <a href=":exports">data exports</a> for bulk downloads.',
        ],
    ],
];

<?php

declare(strict_types=1);

return [
    'title' => 'Privacy Policy',
    'legal' => 'Legal',
    'description' => 'How Rodnik.today uses account data and public contributions, and how to delete your account.',
    'updated' => 'Updated September 7, 2026',
    'operator' => 'Rodnik.today is a public map of water sources, operated by Andrey Kolpakov. This policy covers the website, apps and API. Contact:',
    'sections' => [
        'account' => [
            'title' => 'Your account',
            'body' => 'We store your chosen name, email address, password hash, profile image and settings to provide your account. A pseudonym is fine: we do not require a real name or verify email addresses. Password recovery requires an address you can access. Account email, credentials and sessions are not public. We protect non-public account data with password hashing and access controls.',
        ],
        'public' => [
            'title' => 'Public contributions',
            'body' => 'Your name, reports, photographs, source coordinates and edits are public, including through profiles, the API, exports and Telegram notifications. Publish only material you have the right to share. Your contributions are dedicated to the public domain under CC0 1.0; imported OpenStreetMap data remains under ODbL. Public-domain status does not remove anyone’s privacy or other personal rights.',
        ],
        'usage' => [
            'title' => 'Using the service',
            'body' => 'We process requests, IP addresses, device information and technical logs to operate and protect the service. Cookies and browser storage keep you signed in and remember preferences. Location permission lets you locate yourself on the map; map areas are requested from servers. Photo uploads may include extracted GPS coordinates and filenames. Route searches store a simplified area around the route, linked to your account when signed in.',
        ],
        'providers' => [
            'title' => 'Other services',
            'body' => 'Hosting, email and backup providers process data needed to run Rodnik.today. The website uses Yandex Metrica for usage analytics. Map providers, including OpenStreetMap, OpenTopoMap, Thunderforest, Google, Mapy and Strava, receive ordinary network data and requested map areas when their layers load. UI Avatars receives name-derived initials for default avatars. Telegram receives public notifications. Providers may process data in other countries under their own policies. We do not sell personal data.',
        ],
        'deletion' => [
            'title' => 'Retention and deletion',
            'body' => 'Account data is kept while your account exists; technical records are kept as needed for operation and security. Delete your account in its settings after signing in and confirming your password. Access ends, sessions and tokens are revoked, and account details and private route-search data are deleted. Reports, photos and edits remain public indefinitely, detached from your account and name. Their contents may still identify someone. Previously downloaded exports, Telegram posts and other independent copies may retain earlier attribution. Backups rotate out within 30 days. Account deletion does not revoke CC0. We do not process account-deletion requests by email or Telegram.',
        ],
        'requests' => [
            'title' => 'Questions and specific publications',
            'body' => 'For access, correction or other privacy questions, or a problem with a specific publication, email us with the relevant link, reason and enough information to establish your connection to the data or material. We may ask for reasonable proof before acting; a stranger’s unsubstantiated request does not authorize deletion. We review requests under applicable law. This is separate from deleting your account through its settings.',
        ],
    ],
    'registration_hint' => 'A pseudonym is fine. Email is not verified; password recovery needs an address you can access.',
    'deletion' => [
        'retry' => 'We could not delete your account. Please try again.',
        'title' => 'Delete account',
        'description' => 'Delete your Rodnik.today account through its settings and learn what happens to your contributions.',
        'instructions' => 'Delete your Rodnik.today account yourself in account settings: sign in, select Delete Account, enter your password and confirm. This is the only account-deletion method; we do not process account-deletion requests by email or Telegram. Password recovery requires access to the account’s email address.',
        'effect' => 'Access ends and account details, sessions, tokens and private route-search data are deleted. Published reports, photos and edits remain public, with the author shown as Anonymous and the account link removed. Names or faces inside content and previously downloaded or reposted copies may remain. Account deletion does not revoke CC0.',
        'retention' => 'Backups rotate out within 30 days. For a problem with a specific publication, contact us with its link, the reason and enough proof of your connection to it:',
        'button' => 'Open account settings',
        'summary' => 'Your account and private account data will be deleted. Public reports, photos and edits will remain, with your name and account link removed. Backups rotate out within 30 days.',
        'confirmation' => 'Delete your account permanently? You will lose access, and your account data will be deleted. Public reports, photos and edits will remain anonymously; their contents and existing copies may still identify you. Enter your password to confirm.',
    ],
];

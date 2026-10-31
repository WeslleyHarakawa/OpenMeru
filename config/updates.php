<?php

return [
    // True only on openmeru.com, the install every other one downloads from and updates to:
    // it alone shows the project landing page, the demo, /install and /version.
    // Any other install shows its sign-in page at the root and nothing about the project.
    'source' => filter_var(env('OPENMERU_SOURCE', mb_strtolower((string) env('MAIL_DOMAIN', '')) === 'openmeru.com'), FILTER_VALIDATE_BOOL),

    // Where this install asks for the latest version (the openmeru.com /version endpoint).
    'url' => env('OPENMERU_UPDATE_URL', 'https://openmeru.com/version'),

    // Set to false to never check for updates.
    'check' => (bool) env('OPENMERU_UPDATE_CHECK', true),

    // How long a check result is kept before asking again.
    'ttl_hours' => 24,
];

<?php

return [
    // Where this install asks for the latest version (the openmeru.com /version endpoint).
    'url' => env('OPENMERU_UPDATE_URL', 'https://openmeru.com/version'),

    // Set to false to never check for updates.
    'check' => (bool) env('OPENMERU_UPDATE_CHECK', true),

    // How long a check result is kept before asking again.
    'ttl_hours' => 24,
];

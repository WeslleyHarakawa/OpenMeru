<?php

return [
    // The domain every mailbox lives on (user@MAIL_DOMAIN).
    'domain' => mb_strtolower(env('MAIL_DOMAIN', 'openmeru.com')),

    // Shared with the Cloudflare Email Worker (sent as X-OpenMeru-Secret).
    'secret' => env('INBOUND_SECRET', ''),

    // Where the admin sign-in code is sent (an outside inbox, not one on MAIL_DOMAIN).
    'otp_email' => env('ADMIN_OTP_EMAIL', ''),
];

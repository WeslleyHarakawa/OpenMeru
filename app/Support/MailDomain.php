<?php

namespace App\Support;

class MailDomain
{
    public static function valid(string $domain): bool
    {
        return (bool) preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/', $domain);
    }
}

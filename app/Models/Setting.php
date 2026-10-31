<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    /** Keys stored encrypted at rest. */
    public const SECRETS = ['smtp_password', 'brevo_api_key', 'vapid_private', 'cf_ai_token'];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        $row = static::find($key);
        if (! $row || $row->value === null || $row->value === '') {
            return $default;
        }

        return in_array($key, self::SECRETS, true) ? Crypt::decryptString($row->value) : $row->value;
    }

    public static function put(string $key, ?string $value): void
    {
        $stored = $value !== null && $value !== '' && in_array($key, self::SECRETS, true)
            ? Crypt::encryptString($value)
            : $value;

        static::updateOrCreate(['key' => $key], ['value' => $stored]);
    }
}

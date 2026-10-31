<?php

namespace Tests\Unit;

use App\Console\Commands\PublishCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** openmeru:publish must never carry server data (dumps, uploads, secrets, sessions) into the repository. */
class PublishGuardTest extends TestCase
{
    #[DataProvider('serverFiles')]
    public function test_server_files_are_refused(string $path): void
    {
        $this->assertTrue(PublishCommand::isForbidden($path), $path);
    }

    public static function serverFiles(): array
    {
        return [
            ['storage/backups/openmeru-db-20261031.sql.gz'],
            ['storage/app/private/raw/1/x.eml'],
            ['storage/framework/sessions/abc'],
            ['storage/logs/laravel.log'],
            ['.env'],
            ['.env.backup'],
            ['database/database.sqlite'],
            ['backup/site.sql'],
            ['some/dir/dump.sql'],
            ['keys/server.pem'],
            ['private.key'],
        ];
    }

    #[DataProvider('sourceFiles')]
    public function test_source_files_pass(string $path): void
    {
        $this->assertFalse(PublishCommand::isForbidden($path), $path);
    }

    public static function sourceFiles(): array
    {
        return [
            ['app/Support/Updates.php'],
            ['resources/views/admin/layout.blade.php'],
            ['lang/pt.json'],
            ['public_root/assets/js/mail.js'],
            ['database/migrations/2026_10_19_000200_create_messages_table.php'],
            ['VERSION'],
            ['.gitignore'],
            ['install.sh'],
        ];
    }
}

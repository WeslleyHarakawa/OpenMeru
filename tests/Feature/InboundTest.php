<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InboundTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-inbound-secret';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['inbound.secret' => self::SECRET, 'inbound.domain' => 'example.test']);
    }

    private function mailbox(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['email' => 'bob@example.test']);
    }

    private function raw(string $messageId = 'abc@sender.test'): string
    {
        return "From: Alice <alice@sender.test>\r\nTo: bob@example.test\r\nSubject: Hello there\r\n"
            ."Message-ID: <{$messageId}>\r\nDate: Sat, 31 Oct 2026 10:00:00 +0000\r\n"
            ."Content-Type: text/plain; charset=utf-8\r\n\r\nBody text.\r\n";
    }

    private function deliver(string $to, string $raw, ?string $secret = self::SECRET)
    {
        $server = ['CONTENT_TYPE' => 'message/rfc822', 'HTTP_ACCEPT' => 'application/json'];
        if ($secret !== null) {
            $server['HTTP_X_OPENMERU_SECRET'] = $secret;
        }

        return $this->call('POST', '/hooks/inbound?to='.urlencode($to), [], [], [], $server, $raw);
    }

    public function test_rejects_requests_without_the_secret(): void
    {
        $this->mailbox();

        $this->deliver('bob@example.test', $this->raw(), null)->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_rejects_a_wrong_secret(): void
    {
        $this->mailbox();

        $this->deliver('bob@example.test', $this->raw(), 'nope')->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_rejects_everything_when_no_secret_is_configured(): void
    {
        config(['inbound.secret' => '']);
        $this->mailbox();

        $this->deliver('bob@example.test', $this->raw(), '')->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_requires_recipient_and_body(): void
    {
        $this->deliver('', $this->raw())->assertStatus(422);
        $this->deliver('bob@example.test', '')->assertStatus(422);
    }

    public function test_stores_a_message_in_the_inbox(): void
    {
        $user = $this->mailbox();

        $this->deliver('BOB@example.test', $this->raw())
            ->assertOk()
            ->assertJson(['stored' => true]);

        $message = Message::firstOrFail();
        $this->assertSame($user->id, $message->user_id);
        $this->assertSame('inbox', $message->folder);
        $this->assertSame('Hello there', $message->subject);
        $this->assertSame('alice@sender.test', $message->from_email);
        $this->assertFalse($message->is_read);
        Storage::disk('local')->assertExists($message->raw_path);
    }

    public function test_the_same_message_is_not_stored_twice(): void
    {
        $this->mailbox();

        $this->deliver('bob@example.test', $this->raw('same@sender.test'))->assertOk();
        $this->deliver('bob@example.test', $this->raw('same@sender.test'))
            ->assertOk()
            ->assertJson(['duplicate' => true]);

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_unknown_recipient_is_accepted_but_not_stored(): void
    {
        $this->deliver('ghost@example.test', $this->raw())
            ->assertStatus(202)
            ->assertJson(['stored' => false, 'reason' => 'no_mailbox']);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_disabled_mailbox_gets_nothing(): void
    {
        $this->mailbox(['is_active' => false]);

        $this->deliver('bob@example.test', $this->raw())->assertStatus(202);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_demo_mailbox_gets_nothing(): void
    {
        $user = $this->mailbox();
        $user->forceFill(['is_demo' => true])->save();

        $this->deliver('bob@example.test', $this->raw())->assertStatus(202);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_mail_goes_only_to_the_addressed_mailbox(): void
    {
        $bob = $this->mailbox();
        $carol = User::factory()->create(['email' => 'carol@example.test']);

        $this->deliver('bob@example.test', $this->raw())->assertOk();

        $this->assertSame(1, $bob->messages()->count());
        $this->assertSame(0, $carol->messages()->count());
    }

    public function test_the_webhook_is_not_blocked_by_csrf(): void
    {
        $this->mailbox();

        // Cloudflare's Worker sends no CSRF token; a 419 here would drop every incoming mail.
        $this->deliver('bob@example.test', $this->raw())->assertSuccessful();
    }
}

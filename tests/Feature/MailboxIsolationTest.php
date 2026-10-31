<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MailboxIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->alice = User::factory()->create(['email' => 'alice@example.test']);
        $this->bob = User::factory()->create(['email' => 'bob@example.test']);
    }

    private function message(User $user, array $attributes = []): Message
    {
        $message = new Message;
        $message->user_id = $user->id;
        $message->forceFill($attributes + [
            'folder' => 'inbox',
            'subject' => 'Private to '.$user->email,
            'text_body' => 'secret of '.$user->email,
            'from_email' => 'sender@example.test',
            'is_read' => false,
            'date' => now(),
        ])->save();

        return $message;
    }

    public function test_the_list_only_shows_the_owners_mail(): void
    {
        $this->message($this->alice);
        $this->message($this->bob);

        $response = $this->actingAs($this->alice)->getJson('/api/messages')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertStringNotContainsString('bob@example.test', json_encode($response->json()));
    }

    public function test_search_does_not_reach_other_mailboxes(): void
    {
        $this->message($this->bob, ['subject' => 'needle in bob']);

        $this->actingAs($this->alice)->getJson('/api/messages?q=needle')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_another_mailbox_cannot_read_change_or_delete_a_message(): void
    {
        $message = $this->message($this->bob);

        $this->actingAs($this->alice)->getJson('/api/messages/'.$message->id)->assertNotFound();
        $this->actingAs($this->alice)->patchJson('/api/messages/'.$message->id, ['is_starred' => true])->assertNotFound();
        $this->actingAs($this->alice)->deleteJson('/api/messages/'.$message->id)->assertNotFound();

        $message->refresh();
        $this->assertFalse($message->is_starred);
        $this->assertSame('inbox', $message->folder);
    }

    public function test_reading_marks_only_the_owners_message_as_read(): void
    {
        $message = $this->message($this->alice);

        $this->actingAs($this->alice)->getJson('/api/messages/'.$message->id)->assertOk();

        $this->assertTrue($message->fresh()->is_read);
    }

    public function test_replying_to_someone_elses_message_is_refused(): void
    {
        $message = $this->message($this->bob);

        $this->actingAs($this->alice)->postJson('/api/send', [
            'to' => 'x@example.test',
            'subject' => 'hi',
            'body' => 'hi',
            'reply_to_id' => $message->id,
        ])->assertNotFound();
    }

    public function test_a_draft_cannot_overwrite_someone_elses_message(): void
    {
        $message = $this->message($this->bob, ['folder' => 'drafts', 'subject' => 'bob draft']);

        $this->actingAs($this->alice)->postJson('/api/drafts', [
            'to' => 'x@example.test',
            'subject' => 'overwritten',
            'body' => 'x',
            'draft_id' => $message->id,
        ])->assertNotFound();

        $this->assertSame('bob draft', $message->fresh()->subject);
    }

    public function test_attachments_are_private_to_the_owner(): void
    {
        $message = $this->message($this->bob);
        Storage::disk('local')->put('attachments/bob/file', 'top secret');
        $attachment = $message->attachments()->create([
            'filename' => 'plan.txt', 'mime' => 'text/plain', 'size' => 10, 'path' => 'attachments/bob/file',
        ]);

        $this->actingAs($this->alice)->get('/attachments/'.$attachment->id)->assertNotFound();

        auth()->logout();
        $this->get('/attachments/'.$attachment->id)->assertRedirect();
    }

    public function test_attachments_are_served_locked_down(): void
    {
        $message = $this->message($this->alice);
        Storage::disk('local')->put('attachments/alice/page', '<script>alert(1)</script>');
        $attachment = $message->attachments()->create([
            'filename' => 'page.html', 'mime' => 'text/html', 'size' => 25, 'path' => 'attachments/alice/page',
        ]);

        $response = $this->actingAs($this->alice)->get('/attachments/'.$attachment->id)->assertOk();

        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_inline_attachments_still_carry_the_sandbox(): void
    {
        $message = $this->message($this->alice);
        Storage::disk('local')->put('attachments/alice/vec', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');
        $attachment = $message->attachments()->create([
            'filename' => 'x.svg', 'mime' => 'image/svg+xml', 'size' => 60, 'path' => 'attachments/alice/vec',
        ]);

        $response = $this->actingAs($this->alice)->get('/attachments/'.$attachment->id.'?inline=1')->assertOk();

        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('script-src', $response->headers->get('Content-Security-Policy'));
    }

    public function test_deleting_an_account_removes_only_its_own_data(): void
    {
        $mine = $this->message($this->alice);
        $theirs = $this->message($this->bob);
        Storage::disk('local')->put('attachments/'.$this->alice->id.'/a', 'a');
        Storage::disk('local')->put('attachments/'.$this->bob->id.'/b', 'b');
        $this->alice->forceFill(['password' => 'a-long-password'])->save();

        $this->actingAs($this->alice)->deleteJson('/api/account', ['password' => 'a-long-password'])->assertOk();

        $this->assertDatabaseMissing('messages', ['id' => $mine->id]);
        $this->assertDatabaseHas('messages', ['id' => $theirs->id]);
        Storage::disk('local')->assertMissing('attachments/'.$this->alice->id.'/a');
        Storage::disk('local')->assertExists('attachments/'.$this->bob->id.'/b');
    }

    public function test_deleting_an_account_needs_the_right_password(): void
    {
        $this->actingAs($this->alice)->deleteJson('/api/account', ['password' => 'wrong'])->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $this->alice->id]);
    }

    public function test_a_disabled_mailbox_is_cut_off(): void
    {
        $this->alice->forceFill(['is_active' => false])->save();

        $this->actingAs($this->alice)->getJson('/api/messages')->assertUnauthorized();
    }
}

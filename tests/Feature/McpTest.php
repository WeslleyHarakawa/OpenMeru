<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The MCP server at /mcp that AI coding agents use to install OpenMeru. */
class McpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['updates.source' => true]);
    }

    private function rpc(string $method, array $params = [], mixed $id = 1): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);
    }

    public function test_initialize_and_tools_list(): void
    {
        $this->rpc('initialize', ['protocolVersion' => '2025-03-26', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']])
            ->assertOk()
            ->assertJsonPath('jsonrpc', '2.0')
            ->assertJsonPath('id', 1)
            ->assertJsonPath('result.serverInfo.name', 'openmeru')
            ->assertJsonPath('result.protocolVersion', '2025-03-26');

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->assertStatus(202);

        $tools = $this->rpc('tools/list')->assertOk()->json('result.tools');
        $names = array_column($tools, 'name');
        foreach (['openmeru_overview', 'openmeru_install_plan', 'openmeru_cloudflare_worker', 'openmeru_dns_records', 'openmeru_troubleshoot'] as $t) {
            $this->assertContains($t, $names);
        }
        foreach ($tools as $tool) {
            $this->assertArrayHasKey('inputSchema', $tool);
        }
    }

    public function test_install_plan_uses_the_given_details(): void
    {
        $text = $this->rpc('tools/call', ['name' => 'openmeru_install_plan', 'arguments' => [
            'domain' => 'Example.com', 'otp_email' => 'box@gmail.com', 'admin_email' => 'admin@example.com', 'db_name' => 'mail_db', 'db_user' => 'mail_user', 'panel' => 'cloudpanel',
        ]])->assertOk()->assertJsonPath('result.isError', false)->json('result.content.0.text');

        $this->assertStringContainsString('--domain=example.com', $text);
        $this->assertStringContainsString("--otp-email='box@gmail.com'", $text);
        $this->assertStringContainsString("--db-name='mail_db'", $text);
        $this->assertStringContainsString('/install | bash', $text);
        $this->assertStringContainsString('public_root', $text);
        $this->assertStringContainsString('CloudPanel', $text);

        $worker = $this->rpc('tools/call', ['name' => 'openmeru_cloudflare_worker', 'arguments' => ['domain' => 'example.com', 'url' => 'https://mail.example.com', 'gmail' => 'box@gmail.com']])
            ->assertOk()->json('result.content.0.text');
        $this->assertStringContainsString('https://mail.example.com/hooks/inbound', $worker);
        $this->assertStringContainsString('export default', $worker);

        $dns = $this->rpc('tools/call', ['name' => 'openmeru_dns_records', 'arguments' => ['domain' => 'example.com']])->assertOk()->json('result.content.0.text');
        $this->assertStringContainsString('include:_spf.mx.cloudflare.net include:spf.brevo.com', $dns);
    }

    public function test_bad_input_is_answered_in_json_rpc(): void
    {
        $this->rpc('nope')->assertOk()->assertJsonPath('error.code', -32601);
        $this->rpc('tools/call', ['name' => 'nope'])->assertOk()->assertJsonPath('error.code', -32602);
        $this->postJson('/mcp', ['hello' => 'world'])->assertOk()->assertJsonPath('error.code', -32600);
        $this->rpc('tools/call', ['name' => 'openmeru_install_plan', 'arguments' => ['domain' => "x'; rm -rf /", 'otp_email' => "a'b", 'admin_email' => 'x']])
            ->assertOk()->assertJsonPath('result.isError', false);
    }

    public function test_get_describes_the_server_and_other_installs_have_none(): void
    {
        $this->get('/mcp')->assertStatus(405)->assertJsonPath('name', 'openmeru')->assertJsonPath('transport', 'streamable-http');

        config(['updates.source' => false]);
        $this->rpc('initialize')->assertNotFound();
        $this->get('/mcp')->assertNotFound();
    }
}

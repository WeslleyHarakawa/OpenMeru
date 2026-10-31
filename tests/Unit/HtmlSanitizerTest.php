<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_empty_input_gives_empty_string(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean("  \n "));
    }

    public function test_keeps_basic_formatting(): void
    {
        $out = HtmlSanitizer::clean('<p>Hi <strong>there</strong> <em>you</em></p><ul><li>one</li></ul>');

        $this->assertStringContainsString('<strong>there</strong>', $out);
        $this->assertStringContainsString('<li>one</li>', $out);
    }

    public function test_removes_scripts_with_their_content(): void
    {
        $out = HtmlSanitizer::clean('<p>ok</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('script', $out);
        $this->assertStringNotContainsString('alert', $out);
        $this->assertStringContainsString('ok', $out);
    }

    #[DataProvider('droppedElements')]
    public function test_removes_dangerous_elements(string $html): void
    {
        $out = HtmlSanitizer::clean($html);

        $this->assertDoesNotMatchRegularExpression('/<(iframe|img|svg|object|embed|form|input|style|link|meta|video|audio|math)\b/i', $out);
        $this->assertStringNotContainsString('onerror', $out);
    }

    public static function droppedElements(): array
    {
        return [
            'img onerror' => ['<img src=x onerror=alert(1)>'],
            'iframe' => ['<iframe src="https://evil.test"></iframe>'],
            'svg onload' => ['<svg onload=alert(1)><circle/></svg>'],
            'object' => ['<object data="x.swf"></object>'],
            'form' => ['<form action="https://evil.test"><input name=a></form>'],
            'style tag' => ['<style>body{background:url(https://evil.test)}</style>'],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.test">'],
        ];
    }

    public function test_strips_event_handlers_and_classes(): void
    {
        $out = HtmlSanitizer::clean('<p onclick="steal()" class="x" id="y" data-a="1">hi</p>');

        $this->assertSame('<p>hi</p>', $out);
    }

    #[DataProvider('badHrefs')]
    public function test_drops_unsafe_link_targets(string $href): void
    {
        $out = HtmlSanitizer::clean('<a href="'.$href.'">link</a>');

        $this->assertStringNotContainsString('href', $out);
        $this->assertStringContainsString('link', $out);
    }

    public static function badHrefs(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript upper' => ['JaVaScRiPt:alert(1)'],
            'javascript newline' => ["java\nscript:alert(1)"],
            'data' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            'vbscript' => ['vbscript:msgbox(1)'],
            'protocol relative' => ['//evil.test/x'],
        ];
    }

    public function test_safe_links_open_in_new_tab_without_opener(): void
    {
        $out = HtmlSanitizer::clean('<a href="https://example.test/a" onclick="x()">x</a><a href="mailto:a@b.test">m</a>');

        $this->assertStringContainsString('href="https://example.test/a"', $out);
        $this->assertStringContainsString('target="_blank"', $out);
        $this->assertStringContainsString('rel="noopener noreferrer"', $out);
        $this->assertStringContainsString('href="mailto:a@b.test"', $out);
        $this->assertStringNotContainsString('onclick', $out);
    }

    public function test_keeps_only_allowed_style_properties(): void
    {
        $out = HtmlSanitizer::clean('<span style="color:#ff0000;position:fixed;background-color:url(https://evil.test/x);text-align:center">t</span>');

        $this->assertStringContainsString('color:#ff0000', $out);
        $this->assertStringContainsString('text-align:center', $out);
        $this->assertStringNotContainsString('position', $out);
        $this->assertStringNotContainsString('url', $out);
    }

    public function test_drops_css_expression(): void
    {
        $out = HtmlSanitizer::clean('<div style="color:expression(alert(1))">t</div>');

        $this->assertStringNotContainsString('expression', $out);
    }

    public function test_unknown_wrappers_keep_their_text(): void
    {
        $out = HtmlSanitizer::clean('<font color="red">kept</font>');

        $this->assertStringContainsString('kept', $out);
        $this->assertStringNotContainsString('<font', $out);
    }

    public function test_removes_comments(): void
    {
        $out = HtmlSanitizer::clean('<p>a<!-- <script>x</script> -->b</p>');

        $this->assertStringNotContainsString('<!--', $out);
    }

    public function test_quill_alignment_class_becomes_inline_style(): void
    {
        $out = HtmlSanitizer::clean('<p class="ql-align-center">x</p>');

        $this->assertStringContainsString('text-align:center', $out);
        $this->assertStringNotContainsString('class', $out);
    }
}

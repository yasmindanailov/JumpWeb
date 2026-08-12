<?php

namespace Tests\Feature\Support;

use App\Domain\Content\Services\SocialEmbed;
use Tests\TestCase;

/**
 * #215 — `SocialEmbed`: extrae la URL `src` del iframe de un widget de feed (SnapWidget/
 * LightWidget) y la valida contra la allowlist; rechaza orígenes arbitrarios.
 */
class SocialEmbedTest extends TestCase
{
    public function test_extracts_src_from_full_iframe(): void
    {
        $iframe = '<iframe src="https://snapwidget.com/embed/1234567" class="snapwidget-widget" '
            .'style="border:none;width:100%" scrolling="no"></iframe>';

        $this->assertSame('https://snapwidget.com/embed/1234567', SocialEmbed::clean($iframe));
    }

    public function test_accepts_bare_url_and_lightwidget_subdomain(): void
    {
        $this->assertSame(
            'https://cdn.lightwidget.com/widgets/abc123.html',
            SocialEmbed::clean('https://cdn.lightwidget.com/widgets/abc123.html'),
        );
    }

    public function test_rejects_unknown_host(): void
    {
        $this->assertNull(SocialEmbed::clean('<iframe src="https://evil.example.com/x"></iframe>'));
        $this->assertNull(SocialEmbed::clean('https://snapwidget.com.evil.com/embed/1'),
            'el sufijo debe casar con un punto delante (no snapwidget.com.evil.com)');
    }

    public function test_rejects_non_https_and_empty(): void
    {
        $this->assertNull(SocialEmbed::clean('http://snapwidget.com/embed/1'));
        $this->assertNull(SocialEmbed::clean(''));
        $this->assertNull(SocialEmbed::clean(null));
    }

    public function test_csp_frame_src_lists_providers(): void
    {
        $csp = SocialEmbed::cspFrameSrc();

        $this->assertStringContainsString('https://snapwidget.com', $csp);
        $this->assertStringContainsString('https://*.lightwidget.com', $csp);
    }
}

<?php

namespace Tests\Feature\Analytics;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Pixels;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Los píxeles de anuncios, por instalación** (`specs/analitica.md` §4.3, T3b·1): un id con otra forma es «sin
 * píxel»; sus orígenes entran en la CSP solo con el píxel configurado, por plataforma y directiva; el `<body>`
 * publica un atributo por píxel configurado (el cargador decide por la categoría `marketing`); y los tokens de
 * las APIs de conversiones no viven en `settings`.
 */
class PixelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    private function setting(string $key, ?string $value): void
    {
        if ($value === null) {
            Setting::where('key', $key)->delete();
        } else {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'marketing']);
        }
        Setting::flushMemo();
    }

    public function test_without_ids_there_is_nothing_and_a_malformed_id_is_no_pixel(): void
    {
        $this->assertSame([], Pixels::config());
        $this->assertSame([], Pixels::active());
        $this->assertSame([], Pixels::csp());
        $this->assertSame([], Pixels::forBody());

        $this->setting(Pixels::KEY_GOOGLE_ADS_ID, 'G-ABC123');          // una etiqueta de GA4, no de Ads
        $this->setting(Pixels::KEY_META_PIXEL_ID, 'pixel-1');
        $this->setting(Pixels::KEY_TIKTOK_PIXEL_ID, 'c9abcdefghijklmnopqr');   // minúsculas
        $this->assertSame([], Pixels::config());

        foreach (['AW-1', "AW-123456789'; script-src *", '', null, 12] as $bad) {
            $this->assertNull(Pixels::googleAdsId($bad), var_export($bad, true));
        }
        $this->assertSame('AW-123456789', Pixels::googleAdsId(' AW-123456789 '));
        $this->assertSame('1234567890123456', Pixels::metaPixelId(1234567890123456));
        $this->assertNull(Pixels::metaPixelId('123'));
        $this->assertNull(Pixels::googleAdsLabel('con espacios no'));
    }

    public function test_each_configured_pixel_is_active_with_its_id_and_google_carries_its_label(): void
    {
        $this->setting(Pixels::KEY_META_PIXEL_ID, '1234567890123456');
        $this->assertSame([Pixels::META => '1234567890123456'], Pixels::config());
        $this->assertSame(['data-pixel-meta' => '1234567890123456'], Pixels::forBody());

        $this->setting(Pixels::KEY_GOOGLE_ADS_ID, 'AW-123456789');
        $this->assertSame('AW-123456789', Pixels::config()[Pixels::GOOGLE_ADS], 'sin etiqueta, solo la cuenta');

        $this->setting(Pixels::KEY_GOOGLE_ADS_LABEL, 'AbCdEfGh');
        $this->setting(Pixels::KEY_TIKTOK_PIXEL_ID, 'C9ABCDEFGHIJKLMNOPQR');
        $this->assertSame(
            [Pixels::GOOGLE_ADS => 'AW-123456789/AbCdEfGh', Pixels::META => '1234567890123456', Pixels::TIKTOK => 'C9ABCDEFGHIJKLMNOPQR'],
            Pixels::config(),
        );
        $this->assertSame(['data-pixel-google-ads', 'data-pixel-meta', 'data-pixel-tiktok'], array_keys(Pixels::forBody()));
        $this->assertSame('Meta (Facebook e Instagram)', Pixels::name(Pixels::META));
    }

    public function test_the_csp_origins_are_per_platform_and_per_directive(): void
    {
        $this->setting(Pixels::KEY_META_PIXEL_ID, '1234567890123456');
        $this->assertSame(
            ['script-src' => ['https://connect.facebook.net'], 'connect-src' => ['https://www.facebook.com'], 'img-src' => ['https://www.facebook.com']],
            Pixels::csp(),
        );

        $this->setting(Pixels::KEY_GOOGLE_ADS_ID, 'AW-123456789');
        $csp = Pixels::csp();
        $this->assertContains('https://www.googletagmanager.com', $csp['script-src']);
        $this->assertContains('https://connect.facebook.net', $csp['script-src']);
        $this->assertContains('https://googleads.g.doubleclick.net', $csp['img-src']);
        $this->assertNotContains('https://analytics.tiktok.com', $csp['script-src'], 'TikTok no está configurado');
    }

    // ─── La CSP y el body ──────────────────────────────────────────────────────────────────────

    public function test_the_csp_of_the_site_opens_the_pixel_origins_only_with_a_pixel(): void
    {
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
        foreach (['googletagmanager', 'facebook', 'tiktok'] as $host) {
            $this->assertStringNotContainsString($host, $csp, $host);
        }

        $this->setting(Pixels::KEY_GOOGLE_ADS_ID, 'AW-123456789');
        $this->setting(Pixels::KEY_TIKTOK_PIXEL_ID, 'C9ABCDEFGHIJKLMNOPQR');
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression('/script-src [^;]*https:\/\/www\.googletagmanager\.com/', $csp);
        $this->assertMatchesRegularExpression('/connect-src [^;]*https:\/\/googleads\.g\.doubleclick\.net/', $csp);
        $this->assertMatchesRegularExpression('/img-src [^;]*https:\/\/analytics\.tiktok\.com/', $csp);
        $this->assertStringNotContainsString('facebook', $csp, 'Meta no está configurado');
        $this->assertDoesNotMatchRegularExpression('/frame-src [^;]*tiktok/', $csp, 'ni un frame');
    }

    public function test_the_body_carries_one_attribute_per_configured_pixel_whatever_the_consent(): void
    {
        $this->get('/')->assertOk()->assertDontSee('data-pixel-', false);

        $this->setting(Pixels::KEY_META_PIXEL_ID, '1234567890123456');
        $this->setting(Pixels::KEY_GOOGLE_ADS_ID, 'AW-123456789');
        $this->setting(Pixels::KEY_GOOGLE_ADS_LABEL, 'AbCdEfGh');

        // El `<body>` publica los ids (son públicos); el cargador decide por `data-cookie-marketing`.
        $this->get('/')->assertOk()
            ->assertSee('data-pixel-google-ads="AW-123456789/AbCdEfGh"', false)
            ->assertSee('data-pixel-meta="1234567890123456"', false)
            ->assertDontSee('data-pixel-tiktok', false);
    }

    /** Los tokens de las APIs de conversiones (T3b·2) viven en `config/services.php` desde `.env` (`PAY-06`). */
    public function test_the_conversion_api_tokens_live_in_config_and_not_in_settings(): void
    {
        $this->assertArrayHasKey('meta', config('services'));
        $this->assertArrayHasKey('access_token', config('services.meta'));
        $this->assertArrayHasKey('tiktok', config('services'));
        $this->assertArrayHasKey('access_token', config('services.tiktok'));

        foreach (array_keys(Pixels::ORIGINS) as $platform) {
            $this->assertStringNotContainsString('token', implode(',', array_keys(Setting::query()->where('group', 'marketing')->pluck('value', 'key')->all())));
        }
    }
}

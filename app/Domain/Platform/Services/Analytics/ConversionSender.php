<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Contracts\ConsentLedger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * **La API de conversiones de cada anunciante** (`docs/specs/analitica.md` §4.3, T3b·2): Meta Conversions API y
 * TikTok Events API, desde el servidor, con el código del pedido como id del evento (dedup con el píxel) y los
 * identificadores HASHEADOS que le llegan en {@see Conversion}. Google Ads no tiene API de servidor aquí: su
 * conversión la manda gtag desde el navegador (`cajon/pixels.js`).
 *
 * ⚠️ Solo habla con la plataforma cuyo píxel está configurado ({@see Pixels::config()}) Y cuyo token vive en
 * `config/services.php` (`.env`, `PAY-06`): sin token se anota y no se inventa. ⚠️ No decide sobre el
 * consentimiento: eso lo relee quien la llama (el job) ANTES, con el {@see ConsentLedger}.
 * ⚠️ Un fallo HTTP lanza (`throw()`), para que la cola reintente; el log nunca lleva el token ni un hash.
 */
final class ConversionSender
{
    public const META_GRAPH = 'https://graph.facebook.com';

    public const TIKTOK_EVENTS = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    /**
     * Manda la compra a las plataformas configuradas con token. Devuelve a cuáles se mandó.
     *
     * @return list<string>
     */
    public function send(Conversion $conversion): array
    {
        $pixels = Pixels::config();
        $sent = [];

        if (isset($pixels[Pixels::META])) {
            $token = (string) config('services.meta.access_token');

            if ($token === '') {
                Log::warning('analytics.conversion_skipped', ['platform' => Pixels::META, 'reason' => 'no access token']);
            } else {
                $this->meta($pixels[Pixels::META], $token, $conversion);
                $sent[] = Pixels::META;
            }
        }

        if (isset($pixels[Pixels::TIKTOK])) {
            $token = (string) config('services.tiktok.access_token');

            if ($token === '') {
                Log::warning('analytics.conversion_skipped', ['platform' => Pixels::TIKTOK, 'reason' => 'no access token']);
            } else {
                $this->tiktok($pixels[Pixels::TIKTOK], $token, $conversion);
                $sent[] = Pixels::TIKTOK;
            }
        }

        if ($sent !== []) {
            Log::info('analytics.conversion_sent', ['event_id' => $conversion->eventId, 'platforms' => $sent]);
        }

        return $sent;
    }

    /** Meta Conversions API: `POST /{version}/{pixel_id}/events`. */
    private function meta(string $pixelId, string $token, Conversion $c): void
    {
        $version = (string) config('services.meta.api_version', 'v21.0');
        $user = array_filter([
            'em' => $c->emailHash === null ? null : [$c->emailHash],
            'ph' => $c->phoneHash === null ? null : [$c->phoneHash],
            'fbp' => $c->browserIds['fbp'] ?? null,
            'fbc' => $c->browserIds['fbc'] ?? null,
        ], static fn ($v): bool => $v !== null);

        Http::acceptJson()
            ->post(self::META_GRAPH."/{$version}/{$pixelId}/events", [
                'access_token' => $token,
                'data' => [[
                    'event_name' => 'Purchase',
                    'event_time' => $c->eventTime,
                    'event_id' => $c->eventId,
                    'action_source' => 'website',
                    'event_source_url' => $c->sourceUrl,
                    'user_data' => $user,
                    'custom_data' => ['currency' => $c->currency, 'value' => $c->value],
                ]],
            ])
            ->throw();
    }

    /** TikTok Events API v1.3: `POST /event/track/` con `Access-Token`. */
    private function tiktok(string $pixelId, string $token, Conversion $c): void
    {
        $user = array_filter([
            'email' => $c->emailHash,
            'phone' => $c->phoneHash,
            'ttclid' => $c->clickId,
            'ttp' => $c->browserIds['ttp'] ?? null,
        ], static fn ($v): bool => $v !== null);

        Http::acceptJson()
            ->withHeaders(['Access-Token' => $token])
            ->post(self::TIKTOK_EVENTS, [
                'event_source' => 'web',
                'event_source_id' => $pixelId,
                'data' => [[
                    'event' => 'CompletePayment',
                    'event_time' => $c->eventTime,
                    'event_id' => $c->eventId,
                    'user' => $user,
                    'page' => array_filter(['url' => $c->sourceUrl]),
                    'properties' => ['currency' => $c->currency, 'value' => $c->value, 'content_type' => 'product'],
                ]],
            ])
            ->throw();
    }
}

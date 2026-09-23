<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * **LA INGESTA DE UN LOTE DEL CLIENTE** (`docs/specs/analitica.md` §4.1): valida evento a evento, normaliza,
 * vacía lo que parezca dato personal, resuelve la sesión y escribe con idempotencia.
 *
 * ⚠️⚠️ **Por evento, no por lote** (spec §7.1, producto-3): un `data-jw-track` mal escrito en una landing no
 * puede tirar el `page_viewed` con la campaña que viaja en el mismo envío. Lo válido entra, lo inválido se
 * cuenta y se devuelve con su motivo, y el `Log` NUNCA vuelca el lote (llevaría lo que se está filtrando).
 *
 * ⚠️ El orden de las defensas: nombre en el contrato y de CLIENTE → `props` por lista blanca → claves y
 * valores sin pinta de PII → ruta como patrón → tiempo acotado a `received_at ± 5 min`. La sesión se
 * resuelve una vez con los datos de la primera vista del lote, si la hay.
 *
 * ⚠️ `insertOrIgnore` sobre `(visitor_id, event_id)`: el mismo lote dos veces —`sendBeacon` más un
 * reintento— es una fila, no dos (medicion-7).
 */
final class EventIngestor
{
    /** Desvío máximo que se admite entre el reloj del cliente y el del servidor, en segundos. */
    public const MAX_CLOCK_SKEW_SECONDS = 300;

    public function __construct(private readonly SessionResolver $sessions) {}

    /**
     * @param  list<mixed>  $events  el lote tal como llegó (cada elemento debería ser un array)
     * @param  array<string, mixed>  $meta  lo que viaja fuera de los eventos: `webdriver`, `consent`, `surface`, `internal`
     * @return array{accepted: int, rejected: list<array{index: int, reason: string}>}
     */
    public function ingest(string $visitorId, array $events, array $meta, Request $request): array
    {
        $receivedAt = now();
        $rejected = [];
        $rows = [];
        $entry = null;

        foreach ($events as $index => $event) {
            $result = $this->prepare($event, $receivedAt);

            if (is_string($result)) {
                $rejected[] = ['index' => $index, 'reason' => $result];

                continue;
            }

            if ($result['name'] === 'page_viewed' && $entry === null) {
                $entry = $this->entryFrom($result['props'] ?? [], $meta, $request);
            }

            $rows[] = $result;
        }

        if ($rows === []) {
            return ['accepted' => 0, 'rejected' => $rejected];
        }

        $session = $this->sessions->current($visitorId, $request, create: true, entry: $entry ?? $this->entryFrom([], $meta, $request));
        $this->sessions->touch($session);

        $inserted = AnalyticsEvent::query()->insertOrIgnore(array_map(static fn (array $row): array => [
            'event_id' => $row['event_id'],
            'session_id' => $session->id,
            'visitor_id' => $visitorId,
            'user_id' => null,
            'name' => $row['name'],
            'route' => $row['route'],
            'props' => $row['props'] === null ? null : json_encode($row['props']),
            'occurred_at' => $row['occurred_at'],
            'received_at' => $receivedAt,
        ], $rows));

        if ($rejected !== []) {
            Log::info('analytics.events_rejected', ['count' => count($rejected), 'reasons' => array_count_values(array_column($rejected, 'reason'))]);
        }

        return ['accepted' => (int) $inserted, 'rejected' => $rejected];
    }

    /**
     * Un evento validado y normalizado, o el motivo del rechazo.
     *
     * @return array{event_id: string, name: string, route: ?string, props: ?array<string, mixed>, occurred_at: Carbon}|string
     */
    private function prepare(mixed $event, Carbon $receivedAt): array|string
    {
        if (! is_array($event)) {
            return 'malformed';
        }

        $name = $event['name'] ?? null;

        if (! is_string($name) || ! Contract::exists($name)) {
            return 'unknown_event';
        }

        if (Contract::isServer($name)) {
            return 'server_only';
        }

        $eventId = $event['event_id'] ?? null;

        if (! Visitor::isValid($eventId) || ! is_string($eventId) || strlen($eventId) !== 26) {
            return 'bad_event_id';
        }

        $props = $this->cleanProps($name, $event['props'] ?? []);

        if ($props === false) {
            return 'pii';
        }

        $route = $event['route'] ?? null;
        $route = is_string($route) && $route !== '' ? RouteNormalizer::path($route) : null;

        if ($route !== null && Contract::looksLikePii($route)) {
            return 'pii';
        }

        return [
            'event_id' => $eventId,
            'name' => $name,
            'route' => $route,
            'props' => $props === [] ? null : $props,
            'occurred_at' => $this->clamp($event['occurred_at'] ?? null, $receivedAt),
        ];
    }

    /**
     * Las `props` que el contrato permite para este evento, saneadas; `false` si alguna clave o valor
     * parece dato personal (el evento entero se rechaza: mejor perderlo que guardarlo).
     *
     * @return array<string, scalar>|false
     */
    private function cleanProps(string $name, mixed $props): array|false
    {
        if (! is_array($props)) {
            return [];
        }

        $allowed = Contract::allowedProps($name);
        $clean = [];

        foreach ($props as $key => $value) {
            if (! is_string($key) || Contract::isPiiKey($key)) {
                if (is_string($key) && Contract::isPiiKey($key)) {
                    return false;
                }

                continue;
            }

            if (! in_array($key, $allowed, true) || ! is_scalar($value)) {
                continue;
            }

            if (is_string($value)) {
                if (Contract::looksLikePii($value)) {
                    return false;
                }

                $value = RouteNormalizer::value($value);

                if ($value === null) {
                    continue;
                }
            }

            $clean[$key] = $value;
        }

        if (strlen((string) json_encode($clean)) > Contract::MAX_PROPS_BYTES) {
            return false;
        }

        return $clean;
    }

    /** El reloj del cliente (ms desde la época) acotado al del servidor. */
    private function clamp(mixed $occurredAtMs, Carbon $receivedAt): Carbon
    {
        if (! is_numeric($occurredAtMs)) {
            return $receivedAt->copy();
        }

        $occurred = Carbon::createFromTimestampMs((int) $occurredAtMs);
        $floor = $receivedAt->copy()->subSeconds(self::MAX_CLOCK_SKEW_SECONDS);
        $ceiling = $receivedAt->copy()->addSeconds(self::MAX_CLOCK_SKEW_SECONDS);

        return $occurred->lt($floor) ? $floor : ($occurred->gt($ceiling) ? $ceiling : $occurred);
    }

    /**
     * Lo que abre una sesión: la primera vista del lote (ya saneada) más lo que sabe el servidor.
     *
     * @param  array<string, mixed>  $view  las `props` de `page_viewed`
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function entryFrom(array $view, array $meta, Request $request): array
    {
        $userAgent = $request->userAgent();
        $consent = is_array($meta['consent'] ?? null) ? array_map(static fn ($v): bool => (bool) $v, $meta['consent']) : null;
        $marketing = (bool) ($consent['marketing'] ?? false);

        $clickIds = array_filter([
            'gclid' => $view['gclid'] ?? null,
            'fbclid' => $view['fbclid'] ?? null,
            'ttclid' => $view['ttclid'] ?? null,
        ], static fn ($v): bool => is_string($v) && $v !== '');

        return [
            'surface' => $request->bearerToken() !== null ? 'app' : 'web',
            'entry' => isset($view['entry']) && is_string($view['entry']) ? RouteNormalizer::path($view['entry']) : null,
            'referrer_host' => isset($view['referrer_host']) && is_string($view['referrer_host']) ? RouteNormalizer::referrerHost('https://'.ltrim($view['referrer_host'], '/')) : null,
            'utm_source' => $view['utm_source'] ?? null,
            'utm_medium' => $view['utm_medium'] ?? null,
            'utm_campaign' => $view['utm_campaign'] ?? null,
            'utm_content' => $view['utm_content'] ?? null,
            'utm_term' => $view['utm_term'] ?? null,
            'ref' => $view['ref'] ?? null,
            // Los click ids solo se guardan con `marketing`: sin la categoría no viajan a ninguna plataforma.
            'click_ids' => $marketing && $clickIds !== [] ? $clickIds : null,
            'device' => Device::classify($userAgent),
            'locale' => isset($view['locale']) && is_string($view['locale']) ? substr($view['locale'], 0, 8) : null,
            'consent' => $consent,
            'is_bot' => Device::isBot($userAgent) || (bool) ($meta['webdriver'] ?? false),
            'is_internal' => (bool) ($meta['internal'] ?? false),
        ];
    }
}

<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AnalyticsSession;

/**
 * **El enlace sesión↔cuenta: el régimen IDENTIFICADO** (`docs/specs/analitica.md` §4.1 y §4.3, T3a·3).
 *
 * Al identificarse o comprar, y SOLO con la categoría `analytics` consentida en esa petición, el servidor ata
 * el `user_id` a la sesión actual del visitante y a las suyas de los últimos {@see LINK_DAYS} días —las que la
 * cookie `visitor_id` reconoce como la misma persona—, y a los hechos de esas sesiones. Sin la categoría, nada:
 * el libro se queda en el agregado, que es exento y no es de nadie (`RGPD-07`). Retirarla desde la cuenta
 * {@see unlink()} lo deshace: las sesiones y los hechos pierden el `user_id` y vuelven al agregado.
 *
 * ⚠️ Aquí solo se tocan las tablas del libro (Platform no ve a Identity): lo que la CUENTA guarda del enlace
 * —su oposición, su primera atribución y la fila-prueba de `consents`— lo escribe `Identity\Services\
 * AccountAnalytics`, que es quien llama a esto. Booking, al cobrar, puede llamar directamente: la oposición
 * llega por parámetro.
 * ⚠️ Nunca pisa un `user_id` ya puesto: una sesión que otra cuenta ató no cambia de dueño porque alguien entre
 * desde el mismo navegador.
 */
final class AccountLinker
{
    /** Hacia atrás, cuántos días de sesiones del mismo visitante se atan a la cuenta al identificarse. */
    public const LINK_DAYS = 90;

    public function __construct(private readonly SessionResolver $sessions) {}

    /**
     * Ata las sesiones del visitante de la petición a la cuenta. `null` si no procede (sin categoría, sin
     * visitante, o con la oposición de la cuenta puesta); si no, cuántas sesiones se ataron y el primer toque.
     *
     * ⚠️ `$consentedNow`: la categoría tal como la dice la COOKIE de esta petición, si quien llama la sabe
     * (Identity, que es quien lee `cookie_consent`). La foto de la sesión del libro se toma al abrirla y la
     * ingesta la refresca lote a lote, pero entre consentir y entrar puede no haber llegado ningún lote: la
     * cookie manda cuando está decidida; sin ella, la foto de la sesión.
     *
     * ⚠️ La clave es `visits` y no «sesiones»: es el vocabulario de la spec (§4.2, *visita = sesión*) y el
     * escáner de `AccessRevocationTest` toma cualquier literal `sessions` por la tabla de credenciales.
     *
     * @return array{visits: int, first_touch: array{source: string, medium: string, campaign: ?string}|null}|null
     */
    public function link(int $userId, AttributionContext $context, bool $optedOut = false, ?bool $consentedNow = null): ?array
    {
        if ($optedOut) {
            return null;
        }

        $context->resolve();
        $visitorId = $context->visitorId();

        if ($visitorId === null || ! ($consentedNow ?? $context->consented('analytics'))) {
            return null;
        }

        $sessionIds = AnalyticsSession::query()
            ->where('visitor_id', $visitorId)
            ->where('started_at', '>=', now()->subDays(self::LINK_DAYS))
            ->whereNull('user_id')
            ->pluck('id');

        if ($sessionIds->isNotEmpty()) {
            AnalyticsSession::query()->whereIn('id', $sessionIds)->update(['user_id' => $userId]);
            AnalyticsEvent::query()->whereIn('session_id', $sessionIds)->whereNull('user_id')->update(['user_id' => $userId]);
        }

        $first = $this->sessions->firstTouch($visitorId, self::LINK_DAYS);
        if ($first === null && ($sessionId = $context->sessionId()) !== null) {
            $current = AnalyticsSession::query()->find($sessionId);
            $first = $current === null ? null : AttributionContext::touch($current);
        }

        return ['visits' => $sessionIds->count(), 'first_touch' => $first];
    }

    /** Devuelve al agregado todo lo que se ató a la cuenta: sesiones y hechos pierden su `user_id`. */
    public function unlink(int $userId): int
    {
        $sessions = AnalyticsSession::query()->where('user_id', $userId)->update(['user_id' => null]);
        AnalyticsEvent::query()->where('user_id', $userId)->update(['user_id' => null]);

        return $sessions;
    }
}

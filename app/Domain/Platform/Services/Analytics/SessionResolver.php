<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Models\AnalyticsSession;
use Illuminate\Http\Request;

/**
 * **La sesión abierta de un visitante, y su primer toque** (`docs/specs/analitica.md` §4.1).
 *
 * Una sesión sigue abierta mientras no pasen 30 minutos sin eventos (`AnalyticsSession::IDLE_MINUTES`),
 * medidos con `received_at` —la verdad temporal—, y se CREA con el primer evento, no al escribir la cookie
 * (un rastreador sin JavaScript no abre sesión). Una vista con campaña nueva abre sesión nueva aunque la
 * anterior siga viva: es lo que hace que «primer toque» y «último toque» signifiquen algo.
 *
 * ⚠️ Memoizado por petición en `request()->attributes` (el molde de `PERF-02`): el contexto de atribución
 * y la ingesta preguntan por la misma sesión y solo se consulta una vez.
 */
final class SessionResolver
{
    private const MEMO_KEY = 'analytics.session';

    /**
     * La sesión abierta del visitante; si `$create`, la abre con los datos de la petición cuando no hay.
     *
     * @param  array<string, mixed>  $entry  lo que trae la primera vista (`entry`, `referrer_host`, `utm_*`, `ref`, click ids, `device`, `locale`, `consent`, `is_bot`, `is_internal`)
     */
    public function current(string $visitorId, Request $request, bool $create, array $entry = []): ?AnalyticsSession
    {
        $memo = $request->attributes->get(self::MEMO_KEY);

        if ($memo instanceof AnalyticsSession && $memo->visitor_id === $visitorId) {
            return $memo;
        }

        $session = AnalyticsSession::query()
            ->where('visitor_id', $visitorId)
            ->where('last_seen_at', '>=', now()->subMinutes(AnalyticsSession::IDLE_MINUTES))
            ->orderByDesc('last_seen_at')
            ->first();

        if ($session !== null && $create && $this->startsANewCampaign($session, $entry)) {
            $session = null;
        }

        if ($session === null && $create) {
            $session = $this->open($visitorId, $entry);
        }

        if ($session !== null) {
            $request->attributes->set(self::MEMO_KEY, $session);
        }

        return $session;
    }

    /** Alarga la sesión hasta ahora, escribiendo como mucho una vez por minuto. */
    public function touch(AnalyticsSession $session): void
    {
        if ($session->last_seen_at->lt(now()->subMinute())) {
            $session->forceFill(['last_seen_at' => now()])->save();
        }
    }

    /**
     * El PRIMER toque no directo del visitante en los últimos `$days` días: la sesión más antigua con una
     * fuente que no sea «directo». `null` si no hay ninguna (entonces manda el último toque).
     *
     * @return array{source: string, medium: string, campaign: ?string}|null
     */
    public function firstTouch(string $visitorId, int $days): ?array
    {
        $session = AnalyticsSession::query()
            ->where('visitor_id', $visitorId)
            ->where('started_at', '>=', now()->subDays($days))
            ->where(function ($query): void {
                $query->whereNotNull('utm_source')
                    ->orWhereNotNull('ref')
                    ->orWhereNotNull('referrer_host')
                    ->orWhereNotNull('click_ids');
            })
            ->orderBy('started_at')
            ->first();

        return $session === null ? null : AttributionContext::touch($session);
    }

    /** @param array<string, mixed> $entry */
    private function open(string $visitorId, array $entry): AnalyticsSession
    {
        $now = now();

        return AnalyticsSession::query()->create([
            'visitor_id' => $visitorId,
            'started_at' => $now,
            'last_seen_at' => $now,
            'surface' => $entry['surface'] ?? 'web',
            'entry_route' => $entry['entry'] ?? null,
            'referrer_host' => $entry['referrer_host'] ?? null,
            'utm_source' => $entry['utm_source'] ?? null,
            'utm_medium' => $entry['utm_medium'] ?? null,
            'utm_campaign' => $entry['utm_campaign'] ?? null,
            'utm_content' => $entry['utm_content'] ?? null,
            'utm_term' => $entry['utm_term'] ?? null,
            'ref' => $entry['ref'] ?? null,
            'click_ids' => $entry['click_ids'] ?? null,
            'device' => $entry['device'] ?? null,
            'locale' => $entry['locale'] ?? null,
            'consent' => $entry['consent'] ?? null,
            'is_bot' => (bool) ($entry['is_bot'] ?? false),
            'is_internal' => (bool) ($entry['is_internal'] ?? false),
        ]);
    }

    /**
     * Una vista con `utm_source` distinto del de la sesión abierta (o con un click id nuevo) es otra
     * campaña: se abre sesión aunque la anterior siga viva.
     *
     * @param  array<string, mixed>  $entry
     */
    private function startsANewCampaign(AnalyticsSession $session, array $entry): bool
    {
        $incoming = $entry['utm_source'] ?? null;

        if (is_string($incoming) && $incoming !== '' && $incoming !== $session->utm_source) {
            return true;
        }

        $clickIds = $entry['click_ids'] ?? null;

        return is_array($clickIds) && $clickIds !== [] && $clickIds !== ($session->click_ids ?? []);
    }
}

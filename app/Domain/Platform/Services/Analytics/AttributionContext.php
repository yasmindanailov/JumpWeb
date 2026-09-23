<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Models\AnalyticsSession;
use Illuminate\Http\Request;

/**
 * **DE DÓNDE VIENE LO QUE ESTÁ PASANDO EN ESTA PETICIÓN** (`docs/specs/analitica.md` §4.1).
 *
 * Un singleton DE PETICIÓN (`scoped`) que sabe el canal, el visitante, su sesión y la atribución, y que
 * el `creating` de `Order` copia en el sello sin hacer una sola consulta. Quien lo rellena depende de por
 * dónde entra el trabajo:
 *
 *  - la web y la API: `ResolveVisitor` deja la petición y el contexto se resuelve PEREZOSAMENTE —una
 *    petición de catálogo no paga ninguna consulta por esto (`ApiOverheadTest`)—; el controlador que crea
 *    pedidos llama a {@see resolve()} ANTES de entrar en el dominio, para que el sello no consulte dentro
 *    del lock de aforo;
 *  - el asistente de «Crear pedido» del panel: {@see forPanel()} con la fuente que eligió el operador.
 *    Sin esto, el observador heredaría la cookie y la campaña del NAVEGADOR DEL OPERADOR
 *    (spec §7.1, dinero-4, medicion-9);
 *  - consola, jobs y verificadores: {@see system()}, que es también el estado inicial.
 *
 * ⚠️ **Dos capas en el sello** (rgpd-5): la de campaña va siempre; `visitor_id`, `session_id` y los click
 * ids solo si el visitante consintió la categoría que los cubre (`analytics` para el id, `marketing` para
 * los click ids). Sin consentimiento, el pedido sabe su campaña y no sabe quién navegó.
 */
class AttributionContext
{
    public const CHANNEL_WEB = 'web';

    public const CHANNEL_APP = 'app';

    public const CHANNEL_PANEL = 'panel';

    public const CHANNEL_SYSTEM = 'system';

    /**
     * Por dónde llega un pedido que teclea el operador (T1d): lo elige él en el asistente, obligatorio y sin
     * valor por defecto — un «mostrador» preseleccionado etiquetaría por teléfono lo que entró por correo.
     *
     * @var list<string>
     */
    public const PANEL_SOURCES = ['phone', 'counter', 'email', 'other'];

    /** Días hacia atrás en los que se busca el PRIMER toque no directo de un visitante. */
    public const FIRST_TOUCH_DAYS = 30;

    private string $channel = self::CHANNEL_SYSTEM;

    private ?Request $request = null;

    private ?string $source = null;

    private ?int $operatorId = null;

    private bool $resolved = false;

    private ?string $visitorId = null;

    private ?AnalyticsSession $session = null;

    /** @var array<string, string>|null */
    private ?array $firstTouch = null;

    public function __construct(private readonly SessionResolver $sessions) {}

    /** La petición web o de API de la que sale el contexto. No consulta nada todavía. */
    public function resolveFrom(Request $request): void
    {
        $this->request = $request;
        $this->channel = $request->bearerToken() !== null ? self::CHANNEL_APP : self::CHANNEL_WEB;
        $this->resolved = false;
        $this->session = null;
        $this->firstTouch = null;
        $this->visitorId = Visitor::fromRequest($request);
    }

    /**
     * Un pedido que teclea el operador: el canal es el panel y la fuente, la que eligió.
     *
     * @throws \InvalidArgumentException con una fuente fuera de {@see PANEL_SOURCES}: el asistente la valida
     *                                   antes, y esto es lo que impide que un valor tecleado llegue al sello.
     */
    public function forPanel(string $source, ?int $operatorId = null): void
    {
        if (! in_array($source, self::PANEL_SOURCES, true)) {
            throw new \InvalidArgumentException("«{$source}» no es una fuente del panel");
        }

        $this->request = null;
        $this->channel = self::CHANNEL_PANEL;
        $this->source = $source;
        $this->operatorId = $operatorId;
        $this->resolved = true;
        $this->session = null;
        $this->firstTouch = null;
        $this->visitorId = null;
    }

    /** Consola, cola, verificadores: nadie navega. */
    public function system(): void
    {
        $this->request = null;
        $this->channel = self::CHANNEL_SYSTEM;
        $this->source = null;
        $this->operatorId = null;
        $this->resolved = true;
        $this->session = null;
        $this->firstTouch = null;
        $this->visitorId = null;
    }

    /**
     * Resuelve la sesión y el primer toque (dos consultas, una vez). Llámalo desde el controlador que
     * va a crear un pedido; si nadie lo hace, el sello lo llamará él mismo y pagará las consultas.
     */
    public function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->resolved = true;

        if ($this->request === null || $this->visitorId === null) {
            return;
        }

        $this->session = $this->sessions->current($this->visitorId, $this->request, create: false);
        $this->firstTouch = $this->sessions->firstTouch($this->visitorId, self::FIRST_TOUCH_DAYS);
    }

    public function channel(): string
    {
        return $this->channel;
    }

    public function visitorId(): ?string
    {
        return $this->visitorId;
    }

    public function sessionId(): ?int
    {
        $this->resolve();

        return $this->session?->id;
    }

    /** ¿Consintió el visitante esta categoría? Sin sesión (o sin foto de consentimiento), no. */
    public function consented(string $category): bool
    {
        $this->resolve();

        $consent = $this->session?->consent;

        return (bool) (($consent ?? [])[$category] ?? false);
    }

    /**
     * **El sello**, listo para copiar en el pedido: las cuatro columnas planas y el JSON.
     *
     * @return array{attribution_channel: string, attribution_source: ?string, attribution_medium: ?string, attribution_campaign: ?string, attribution: array<string, mixed>}
     */
    public function seal(): array
    {
        $this->resolve();

        if ($this->channel === self::CHANNEL_PANEL) {
            return [
                'attribution_channel' => self::CHANNEL_PANEL,
                'attribution_source' => $this->source,
                'attribution_medium' => 'offline',
                'attribution_campaign' => null,
                'attribution' => array_filter(['operator_id' => $this->operatorId], static fn ($v): bool => $v !== null),
            ];
        }

        if ($this->channel === self::CHANNEL_SYSTEM || $this->session === null) {
            return [
                'attribution_channel' => $this->channel,
                'attribution_source' => null,
                'attribution_medium' => null,
                'attribution_campaign' => null,
                'attribution' => [],
            ];
        }

        $session = $this->session;
        $last = self::touch($session);
        $first = $this->firstTouch ?? $last;

        $json = array_filter([
            'content' => $session->utm_content,
            'term' => $session->utm_term,
            'ref' => $session->ref,
            'entry_route' => $session->entry_route,
            'referrer_host' => $session->referrer_host,
            'device' => $session->device,
            'locale' => $session->locale,
            'first_touch' => $first,
            'last_touch' => $last,
            'consent' => $session->consent,
            // Los identificadores, SOLO con la categoría que los cubre.
            'visitor_id' => $this->consented('analytics') ? $session->visitor_id : null,
            'session_id' => $this->consented('analytics') ? $session->id : null,
            'click_ids' => $this->consented('marketing') && $session->click_ids !== null && $session->click_ids !== [] ? $session->click_ids : null,
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== []);

        return [
            'attribution_channel' => $this->channel,
            'attribution_source' => $first['source'] ?? null,
            'attribution_medium' => $first['medium'] ?? null,
            'attribution_campaign' => $first['campaign'] ?? null,
            'attribution' => $json,
        ];
    }

    /**
     * La fuente, el medio y la campaña de una sesión, con las reglas del contrato: `gclid` es `google/cpc`
     * aunque no venga `utm`; un `ref` sin `utm_source` es la fuente; sin nada, `direct`.
     *
     * @return array{source: string, medium: string, campaign: ?string}
     */
    public static function touch(AnalyticsSession $session): array
    {
        $clickIds = $session->click_ids ?? [];

        if ($session->utm_source !== null) {
            return [
                'source' => $session->utm_source,
                'medium' => $session->utm_medium ?? (isset($clickIds['gclid']) ? 'cpc' : 'referral'),
                'campaign' => $session->utm_campaign,
            ];
        }

        if (isset($clickIds['gclid'])) {
            return ['source' => 'google', 'medium' => 'cpc', 'campaign' => $session->utm_campaign];
        }

        if ($session->ref !== null) {
            return ['source' => $session->ref, 'medium' => 'referral', 'campaign' => null];
        }

        if ($session->referrer_host !== null) {
            return ['source' => $session->referrer_host, 'medium' => 'referral', 'campaign' => null];
        }

        return ['source' => 'direct', 'medium' => 'none', 'campaign' => null];
    }
}

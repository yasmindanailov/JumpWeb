<?php

namespace App\Domain\Booking\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * **Una PROMOCIÓN** (`docs/specs/promociones.md`, `DECISIONES #684` y `#770`): un texto en es/en/fr vinculado a un
 * producto, una zona o toda la instalación, que sale SOLO en los sitios de su objetivo que dibuja el mockup.
 *
 * ⚠️⚠️ **No es dinero.** Una promoción no cambia ningún precio: un descuento de verdad sigue siendo un hecho de precio
 * (`promo.percent`, `#628`) y va por `CRITICAL_RE`. El texto «−20 % si reservas online» es una promoción; que la entrada
 * cueste menos lo dicen los precios.
 *
 * ⚠️ «Hoy» es el día del PARQUE (`DisplayTime::today()`): entre las dos medianoches el del contenedor no es el mismo, y
 * una oferta que «acaba el 30» dura el 30 entero.
 *
 * @property string $kind
 * @property array<string, string> $text
 * @property ?int $zone_id
 * @property ?int $ticket_type_id
 * @property ?Carbon $starts_on
 * @property ?Carbon $ends_on
 * @property bool $is_active
 * @property int $position
 */
class Promotion extends Model
{
    use HasTranslations;

    /** Una OFERTA: con fecha de fin obligatoria, la etiqueta encima del precio que cambia (`OfferTag`). */
    public const KIND_OFFER = 'offer';

    /** Un REGALO: lo que el parque da sin cobrar (`#589`); permanente salvo que se le ponga fecha. */
    public const KIND_GIFT = 'gift';

    public const KINDS = [self::KIND_OFFER, self::KIND_GIFT];

    public const TARGET_INSTALLATION = 'installation';

    public const TARGET_ZONE = 'zone';

    public const TARGET_PRODUCT = 'product';

    public const TARGETS = [self::TARGET_INSTALLATION, self::TARGET_ZONE, self::TARGET_PRODUCT];

    protected $guarded = [];

    /** Los valores por defecto de la tabla, también en el modelo recién creado (sin ellos, `state()` la daba apagada). */
    protected $attributes = [
        'is_active' => true,
        'position' => 0,
    ];

    protected $casts = [
        'text' => 'array',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        /*
         * Las dos reglas que ningún formulario puede saltarse, en el MODELO: el panel las valida antes, pero un seeder,
         * una importación o un `tinker` escriben aquí igual. Una oferta sin fin no es una oferta —es un precio que nadie
         * va a quitar— y una promoción con dos objetivos no sabría dónde salir.
         */
        static::saving(function (Promotion $promocion): void {
            if (! in_array($promocion->kind, self::KINDS, true)) {
                throw new InvalidArgumentException("«{$promocion->kind}» no es una clase de promoción.");
            }
            if ($promocion->zone_id !== null && $promocion->ticket_type_id !== null) {
                throw new InvalidArgumentException('Una promoción va a UN objetivo: una zona o un producto, no los dos.');
            }
            if ($promocion->kind === self::KIND_OFFER && $promocion->ends_on === null) {
                throw new InvalidArgumentException('Una oferta necesita su fecha de fin.');
            }
            if ($promocion->starts_on !== null && $promocion->ends_on !== null && $promocion->ends_on->lt($promocion->starts_on)) {
                throw new InvalidArgumentException('La fecha de fin es anterior a la de inicio.');
            }
        });
    }

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** @return BelongsTo<TicketType, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    /** A qué va: `installation`, `zone` o `product` (se deriva de las dos claves, que son la verdad). */
    public function target(): string
    {
        return match (true) {
            $this->ticket_type_id !== null => self::TARGET_PRODUCT,
            $this->zone_id !== null => self::TARGET_ZONE,
            default => self::TARGET_INSTALLATION,
        };
    }

    /**
     * **Vigentes HOY** (del parque): activas y con hoy dentro de sus fechas —sin inicio, ya; sin fin, siempre—.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeCurrent(Builder $query, ?Carbon $hoy = null): Builder
    {
        $dia = ($hoy ?? DisplayTime::today())->toDateString();

        return $query
            ->where($query->qualifyColumn('is_active'), true)
            ->where(fn (Builder $q) => $q->whereNull($q->qualifyColumn('starts_on'))->orWhere($q->qualifyColumn('starts_on'), '<=', $dia))
            ->where(fn (Builder $q) => $q->whereNull($q->qualifyColumn('ends_on'))->orWhere($q->qualifyColumn('ends_on'), '>=', $dia));
    }

    /**
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeGifts(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('kind'), self::KIND_GIFT);
    }

    /**
     * **El orden de un apilado** (`#770`, «todas, una encima de otra»): la que acaba antes primero —es la que más prisa
     * da—, las que no acaban al final, y dentro de eso juntas por objetivo y en el orden del panel. Agrupar por objetivo
     * no cambia el apilado de ningún sitio (cada uno pinta las de SU objetivo) y deja el listado del panel legible: los
     * regalos de un pack, seguidos.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeStacked(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN '.$query->qualifyColumn('ends_on').' IS NULL THEN 1 ELSE 0 END')
            ->orderBy($query->qualifyColumn('ends_on'))
            ->orderBy($query->qualifyColumn('zone_id'))
            ->orderBy($query->qualifyColumn('ticket_type_id'))
            ->orderBy($query->qualifyColumn('position'))
            ->orderBy($query->qualifyColumn('id'));
    }

    /**
     * El estado para el panel: `inactive` (apagada a mano), `scheduled` (empieza más adelante), `ended` (ya acabó) o
     * `current`. Con la misma regla que {@see scopeCurrent()}.
     */
    public function state(?Carbon $hoy = null): string
    {
        $dia = ($hoy ?? DisplayTime::today())->toDateString();

        return match (true) {
            ! $this->is_active => 'inactive',
            $this->starts_on !== null && $this->starts_on->toDateString() > $dia => 'scheduled',
            $this->ends_on !== null && $this->ends_on->toDateString() < $dia => 'ended',
            default => 'current',
        };
    }

    /**
     * **El texto en ESE idioma, sin respaldo** —o `null`—. ⚠️ Es la lectura de una OFERTA: una oferta sin traducir no
     * sale en ese idioma (spec §0), porque la página le pone su fecha en el idioma de la visita y medio anuncio en
     * español dentro de una frase en inglés se lee roto. Un REGALO sí cae al respaldo ({@see tr()}), como hacía `gifts`.
     */
    public function textIn(string $idioma): ?string
    {
        $texto = trim((string) ($this->text[$idioma] ?? ''));

        return $texto === '' ? null : $texto;
    }
}

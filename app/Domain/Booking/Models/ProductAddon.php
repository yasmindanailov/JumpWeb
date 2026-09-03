<?php

namespace App\Domain\Booking\Models;

use App\Domain\Booking\Services\MixedPartySettings;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivote `product_addons` con casts tipados (feature de complementos avanzados).
 *
 * Lleva la configuración de CÓMO se ofrece un complemento dentro de un producto concreto
 * (config por enganche, no global). Modelarlo como Pivot propio (`->using(ProductAddon::class)`)
 * permite leer `$addon->pivot->is_included` como `bool` real, `included_quantity` como `int`, etc.,
 * en vez de los `0/1` crudos que devuelve `withPivot` sin casts.
 *
 * La tabla tiene `id` autoincremental (no es un pivote compuesto puro) y NO tiene timestamps.
 */
class ProductAddon extends Pivot
{
    protected $table = 'product_addons';

    public $incrementing = true;

    public $timestamps = false;

    /** Modos de cantidad de un complemento incluido. */
    public const MODE_FIXED = 'fixed';       // cantidad propia + extras opcionales (p. ej. la tarta)

    public const MODE_PER_GUEST = 'per_guest'; // una unidad por invitado del pack (p. ej. el menú)

    /**
     * FASE de venta de este enganche (`specs/complementos-post-reserva.md` §4.1, `#413`).
     *
     * Dice **cuándo se VENDE**, no «dónde lo ve el cliente», y esa precisión es la que hace
     * demostrable la propiedad de §1.3: un `postform` **no nace nunca con el pedido** —tampoco en el
     * alta manual del mostrador—, así que su línea vale 0 al nacer (`LineFacts::birthValue`) y
     * quitarla es NEUTRO en dinero.
     *
     * ⚠️ **No existe un valor `both`**: obligaría a distinguir POR UNIDAD qué se cobró y qué no, que
     * es justo la ambigüedad que esta feature elimina. Entraría de forma aditiva si algún día hace falta.
     */
    public const STAGE_BOOKING = 'booking';   // se vende al reservar (los 29 enganches de hoy)

    public const STAGE_POSTFORM = 'postform'; // se añade DESPUÉS, desde el post-form del cliente

    /** @var list<string> Lista CERRADA: con sufijos libres ninguna guarda de paridad puede existir. */
    public const STAGES = [self::STAGE_BOOKING, self::STAGE_POSTFORM];

    protected $casts = [
        'position' => 'integer',
        'is_included' => 'boolean',
        'included_quantity' => 'integer',
        'is_mandatory' => 'boolean',
        'allow_extra' => 'boolean',
        'max_qty' => 'integer',
        'requires_addon_id' => 'integer',
        // El plazo de corte del complemento, en HORAS antes del inicio de la franja. `0` es un valor
        // VÁLIDO («hasta que empiece la fiesta») y distinto de `null` («este enganche no tiene
        // plazo», que solo es legal en `booking`): casteado para que un `'0'` de SQLite y un `0` de
        // MySQL no signifiquen cosas distintas — la trampa que `cajon-en-movil.md` §5.2 dejó escrita.
        'postform_cutoff_hours' => 'integer',
    ];

    /** ¿La cantidad sigue al nº de invitados del pack (no la toca el cliente)? */
    public function isPerGuest(): bool
    {
        return $this->quantity_mode === self::MODE_PER_GUEST;
    }

    /**
     * La fase de venta declarada, saneada contra la lista cerrada. Desconocida o ausente → `booking`.
     *
     * ⚠️⚠️ **Se llama `saleStage()` y no `stage()`, y no es cosmética**: un método con el MISMO nombre
     * que una columna hace que Eloquent lo tome por una relación al resolver el atributo, y un pivote
     * construido con atributos parciales —lo que hace `attach()` con `newPivot(..., false)`— revienta
     * con `Undefined property: $stage`. Medido: 105 casos en rojo con el nombre colisionando.
     *
     * ⚠️ Y por lo mismo se lee de `getAttributes()` y no de `$this->stage`: un pivote parcial no tiene
     * ese atributo, y `__get` volvería a entrar por la puerta de las relaciones.
     */
    public function saleStage(): string
    {
        $stage = $this->getAttributes()['stage'] ?? '';
        $stage = is_string($stage) ? $stage : '';

        return in_array($stage, self::STAGES, true) ? $stage : self::STAGE_BOOKING;
    }

    /** ¿Este enganche se vende DESPUÉS de reservar? */
    public function isPostFormStage(): bool
    {
        return $this->saleStage() === self::STAGE_POSTFORM;
    }

    /** El plazo de corte en horas antes del inicio de la franja; `null` = no declarado. */
    public function postformCutoffHours(): ?int
    {
        $hours = $this->postform_cutoff_hours;

        return $hours === null ? null : max(0, (int) $hours);
    }

    /**
     * ¿Por qué NO puede este enganche venderse en el post-form? `null` = configuración sana.
     *
     * **Punto ÚNICO de las nueve reglas** (`specs/complementos-post-reserva.md` §4.3), y por eso
     * devuelve un motivo en vez de lanzar: lo usan las DOS mitades del mismo contrato —
     *
     *  · el **guard** de {@see booted()}, que lo convierte en excepción al guardar el pivote por
     *    Eloquent (la cara amable la pone el formulario del panel, que esconde lo prohibido);
     *  · el **cinturón** de `AddonResolver`, que con una fila torcida por la puerta de atrás
     *    (`Query\Builder::update()`, SQL crudo o un seeder — los tres saltan los eventos, medido)
     *    **no la ofrece ni la vende**, jamás la degrada a «se vende normal».
     *
     * Cada prohibición cierra un agujero concreto y ninguna es estética:
     *  1. `is_mandatory` se **auto-inyecta** sin que nadie lo pida: sería una deuda creada sin un clic.
     *  2. `is_included` da unidades gratis, y `free_quantity > 0` rompe la igualdad
     *     `chargedSubtotalCents == Δ` de la que vive la propiedad de §1.3.
     *  3. `per_guest` ata la cantidad al nº de INVITADOS (los niños); el caso del owner es *para los
     *     adultos*: un número equivocado con aspecto de correcto.
     *  4. Un grupo excluyente **siempre tiene un elegido** y post-venta el estado normal es «ninguno»,
     *     que un grupo no sabe expresar.
     *  5. Un complemento que OCUPA aforo exigiría el lock de zona/día y la franja de aterrizaje: esta
     *     feature no toca aforo, y esa es la mitad de su coste.
     *  6. Un requisito de OTRA fase nunca estaría en la selección de ésta → el dependiente quedaría
     *     **invisible sin fallar**.
     *  7. *(Los PORTADORES de fiesta mixta se comprueban en el guard de escritura y NO aquí: son
     *     `is_sellable = false` e `is_active = false` por construcción, así que la relación `addons()`
     *     ya los deja fuera de toda lectura — y meter aquí su consulta costaba **dos consultas por
     *     complemento en el camino de lectura**, medido por el presupuesto del `GET` del post-form.
     *     Un cinturón que no puede alcanzar nada no vale su coste.)*
     *  8. `max_qty` es OBLIGATORIO (D3): el enlace del post-form se reenvía, así que la deuda máxima
     *     que un tercero puede crear tiene que estar declarada por el parque — y el tope de 20 que
     *     parece existir vive en `AddonResolver::viewModel()` como pista de UI, no como autoridad.
     *  9. El plazo es OBLIGATORIO (D10): `null` significaría «hereda el cierre del post-form», que es
     *     el predicado con el reloj torcido de §4.9 — y dejaría quitar un extra ya consumido.
     */
    public static function postFormProblem(self $pivot, ?TicketType $addon): ?string
    {
        if ($pivot->is_mandatory) {
            return 'is_mandatory';
        }
        if ($pivot->is_included) {
            return 'is_included';
        }
        if ($pivot->isPerGuest()) {
            return 'per_guest';
        }
        if ($pivot->choiceGroup() !== null) {
            return 'choice_group';
        }
        if ($addon !== null && $addon->occupiesAfterParent()) {
            return 'occupies_after_parent';
        }
        if ($pivot->max_qty === null || (int) $pivot->max_qty < 1) {
            return 'missing_max_qty';
        }
        if ($pivot->postformCutoffHours() === null) {
            return 'missing_cutoff';
        }

        return null;
    }

    /** ¿Es el producto uno de los dos portadores de fiesta mixta? (regla 7 de {@see postFormProblem}) */
    private static function isMixedPartyCarrier(int $addonId): bool
    {
        foreach ([MixedPartySettings::surchargeProduct(), MixedPartySettings::creditProduct()] as $carrier) {
            if ($carrier !== null && (int) $carrier->getKey() === $addonId) {
                return true;
            }
        }

        return false;
    }

    /**
     * La HORA EXTRA (`specs/hora-extra.md` §4.4·5 + §7·D2): un complemento que OCUPA no puede
     * engancharse con `per_guest` (impondría la hora extra a TODO el grupo, lo contrario del
     * encargo) ni como obligatorio (la auto-inyección convertiría «no cabe la hora extra» en «no se
     * puede vender el padre a esa hora», `AFORO-02` por otra puerta), ni colgar de un PACK (allí
     * «todos se quedan» es que la fiesta DURA MÁS — otro mecanismo — y el ocupante sería invisible
     * para `max_guests_per_slot`).
     *
     * Corre en `saving` del PIVOTE, así que cubre el alta y la edición del enganche por Eloquent;
     * su límite es el de siempre (`#299`: los eventos no ven `Query\Builder::update()`) y el
     * cinturón está en `AddonResolver::resolve()`, que re-valida las tres reglas al vender.
     */
    protected static function booted(): void
    {
        // La FASE de venta (`specs/complementos-post-reserva.md` §4.3, `#413`): las nueve reglas de
        // {@see postFormProblem} en su cara dura. La amable —esconder lo prohibido en el formulario—
        // la pone `AddonsRelationManager`, porque esto es una `InvalidArgumentException` y el
        // catálogo de Filament no la captura: llegar aquí desde la pantalla sería un error feo.
        static::saving(function (self $pivot): void {
            if (! $pivot->isPostFormStage()) {
                return;
            }

            $addon = TicketType::query()->find($pivot->addon_id);
            $problem = self::postFormProblem($pivot, $addon);

            // Regla 7, que vive SOLO en la escritura: un portador de fiesta mixta enganchado como
            // venta posterior dejaría que el cliente creara una línea de ese mismo producto SIN la
            // marca del ajuste — indistinguible en la ficha e invisible para el reconciliador mixto.
            // No está en el cinturón porque allí es inalcanzable (los portadores no son vendibles) y
            // costaba dos consultas por complemento en cada lectura del formulario.
            if ($problem === null && $addon !== null && self::isMixedPartyCarrier((int) $addon->getKey())) {
                $problem = 'mixed_party_carrier';
            }

            if ($problem !== null) {
                throw new \InvalidArgumentException(
                    "Un complemento de venta POSTERIOR no admite «{$problem}» "
                    .'(`specs/complementos-post-reserva.md` §4.3).'
                );
            }

            // Regla 6, la mitad que mira al OTRO lado: el requisito tiene que ser de la MISMA fase, o
            // el dependiente quedaría invisible sin fallar. Se consulta el pivote hermano del mismo
            // producto, que es donde vive la fase del requisito.
            $requires = $pivot->requiresAddonId();
            if ($requires !== null) {
                $required = self::query()
                    ->where('product_id', $pivot->product_id)
                    ->where('addon_id', $requires)
                    ->first();

                if ($required !== null && ! $required->isPostFormStage()) {
                    throw new \InvalidArgumentException(
                        'Un complemento de venta POSTERIOR no puede requerir a uno que se vende al '
                        .'reservar: su requisito nunca estaría en la selección de esta fase y quedaría '
                        .'invisible sin fallar (`specs/complementos-post-reserva.md` §4.3·6).'
                    );
                }
            }
        });

        static::saving(function (self $pivot): void {
            $addon = TicketType::query()->find($pivot->addon_id);
            if ($addon === null || ! $addon->occupiesAfterParent()) {
                return;
            }

            if ($pivot->isPerGuest() || $pivot->is_mandatory) {
                throw new \InvalidArgumentException(
                    'Un complemento que OCUPA (hora extra) no puede ser por-invitado ni obligatorio: '
                    .'la cantidad son las entradas que SE QUEDAN y la elige el cliente '
                    .'(`specs/hora-extra.md` §4.4·5).'
                );
            }

            $parent = TicketType::query()->find($pivot->product_id);
            if ($parent !== null && $parent->isPack()) {
                throw new \InvalidArgumentException(
                    'Un complemento que OCUPA (hora extra) no puede colgar de un PACK: en un pack '
                    .'«quedarse más» es que la fiesta dura más — otro mecanismo (`specs/hora-extra.md` §7·D2).'
                );
            }
        });
    }

    /**
     * Id del complemento REQUERIDO por este enganche (dependencia «requiere»), o null si es
     * independiente. El dependiente solo es seleccionable/vendible cuando el requerido también lo
     * está (lo aplica `AddonResolver` a punto fijo, en oferta y en cobro). `0`/vacío → sin requisito.
     */
    public function requiresAddonId(): ?int
    {
        $id = (int) ($this->requires_addon_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /** Clave de grupo de elección excluyente (o null si el complemento es independiente). */
    public function choiceGroup(): ?string
    {
        $group = is_string($this->choice_group) ? trim($this->choice_group) : '';

        return $group === '' ? null : $group;
    }
}

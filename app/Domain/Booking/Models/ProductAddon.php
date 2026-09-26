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
     * @var list<string> Lista CERRADA, hermana de {@see STAGES} y por el mismo motivo: es lo que
     *                   permite sanear un valor desconocido en vez de propagarlo (`#448`).
     */
    public const MODES = [self::MODE_FIXED, self::MODE_PER_GUEST];

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

    /** Los BLOQUES de la lista de invitados (F5, `#749`): la pregunta de la tarta y lo de los padres. */
    public const BLOCK_CAKE = 'cake';

    public const BLOCK_ADULTS = 'adults';

    /** @var list<string> Lista CERRADA, como {@see STAGES}. */
    public const POSTFORM_BLOCKS = [self::BLOCK_CAKE, self::BLOCK_ADULTS];

    protected $casts = [
        'position' => 'integer',
        'is_included' => 'boolean',
        'included_quantity' => 'integer',
        'is_mandatory' => 'boolean',
        'allow_extra' => 'boolean',
        // D12: si este enganche es «el menú» que la invitación digital enseña
        // (`specs/celebracion-e-invitacion.md` §4.4, `#573`). Casteado por lo mismo que
        // `postform_cutoff_hours`: un `'0'` de SQLite y un `0` de MySQL tienen que significar lo mismo.
        'show_in_invitation' => 'boolean',
        'max_qty' => 'integer',
        'requires_addon_id' => 'integer',
        // El plazo de corte del complemento, en HORAS antes del inicio de la franja. `0` es un valor
        // VÁLIDO («hasta que empiece la fiesta») y distinto de `null` («este enganche no tiene
        // plazo», que solo es legal en `booking`): casteado para que un `'0'` de SQLite y un `0` de
        // MySQL no signifiquen cosas distintas — la trampa que `cajon-en-movil.md` §5.2 dejó escrita.
        'postform_cutoff_hours' => 'integer',
    ];

    /**
     * ¿Este enganche es «el menú» que la invitación digital enseña? (D12, `#575`).
     *
     * ⚠️ Existe por lo mismo que {@see saleStage()} y {@see postformCutoffHours()}, y no es estilo: un
     * `$record->pivot?->show_in_invitation` es un acceso DINÁMICO que Larastan no puede resolver
     * —`property.notFound`— y que sumaría una entrada al trinquete de la línea base, **que solo
     * encoge**. Un método tipado lo resuelve y además da el valor saneado.
     */
    public function showsInInvitation(): bool
    {
        return (bool) $this->show_in_invitation;
    }

    /** ¿La cantidad sigue al nº de invitados del pack (no la toca el cliente)? */
    public function isPerGuest(): bool
    {
        return $this->quantityUnit() === self::MODE_PER_GUEST;
    }

    /**
     * La UNIDAD en la que se cuenta este complemento, saneada contra la lista cerrada
     * (`specs/hora-extra.md` §12.5, `#448`). Desconocida o ausente → `fixed`.
     *
     * ⚠️⚠️ **Se llama `quantityUnit()` y no `quantityMode()` por la misma razón que `saleStage()` no
     * se llama `stage()`**: un método homónimo de una columna hace que Eloquent lo tome por relación
     * al resolver el atributo, y un pivote construido con atributos parciales —lo que hace `attach()`
     * con `newPivot(..., false)`— revienta. Por eso también se lee de `getAttributes()`.
     *
     * ⚠️ **El saneo importa**: hasta `#448`, `isPerGuest()` comparaba la cadena CRUDA, así que un
     * valor torcido por `Query\Builder::update()` —la puerta que los eventos de Eloquent no ven— se
     * leía como `fixed` **en silencio**. Sigue cayendo a `fixed`, pero ahora por decisión escrita: es
     * el lado que trata la cantidad como BLOQUES, o sea el que reserva igual o más sala.
     */
    public function quantityUnit(): string
    {
        $mode = $this->getAttributes()['quantity_mode'] ?? '';
        $mode = is_string($mode) ? $mode : '';

        return in_array($mode, self::MODES, true) ? $mode : self::MODE_FIXED;
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

    /**
     * En qué BLOQUE de la lista de invitados va este enganche (F5 de `fiesta-sistema-nuevo.md` §4.11, `#749`): la
     * pregunta de la tarta, lo de los padres o —`null`— la rejilla de siempre. Es PRESENTACIÓN, no una regla de venta:
     * un valor desconocido, o puesto en un enganche que no se vende después, se lee `null` en vez de fallar. Por
     * `getAttributes()`, como {@see saleStage()}.
     */
    public function postformBlock(): ?string
    {
        if (! $this->isPostFormStage()) {
            return null;
        }
        $block = $this->getAttributes()['postform_block'] ?? null;

        return is_string($block) && in_array($block, self::POSTFORM_BLOCKS, true) ? $block : null;
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

    /**
     * ¿Es el producto uno de los dos portadores de fiesta mixta? (regla 7 de {@see postFormProblem}).
     *
     * ⚠️ **Público desde `#417`, y con un segundo consumidor que importa**: `AddonDateReconciler`
     * tiene que EXCLUIRLOS. Los portadores no tienen precio en catálogo ningún día, así que la regla
     * «sin precio ese día ⇒ retirar la línea» los retiraría en **todos** los cambios de fecha — y
     * `MixedPartySurcharge` los gobierna en ese mismo post-commit, o sea dos servicios peleando por
     * la misma línea con el dinero moviéndose dos veces (`specs/hora-extra.md` §9.8·H2).
     */
    public static function isMixedPartyCarrierId(int $addonId): bool
    {
        return self::isMixedPartyCarrier($addonId);
    }

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
        // ❗❗❗ **EL CANDADO DEL MODO** (`specs/hora-extra.md` §11.11·A1, `#443`). Cambiar
        // `quantity_mode` con líneas vivas en reservas todavía editables **re-precia una fiesta ya
        // vendida en la primera edición de cantidad**: `OrderItemEditor` re-escala los por-invitado
        // leyendo el pivote VIVO, así que una hija vendida como 1 bloque a 5,00 € pasaría a N
        // unidades a 5,00 € con su `recordEdit(+Δ)`. Es `PAY-19` («lo que se compró ayer no lo
        // reescribe el catálogo de mañana») roto por la puerta de la configuración.
        //
        // ⚠️⚠️ **Va en el DOMINIO y no solo en el formulario** (`SEC-04`): el candado de la pantalla
        // es la cara amable, pero el estado de un campo deshabilitado de Filament vive en el servidor
        // y se puede mover por Livewire. Quien decide es este guard.
        //
        // ⚠️ **No lo trae esta feature**: el agujero existe hoy para cualquier complemento que alguien
        // pase de `fixed` a `per_guest`. La hora extra por invitado solo le da un sujeto caro.
        //
        // ▶ La salida cuando hace falta cambiarlo YA es la que `#427` estableció: **un producto
        // nuevo** (que además es lo natural, porque el precio cambia de unidad). Editar el PRECIO
        // sigue siendo seguro: `unit_price` es histórico en la línea.
        static::saving(function (self $pivot): void {
            if (! $pivot->exists || ! $pivot->isDirty('quantity_mode')) {
                return;
            }
            if ($pivot->hasEditableSoldLines()) {
                throw new \InvalidArgumentException(
                    'Este enganche tiene reservas vendidas que todavía se pueden editar: cambiarle el '
                    .'modo de cantidad re-preciaría esas fiestas en el siguiente guardado del panel. '
                    .'Crea un complemento nuevo (`specs/hora-extra.md` §11.11·A1).'
                );
            }
        });

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
                    .'«quedarse más» es que la fiesta dura más — otro mecanismo (`specs/hora-extra.md` §7·D2). '
                    .'Para eso está `extends_parent_stay` (§10).'
                );
            }
        });

        // LA HORA EXTRA DE UN PACK (`specs/hora-extra.md` §10.3.1): el guard ESPEJO del de arriba.
        // Donde el ocupante tiene prohibido colgar de un pack, el extensor tiene prohibido colgar de
        // cualquier otra cosa — y las dos prohibiciones son la misma idea vista desde sus dos lados:
        // «quedarse más» significa cosas distintas en una entrada y en una fiesta.
        static::saving(function (self $pivot): void {
            $addon = TicketType::query()->find($pivot->addon_id);
            if ($addon === null || ! $addon->extendsParentStay()) {
                return;
            }

            $parent = TicketType::query()->find($pivot->product_id);
            if ($parent !== null && ! $parent->isPack()) {
                throw new \InvalidArgumentException(
                    'Un complemento que EXTIENDE la estancia solo puede colgar de un PACK: en una '
                    .'entrada, «quedarse más» son personas que se quedan y eso es `occupies_after_parent` '
                    .'(`specs/hora-extra.md` §10.3.1).'
                );
            }

            // Un INCLUIDO se auto-inyecta con su `included_quantity`, así que **toda fiesta nacería
            // alargada** sin que nadie lo pida — y una fiesta que dura más de serie no es un
            // complemento incluido: es un pack más largo (§7·D2, que sigue siendo cierto).
            // `is_mandatory` es lo mismo por otra puerta.
            //
            // ⚠️⚠️ **`per_guest` YA NO está aquí** (`#443`, §11.5.2): desde que los BLOQUES salen de
            // {@see \App\Domain\Booking\Services\AddonOccupancy::blocksFor()} y no de la cantidad, un
            // extensor por-invitado significa «una hora para toda la fiesta, cobrada por invitado» —
            // que es el encargo. Lo que hacía imposible la combinación no era el modo: era que los
            // minutos se derivaban de la cantidad, y una fiesta de 15 habría alargado la sala 900.
            if ($pivot->is_mandatory || $pivot->is_included) {
                throw new \InvalidArgumentException(
                    'Un complemento que EXTIENDE la estancia no puede ser obligatorio ni incluido: '
                    .'alargaría la fiesta sin que el cliente lo pida, y una fiesta que dura más de '
                    .'serie es un PACK más largo (`specs/hora-extra.md` §10.3.1).'
                );
            }

            // Vender aforo DESPUÉS de reservar exige el lock de zona/día y una revalidación que esta
            // fase no tiene (`complementos-post-reserva.md` §4.3·5, la misma puerta que el ocupante).
            if ($pivot->stage === self::STAGE_POSTFORM) {
                throw new \InvalidArgumentException(
                    'Un complemento que EXTIENDE la estancia no puede venderse DESPUÉS de reservar: '
                    .'mueve aforo, y esa fase no revalida cupo (`specs/hora-extra.md` §10.5·6).'
                );
            }

            // `#423` · A6: el tope del resolutor («no se quedan más de los que entran») no significa
            // nada para bloques de tiempo —con 20 invitados dejaría pedir 20 horas—, así que el tope
            // de un extensor es SUYO y tiene que existir. Es la misma regla que `#413` impuso a los
            // `postform` por el mismo motivo: sin tope declarado, el único freno sería el rechazo.
            //
            // ⚠️ **Salvo por-invitado** (`#443`, §11.5.2): allí la cantidad no la elige nadie —la fija
            // el nº de invitados— y `AddonResolver::effectiveQuantity()` **sale por `per_guest` ANTES
            // de mirar `max_qty`**, así que este tope no lo leería nadie. El tope real pasa a ser el
            // `max_qty` del PACK. *Exigir un número que nadie mira es peor que no exigirlo: parece
            // una defensa.*
            if ($pivot->isPerGuest()) {
                return;
            }
            if ($pivot->max_qty === null || (int) $pivot->max_qty < 1) {
                throw new \InvalidArgumentException(
                    'Un complemento que EXTIENDE la estancia necesita un máximo por reserva '
                    .'(`max_qty` >= 1): sin él, el único freno sería que el aforo lo rechace '
                    .'(`specs/hora-extra.md` §10.5·2).'
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

    /**
     * ¿Tiene este enganche líneas VIVAS **SIN SELLO** en reservas que todavía se pueden editar?
     *
     * ❗❗❗ **Es el candado del MODO** (`#443`), **RE-APUNTADO por el sello** (`specs/hora-extra.md`
     * §12.11, `#448`). El agujero que cerraba está medido: `OrderItemEditor` re-escalaba los
     * complementos por-invitado leyendo **el pivote VIVO**, así que pasar un enganche de `fixed` a
     * `per_guest` convertía la hija de una fiesta ya vendida de **1 bloque a 5,00 €** en **N unidades
     * a 5,00 €** en su primera edición de cantidad. Sobre las dos horas extra reales de producción,
     * **+35,00 €** y **+56,00 €** que nadie vendió: `PAY-19` roto por la puerta de la configuración.
     *
     * ▶ **Desde la T2 ese agujero ya no existe para una línea SELLADA**: las tres lecturas le
     * preguntan a la línea, no al catálogo. Así que el candado deja de preguntar «¿hay ventas vivas?»
     * y pregunta **«¿hay ventas vivas que aún no declaren su unidad?»** — un conjunto que **solo
     * puede encoger**, porque toda venta nueva nace sellada por las tres puertas.
     *
     * ❗❗ **Por qué se RE-APUNTA en vez de retirarse** (`[DECIDIDO owner, 2026-09-08]`): retirarlo
     * dejaría a las líneas sin sello —las vendidas antes del despliegue— expuestas al pivote vivo, y
     * el agujero se reabriría para ellas **en silencio**. Re-apuntado, el candado muere por
     * VACIAMIENTO y no por decreto: no hay ni un instante sin defensa, y cuando el conjunto quede
     * vacío no vuelve a cerrarse jamás.
     *
     * ⚠️⚠️ **Y esto cierra por sí solo el punto ciego que el candado tenía y nadie había escrito**:
     * filtraba `cancelled_at` pero **no miraba el estado del PEDIDO**, mientras el editor sí lo exige
     * (`editItemBlockedReason` → `order_not_operational`). Un carrito abandonado en `pending` cerraba
     * el candado sobre una línea que el editor jamás podría tocar —y `ExpireOrders` solo escribe
     * `orders.status`, así que esas hijas conservan `cancelled_at` nulo para siempre—. Ya no hace
     * falta mirar el estado: **esos carritos nacen sellados y dejan de contar por su cuenta**.
     * ▶ Y por eso NO se añade un filtro por estado, que además sería un error: un `pending` puede
     * pagarse después y volverse editable.
     *
     * ⚠️ **«Editable» y no «vendida»**: el editor rechaza una reserva ya celebrada (`item_finished`),
     * así que una fiesta pasada no puede re-escalarse.
     *
     * ⚠️ El predicado del fin es de dominio y vive en PHP (`isFinishedInPractice()`, que desde `#426`
     * mira inicio + duración efectiva en hora del parque): traducirlo a SQL sería una segunda copia
     * de la regla más delicada del calendario. Los candidatos son pocos —y desde `#448`, cada vez
     * menos— y se evalúan en memoria.
     */
    public function hasEditableSoldLines(): bool
    {
        $addonId = (int) $this->addon_id;
        $productId = (int) $this->product_id;
        if ($addonId <= 0 || $productId <= 0) {
            return false;
        }

        $parents = OrderItem::query()
            ->whereNull('cancelled_at')
            ->whereNull('parent_item_id')
            ->where('ticket_type_id', $productId)
            ->whereHas('children', fn ($q) => $q
                ->whereNull('cancelled_at')
                ->where('ticket_type_id', $addonId)
                // La mitad que el sello añade: una hija que declara su unidad ya no la reinterpreta
                // nadie, así que no hay nada que proteger en ella.
                ->whereNull('addon_quantity_mode'))
            ->with(['ticketType', 'slot'])
            ->get();

        foreach ($parents as $parent) {
            if (! $parent->isFinishedInPractice()) {
                return true;
            }
        }

        return false;
    }

    /** Clave de grupo de elección excluyente (o null si el complemento es independiente). */
    public function choiceGroup(): ?string
    {
        $group = is_string($this->choice_group) ? trim($this->choice_group) : '';

        return $group === '' ? null : $group;
    }
}

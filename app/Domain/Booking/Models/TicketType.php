<?php

namespace App\Domain\Booking\Models;

use App\Domain\Booking\Contracts\CelebrantAgeMismatch;
use App\Domain\Booking\Exceptions\OverlappingAgeRangeException;
use App\Domain\Booking\Services\AddonResolver;
use App\Domain\Booking\Services\ProductIcon;
use App\Domain\Content\Models\LandingService;
use App\Domain\Platform\Concerns\HasTranslations;
use App\Domain\Platform\Services\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class TicketType extends Model
{
    use HasTranslations;

    /**
     * Disco de la FOTO de la ficha (`#632` P1, T6 del menú de hechos). Es `public/uploads`:
     * gitignorado y excluido del `rsync --delete` del despliegue, o sea el hueco de la instalación.
     * El mismo que usan `Offer` y `BarImage`, y por el mismo motivo: lo que sube la clienta no entra
     * en el repo del producto.
     */
    public const IMAGE_DISK = 'uploads';

    /** Tipos de producto del catálogo unificado (Decisión A, §2 del plan). */
    public const TYPE_ENTRY = 'entry';   // entrada (admisión por zona + duración)

    public const TYPE_PACK = 'pack';     // pack (cumpleaños): mín/máx + extras

    public const TYPE_ADDON = 'addon';   // complemento (no consume aforo)

    /** Unidad de la ANTELACIÓN MÍNIMA de reserva por producto (auditoría Fase 1). */
    public const UNIT_DAYS = 'days';     // días de calendario ("el mismo día no")

    public const UNIT_HOURS = 'hours';   // horas rodantes (la franja debe empezar ≥ ahora + N h)

    /**
     * Columnas del pivote `product_addons` que exponen la config por-enganche de un complemento
     * (incluido/obligatorio/excluyente). Compartidas por `addons()` y `configurableAddons()` para
     * que el flujo de compra y el panel lean siempre los mismos datos.
     */
    public const ADDON_PIVOT_COLUMNS = [
        'position',
        'is_included',
        'included_quantity',
        'is_mandatory',
        'quantity_mode',
        'allow_extra',
        'choice_group',
        'max_qty', // P9: tope opcional de cantidad (null = sin límite). Solo aplica a `fixed`.
        'requires_addon_id', // dependencia «requiere»: id del complemento que debe estar elegido (null = ninguno).
        // La FASE de venta y su plazo (`specs/complementos-post-reserva.md` §4.1 y §4.6, `#413`).
        // ⚠️⚠️ **Si una columna del pivote NO está en esta lista, se cae en silencio al enganchar**:
        // la acción de Filament escribe `Arr::only($data, $relationship->getPivotColumns())`, que es
        // exactamente esto — y `AddonResolver` la leería `null` sin avisar. Es la primera de las tres
        // listas blancas que hay entre el formulario del panel y la fila (§4.7·ter).
        'stage',
        'postform_cutoff_hours',
        // D12 (`#574`): si este enganche es «el menú» que la invitación digital enseña.
        'show_in_invitation',
    ];

    /** Tipos de SEÑAL configurable para packs (#83). */
    public const DEPOSIT_NONE = 'none';        // pago total (sin señal)

    public const DEPOSIT_PERCENT = 'percent';  // deposit_value % del total

    public const DEPOSIT_FIXED = 'fixed';      // deposit_value céntimos

    /**
     * Fase de captura de un campo de `event_fields` (#217): en la RESERVA (datos básicos que el
     * cliente sí conoce al comprar) o en el POST-FORM (datos que rellena después, p. ej. nº
     * aproximado de adultos / observaciones). Por defecto `booking` → compatibilidad total con los
     * campos existentes. La compra solo muestra/valida los `booking`; el post-form, los `postform`.
     */
    public const EVENT_STAGE_BOOKING = 'booking';

    public const EVENT_STAGE_POSTFORM = 'postform';

    /**
     * Tope defensivo de longitud de UNA respuesta del cliente (#217): muy holgado, no trunca
     * respuestas realistas (nombres, alergias, observaciones…); solo blinda contra inflar la
     * columna JSON con un valor gigante. Se aplica server-side a `event_data` y `guest_data`.
     */
    public const ANSWER_MAX_LENGTH = 2000;

    /**
     * Tipos de campo de un esquema data-driven (`event_fields` y `guest_fields`). **Fuente ÚNICA**:
     * hasta el 2026-08-29 esta lista estaba copiada en tres sitios (el modelo, el saneo del panel y
     * el `Select` del formulario) y ninguno sabía de los otros — añadir un tipo exigía acertar los
     * tres. Cualquier tipo nuevo entra aquí y en `fieldTypeLabelKey()`, y nada más.
     */
    public const FIELD_TYPE_TEXT = 'text';

    public const FIELD_TYPE_NUMBER = 'number';

    public const FIELD_TYPE_TEXTAREA = 'textarea';

    /**
     * **La EDAD del invitado** (`docs/specs/cumple-mixto.md` §9·3). No es un `number` con otro
     * rótulo: es el campo del que el sistema deriva a qué producto de la familia corresponde cada
     * niño, y de ahí sale un cobro. Por eso lo declara el ESQUEMA —no una convención sobre la
     * clave, que un renombrado rompería en silencio— y por eso su saneo tiene cota real (§8.6: el
     * `number` de hoy solo borra los no-dígitos, así que un «999» entra tal cual).
     */
    public const FIELD_TYPE_AGE = 'age';

    /**
     * **La EDAD DEL CUMPLEAÑERO** (`DECISIONES #588`, `[DECIDIDO owner]`). No es la `age` por
     * invitado: es UNA por fiesta, se pide al RESERVAR y decide si el pack es el suyo —con la edad
     * fuera del tramo del pack la web no deja reservarlo (`celebrantAgeMismatch()`)—. Como la otra,
     * la declara el ESQUEMA y no una convención sobre la clave, y su saneo tiene cota real.
     */
    public const FIELD_TYPE_CELEBRANT_AGE = 'celebrant_age';

    /** Todos los tipos que el producto conoce. **No es la lista que acepta cada esquema**: ver abajo. */
    /** @var list<string> */
    public const FIELD_TYPES = [
        self::FIELD_TYPE_TEXT,
        self::FIELD_TYPE_NUMBER,
        self::FIELD_TYPE_TEXTAREA,
        self::FIELD_TYPE_AGE,
        self::FIELD_TYPE_CELEBRANT_AGE,
    ];

    /**
     * Los tipos que acepta el esquema de datos del EVENTO (`event_fields`), que se piden UNA vez al
     * reservar. **La EDAD por invitado no está, y no es una omisión**: es un dato POR INVITADO del que
     * sale un cobro, y una sola edad para toda la fiesta no significa nada. La del CUMPLEAÑERO sí
     * está (`#588`): esa es una por fiesta.
     *
     * ⚠️⚠️ **Esta lista es también el CONTRATO de la API.** `GET /api/v1/catalog/products/{id}`
     * publica estos campos y `openapi/v1.yaml` los declara con `enum` cerrado y
     * `additionalProperties: false` — un tipo que llegue aquí y no esté en el contrato es una
     * respuesta inválida para un cliente estricto. Lo vigila `CatalogFieldTypesMatchContractTest`,
     * que compara las dos listas: añadir un tipo en un sitio y no en el otro pone la suite en rojo.
     *
     * @var list<string>
     */
    public const EVENT_FIELD_TYPES = [
        self::FIELD_TYPE_TEXT,
        self::FIELD_TYPE_NUMBER,
        self::FIELD_TYPE_TEXTAREA,
        self::FIELD_TYPE_CELEBRANT_AGE,
    ];

    /** Los que acepta el esquema POR INVITADO (`guest_fields`), el único donde la EDAD significa algo. */
    /** @var list<string> */
    public const GUEST_FIELD_TYPES = [
        self::FIELD_TYPE_TEXT,
        self::FIELD_TYPE_NUMBER,
        self::FIELD_TYPE_TEXTAREA,
        self::FIELD_TYPE_AGE,
    ];

    /**
     * Los tipos válidos de UNO de los dos esquemas. Fuente única para las TRES puertas por las que
     * entra un esquema —el `Select` del panel, el saneo del panel y el del modelo—, que hasta el
     * 2026-08-30 usaban la lista COMPLETA en los dos esquemas y solo el `Select` distinguía.
     *
     * ▶ Ahí estaba el hueco: el formulario no ofrecía `age` en los datos del evento, pero **el saneo
     * lo aceptaba si llegaba**, que es justo lo que la regla 12 de este proyecto dice que no se puede
     * suponer. Medido: forzado, persistía y salía por la API contra su propio contrato.
     *
     * @return list<string>
     */
    public static function fieldTypesFor(bool $perGuest): array
    {
        return $perGuest ? self::GUEST_FIELD_TYPES : self::EVENT_FIELD_TYPES;
    }

    /**
     * Cota de una edad declarada en el post-form. No pretende ser una regla de negocio (el tramo lo
     * ponen `guest_age_min`/`guest_age_max` de cada producto): es la cota de SANEO, la que impide
     * que un «999» llegue a la aritmética de un suplemento. Fuera de rango = no respondido.
     */
    public const GUEST_AGE_MIN = 0;

    public const GUEST_AGE_MAX = 120;

    /**
     * Esquema POR DEFECTO de los datos por-niño de un pack de cumpleaños (#217): las 4 columnas
     * que la clienta pidió { nombre, alergia/intolerancia, observaciones, menú especial }. Es solo
     * el SEMBRADO inicial (lo siembra `LandingContentSeeder` en los packs) — el esquema es
     * data-driven y editable en el panel, así que la clienta puede añadir/quitar/renombrar columnas.
     * Mismo formato que `event_fields`: {key, type, required, label{es,en,fr}}.
     */
    public const DEFAULT_GUEST_FIELDS = [
        ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre del niño/a', 'en' => "Child's name", 'fr' => "Nom de l'enfant"]],
        ['key' => 'allergy', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Alergia / intolerancia', 'en' => 'Allergy / intolerance', 'fr' => 'Allergie / intolérance']],
        ['key' => 'notes', 'type' => 'textarea', 'required' => false, 'label' => ['es' => 'Observaciones', 'en' => 'Notes', 'fr' => 'Remarques']],
        ['key' => 'special_menu', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Menú especial', 'en' => 'Special menu', 'fr' => 'Menu spécial']],
    ];

    protected $guarded = [];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'period_label' => 'array',
        'features' => 'array',
        // La merienda de la invitación por grupos (F1b de `fiesta-sistema-nuevo.md`): tres listas i18n, como `features`.
        'menu_drink' => 'array',
        'menu_food' => 'array',
        'menu_sweet' => 'array',
        // ⚠️ Aquí estaba `gifts` (`#589`): los regalos viven en PROMOCIONES desde `#770` ({@see giftLines()}).
        'conditions' => 'array',
        'badge' => 'array',
        'event_fields' => 'array',
        'guest_fields' => 'array',
        // D15: si este producto ofrece INVITACIÓN DIGITAL (`specs/celebracion-e-invitacion.md` §4.4,
        // `#573`). Hermano de `guardian_authorization`: los dos contestan a «¿qué papeles pide?».
        'guest_invitation' => 'boolean',
        'tax_rate' => 'decimal:2',
        'duration_min' => 'integer',
        'seats_per_unit' => 'integer',
        'min_qty' => 'integer',
        'max_qty' => 'integer',
        'deposit_value' => 'integer',
        'available_after_open_min' => 'integer',
        'available_before_close_min' => 'integer',
        'min_advance_value' => 'integer',
        'prep_before_min' => 'integer',
        'prep_after_min' => 'integer',
        'guest_age_min' => 'integer',
        'guest_age_max' => 'integer',
        // Hasta cuántas horas antes se cambia o se cancela (`#699`): lo INFORMA, no lo aplica (cancela el
        // personal). La frase la escribe `CancellationCutoffRule`.
        'cancellation_cutoff_hours' => 'integer',
        // Lo que Mi cuenta DICE de lo reservado (`#775`): el aviso de un complemento ({@see reservationNote()}) y si la
        // señal se devuelve al cancelar en plazo. Datos de la instalación, no política del producto.
        'reservation_note' => 'array',
        'deposit_refundable_in_time' => 'boolean',
        'featured' => 'boolean',
        'is_sellable' => 'boolean',
        'is_active' => 'boolean',
        'occupies_after_parent' => 'boolean',
        'extends_parent_stay' => 'boolean',
    ];

    /**
     * Zona a la que da acceso (null = ambas zonas).
     *
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Sección editorial de /servicios que reubica ESTE pack a la superficie «Servicios» (#256,
     * modelo A). Si existe, el pack deja de anunciarse en la sección Cumpleaños
     * (`scopeBirthdaySurfacePacks` lo excluye con `whereDoesntHave('landingService')`). NULL = el
     * pack es de cumpleaños (defecto). Editorial; lo comercial sigue en este TicketType + su Zone.
     *
     * ▶ Desde `#588` es una tabla de enlace (un servicio vende varios productos), pero un producto
     * sigue estando en UN servicio como mucho: el índice único vive en `ticket_type_id`.
     *
     * @return BelongsToMany<LandingService, $this>
     */
    public function landingServices(): BelongsToMany
    {
        return $this->belongsToMany(LandingService::class, 'landing_service_products')->withTimestamps();
    }

    /**
     * Precios por tarifa (matriz de §4bis). Única fuente de verdad del precio.
     *
     * @return MorphMany<Price, $this>
     */
    public function prices(): MorphMany
    {
        return $this->morphMany(Price::class, 'priceable');
    }

    /**
     * `#324` — los TRAMOS de precio por cantidad (`docs/specs/precio-por-tramo.md`). Un producto sin
     * tramos no tiene filas y se comporta exactamente como antes: por eso esto es una tabla propia y
     * no filas extra en `prices`, que habrían cambiado el significado de `displayPriceCents()` y
     * `priceVaries()` sin que fallara nada.
     *
     * @return HasMany<PriceTier, $this>
     */
    public function priceTiers(): HasMany
    {
        return $this->hasMany(PriceTier::class);
    }

    /** Entrada sin límite de tiempo (duration_min vacío = ilimitada). */
    public function isUnlimited(): bool
    {
        return empty($this->duration_min);
    }

    /** ¿Es un pack (cumpleaños)? Aforo por cupo, no por plazas (#82). */
    public function isPack(): bool
    {
        return $this->type === self::TYPE_PACK;
    }

    /**
     * **El aviso de este complemento en la reserva del cliente** (`#775`, T5b de `specs/isla-y-landing-nueva.md`
     * §4.13), en el idioma activo y con la cantidad en `:n` —«Tenéis 2 pares de calcetines comprados; os los damos en
     * la puerta.»—. `null` si no hay texto: entonces Mi cuenta lo nombra con su cantidad, sin prometer nada.
     *
     * ⚠️ Lo escribe el panel de cada instalación: dónde se recoge un complemento es suyo, no del producto.
     */
    public function reservationNote(int $quantity): ?string
    {
        $nota = trim((string) $this->tr('reservation_note'));

        return $nota === '' ? null : str_replace(':n', (string) $quantity, $nota);
    }

    /**
     * **Los REGALOS del producto** (`#589`, `[DECIDIDO owner]`), en el idioma activo y sin vacíos: lo
     * que el parque da sin cobrar —«cono de chuches», «calcetines para todos»—, aparte de lo que
     * INCLUYE (`features`).
     *
     * ⚠️ Una sola normalización para todas las superficies —portada, `/cumpleanos`, `/servicios`, el
     * post-form, el panel y la API—: cada una pinta la lista tal cual, cada regalo en su etiqueta.
     * ▶ **Desde `#770` son PROMOCIONES** de clase regalo de este producto, vigentes hoy y en su orden
     * (`giftPromotions`): se gestionan en «Promociones» del panel y la forma de `gifts` no cambia. Cada
     * regalo cae al idioma de respaldo, como hacía la columna.
     * ⚠️ Quien recorre una LISTA de productos carga `giftPromotions` con ellos: sin eso, una consulta por
     * producto.
     *
     * @return list<string>
     */
    public function giftLines(): array
    {
        $regalos = $this->relationLoaded('giftPromotions') ? $this->giftPromotions : $this->giftPromotions()->get();

        return array_values(array_filter(
            $regalos->map(fn (Promotion $regalo): string => trim((string) $regalo->tr('text')))->all(),
            fn (string $gift): bool => $gift !== '',
        ));
    }

    /**
     * Los REGALOS de este producto que están vigentes hoy, en el orden del panel ({@see giftLines()}).
     *
     * @return HasMany<Promotion, $this>
     */
    public function giftPromotions(): HasMany
    {
        return $this->hasMany(Promotion::class, 'ticket_type_id')->gifts()->current()->orderBy('position')->orderBy('id');
    }

    /**
     * **Lo que INCLUYE el producto** (`features`), en el idioma activo y sin vacíos.
     *
     * ⚠️⚠️ **Un campo traducible llega de DOS formas según quién lo escribiera** —una lista o un texto
     * suelto—, y ésa es «la trampa de `features`» que la spec de la invitación cita (`#463`). Leerlo a
     * pelo y recorrerlo devuelve las LETRAS de la cadena cuando vino suelto.
     *
     * ▶ Nació gemelo de {@see giftLines()}, cuando los regalos eran una columna. Nace aquí, en el modelo,
     * porque ya era la cuarta copia de la misma regla —`CatalogReader`, `PostFormAddons`,
     * `CreateManualOrderPage`— y la quinta la pedía la invitación digital (`#521`). Los otros tres
     * siguen con la suya: migrarlos es otra tanda, y se anota en vez de hacerse de paso.
     *
     * @return list<string>
     */
    public function featureLines(): array
    {
        return $this->lineasDe('features');
    }

    /**
     * Los tres grupos de la merienda que la invitación pinta con su icono (F1b de `fiesta-sistema-nuevo.md`):
     * «para beber», «para comer» y «y para terminar», en el idioma activo y sin vacíos. Los tres vacíos = el
     * complemento no está repartido en grupos, y la invitación lo enseña por su nombre y sus ventajas.
     *
     * @return array{drink: list<string>, food: list<string>, sweet: list<string>}
     */
    public function invitationMenuGroups(): array
    {
        return ['drink' => $this->lineasDe('menu_drink'), 'food' => $this->lineasDe('menu_food'), 'sweet' => $this->lineasDe('menu_sweet')];
    }

    /**
     * Una lista i18n (`{es: […], …}`) leída en el idioma activo: cada línea recortada y sin las vacías.
     *
     * @return list<string>
     */
    private function lineasDe(string $campo): array
    {
        $lineas = $this->tr($campo);
        $lineas = is_array($lineas) ? $lineas : [$lineas];

        return array_values(array_filter(
            array_map(fn (mixed $linea): string => is_scalar($linea) ? trim((string) $linea) : '', $lineas),
            fn (string $linea): bool => $linea !== '',
        ));
    }

    /**
     * **La clave del icono que marca este producto** (`DECISIONES #140`).
     *
     * ⚠️ Lo decide {@see ProductIcon}, no cada superficie. La regla era un booleano —tarta si es
     * pack, entrada si no— escrito TRES veces: en un componente Blade sin llamantes y, con la
     * geometría entera copiada dentro, en dos componentes del cajón. Con ella, la tirolina, la tarta
     * y los calcetines eran los tres «un ticket».
     *
     * Nunca devuelve `null`: quien pinta un marcador siempre necesita uno, y dejar que cada
     * superficie resuelva su propio respaldo es exactamente cómo se llegó a las tres copias.
     */
    public function iconKey(): string
    {
        return ProductIcon::forProduct($this->icon, $this->isPack());
    }

    /**
     * **URL pública de la FOTO de la ficha, o `null` si esta instalación no subió ninguna**
     * (`#632` P1).
     *
     * ⚠️ `asset('uploads/'.…)` y no el `url()` del disco: `public/uploads` lo sirve el servidor web
     * de forma NATIVA, sin depender del symlink `public/storage` —que en producción está roto—. Es
     * la misma resolución, letra por letra, que `Offer::imageUrl()` y `BarImage::imageUrl()`.
     *
     * ⚠️ No comprueba que el fichero exista: una foto borrada a mano del disco daría una URL que da
     * 404, y eso es correcto —quien pinta decide qué hacer con una imagen que no carga—. Mirar el
     * disco aquí costaría una llamada de E/S por producto en la lista del catálogo.
     */
    public function imageUrl(): ?string
    {
        return $this->image ? asset('uploads/'.ltrim((string) $this->image, '/')) : null;
    }

    /**
     * ¿Es un PACK realmente COMPRABLE como oferta de /servicios (#256)? Coherencia CTA⟺catálogo
     * (#210/#226): vendible + activo, con PRECIO (>0) y en una ZONA OPERATIVA. Los packs venden por
     * aforo de ZONA: sin zona el sidebar no puede calcular cantidad (`maxQty()=0`) → no debe
     * anunciarse «Reservar». FUENTE ÚNICA para la card de /servicios (`LandingService::isPurchasable`)
     * y para el aviso del panel (`LandingServiceForm::packWarning`), para que no diverjan.
     */
    public function isSellablePackForLanding(): bool
    {
        return $this->isPack()
            && $this->is_sellable
            && $this->is_active
            && $this->zone_id !== null
            && ($this->zone?->is_active ?? false)
            && $this->hasPositivePrice();
    }

    /**
     * ¿Tiene algún precio > 0? Reutiliza la relación `prices` YA eager-loaded en la landing
     * (`/servicios` carga `ticketType.prices`) → 0 queries por servicio. Solo cae a un `EXISTS`
     * puntual cuando la relación no está cargada (p. ej. el aviso del panel, que carga el pack
     * con `->with('zone')` pero sin precios). Antes: siempre disparaba el `EXISTS` (N+1).
     */
    private function hasPositivePrice(): bool
    {
        if ($this->relationLoaded('prices')) {
            return $this->prices->contains(fn ($price) => (int) $price->amount_cents > 0);
        }

        return $this->prices()->where('amount_cents', '>', 0)->exists();
    }

    /**
     * ¿Cumple la ANTELACIÓN MÍNIMA de reserva para una franja que empieza en $date $startTime,
     * evaluada respecto a $now (zona operativa del parque)? (auditoría Fase 1)
     *
     *  - unidad `days`: regla de CALENDARIO — la fecha de visita debe ser ≥ hoy + N días (toda la
     *    jornada objetivo queda disponible; "el mismo día no" = N≥1).
     *  - unidad `hours`: regla RODANTE — el inicio de la franja debe ser ≥ ahora + N horas.
     *  - `min_advance_value = 0` → sin restricción (el corte intra-día sigue aplicando aparte).
     */
    public function meetsMinAdvance(string $date, string $startTime, Carbon $now): bool
    {
        $value = (int) $this->min_advance_value;
        if ($value <= 0) {
            return true;
        }

        if ($this->min_advance_unit === self::UNIT_HOURS) {
            return Carbon::parse($date.' '.$startTime, $now->getTimezone())
                ->gte($now->copy()->addHours($value));
        }

        // días (calendario): comparar FECHAS, no instantes, para que valga toda la jornada objetivo.
        return $date >= $now->copy()->addDays($value)->toDateString();
    }

    /**
     * ¿Es un complemento (add-on)? Históricamente neutro al aforo y sin franja (#87); desde la
     * hora extra (`specs/hora-extra.md`) uno puede declararse OCUPANTE — {@see occupiesAfterParent()}.
     */
    public function isAddon(): bool
    {
        return $this->type === self::TYPE_ADDON;
    }

    /**
     * ¿Este complemento OCUPA la franja siguiente al tramo de su padre? (la HORA EXTRA,
     * `specs/hora-extra.md` §4.1). Es el INTERRUPTOR declarado, explícito a propósito: derivarlo de
     * la duración haría que ponerle duración a una camiseta se comiera aforo en silencio.
     *
     * ⚠️ Que declare ocupar no es que PUEDA: la configuración sana la dice
     * {@see hasSaneOccupancyConfig()}, y un ocupante declarado con configuración rota **ni se
     * ofrece ni se vende** (el cinturón de §4.1) — jamás degrada a complemento neutro, porque eso
     * sería vender sin ocupar.
     */
    public function occupiesAfterParent(): bool
    {
        return $this->isAddon() && $this->occupies_after_parent === true;
    }

    /**
     * ¿La configuración de OCUPANTE es sana? Duración positiva (es CUÁNTO ocupa: nula significaría
     * «hasta el cierre» para `occupancyMap`, el peor modo de fallo posible) y al menos una plaza por
     * unidad (el diseño depende del default 1 que el formulario no enseña — §4.11·3).
     *
     * Fuente ÚNICA de la regla: la usan el guard de `booted()` (rechaza la escritura Eloquent) y el
     * cinturón del punto de composición (para lo que entre por `Query\Builder::update()`, que los
     * eventos del modelo no ven — el límite documentado de `#299`).
     */
    public function hasSaneOccupancyConfig(): bool
    {
        return $this->duration_min !== null
            && (int) $this->duration_min > 0
            && (int) ($this->seats_per_unit ?? 1) >= 1;
    }

    /**
     * ¿Este complemento EXTIENDE la estancia de su padre? (la hora extra de un PACK,
     * `specs/hora-extra.md` §10.3.1). Es el hermano de {@see occupiesAfterParent()} y **excluyente**
     * con él, no un modo suyo: los dos «prolongan la estancia», pero su UNIDAD es distinta —el
     * ocupante se vende por PERSONA y éste por BLOQUE DE TIEMPO—, y reinterpretar el mismo
     * complemento según el tipo del padre haría que su precio cambiara de unidad sin que nada lo
     * diga (`prices` es una tabla sola).
     *
     * ⚠️ Lo que extiende NO es «una franja más»: es la VENTANA de la fiesta, que sigue siendo UNA
     * —una sola fiesta, los mismos invitados, más rato—. Por eso su línea hija no lleva franja
     * propia ni plazas propias: las lleva el padre, alargado ({@see OrderItem::occupiedMinutes()}).
     */
    public function extendsParentStay(): bool
    {
        return $this->isAddon() && $this->extends_parent_stay === true;
    }

    /**
     * ¿La configuración de EXTENSOR es sana? Solo pide duración positiva: es CUÁNTO alarga, y una
     * duración nula significaría «alarga nada» —vender una hora extra que no ocupa— o, peor, entrar
     * en la aritmética de la ventana como `null`.
     *
     * ⚠️ **`seats_per_unit` no entra a propósito**, y ésa es la diferencia con
     * {@see hasSaneOccupancyConfig()}: un extensor no se queda con plazas propias, se queda con las
     * del padre durante más rato. Pedirle plazas sería la puerta al defecto (b) de §10.1 —la línea
     * hija que dice «1 persona» donde hay veinte—.
     *
     * Fuente ÚNICA de la regla: la usan el guard de `booted()` y el cinturón del punto de
     * composición (`AddonOccupancy::sellableStayExtension()`), para lo que entre por
     * `Query\Builder::update()`, que los eventos del modelo no ven.
     */
    public function hasSaneStayExtensionConfig(): bool
    {
        return $this->duration_min !== null && (int) $this->duration_min > 0;
    }

    /**
     * Complementos (type=addon) aplicables a este producto, configurados por producto en el panel
     * (#87, pivote `product_addons`). Solo vendibles y activos, en el orden definido.
     *
     * @return BelongsToMany<TicketType, $this>
     */
    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_addons', 'product_id', 'addon_id')
            ->using(ProductAddon::class)
            ->withPivot(self::ADDON_PIVOT_COLUMNS)
            ->where('ticket_types.type', self::TYPE_ADDON)
            ->where('ticket_types.is_sellable', true)
            ->where('ticket_types.is_active', true)
            ->orderBy('product_addons.position');
    }

    /**
     * Complementos enlazados a este producto para GESTIÓN en el panel (7.6 iter. 2):
     * a diferencia de `addons()`, NO filtra por vendible/activo (el operador debe ver y
     * poder quitar también los addons ocultos); solo restringe a `type=addon`. Expone la
     * `position` del pivote para ordenarlos.
     *
     * @return BelongsToMany<TicketType, $this>
     */
    public function configurableAddons(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_addons', 'product_id', 'addon_id')
            ->using(ProductAddon::class)
            ->where('ticket_types.type', self::TYPE_ADDON)
            ->withPivot(self::ADDON_PIVOT_COLUMNS)
            ->orderBy('product_addons.position');
    }

    /**
     * Los complementos de este producto que se venden **AL RESERVAR** (`#413` §4.4).
     *
     * Es `AddonResolver::forStage($this->addons, STAGE_BOOKING)` con nombre propio, y existe por una
     * razón de ARQUITECTURA, no de comodidad: la landing (`Content`) solo puede mirar a `Booking` a
     * través de sus contratos, y su única exención declarada es tipar `TicketType`. Que el filtro
     * viva aquí le deja nombrar el eje **sin arrastrar dos flechas nuevas** al grafo de módulos —
     * cuyas baselines SOLO ENCOGEN.
     *
     * ⚠️ Sigue siendo explícito en el punto de llamada, que es lo que la spec exige: el nombre dice
     * la fase. Lo que NO se hace es filtrar dentro de la relación `addons()`, que comparten doce
     * clases y una de ellas es el editor del panel.
     *
     * @return Collection<int, self>
     */
    public function addonsSoldAtBooking(): Collection
    {
        return AddonResolver::forStage($this->addons, ProductAddon::STAGE_BOOKING);
    }

    /**
     * El gemelo de {@see addonsSoldAtBooking()}: los complementos de este producto que se venden
     * **DESPUÉS de reservar**, desde el formulario de la reserva (`#413`).
     *
     * ▶ Lo pide `/cumpleanos` (`DECISIONES #528`), que enseña lo que se puede añadir después con su
     * precio y su plazo. Existe aquí por la misma razón que su gemelo: el nombre dice la FASE en el
     * punto de llamada y el filtro no se esconde dentro de la relación `addons()`.
     *
     * @return Collection<int, self>
     */
    public function addonsSoldAfterBooking(): Collection
    {
        return AddonResolver::forStage($this->addons, ProductAddon::STAGE_POSTFORM);
    }

    /**
     * Inverso de `configurableAddons` (claves de pivote intercambiadas): productos que
     * ENGANCHAN este complemento. Lo necesita el panel (7.6 iter. 2) para que la acción
     * "Añadir complemento" excluya los ya enganchados — sin declararlo, Filament intenta
     * adivinar la relación inversa de un pivote autorreferencial y falla (`ticketTypes()`).
     *
     * @return BelongsToMany<TicketType, $this>
     */
    public function addonOfProducts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_addons', 'addon_id', 'product_id');
    }

    /**
     * Importe a cobrar por adelantado (la SEÑAL) para un total dado, en céntimos (#83):
     * - `none`    → el total (pago completo).
     * - `percent` → deposit_value % del total (redondeado).
     * - `fixed`   → deposit_value céntimos.
     * Acotado siempre a [0, total] para no cobrar de más ni negativo.
     */
    public function depositCents(int $totalCents): int
    {
        // Sin señal REAL (none, o `fixed`/`percent` con `deposit_value=0`) → se cobra el TOTAL online,
        // idéntico a `none`. Coherente con `hasDeposit()`, que ya trata el valor 0 como "sin señal", y
        // con lo que se le anuncia al cliente (auditoría Fase 1, M1). Sin esta guarda, un `fixed`/
        // `percent` con valor 0 devolvía 0 → `onlineDueCents()=0` → `DS_MERCHANT_AMOUNT='0'` → Redsys
        // rechaza el cobro y el checkout queda roto con el aforo retenido sin aviso.
        if (! $this->hasDeposit()) {
            return $totalCents;
        }

        $deposit = match ($this->deposit_type) {
            self::DEPOSIT_PERCENT => (int) round($totalCents * (int) $this->deposit_value / 100),
            self::DEPOSIT_FIXED => (int) $this->deposit_value,
            default => $totalCents, // none (defensivo; ya cubierto por hasDeposit() arriba)
        };

        return max(0, min($deposit, $totalCents));
    }

    /**
     * ¿El producto cobra una SEÑAL (pago parcial online; resto en el parque)? `none` o valor 0 → no.
     */
    public function hasDeposit(): bool
    {
        return $this->deposit_type !== self::DEPOSIT_NONE && (int) $this->deposit_value > 0;
    }

    /**
     * Etiqueta legible de la señal CONFIGURADA, data-driven (#225 F2), para anunciarla en el flujo
     * de compra ANTES de elegir cantidad (catálogo): `fixed` → importe formateado ("30,00 €");
     * `percent` → porcentaje ("30 %"). Null si no hay señal. Se usa el valor configurado (no
     * `depositCents`, que con un precio POR NIÑO de un pack capa la señal por-unidad y engañaría).
     */
    public function depositLabel(): ?string
    {
        if (! $this->hasDeposit()) {
            return null;
        }

        return match ($this->deposit_type) {
            self::DEPOSIT_FIXED => Money::format((int) $this->deposit_value),
            self::DEPOSIT_PERCENT => ((int) $this->deposit_value).' %',
            default => null,
        };
    }

    /**
     * Esquema de campos del evento de un pack (data-driven, #86), normalizado. Cada campo: key,
     * label (array traducible o string), type (text|number|textarea), required y **`stage`**
     * (`booking` = se pide al reservar / `postform` = se pide en el formulario posterior, #217).
     * `$stage` filtra a esa fase (la compra usa `booking`; el post-form, `postform`); sin él,
     * devuelve todos. Vacío si el producto no define campos. Editable en el panel.
     *
     * @return array<int, array{key:string, label:array<string,string>|string, type:string, required:bool, stage:string}>
     */
    public function eventFields(?string $stage = null): array
    {
        return self::normalizeFieldSchema($this->event_fields, true, $stage);
    }

    /** Etiqueta de un campo del evento en el idioma activo (con respaldo). */
    public function eventFieldLabel(array $field): string
    {
        return self::resolveFieldLabel($field);
    }

    /**
     * Esquema de columnas POR NIÑO de un pack de cumpleaños (data-driven, #217), normalizado.
     * Mismo formato que `eventFields()` ({key, label, type, required}); su columna es
     * `guest_fields` (distinta de `event_fields`, que son los datos básicos del evento). Cada
     * RESERVA guarda en `order_items.guest_data` una lista con un objeto por invitado (#217).
     *
     * @return array<int, array{key:string, label:array<string,string>|string, type:string, required:bool}>
     */
    public function guestFields(): array
    {
        return self::normalizeFieldSchema($this->guest_fields);
    }

    /** Etiqueta de una columna por-niño en el idioma activo (con respaldo). */
    public function guestFieldLabel(array $field): string
    {
        return self::resolveFieldLabel($field);
    }

    /**
     * **La CLAVE de la columna de NOMBRE del esquema por invitado**: la primera de tipo `text`
     * (`specs/celebracion-e-invitacion.md` §4.4, §7.2·R2; `DECISIONES #574`).
     *
     * ⚠️⚠️ **El esquema por invitado NO tiene un tipo «nombre»** —sus tipos son `text`, `number`,
     * `textarea` y `age`—, así que `name` es la clave del SEMBRADO por convención, no una garantía.
     * La revisión adversarial lo midió: una spec que contara «fichas con nombre» leyendo `name` se
     * rompería en la primera instalación que renombrara la columna desde su panel, **sin fallar**.
     *
     * De aquí leen el emparejado de la invitación, la lista completa, la puerta y la hoja de sala, así
     * que la regla vive en UN sitio. `null` si el esquema no declara ninguna columna de texto: ese
     * producto no puede ofrecer invitación digital, y el interruptor lo exige.
     */
    public function guestNameFieldKey(): ?string
    {
        // ⚠️ Sin `?? null`: `normalizeFieldSchema()` garantiza `type` en toda fila que devuelve, y
        // fingir que puede faltar no solo es falso — sumaba dos entradas al trinquete de la línea
        // base de Larastan, que solo encoge.
        foreach ($this->guestFields() as $field) {
            if ($field['type'] === self::FIELD_TYPE_TEXT) {
                return (string) $field['key'];
            }
        }

        return null;
    }

    /**
     * **La CLAVE del nombre del HOMENAJEADO**, con la misma regla: la primera columna `text` de los
     * campos del evento, en fase de RESERVA y, si allí no hay ninguna, en la del FORMULARIO DE
     * INVITADOS.
     *
     * ⚠️ La spec decía «se prerrellena desde `event_data` por las claves `celebrant` y `age`». Medido
     * el 2026-09-17: **la edad sí tiene lector canónico por TIPO** ({@see celebrantAgeFieldKey()}),
     * pero el nombre no — `celebrant` es una clave del sembrado (`LandingContentSeeder`,
     * `ProductionSeeder`), igual que `name` en el esquema por invitado. Se lee por la misma regla que
     * su hermana en vez de quemar la clave, que es lo que `#588` ya hizo con la edad.
     *
     * ⚠️⚠️ **Las DOS fases, y la de reserva primero** (`DECISIONES #692`): el panel deja pedir el nombre
     * en el formulario de invitados en vez de al comprar —`[DECIDIDO owner]`, cero fricción en la
     * compra—, y el formulario lo guarda en el MISMO `event_data`. Mirando solo la reserva, ese cambio
     * de un desplegable dejaba sin nombre, EN SILENCIO, el prerrelleno de la invitación y la columna
     * «Homenajeado» de la hoja del día.
     */
    public function celebrantNameFieldKey(): ?string
    {
        foreach ([self::EVENT_STAGE_BOOKING, self::EVENT_STAGE_POSTFORM] as $stage) {
            foreach ($this->eventFields($stage) as $field) {
                if ($field['type'] === self::FIELD_TYPE_TEXT) {
                    return (string) $field['key'];
                }
            }
        }

        return null;
    }

    // ─── El JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.2) ───

    /**
     * ¿Este producto necesita el justificante firmado del padre o la madre de un menor que **no es
     * menor a cargo** de quien reserva? `[DECIDIDO owner, 2026-09-01]`, tres estados y no un booleano.
     *
     * ⚠️ **`optional` y `required` no son «el mismo interruptor con más fuerza»**: en el primero lo
     * declara el CLIENTE (viene un amigo de su hijo, y solo él lo sabe); en el segundo lo sabe el
     * PRODUCTO (una excursión de colegio son cien menores ajenos por definición) y no hay nada que
     * preguntar. Por eso uno pinta una casilla y el otro una nota.
     */
    public const GUARDIAN_NONE = 'none';

    public const GUARDIAN_OPTIONAL = 'optional';

    public const GUARDIAN_REQUIRED = 'required';

    /** @var list<string> */
    public const GUARDIAN_MODES = [
        self::GUARDIAN_NONE,
        self::GUARDIAN_OPTIONAL,
        self::GUARDIAN_REQUIRED,
    ];

    /**
     * El modo, SANEADO. Nunca se lee la columna a pelo: un valor que no esté en la lista —de una
     * importación, de un seeder viejo o de un `update()` a mano— tiene que degradar a `none`, que es
     * el estado que no hace nada. La regla 12 del proyecto aplicada a una columna de texto libre.
     */
    public function guardianMode(): string
    {
        $mode = (string) ($this->guardian_authorization ?? self::GUARDIAN_NONE);

        return in_array($mode, self::GUARDIAN_MODES, true) ? $mode : self::GUARDIAN_NONE;
    }

    /**
     * ¿Se le PREGUNTA al cliente? Solo en `optional`: en `required` no hay pregunta que hacer, y
     * pintarla como una casilla marcada e inerte invita a intentar desmarcarla.
     */
    public function offersGuardianAuthorization(): bool
    {
        return $this->guardianMode() === self::GUARDIAN_OPTIONAL;
    }

    /**
     * ¿La línea nace marcada pase lo que pase? **Lo decide el servidor, nunca el navegador**: si esto
     * dependiera de la casilla, un cliente compraría una excursión sin justificantes quitando un
     * `input` del DOM.
     */
    public function requiresGuardianAuthorization(): bool
    {
        return $this->guardianMode() === self::GUARDIAN_REQUIRED;
    }

    // ─── La INVITACIÓN DIGITAL y el EMBUDO (`celebracion-e-invitacion.md` §4.8, `#575`) ───

    /**
     * ¿Este producto ofrece invitación digital? (D15). Exige **las tres cosas**, no solo la casilla:
     *
     *  - el interruptor encendido;
     *  - que sea un **pack** —la invitación es de una fiesta, y el post-form del que cuelga solo lo
     *    tienen los packs—;
     *  - y que su esquema por invitado **tenga columna de nombre** ({@see guestNameFieldKey()}), sin
     *    la cual no hay con qué emparejar lo que conteste un padre.
     *
     * ⚠️ Se comprueban aquí y no solo en el formulario del panel porque **un formulario no es una
     * autoridad**: el guard de `saving()` impide guardar la combinación imposible, y esto impide que
     * una fila que ya la tuviera —de una importación, de un `update()` a mano— encienda la feature.
     */
    public function offersGuestInvitation(): bool
    {
        return (bool) $this->guest_invitation
            && $this->isPack()
            && $this->guestNameFieldKey() !== null;
    }

    /**
     * **El modo del justificante QUE VE EL EMBUDO** (D15, §4.8).
     *
     * Con la invitación encendida el cajón **no enseña la casilla del justificante**: quien contesta
     * ya dice por su cuenta si el niño viene con un adulto o necesita firma, así que preguntárselo
     * además al anfitrión sería pedir dos veces el mismo dato y por el lado que no lo sabe.
     *
     * ⚠️⚠️ **Esto NO es un permiso, es una OFERTA** (`#400`: «ofrecer ≠ permitir»). `OrderCreator` —que
     * está en el `CRITICAL_RE`— **no se toca**: sigue escribiendo la marca con `requiresGuardian...`
     * o con la casilla de la línea. Una marca forjada por un cliente que edite el DOM solo produce el
     * correo del justificante suelto, que ya funciona en cualquier pedido pagado.
     */
    public function funnelGuardianMode(): string
    {
        return $this->offersGuestInvitation() ? self::GUARDIAN_NONE : $this->guardianMode();
    }

    /** ¿El embudo le PREGUNTA al cliente por el justificante? Lo leen el cajón y el pedido manual. */
    public function offersGuardianInFunnel(): bool
    {
        return $this->funnelGuardianMode() === self::GUARDIAN_OPTIONAL;
    }

    /** ¿Este producto tiene algo que ver con justificantes? (`optional` o `required`). */
    public function usesGuardianAuthorization(): bool
    {
        return $this->guardianMode() !== self::GUARDIAN_NONE;
    }

    // ─── Familia y tramo de edad (cumpleaños MIXTO, `specs/cumple-mixto.md` §9) ───────

    /**
     * ¿Este tipo de campo se escribe con dígitos? Lo consultan las SEIS superficies que pintan un
     * esquema data-driven (post-form web, los dos formularios del panel, los dos pasos del cajón)
     * para elegir `type="number"` / `inputmode`. Vive aquí para que añadir un tipo numérico nuevo
     * —como la EDAD— no obligue a acordarse de seis ficheros.
     */
    public static function isNumericFieldType(?string $type): bool
    {
        return in_array($type, [self::FIELD_TYPE_NUMBER, self::FIELD_TYPE_AGE, self::FIELD_TYPE_CELEBRANT_AGE], true);
    }

    /**
     * La CLAVE del campo de edad de este pack, o `null` si su esquema no declara ninguno. Es el
     * único puente entre el esquema data-driven y el veredicto de mezcla: se busca por TIPO, nunca
     * por nombre de clave (una instalación puede llamarlo `edad`, `age` o `alter`).
     *
     * Si hubiera más de uno —el panel no lo impide— manda el PRIMERO del esquema: una decisión
     * arbitraria pero estable, mejor que un resultado que dependa del orden de lectura.
     */
    public function guestAgeFieldKey(): ?string
    {
        foreach ($this->guestFields() as $field) {
            if (($field['type'] ?? null) === self::FIELD_TYPE_AGE) {
                return (string) $field['key'];
            }
        }

        return null;
    }

    /**
     * La CLAVE del campo de EDAD DEL CUMPLEAÑERO de este pack (fase de reserva), o `null` si su esquema
     * no lo declara. Se busca por TIPO, nunca por nombre de clave; con varios manda el PRIMERO, la
     * misma regla que `guestAgeFieldKey()`.
     */
    public function celebrantAgeFieldKey(): ?string
    {
        foreach ($this->eventFields(self::EVENT_STAGE_BOOKING) as $field) {
            if ($field['type'] === self::FIELD_TYPE_CELEBRANT_AGE) {
                return (string) $field['key'];
            }
        }

        return null;
    }

    /**
     * **¿La edad del cumpleañero cabe en el tramo de este pack?** (`DECISIONES #588`, `[DECIDIDO
     * owner]`). `null` cuando cabe o cuando no hay nada que comprobar: no es un pack, no declara tramo,
     * no pide la edad o no está contestada —lo que FALTA lo dice la regla de obligatorios, no ésta—.
     *
     * ▶ Con la edad fuera recomienda el pack de su MISMA familia de edades (`guest_age_family`) que la
     * admite, activo y en venta online; sin familia no hay a quién recomendar y no recomienda nada.
     * ⚠️ La edad se lee SANEADA (`sanitizeEventData`): «cinco» o «999» no es una edad, es un hueco.
     *
     * @param  array<string, mixed>  $answers  las respuestas del evento, tal como llegan
     */
    public function celebrantAgeMismatch(array $answers): ?CelebrantAgeMismatch
    {
        if (! $this->isPack() || ($this->guest_age_min === null && $this->guest_age_max === null)) {
            return null;
        }

        $key = $this->celebrantAgeFieldKey();
        $answer = $key === null ? null : ($this->sanitizeEventData($answers, self::EVENT_STAGE_BOOKING)[$key] ?? null);

        if ($answer === null || $this->coversGuestAge((int) $answer)) {
            return null;
        }

        $age = (int) $answer;
        $family = trim((string) $this->guest_age_family);
        $suggestion = $family === '' ? null : self::query()
            ->ofType(self::TYPE_PACK)
            ->where('guest_age_family', $family)
            ->whereKeyNot($this->getKey())
            ->where('is_active', true)
            ->sellable()
            ->orderBy('position')
            ->get()
            ->first(fn (self $pack): bool => $pack->coversGuestAge($age));

        return new CelebrantAgeMismatch(
            field: (string) $key,
            age: $age,
            min: $this->guest_age_min === null ? null : (int) $this->guest_age_min,
            max: $this->guest_age_max === null ? null : (int) $this->guest_age_max,
            suggestedProductId: $suggestion === null ? null : (int) $suggestion->getKey(),
            suggestedProductName: $suggestion === null ? null : (string) $suggestion->tr('name'),
        );
    }

    /**
     * ¿Este producto participa en una familia por edad? Hacen falta las TRES cosas, y la ausencia
     * de cualquiera lo apaga entero (una excursión de colegio no distingue edades):
     *  - una familia declarada — es lo único que lo conecta con sus alternativos;
     *  - un tramo, aunque sea abierto por un lado (`de 7 en adelante` es `min=7`, `max=null`);
     *  - un campo de EDAD en su post-form: sin el dato no hay veredicto que derivar.
     */
    public function participatesInAgeFamily(): bool
    {
        return $this->guestAgeFamily() !== null
            && ($this->guest_age_min !== null || $this->guest_age_max !== null)
            && $this->guestAgeFieldKey() !== null;
    }

    /** La familia normalizada (recortada y en minúsculas), o `null` si no declara ninguna. */
    public function guestAgeFamily(): ?string
    {
        $family = trim((string) ($this->guest_age_family ?? ''));

        return $family === '' ? null : mb_strtolower($family);
    }

    /**
     * ¿El tramo de este producto cubre esa edad? **Los dos extremos van INCLUIDOS** («de 1 a 6» es
     * `1`–`6`: el de 6 entra y el de 7 no), que es como lo lee un humano y como lo dijo el owner.
     * Un extremo nulo es «sin tope por ese lado», no «cero».
     */
    public function coversGuestAge(int $age): bool
    {
        if ($this->guest_age_min !== null && $age < (int) $this->guest_age_min) {
            return false;
        }

        return ! ($this->guest_age_max !== null && $age > (int) $this->guest_age_max);
    }

    /**
     * El hermano de la MISMA familia cuyo tramo PISA al dado, o `null` si el tramo está libre.
     *
     * **Es la verdad ÚNICA del criterio de solape** (T6, `cumple-mixto.md` §26): los extremos
     * nulos se comparan como los topes reales de la columna (`unsignedTinyInteger`, 0–255) — «de 7
     * en adelante» y «hasta 6» se solapan o no según números, no según casos especiales, el mismo
     * criterio que {@see coversGuestAge}. La consumen el guardián de dominio de {@see booted} y el
     * form del catálogo (que le pone su aviso amable delante): nadie re-implementa la comparación.
     */
    public static function overlappingAgeSibling(string $family, ?int $min, ?int $max, ?int $exceptId = null): ?self
    {
        $mine = [$min ?? 0, $max ?? 255];

        return self::query()
            ->where('type', self::TYPE_PACK)
            ->where('guest_age_family', $family)
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->get(['id', 'name', 'guest_age_min', 'guest_age_max'])
            ->first(function (self $sibling) use ($mine): bool {
                $theirs = [(int) ($sibling->guest_age_min ?? 0), (int) ($sibling->guest_age_max ?? 255)];

                return $mine[0] <= $theirs[1] && $theirs[0] <= $mine[1];
            });
    }

    /**
     * T6 (`cumple-mixto.md` §26, el hueco G de `#284`): **los tramos de una familia no pueden
     * solaparse, y la regla vive en el DOMINIO** — hasta aquí solo la aplicaba el form del panel,
     * y «por construcción es imposible» era verdad únicamente ahí. Corre en `saving`, así que
     * cubre toda escritura Eloquent: semillas, comandos, factories, tinker con `save()`.
     *
     * ⚠️ Valida SOLO cuando cambian los TÉRMINOS del tramo (familia o topes; una fila nueva los
     * cambia todos): una fila con un solape metido por la puerta de atrás sigue editable en
     * precio o nombre — bloquearla dejaría el catálogo ingobernable —, pero tocar SUS tramos
     * exige sanearla. ⚠️ El límite, dicho: los eventos de Eloquent NO ven un
     * `Query\Builder::update()` ni SQL crudo; los cinturones de siempre quedan (el lector
     * resuelve por el tramo de menor edad, y el sellador copia la realidad sin frenar ventas).
     */
    protected static function booted(): void
    {
        /*
         * **Limpieza de huérfanos de la foto** — copiada de `Offer::booted()`, que es donde esta
         * casa ya resolvió el problema: `FileUpload` sube el fichero nuevo pero NO borra el viejo,
         * así que sin esto cada cambio de foto deja basura en `public/uploads` para siempre.
         * `delete()` sobre una ruta que no existe es un no-op seguro.
         */
        static::updating(function (self $type): void {
            if ($type->isDirty('image') && ($anterior = $type->getOriginal('image'))) {
                Storage::disk(self::IMAGE_DISK)->delete($anterior);
            }
        });

        static::deleted(function (self $type): void {
            if ($type->image) {
                Storage::disk(self::IMAGE_DISK)->delete($type->image);
            }
        });

        /*
         * **La INVITACIÓN DIGITAL exige tres cosas, y la BD es quien las impone** (D15, `#575`).
         *
         * ⚠️⚠️ Va en el modelo y no solo en el formulario del panel porque **un formulario no es una
         * autoridad**: lo escriben también los seeders, las importaciones y cualquier `update()` a
         * mano, y una fila con la combinación imposible encendería una feature que no puede funcionar.
         * Es la misma doctrina que el guard de `occupies_after_parent` de aquí abajo.
         *
         * ⚠️ `required` es incompatible por definición: si el justificante hace falta SIEMPRE, no hay
         * nada que preguntarle a nadie y la invitación no cambia el embudo.
         */
        static::saving(function (self $type): void {
            if (! $type->guest_invitation) {
                return;
            }

            if ($type->guardianMode() === self::GUARDIAN_REQUIRED) {
                throw new \InvalidArgumentException(
                    'Un producto con justificante OBLIGATORIO no puede ofrecer invitación digital: '
                    .'si hace falta siempre, no hay nada que preguntar.'
                );
            }

            if (! $type->isPack()) {
                throw new \InvalidArgumentException(
                    'La invitación digital es de una FIESTA: solo un pack puede ofrecerla.'
                );
            }

            if ($type->guestNameFieldKey() === null) {
                throw new \InvalidArgumentException(
                    'Un producto sin columna de NOMBRE en sus datos por invitado no puede ofrecer '
                    .'invitación digital: no habría con qué emparejar lo que conteste un padre.'
                );
            }
        });

        static::saving(function (self $type): void {
            if ($type->type !== self::TYPE_PACK) {
                return;
            }

            $family = $type->guestAgeFamily();
            if ($family === null) {
                return;
            }

            $termsTouched = ! $type->exists
                || $type->isDirty(['guest_age_family', 'guest_age_min', 'guest_age_max', 'type']);
            if (! $termsTouched) {
                return;
            }

            $min = $type->guest_age_min !== null ? (int) $type->guest_age_min : null;
            $max = $type->guest_age_max !== null ? (int) $type->guest_age_max : null;

            if ($min !== null && $max !== null && $max < $min) {
                throw new \InvalidArgumentException(sprintf(
                    'Tramo de edad invertido (%d–%d) en la familia «%s».', $min, $max, $family,
                ));
            }

            $sibling = self::overlappingAgeSibling($family, $min, $max, $type->getKey());
            if ($sibling !== null) {
                throw new OverlappingAgeRangeException($sibling);
            }

            // `#324` (`[DECIDIDO owner]`, spec `precio-por-tramo.md` §7·3): **un producto no puede
            // tener familia de edades Y tramos de cantidad a la vez.**
            //
            // El sello de `#288` congela al vender el precio de cada tramo de EDAD; un tramo de
            // CANTIDAD lo movería después, y «¿qué cantidad se sella?» no tiene respuesta buena — el
            // grupo entero y el subgrupo de esa edad son números distintos y los dos son defendibles.
            // Hoy no se cruzan (una excursión no es una fiesta mixta), así que la puerta se cierra
            // ANTES de que se abra mal: cuesta esta guarda, y abrirla mal cuesta un cobro erróneo.
            if ($type->exists && $type->priceTiers()->exists()) {
                throw new \InvalidArgumentException(
                    'Un producto con tramos de precio por cantidad no puede declarar familia de edades: '
                    .'el sello congelaría un precio que el tramo movería después.'
                );
            }
        });

        // La HORA EXTRA (`specs/hora-extra.md` §4.1): `occupies_after_parent = true` con
        // `duration_min` nula es una configuración IMPOSIBLE — declara que ocupa y no dice cuánto
        // (y para `occupancyMap`, duración nula = «hasta el cierre»: una fila torcida ocuparía el
        // resto del día). Igual que el guard de tramos de arriba: valida al tocar los TÉRMINOS,
        // corre en `saving` (semillas, comandos, factories, tinker) y su límite es el mismo — los
        // eventos no ven `Query\Builder::update()`, y ese hueco lo tapa el cinturón del punto de
        // composición (ni se ofrece ni se vende). La regla sana vive en `hasSaneOccupancyConfig()`,
        // fuente única compartida con ese cinturón.
        static::saving(function (self $type): void {
            $termsTouched = ! $type->exists
                || $type->isDirty(['occupies_after_parent', 'duration_min', 'seats_per_unit', 'type']);
            if (! $termsTouched || $type->occupies_after_parent !== true) {
                return;
            }

            if ($type->type !== self::TYPE_ADDON) {
                throw new \InvalidArgumentException(
                    'Solo un COMPLEMENTO puede ocupar detrás de su padre (`occupies_after_parent`): '
                    .'una entrada o un pack no tienen padre del que colgar.'
                );
            }

            // Los dos interruptores son EXCLUYENTES (`specs/hora-extra.md` §10.3.1): ocupar es
            // quedarse en la franja siguiente con plazas propias y extender es alargar la ventana
            // del padre sin plazas. Un complemento con los dos encendidos no tiene unidad —¿su
            // cantidad son personas u horas?— y su precio no significaría nada.
            if ($type->extends_parent_stay === true) {
                throw new \InvalidArgumentException(
                    'Un complemento no puede OCUPAR y EXTENDER a la vez (`occupies_after_parent` + '
                    .'`extends_parent_stay`): su cantidad serían personas y bloques de tiempo al '
                    .'mismo tiempo (`specs/hora-extra.md` §10.3.1).'
                );
            }

            if (! $type->hasSaneOccupancyConfig()) {
                throw new \InvalidArgumentException(
                    'Un complemento que OCUPA tiene que decir cuánto (`duration_min` > 0) y cuántas '
                    .'plazas por unidad (`seats_per_unit` >= 1): «ocupa y no dice cuánto» es la '
                    .'configuración imposible de `specs/hora-extra.md` §4.1.'
                );
            }

            // La OTRA dirección de la guarda del pivote (`ProductAddon::booted()`), la lección de
            // `#324` (dos reglas cruzadas necesitan guarda en las DOS direcciones): encender el
            // interruptor a un complemento YA enganchado como por-invitado/obligatorio o colgado de
            // un pack dejaría en pie una configuración que el pivote habría rechazado al revés.
            if ($type->exists) {
                $conflicting = ProductAddon::query()
                    ->where('addon_id', $type->getKey())
                    ->where(fn ($q) => $q
                        ->where('quantity_mode', ProductAddon::MODE_PER_GUEST)
                        ->orWhere('is_mandatory', true)
                        // La misma dirección inversa para la FASE (`#413` §4.3·5): `occupies_after_parent`
                        // es columna de ESTE modelo, así que encender el interruptor a un complemento ya
                        // enganchado como venta POSTERIOR no lo ve el guard del pivote — y dejaría en pie
                        // una configuración que aquél rechaza al revés. Ocupar aforo después de reservar
                        // exige el lock de zona/día y la franja de aterrizaje, que esta feature no tiene.
                        ->orWhere('stage', ProductAddon::STAGE_POSTFORM))
                    ->exists();
                $onPack = ProductAddon::query()
                    ->where('addon_id', $type->getKey())
                    ->whereIn('product_id', self::query()->select('id')->where('type', self::TYPE_PACK))
                    ->exists();
                if ($conflicting || $onPack) {
                    throw new \InvalidArgumentException(
                        'Este complemento no puede pasar a OCUPAR: está enganchado como por-invitado/'
                        .'obligatorio, de venta POSTERIOR, o cuelga de un pack — deshaz esos enganches '
                        .'primero (`specs/hora-extra.md` §4.4·5 y §7·D2, '
                        .'`specs/complementos-post-reserva.md` §4.3·5).'
                    );
                }
            }
        });

        // LA HORA EXTRA DE UN PACK (`specs/hora-extra.md` §10.3.1): el guard hermano del de arriba,
        // con la MISMA forma a propósito —valida al tocar los términos, corre en `saving` y su
        // límite es el mismo (`Query\Builder::update()` no dispara eventos; ese hueco lo tapa el
        // cinturón de `AddonOccupancy::sellableStayExtension()`)—. Lo que cambia son las reglas,
        // porque un extensor no es un ocupante: no pide plazas y **solo puede colgar de un PACK**.
        static::saving(function (self $type): void {
            $termsTouched = ! $type->exists
                || $type->isDirty(['extends_parent_stay', 'occupies_after_parent', 'duration_min', 'type']);
            if (! $termsTouched || $type->extends_parent_stay !== true) {
                return;
            }

            if ($type->type !== self::TYPE_ADDON) {
                throw new \InvalidArgumentException(
                    'Solo un COMPLEMENTO puede extender la estancia de su padre '
                    .'(`extends_parent_stay`): una entrada o un pack no tienen padre al que alargar.'
                );
            }

            if ($type->occupies_after_parent === true) {
                throw new \InvalidArgumentException(
                    'Un complemento no puede EXTENDER y OCUPAR a la vez (`extends_parent_stay` + '
                    .'`occupies_after_parent`): su cantidad serían bloques de tiempo y personas al '
                    .'mismo tiempo (`specs/hora-extra.md` §10.3.1).'
                );
            }

            if (! $type->hasSaneStayExtensionConfig()) {
                throw new \InvalidArgumentException(
                    'Un complemento que EXTIENDE la estancia tiene que decir cuánto alarga '
                    .'(`duration_min` > 0): sin eso vendería una hora extra que no ocupa nada '
                    .'(`specs/hora-extra.md` §10.3.1).'
                );
            }

            // La OTRA dirección de la guarda del pivote (la lección de `#324`, aplicada al espejo):
            // encender el interruptor a un complemento YA enganchado a algo que no es un pack —o
            // como obligatorio, incluido o de venta posterior— dejaría en pie una configuración que
            // el pivote rechaza al revés.
            //
            // ⚠️ `per_guest` SALIÓ de esta lista en `#443` (§11.5.2): desde que los minutos salen del
            // BLOQUE y no de la cantidad, un extensor por-invitado es la forma de cobrar la hora
            // extra por invitado — el caso de uso, no una configuración imposible.
            if ($type->exists) {
                $conflicting = ProductAddon::query()
                    ->where('addon_id', $type->getKey())
                    ->where(fn ($q) => $q
                        ->where('is_mandatory', true)
                        ->orWhere('is_included', true)
                        ->orWhere('stage', ProductAddon::STAGE_POSTFORM))
                    ->exists();
                $offPack = ProductAddon::query()
                    ->where('addon_id', $type->getKey())
                    ->whereNotIn('product_id', self::query()->select('id')->where('type', self::TYPE_PACK))
                    ->exists();
                if ($conflicting || $offPack) {
                    throw new \InvalidArgumentException(
                        'Este complemento no puede pasar a EXTENDER la estancia: está enganchado a '
                        .'algo que no es un PACK, o como obligatorio/incluido/de venta POSTERIOR — '
                        .'deshaz esos enganches primero (`specs/hora-extra.md` §10.3.1 y §11.5.2).'
                    );
                }
            }
        });
    }

    /**
     * Normaliza un esquema de campos data-driven ({key, label, type, required}), compartido por
     * `event_fields` y `guest_fields`: descarta filas sin clave, fuerza `type` a uno válido y
     * castea `required` a bool. Fuente única para que ambos esquemas se traten igual.
     *
     * `$withStage` añade la `stage` del campo (solo `event_fields` la tiene): si además se pasa
     * `$stage`, FILTRA a los campos de esa fase (la compra usa `booking`; el post-form, `postform`).
     *
     * @return array<int, array<string,mixed>>
     */
    private static function normalizeFieldSchema(mixed $raw, bool $withStage = false, ?string $stage = null): array
    {
        $fields = is_array($raw) ? $raw : [];

        $clean = [];
        foreach ($fields as $field) {
            if (! is_array($field) || empty($field['key'])) {
                continue;
            }
            $type = $field['type'] ?? 'text';
            $entry = [
                'key' => (string) $field['key'],
                'label' => $field['label'] ?? $field['key'],
                // El esquema del EVENTO no acepta `age`; el POR INVITADO sí. `$withStage` es lo que
                // los distingue (solo `event_fields` lleva etapa), y aquí decide también los tipos.
                'type' => in_array($type, self::fieldTypesFor(perGuest: ! $withStage), true) ? $type : self::FIELD_TYPE_TEXT,
                'required' => (bool) ($field['required'] ?? false),
            ];

            if ($withStage) {
                $fieldStage = $field['stage'] ?? self::EVENT_STAGE_BOOKING;
                $entry['stage'] = in_array($fieldStage, [self::EVENT_STAGE_BOOKING, self::EVENT_STAGE_POSTFORM], true)
                    ? $fieldStage
                    : self::EVENT_STAGE_BOOKING;

                if ($stage !== null && $entry['stage'] !== $stage) {
                    continue; // filtrado por fase
                }
            }

            $clean[] = $entry;
        }

        return $clean;
    }

    /** Resuelve la etiqueta i18n de un campo ({label} array/string) al idioma activo, con respaldo. */
    private static function resolveFieldLabel(array $field): string
    {
        $label = $field['label'] ?? ($field['key'] ?? '');
        if (is_array($label)) {
            return (string) ($label[app()->getLocale()] ?? $label[config('app.fallback_locale')] ?? reset($label) ?: '');
        }

        return (string) $label;
    }

    /**
     * Sanea las respuestas del cliente: deja SOLO las claves del esquema, recorta y descarta vacíos
     * (regla 12: no se confía en el cliente). Los campos `number` se quedan en dígitos.
     *
     * @param  array<string,mixed>  $answers
     * @return array<string,string>
     */
    public function sanitizeEventData(array $answers, ?string $stage = null): array
    {
        $clean = [];
        foreach ($this->eventFields($stage) as $field) {
            $value = self::sanitizeAnswerValue($field, $answers[$field['key']] ?? null);
            if ($value !== null) {
                $clean[$field['key']] = $value;
            }
        }

        return $clean;
    }

    /**
     * Sanea UN valor de respuesta según su campo: recorta, deja los `number` en dígitos y devuelve
     * `null` si queda vacío (= "no respondido"). Fuente única compartida por `sanitizeEventData`
     * (datos del evento) y `sanitizeGuestData` (datos por-niño), para que el saneo no diverja.
     */
    private static function sanitizeAnswerValue(array $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        $type = $field['type'] ?? self::FIELD_TYPE_TEXT;

        if (self::isNumericFieldType($type)) {
            $value = preg_replace('/\D+/', '', $value) ?? '';
        }

        // ⚠️ La EDAD además se ACOTA, y fuera de rango vale «no respondido» (no se recorta ni se
        // clampa): de este campo sale un cobro, y un valor imposible tiene que verse como el hueco
        // que es —el campo se marca incompleto y el cliente lo corrige— y no colarse como un 120.
        if (in_array($type, [self::FIELD_TYPE_AGE, self::FIELD_TYPE_CELEBRANT_AGE], true) && $value !== '') {
            $age = (int) $value;
            if ($age < self::GUEST_AGE_MIN || $age > self::GUEST_AGE_MAX) {
                return null;
            }
            $value = (string) $age; // normaliza «007» → «7»: la comparación con el tramo es numérica.
        }

        // Cap defensivo (#217): impide inflar la columna JSON con valores enormes (post-form o compra).
        $value = mb_substr($value, 0, self::ANSWER_MAX_LENGTH);

        return $value === '' ? null : $value;
    }

    /**
     * Las respuestas del evento **emparejadas con la etiqueta de su campo**, en el orden del
     * ESQUEMA y sin las que quedaron vacías. `$stage` acota a una fase, igual que en
     * `sanitizeEventData()` y `missingRequiredEventFields()` — que son sus hermanas de firma.
     *
     * **Fuente única de la composición** (Fase 4 · paso 4.0b·4b). Vivía copiada en
     * `Purchase::resolveEventData()`, y con el endpoint `orders/{code}/event-data` iba a nacer una
     * tercera: emparejar respuesta con etiqueta es una regla *data-driven* —el orden lo pone el
     * esquema y los textos viven en BD con su respaldo de idioma—, no una vuelta de bucle.
     *
     * ⚠️ **Solo las claves que el esquema ACTUAL declara.** Si el pack se editó después de la
     * compra, las respuestas cuyo campo desapareció **no salen**: sin campo no hay etiqueta que
     * emparejar y —lo que decide el caso— tampoco `stage`, así que un filtro por fase no podría
     * clasificarlas. La hoja de sala del panel (`ReservationSlip::eventDataRows()`) sí las enseña,
     * con la etiqueta derivada de la clave, porque su lector es el operador y necesita ver TODO lo
     * que el cliente contestó; la divergencia está anotada en `DEUDA.md`.
     *
     * `is_scalar` no es paranoia: `sanitizeEventData()` garantiza cadenas, pero `event_data` es una
     * columna JSON con un cast a `array` y los pedidos importados no pasaron por ese saneo — el
     * dominio ya lo reconoce en `DailyReservationsSummary::celebrantOf()`.
     *
     * @param  array<string,mixed>  $answers
     * @return list<array{key:string, label:string, value:string}>
     */
    public function eventAnswers(array $answers, ?string $stage = null): array
    {
        $out = [];
        foreach ($this->eventFields($stage) as $field) {
            // `is_scalar` cubre también el «no respondido» (`null`), que es el caso frecuente.
            $value = $answers[$field['key']] ?? null;
            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }

            $out[] = [
                'key' => (string) $field['key'],
                'label' => $this->eventFieldLabel($field),
                'value' => (string) $value,
            ];
        }

        return $out;
    }

    /**
     * Claves de campos OBLIGATORIOS que el cliente no rellenó (para bloquear la reserva).
     *
     * @param  array<string,mixed>  $answers
     * @return array<int,string>
     */
    public function missingRequiredEventFields(array $answers, ?string $stage = null): array
    {
        $clean = $this->sanitizeEventData($answers, $stage);

        $missing = [];
        foreach ($this->eventFields($stage) as $field) {
            if ($field['required'] && ! isset($clean[$field['key']])) {
                $missing[] = $field['key'];
            }
        }

        return $missing;
    }

    /**
     * **Las filas del cliente, puestas en el ORDEN que manda** — antes de tocar nada más.
     *
     * ⚠️⚠️ **Ordenar por CLAVE antes de reindexar, o el orden de la PÁGINA reordena a los invitados**
     * (`#571`, T2 de `specs/celebracion-e-invitacion.md`). PHP conserva el orden en que LLEGAN los
     * campos (`guests[5][…]` antes que `guests[0][…]`), y `array_values` convierte ese orden en
     * posiciones: con las fichas pendientes pintadas ARRIBA, cada guardado movía a los invitados de
     * sitio —no mezcla datos, pero de la posición cuelgan el régimen de cada ficha, las que se
     * descartan al bajar invitados y la hoja de sala—. Solo se ordena si TODAS las claves son
     * enteras: una lista ya ordenada queda igual, y la API manda listas.
     *
     * Después reindexa a una lista contigua 0..n: robusto frente a payloads asociativos o con huecos
     * (p. ej. una UI JS que borra una fila sin reindexar) — el saneo NO confía en que el cliente
     * mande una lista perfecta (regla 12).
     *
     * ▶ **Es público porque la compactación de `§7.1·5` tiene que correr DESPUÉS de esto y ANTES de
     * que `sanitizeGuestData()` recorte** (`#718`): compactar sobre las claves sin ordenar deshacía
     * el arreglo de `#571`, y lo cazó su propio caso.
     *
     * @param  array<int,mixed>  $rows
     * @return array<int,mixed>
     */
    public static function orderGuestRows(array $rows): array
    {
        if (! array_is_list($rows) && array_filter(array_keys($rows), 'is_int') === array_keys($rows)) {
            ksort($rows);
        }

        return array_values($rows);
    }

    /**
     * Sanea las respuestas POR NIÑO del post-form (#217): normaliza la lista a EXACTAMENTE
     * `$count` filas (una por invitado, 0-indexada), y dentro de cada fila deja solo las claves
     * del esquema `guestFields()`, recorta y descarta vacíos (regla 12: no se confía en el
     * cliente; espejo de `sanitizeEventData`). Las filas que excedan `$count` se descartan; las
     * que falten se rellenan como `[]` (niño sin datos todavía). Estructura canónica para
     * persistir en `order_items.guest_data`.
     *
     * @param  array<int,mixed>  $rows  lista enviada por el cliente (una entrada por niño)
     * @return array<int, array<string,string>>
     */
    public function sanitizeGuestData(array $rows, int $count): array
    {
        $count = max(0, $count);
        $rows = self::orderGuestRows($rows);
        $fields = $this->guestFields();

        $clean = [];
        for ($i = 0; $i < $count; $i++) {
            $answers = is_array($rows[$i] ?? null) ? $rows[$i] : [];
            $row = [];
            foreach ($fields as $field) {
                $value = self::sanitizeAnswerValue($field, $answers[$field['key']] ?? null);
                if ($value !== null) {
                    $row[$field['key']] = $value;
                }
            }
            $clean[] = $row;
        }

        return $clean;
    }

    /**
     * ¿El post-form por-niño está COMPLETO para `$count` invitados? = cada uno de los `$count`
     * niños tiene rellenas TODAS las columnas `required` del esquema `guestFields()`. Lectura pura
     * (no toca BD): la autoridad de "FORM OK/NO" se deriva en vivo de los datos contra la cantidad
     * ACTUAL del item, no de un flag congelado ({@see OrderItem::guestFormStatus}). Si el esquema no
     * tiene columnas obligatorias, basta con que existan las `$count` filas.
     *
     * @param  array<int,mixed>  $rows
     */
    public function guestDataComplete(array $rows, int $count): bool
    {
        $count = max(0, $count);
        $clean = $this->sanitizeGuestData($rows, $count);

        $required = array_values(array_filter(
            $this->guestFields(),
            fn (array $field): bool => $field['required'],
        ));

        for ($i = 0; $i < $count; $i++) {
            foreach ($required as $field) {
                if (! isset($clean[$i][$field['key']])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Nº de niños (de 0..$count) con TODAS sus columnas `required` rellenas. Espejo de
     * {@see guestDataComplete} pero CONTANDO, para el indicador «X/N fichas completas» del post-form
     * individualizado por reserva (#217). Si el esquema no tiene columnas obligatorias, toda fila
     * existente cuenta como completa (coherente con `guestDataComplete`).
     *
     * @param  array<int,mixed>  $rows
     */
    public function guestDataCompletedCount(array $rows, int $count): int
    {
        return count($this->guestDataCompletedIndexes($rows, $count));
    }

    /**
     * QUÉ fichas tienen rellenas todas sus columnas obligatorias (índices 0-based). Es la misma
     * cuenta que {@see guestDataCompletedCount}, por posición: `OrderItem::guestFormProgress()` la
     * necesita así para descontar las fichas cuya edad no tiene producto (`specs/cumple-mixto.md`
     * §22.2), que este modelo no sabe reconocer — eso vive en el sello de cada reserva.
     *
     * @param  array<int,mixed>  $rows
     * @return list<int>
     */
    public function guestDataCompletedIndexes(array $rows, int $count): array
    {
        $count = max(0, $count);
        $clean = $this->sanitizeGuestData($rows, $count);
        $required = array_values(array_filter(
            $this->guestFields(),
            fn (array $field): bool => $field['required'],
        ));

        $done = [];
        for ($i = 0; $i < $count; $i++) {
            $complete = true;
            foreach ($required as $field) {
                if (! isset($clean[$i][$field['key']])) {
                    $complete = false;
                    break;
                }
            }
            if ($complete) {
                $done[] = $i;
            }
        }

        return $done;
    }

    /**
     * Productos EN VENTA ONLINE (`is_sellable`). Gobierna el catálogo público, el alta manual del
     * panel y la autoridad de servidor (`OrderCreator`). NO exige `is_active` («Visible en la web»):
     * un producto OCULTO de la web puede venderse igualmente (catálogo/panel/checkout). La
     * visibilidad en la landing es un eje INDEPENDIENTE que gobierna `is_active` por separado (P3).
     *
     * @param  Builder<TicketType>  $query
     */
    public function scopeSellable(Builder $query): void
    {
        $query->where('is_sellable', true);
    }

    /**
     * Restringe a productos cuya ZONA opera (`zones.is_active`) — o que no tienen zona (los
     * complementos). Una zona desactivada no vende: sus entradas/packs quedan fuera del flujo de
     * compra (catálogo, selección y `OrderCreator`), de forma coherente entre lo que se muestra y
     * lo que se acepta. La actividad del PRODUCTO la cubre `scopeSellable` aparte. (#210)
     *
     * @param  Builder<TicketType>  $query
     */
    public function scopeInOperationalZone(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereNull('zone_id')
            ->orWhereHas('zone', fn (Builder $z) => $z->where('is_active', true)));
    }

    /**
     * Filtra por tipo de producto (entry/pack/addon).
     *
     * @param  Builder<TicketType>  $query
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Packs que se anuncian en la sección CUMPLEAÑOS de la landing (#256, modelo A): packs vendibles
     * de zona operativa SIN un `LandingService` que los reubique en /servicios. FUENTE ÚNICA para
     * `HomeController` y `EventsController` (que no diverjan). Un pack «se mueve» a Servicios en
     * cuanto se le crea un `LandingService`; mientras no lo tenga, es de cumpleaños.
     *
     * @param  Builder<TicketType>  $query
     */
    public function scopeBirthdaySurfacePacks(Builder $query): void
    {
        $query->ofType(self::TYPE_PACK)
            ->where('is_active', true)
            ->sellable()
            ->inOperationalZone()
            ->whereDoesntHave('landingServices');
    }

    /**
     * La cantidad MÍNIMA contratable de este producto: el mínimo de invitados de un pack, 1 en
     * cualquier otra cosa. Nunca menos de 1 (un `min_qty` a 0 o nulo no puede bajar de ahí).
     *
     * Vive aquí porque la misma derivación estaba escrita a mano en media docena de sitios
     * —`CartLineValidator`, `CatalogReader`, `SlotOffer`, `OrderCreator`, `OrderItemEditor` y la
     * página de pedido manual—, y desde `#329` además decide el SUELO de la escala de tramos: una
     * regla de dinero repartida en seis copias es seis sitios donde puede divergir (la lección de
     * `#320` con el estado de la exención de un menor).
     */
    public function contractableMinimum(): int
    {
        return $this->isPack() ? max(1, (int) ($this->min_qty ?? 1)) : 1;
    }

    /**
     * El precio de TRAMO que aplica a `$quantity`, o `null` si el producto no tiene tramos que la
     * cubran (y entonces manda el precio de siempre, `prices`).
     *
     * ⚠️⚠️ Los COMPLEMENTOS quedan fuera, y no es una optimización: es la regla. Un complemento (la
     * tarta, los calcetines) no se vende por volumen — lo dice la migración de `price_tiers`, que es
     * solo de productos principales. ▶ Y lo destapó el presupuesto de consultas, no una lectura: un
     * addon **es una fila de `ticket_types`** con `type = addon`, así que el `instanceof` los
     * alcanzaba y cada uno pagaba una consulta para preguntar por unos tramos que no puede tener
     * (`ApiOverheadTest`: 10 consultas → 16). *Un `instanceof` describe la clase, no el rol, y aquí
     * tres roles comparten clase.*
     *
     * ❗❗ **`#329` — LA ESCALA NO EMPIEZA POR DEBAJO DEL MÍNIMO CONTRATABLE.** La cantidad con la
     * que se elige tramo va acotada por abajo a {@see contractableMinimum()}, y eso es lo que hace
     * que el pedido manual por debajo del mínimo (D7 al crear) tenga un precio definido:
     * `[DECIDIDO owner, 2026-09-01]` una excursión de 20 con la escala en 30→15 € / 70→13 € /
     * 100→12 € se cobra a **15 €**, el primer tramo. La propiedad que lo resume, y la que vigila la
     * guarda: **vender por debajo del mínimo nunca sale más barato por cabeza que vender justo en
     * el mínimo.**
     *
     * ⚠️ **El suelo NO se puso dentro de `PriceTier::resolve()`, y la diferencia es dinero**: allí
     * sería «si ningún tramo cubre, coge el más pequeño», y eso REGALARÍA el descuento de volumen a
     * toda entrada comprada por debajo de su primer tramo (5 unidades de un producto con «10+ →
     * 8 €» pasarían a pagar 8 € en vez de su precio base). El suelo es el mínimo DEL PRODUCTO, que
     * en una entrada vale 1 y por tanto no mueve nada.
     */
    public function tierPriceCents(int $rateTypeId, int $quantity): ?int
    {
        if ($this->isAddon()) {
            return null;
        }

        return PriceTier::resolve($this->priceTiers, $rateTypeId, max($quantity, $this->contractableMinimum()));
    }

    /**
     * Precio (céntimos) para una tarifa concreta; null si no está definido.
     * Usa la relación `prices` (cárgala con eager load para evitar N+1).
     *
     * ▶ `#324` — admite la CANTIDAD: si el producto declara tramos de volumen y alguno cubre esa
     * cantidad, manda el tramo; si no, el precio de siempre (`docs/specs/precio-por-tramo.md`). El
     * default a 1 mantiene idéntica la conducta de todo llamante que no la pase.
     */
    public function priceCentsForRate(?RateType $rate, int $quantity = 1): ?int
    {
        if (! $rate) {
            return null;
        }

        return $this->tierPriceCents((int) $rate->id, $quantity)
            ?? $this->prices->firstWhere('rate_type_id', $rate->id)?->amount_cents;
    }

    /**
     * `#324` — precio a ENSEÑAR para una tarifa cuando **todavía no se sabe la cantidad**: es la
     * pregunta del calendario de disponibilidad, que pinta un precio por día antes de que el cliente
     * elija cuántos (`AvailabilityReader::dates()` recibe un producto y ninguna cantidad).
     *
     * Con tramos devuelve **el más barato de esa tarifa**, que es la misma regla que
     * {@see displayPriceCents()} y el `[DECIDIDO owner]` del «desde 12 €». Se separa de
     * {@see priceCentsForRate()} en vez de inventarle una cantidad porque son dos preguntas
     * distintas —«¿cuánto cuesta esto?» y «¿cuánto cuesta COMPRAR N?»— y una cantidad fingida en la
     * primera acabaría cobrándose en la segunda.
     */
    public function displayPriceCentsForRate(?RateType $rate): ?int
    {
        if (! $rate) {
            return null;
        }

        $tiers = $this->priceTiers->where('rate_type_id', $rate->id);

        return $tiers->isNotEmpty()
            ? (int) $tiers->min('amount_cents')
            : $this->prices->firstWhere('rate_type_id', $rate->id)?->amount_cents;
    }

    /**
     * Precio de referencia para la landing: el de la tarifa `normal` (o, si falta,
     * el más bajo). El precio del día se aplica en el panel de compra (RateResolver).
     *
     * ▶ `#324` — con tramos de volumen, el de referencia es **el MÁS BARATO** de todos ellos
     * (`[DECIDIDO owner]`: «desde 12 €», el del tramo de 100). ⚠️ Se preguntó con la alternativa
     * delante —el del tramo mínimo vendible, 15 €, para que nadie vea 12 y pague 15— y el owner
     * eligió el más barato: «desde» señala variabilidad y anuncia el precio real más bajo que
     * existe. **No lo «corrijas» creyendo que es un descuido**; está en la spec §7·2.
     */
    public function displayPriceCents(): int
    {
        if ($this->priceTiers->isNotEmpty()) {
            return (int) $this->priceTiers->min('amount_cents');
        }

        $normal = $this->prices->first(
            fn (Price $price) => optional($price->rateType)->key === RateType::KEY_NORMAL
        );

        $cents = $normal?->amount_cents ?? $this->prices->min('amount_cents');

        return (int) ($cents ?? 0);
    }

    /**
     * ¿El precio varía según el día (hay tarifas con importes distintos)? Si es así,
     * la landing muestra "desde X€" (el precio del día se aplica en la compra).
     */
    public function priceVaries(): bool
    {
        return $this->prices->max('amount_cents') > $this->prices->min('amount_cents');
    }

    /**
     * Tarifas ESPECIALES de esta entrada para la card de la landing (presentación
     * «Suplemento +X€»). Por cada tarifa activa marcada `is_special` que tenga un precio
     * PROPIO y DISTINTO de la tarifa base `normal`, devuelve el recargo sobre esa base.
     *
     * DATA-DRIVEN: soporta N tarifas (la clienta puede crear «Verano», «Puentes»… desde
     * /admin/rate-types y ponerles un precio por producto) sin tocar código. DEFENSIVO:
     *  - Sin precio en la tarifa base `normal` → `[]` (no hay «+X sobre el precio de diario»
     *    con sentido; el hero cae a `displayPriceCents()` y no se anuncia recargo).
     *  - Solo tarifas ACTIVAS + `is_special` + con precio propio ≠ base (una igual a la base
     *    no aporta info). Ordenadas por prioridad, igual que los campos del panel.
     *  - `surchargeCents` = especial − base; el panel NO valida el signo (cada precio es
     *    independiente), así que puede ser ≤ 0 (una «especial» más barata es configurable).
     *    La vista lo resuelve: >0 → «+X€»; ≤0 → importe absoluto (nunca miente ni oculta).
     *
     * Requiere `prices.rateType` en eager load (los controladores de la landing ya lo cargan);
     * opera sobre la colección ya en memoria → sin queries por card (presupuesto W1 intacto).
     *
     * @return list<array{rate: RateType, priceCents: int, surchargeCents: int}>
     */
    public function specialRateSurcharges(): array
    {
        $baseCents = $this->prices->first(
            fn (Price $price) => optional($price->rateType)->key === RateType::KEY_NORMAL
                && $price->amount_cents !== null
        )?->amount_cents;

        if ($baseCents === null) {
            return [];
        }

        return $this->prices
            ->filter(fn (Price $price): bool => $price->amount_cents !== null
                && $price->rateType !== null
                && $price->rateType->is_special
                && $price->rateType->is_active
                && (int) $price->amount_cents !== (int) $baseCents)
            ->sortBy(fn (Price $price): int => (int) $price->rateType->priority)
            ->map(fn (Price $price): array => [
                'rate' => $price->rateType,
                'priceCents' => (int) $price->amount_cents,
                'surchargeCents' => (int) $price->amount_cents - (int) $baseCents,
            ])
            ->values()
            ->all();
    }

    /** Parte entera del precio de referencia (para la landing). */
    public function euros(): string
    {
        return number_format(intdiv($this->displayPriceCents(), 100), 0, ',', '.');
    }

    /** Céntimos del precio de referencia (para la landing). */
    public function cents(): string
    {
        return str_pad((string) ($this->displayPriceCents() % 100), 2, '0', STR_PAD_LEFT);
    }
}

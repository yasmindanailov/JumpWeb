<?php

namespace App\Domain\Booking\Models;

use App\Domain\Booking\Services\ProductIcon;
use App\Domain\Content\Models\LandingService;
use App\Domain\Platform\Concerns\HasTranslations;
use App\Domain\Platform\Services\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class TicketType extends Model
{
    use HasTranslations;

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

    /** @var list<string> */
    public const FIELD_TYPES = [
        self::FIELD_TYPE_TEXT,
        self::FIELD_TYPE_NUMBER,
        self::FIELD_TYPE_TEXTAREA,
        self::FIELD_TYPE_AGE,
    ];

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
        'conditions' => 'array',
        'badge' => 'array',
        'event_fields' => 'array',
        'guest_fields' => 'array',
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
        'featured' => 'boolean',
        'is_sellable' => 'boolean',
        'is_active' => 'boolean',
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
     * @return HasOne<LandingService, $this>
     */
    public function landingService(): HasOne
    {
        return $this->hasOne(LandingService::class);
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

    /** ¿Es un complemento (add-on)? No consume aforo ni tiene franja (#87). */
    public function isAddon(): bool
    {
        return $this->type === self::TYPE_ADDON;
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

    // ─── Familia y tramo de edad (cumpleaños MIXTO, `specs/cumple-mixto.md` §9) ───────

    /**
     * ¿Este tipo de campo se escribe con dígitos? Lo consultan las SEIS superficies que pintan un
     * esquema data-driven (post-form web, los dos formularios del panel, los dos pasos del cajón)
     * para elegir `type="number"` / `inputmode`. Vive aquí para que añadir un tipo numérico nuevo
     * —como la EDAD— no obligue a acordarse de seis ficheros.
     */
    public static function isNumericFieldType(?string $type): bool
    {
        return in_array($type, [self::FIELD_TYPE_NUMBER, self::FIELD_TYPE_AGE], true);
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
                'type' => in_array($type, self::FIELD_TYPES, true) ? $type : self::FIELD_TYPE_TEXT,
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
        if ($type === self::FIELD_TYPE_AGE && $value !== '') {
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
        // Reindexa a una lista contigua 0..n: robusto frente a payloads asociativos o con huecos
        // (p. ej. una UI JS que borra una fila sin reindexar) — el saneo NO confía en que el
        // cliente mande una lista perfecta (regla 12). El orden de los invitados se preserva.
        $rows = array_values($rows);
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
        $count = max(0, $count);
        $clean = $this->sanitizeGuestData($rows, $count);
        $required = array_values(array_filter(
            $this->guestFields(),
            fn (array $field): bool => $field['required'],
        ));

        $done = 0;
        for ($i = 0; $i < $count; $i++) {
            $complete = true;
            foreach ($required as $field) {
                if (! isset($clean[$i][$field['key']])) {
                    $complete = false;
                    break;
                }
            }
            if ($complete) {
                $done++;
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
            ->whereDoesntHave('landingService');
    }

    /**
     * Precio (céntimos) para una tarifa concreta; null si no está definido.
     * Usa la relación `prices` (cárgala con eager load para evitar N+1).
     */
    public function priceCentsForRate(?RateType $rate): ?int
    {
        if (! $rate) {
            return null;
        }

        return $this->prices->firstWhere('rate_type_id', $rate->id)?->amount_cents;
    }

    /**
     * Precio de referencia para la landing: el de la tarifa `normal` (o, si falta,
     * el más bajo). El precio del día se aplica en el panel de compra (RateResolver).
     */
    public function displayPriceCents(): int
    {
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

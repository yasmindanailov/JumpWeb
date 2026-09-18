<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Exceptions\GuardianAuthorizationHasSignaturesException;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fase 6 · el JUSTIFICANTE de un menor invitado a una reserva — «waiver offshore»
 * (`docs/specs/waiver-por-reserva.md` §4.2).
 *
 * Una fila = **una autorización**: un menor, el adulto que responde por él y **la RESERVA a la que
 * va** — no el pedido.
 * Es la hermana PUNTUAL de {@see Dependent}: aquélla es una persona permanente de una cuenta; ésta
 * nace con una reserva y se agota con ella. Por eso son dos tablas y no una con una columna de
 * «ámbito» (§1.3, la lección de `prices` de `#324`).
 *
 * Tres reglas que son la entidad entera:
 *  - **No se edita.** Sin `updated_at` y sin escritor que actualice: una corrección es una
 *    autorización nueva. Lo que la fila dice es lo que se firmó.
 *  - **No se borra mientras su prueba exista** (`deleting` + FK RESTRICT desde `waiver_signatures`),
 *    igual que un menor a cargo con waiver. La única salida es la poda por plazo, que se la lleva
 *    cuando ya no le queda ninguna firma.
 *  - **La identifica `minor_key`, no el nombre crudo.** Ver {@see keyFor()}.
 *
 * ⚠️ **`order_item_id` es un id ENTERO sin relación Eloquent**: Booking no puede mirar a Identity, así
 * que la flecha va al revés y por contrato (`ModuleBoundariesTest`). Es el patrón de
 * {@see DependentAssignment}, que cuelga de la misma columna y por la misma razón.
 *
 * ⚠️⚠️ **Colgaba del PEDIDO hasta `#401`, y lo encontró el owner con datos reales** (§13): un pedido
 * con dos reservas en días distintos hacía que la hoja del padre dijera «Días de la visita: 03/09 ·
 * 07/09». *Un padre no autoriza un pedido: autoriza que su hijo entre a una visita concreta.* De esa
 * sola raíz salían la capacidad sumada de las dos líneas, el correo único para dos reservas marcadas
 * y un «un niño, un papel» que impedía autorizar al mismo niño para dos visitas del mismo pedido.
 */
#[Fillable([
    'order_item_id',
    // El vínculo con la respuesta de la invitación (`#576`). Lo escribe el firmador y solo cuando el
    // contrato confirma que esa respuesta es un «sí» vivo de ESTA reserva.
    'invitation_reply_id',
    'minor_name', 'minor_surname', 'minor_key', 'minor_born_on',
    'guardian_name', 'guardian_surname', 'guardian_relationship', 'guardian_email', 'guardian_phone',
])]
class GuardianAuthorization extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    public const NAME_MAX = 120;

    public const SURNAME_MAX = 120;

    public const KEY_MAX = 255;

    public const EMAIL_MAX = 255;

    public const PHONE_MAX = 32;

    /**
     * Relación del ADULTO que firma con el menor. **Es la misma lista cerrada que la de un menor a
     * cargo** ({@see Dependent::RELATIONSHIPS}) y se reutiliza a propósito: la pregunta es idéntica
     * —«¿por qué puede este adulto firmar por este niño?»— y dos listas para una pregunta acaban
     * divergiendo. No se redeclara aquí para que no haya una segunda copia que mantener.
     */
    public const RELATIONSHIPS = Dependent::RELATIONSHIPS;

    protected $casts = [
        'order_item_id' => 'integer',
        'minor_born_on' => 'immutable_date',
    ];

    protected static function booted(): void
    {
        // La misma doctrina que `Dependent::deleting` (`menores-a-cargo.md` §4.4), y por la misma
        // razón: una fila con una prueba detrás no se borra desde ningún sitio. La FK RESTRICT lo
        // impone en la base de datos; esto da el mensaje y cubre a quien borre por modelo.
        static::deleting(function (self $row): void {
            if (! $row->pruneAuthorised && $row->waiverSignatures()->exists()) {
                throw GuardianAuthorizationHasSignaturesException::for($row);
            }
        });
    }

    /** Solo la poda por plazo puede borrar una fila que ya no tiene prueba detrás. */
    private bool $pruneAuthorised = false;

    // ─── La clave de identidad ────────────────────────────────────────────────

    /**
     * La clave con la que «un niño, un papel» (`[DECIDIDO owner]` §7·9) se cumple **igual en MySQL y
     * en SQLite**.
     *
     * ⚠️⚠️ **No se puede poner el `UNIQUE` sobre los nombres crudos**, y está medido: todas las tablas
     * del proyecto son `utf8mb4_unicode_ci`, donde `'Perez' = 'Pérez'` y `'ana' = 'Ana'` dan **1**;
     * en SQLite —donde corre la suite— la comparación es byte a byte y dan **0**. Una guarda escrita
     * sobre esa columna mediría una conducta en el test y la contraria en producción: es el
     * precedente de `panel-navegacion.md` §7.3, invertido.
     *
     * La salida no es elegir motor, es no depender de ninguno: se normaliza en PHP.
     *
     * ⚠️ **El respaldo cuando `Str::ascii()` deja la cadena vacía no es decorativo**: esos nombres se
     * convertirían en `''` y **todos** esos menores colisionarían en la misma clave dentro de un
     * pedido. Con el respaldo, se normaliza lo que se pueda y se conserva el original.
     * ▶ **«Alfabeto no latino» era el criterio equivocado**, y esta línea lo decía hasta que se midió
     * (2026-09-17, `#573`): el cirílico, el griego y el árabe **sí** se transliteran. El detalle, con
     * los alfabetos que de verdad se vacían, en el docblock de {@see PersonNameKey}.
     *
     * ▶ **La normalización SUBIÓ a `Platform\Services\PersonNameKey`** (T4·1 de
     * `celebracion-e-invitacion.md`, `DECISIONES #573`): desde la invitación digital la misma
     * pregunta se hace en **Booking** —emparejar lo que contesta un padre con una ficha del
     * post-form— y Booking no puede mirar a Identity. Aquí se conserva el método porque es el
     * vocabulario de esta entidad («la clave de un menor»), y porque su firma de dos campos —nombre y
     * apellidos en columnas separadas (`#236`)— es de esta tabla, no del normalizador. La salida es
     * **idéntica**, con un caso de paridad que lo vigila.
     */
    public static function keyFor(string $name, string $surname): string
    {
        return PersonNameKey::for($name.' '.$surname);
    }

    /** Nombre y apellidos, con el espacio SOLO si hay apellidos — como {@see Dependent::fullName()}. */
    public function minorFullName(): string
    {
        $surname = trim((string) $this->minor_surname);

        return $surname !== '' ? trim((string) $this->minor_name).' '.$surname : trim((string) $this->minor_name);
    }

    public function guardianFullName(): string
    {
        $surname = trim((string) $this->guardian_surname);

        return $surname !== '' ? trim((string) $this->guardian_name).' '.$surname : trim((string) $this->guardian_name);
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    /**
     * Las firmas hechas para este menor invitado. Normalmente UNA; son varias cuando se publica una
     * versión nueva del texto y se vuelve a firmar (la cadena es justo para eso).
     *
     * @return HasMany<WaiverSignature, $this>
     */
    public function waiverSignatures(): HasMany
    {
        return $this->hasMany(WaiverSignature::class, 'subject_authorization_id')
            ->where('subject_type', WaiverSignature::SUBJECT_GUEST_MINOR);
    }

    // ─── Poda (§4.14) ─────────────────────────────────────────────────────────

    /**
     * Las filas que se quedaron **sin ninguna prueba detrás**: la poda por plazo se llevó su última
     * firma y lo que queda es PII de un menor sin nada que la justifique. Es exactamente lo que hace
     * `Dependent::prunable()` con un menor desvinculado y sin referencias.
     *
     * ⚠️ Una autorización **nace con su firma en la misma transacción** —lo hace el servicio
     * `GuardianAuthorizationSigner`, citado aquí en prosa a propósito: con `{@see}` Pint acorta el
     * FQN y AÑADE el `use`, creando una dependencia Modelo → Servicio hacia la clase que ya depende
     * de este modelo (la trampa de `DECISIONES #320`)—, así que una fila sin firmas solo puede venir
     * de la poda. No hay ventana en la que ésta se lleve una recién creada.
     *
     * ⚠️⚠️ Esto no corre solo: el modelo tiene que estar en la lista EXPLÍCITA de `model:prune` de
     * `routes/console.php`, y **después** de `WaiverSignature` (la FK es RESTRICT). Un `Prunable` que
     * no entre ahí no se poda nunca.
     *
     * @return Builder<GuardianAuthorization>
     */
    public function prunable(): Builder
    {
        return static::query()->whereDoesntHave('waiverSignatures');
    }

    protected function pruning(): void
    {
        $this->pruneAuthorised = true;
    }
}

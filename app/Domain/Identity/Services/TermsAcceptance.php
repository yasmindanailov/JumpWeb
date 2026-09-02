<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * **LA ACEPTACIÓN DE LAS CONDICIONES, VERSIONADA** (`specs/auth-con-google.md` §21.4, `#348`).
 *
 * `[DECIDIDO owner, 2026-09-02]`: las condiciones se aceptan **en el momento del contrato** —la
 * compra— y no al crear la cuenta, *«y si se cambian las condiciones, se pide de nuevo diciendo que
 * se han actualizado, igual que el waiver»*.
 *
 * ## Por qué esto no es solo comodidad
 *
 * ⚠️⚠️ **Medido antes de escribir nada: el embudo NO enseña las condiciones en ningún sitio** —cero
 * enlaces a `legal.condiciones` en los ocho pasos del cajón, y ningún correo las enlaza tampoco—.
 * Hoy solo aparecen en la casilla del alta, así que **un cliente que ya tiene cuenta compra sin que
 * se le muestren nunca**. Eso es justo lo que la LCGC (Ley 7/1998, art. 5) pide evitar para que unas
 * condiciones generales queden incorporadas al contrato, y lo que el TRLGDCU (RDL 1/2007, art. 97)
 * exige como información precontractual. *Mover la aceptación al checkout no relaja nada: cierra un
 * hueco que existe hoy.*
 *
 * ## De dónde sale la versión, y por qué NO de una constante
 *
 * De la **publicación**, igual que el descargo: `LegalDocumentVersion` con slug `condiciones`. La
 * alternativa era `Consent::CURRENT_VERSION`, una constante escrita a mano que —medido— **no la lee
 * nadie**: con ella, alguien edita el texto en el panel, se olvida de subir la constante y el
 * producto afirma que el cliente aceptó un texto que nunca vio. `[DECIDIDO owner]`: la versión no
 * puede divergir del texto.
 *
 * ⚠️ **Sin ninguna versión publicada, esto NO pide nada** y la venta sigue. El hueco falla hacia
 * invisible, como las claves de Google o el kit del cliente: una instalación recién montada no puede
 * quedarse sin poder vender porque a nadie le haya dado tiempo a pulsar «Publicar».
 *
 * @see LegalDocuments  de dónde sale la versión vigente
 */
final class TermsAcceptance
{
    /** La página legal de las condiciones (`Page::PROTECTED_ACTIVE_SLUGS`). */
    public const SLUG = 'condiciones';

    /** La versión vigente en el idioma pedido, o `null` si esta instalación no ha publicado ninguna. */
    public static function currentVersion(?string $locale = null): ?LegalDocumentVersion
    {
        return LegalDocuments::current(self::SLUG, $locale);
    }

    /**
     * **¿Tiene que aceptar antes de contratar?**
     *
     * ⚠️⚠️ **La regla de gracia va AQUÍ y en ningún otro sitio** (`[DECIDIDO owner, 2026-09-02]`, sobre
     * las dos opciones y su coste): quien aceptó las condiciones en el alta **antes de que existiera
     * el versionado** cuenta como que aceptó la **PRIMERA** versión publicada, y no se le vuelve a
     * preguntar.
     *
     * ▶ **Y se hace con una REGLA, no reescribiendo su fila.** Cambiarle la versión al consentimiento
     * viejo —de `2026-05-23` a `v1·es`— dejaría el registro afirmando que aceptó un documento que
     * todavía no existía cuando firmó. *Una prueba no se edita para que la consulta salga más corta.*
     *
     * ▶ **La gracia MUERE en la v2**: sale de que `numberOf()` no sabe leer una versión anterior al
     * versionado, así que solo empata con la primera. Al publicar una segunda, todo el mundo vuelve a
     * pasar por la casilla — que es exactamente lo que el owner pidió.
     *
     * ⚠️ **El supuesto que asume esta decisión, dicho para que no se descubra tarde**: que el texto de
     * la v1 sea el MISMO que aceptaron. Lo es mientras nadie edite `condiciones` entre hoy y su
     * primera publicación. Si hay que retocarlo, se publica ANTES y se edita después.
     */
    public function pendingFor(User $user): bool
    {
        return $this->statusFor($user)['pending'];
    }

    /**
     * **Si le faltan Y si es porque han CAMBIADO**, que son dos cosas distintas y las dos hacen falta.
     *
     * `pending` decide si se le pide; `updated` decide **qué se le dice**. `[owner]`: *«si se cambian
     * las condiciones, se pide de nuevo diciendo que las condiciones se han actualizado»* — y eso no
     * se puede decir sin distinguir a quien nunca las aceptó de quien aceptó una versión anterior.
     *
     * ⚠️ Las dos salen de la MISMA lectura: separarlas en dos métodos públicos costaría dos consultas
     * por cada página con sesión, porque de aquí bebe el contexto de cuenta.
     *
     * @return array{pending: bool, updated: bool}
     */
    public function statusFor(User $user): array
    {
        $current = LegalDocuments::latestVersionNumber(self::SLUG);

        if ($current === null) {
            return ['pending' => false, 'updated' => false];
        }

        $accepted = $this->liveVersions($user);

        foreach ($accepted as $version) {
            $number = self::numberOf($version);

            if ($number === $current) {
                return ['pending' => false, 'updated' => false];
            }

            if ($number === null && $current === 1) {
                return ['pending' => false, 'updated' => false];
            }
        }

        // Tiene alguna aceptación, pero no la vigente: para él las condiciones han CAMBIADO. Sin
        // ninguna, es la primera vez y decirle «las hemos actualizado» sería mentirle.
        return ['pending' => true, 'updated' => $accepted !== []];
    }

    /**
     * Registra la aceptación de la versión vigente y devuelve cuál era.
     *
     * ⚠️ **Bajo el lock del titular y comprobando otra vez**, como el firmador del descargo: dos
     * pestañas o un doble clic no pueden dejar dos pruebas del mismo consentimiento. Y devuelve
     * `null` si no había nada que aceptar, para que quien llama no invente una fila.
     */
    public function accept(User $user, string $ip): ?LegalDocumentVersion
    {
        $version = self::currentVersion();

        if ($version === null) {
            return null;
        }

        return DB::transaction(function () use ($user, $ip, $version): ?LegalDocumentVersion {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->pendingFor($locked)) {
                return $version;
            }

            $now = now();

            $locked->consents()->create([
                'type' => Consent::TYPE_TERMS,
                'accepted_at' => $now,
                'ip' => $ip,
                'version' => $version->label(),
            ]);

            // La columna que ya leían el panel y el export: se mantiene al día, no se sustituye.
            $locked->forceFill(['terms_accepted_at' => $now])->save();

            return $version;
        });
    }

    /**
     * El NÚMERO de versión que declara una etiqueta de consentimiento, o `null` si es anterior al
     * versionado.
     *
     * ⚠️ Se compara por NÚMERO y no por la etiqueta entera: `v1·es` y `v1·en` son la misma versión, y
     * quien aceptó en inglés y vuelve en castellano ya la aceptó. Es el mismo formato que escribe el
     * firmador del descargo (`LegalDocumentVersion::label()`).
     */
    public static function numberOf(?string $label): ?int
    {
        return preg_match('/^v(\d+)·/', (string) $label, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * @return list<string> las versiones de las condiciones que este titular tiene VIVAS
     */
    private function liveVersions(User $user): array
    {
        return $user->consents()
            ->where('type', Consent::TYPE_TERMS)
            ->whereNull('revoked_at')
            ->pluck('version')
            ->map(fn ($v): string => (string) $v)
            ->all();
    }
}

<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Models\Experiment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * **Los experimentos: la asignación la hace el SERVIDOR** (`docs/specs/analitica.md` §4.4, T5a).
 *
 * La cookie del visitante es `HttpOnly` (§4.1), así que el cliente no puede repartirse solo: el servidor
 * calcula la variante con `hash(clave | sujeto)` y la manda en `/sidebar/session` (`no-store`, por
 * visitante) y en el `data-boot` de las páginas SSR. No se guarda ninguna asignación: el mismo sujeto cae
 * siempre en la misma variante mientras el experimento no cambie, y lo que de verdad cuenta es
 * `experiment_exposed` en el libro —a quién se le ENSEÑÓ—, no a quién se le asignó.
 *
 * ⚠️ **El sujeto es el VISITANTE cuando lo hay, y el titular solo si no lo hay** (`[DECIDIDO 24-09]`, `#737`).
 * La spec decía «`hash(user_id)` en el régimen identificado», y se midió lo que eso haría en la compra: el
 * paso de identificarse va EN MEDIO del embudo, así que a quien entra con su cuenta le cambiaría la
 * variante —la carcasa, el orden de los pasos— a mitad de compra, y cada compra con sesión sería una
 * exposición contaminada. Con la cookie manda la cookie: la unidad de la exposición y la de la conversión
 * es la misma (`analytics_events.visitor_id`), y la contaminación que queda —una persona con dos
 * dispositivos— es la que el panel cuenta (§4.4). El titular es el sujeto de la app (Bearer sin
 * `X-Visitor`), que no tiene cookie.
 *
 * ⚠️ Los pesos son enteros positivos y el reparto es por acumulación en el orden guardado: `n = hash mod
 * total`, y la variante es la primera cuyo peso acumulado supera `n`. Cambiar los pesos o el orden
 * REBARAJA a todo el mundo: un experimento vivo no se edita, se cierra y se abre otro.
 *
 * Está en `Platform` y no mira a nadie (`ModuleBoundariesTest`): el arranque del cajón (capa de entrega)
 * es quien lo llama.
 */
final class Experiments
{
    public const CACHE_KEY = 'analytics:experiments:rows';

    public const CACHE_SECONDS = 60;

    /** La clave de un experimento, tal y como viaja al cliente y al libro. */
    public const KEY_RE = '/^[a-z][a-z0-9_-]{0,47}$/';

    /** La clave de una variante. */
    public const VARIANT_RE = '/^[a-z0-9][a-z0-9_-]{0,31}$/';

    /**
     * Las variantes de quien hace ESTA petición: el visitante del contexto de atribución (`ResolveVisitor`, que
     * en la web acuña la cookie ANTES de componer la página para que la primera vista ya traiga la suya) o,
     * sin él, el titular de la sesión.
     *
     * @return array<string, string> clave del experimento → variante
     */
    public static function forRequest(): array
    {
        $userId = auth()->id();

        return self::assignments(app(AttributionContext::class)->visitorId(), $userId === null ? null : (int) $userId);
    }

    /**
     * @return array<string, string> clave del experimento → variante; vacío sin sujeto o sin experimentos vivos
     */
    public static function assignments(?string $visitorId, ?int $userId): array
    {
        $subject = self::subject($visitorId, $userId);

        if ($subject === null) {
            return [];
        }

        $out = [];

        foreach (self::running() as $experiment) {
            $variant = self::variantFor($experiment, $subject);

            if ($variant !== null) {
                $out[$experiment->key] = $variant;
            }
        }

        return $out;
    }

    /** El sujeto del reparto (ver la cabecera): `v:<visitante>`, si no `u:<titular>`, si no nada. */
    public static function subject(?string $visitorId, ?int $userId): ?string
    {
        if ($visitorId !== null && $visitorId !== '') {
            return 'v:'.$visitorId;
        }

        return $userId === null ? null : 'u:'.$userId;
    }

    /** La variante de un sujeto en un experimento, o `null` si el experimento no tiene dos variantes válidas. */
    public static function variantFor(Experiment $experiment, string $subject): ?string
    {
        $variants = $experiment->weightedVariants();

        if (count($variants) < 2) {
            return null;
        }

        // 32 bits del SHA-256: uniformes, deterministas y con margen de sobra para cualquier suma de pesos.
        $n = hexdec(substr(hash('sha256', $experiment->key.'|'.$subject), 0, 8)) % array_sum($variants);

        foreach ($variants as $key => $weight) {
            if ($n < $weight) {
                return $key;
            }

            $n -= $weight;
        }

        return array_key_last($variants);
    }

    /**
     * Los experimentos vivos AHORA. Las filas van cacheadas 60 s (y se olvidan al guardar o borrar una); la ventana
     * temporal se mira en cada lectura, para que uno que acaba no siga asignando hasta que caduque la caché.
     *
     * ⚠️ En la caché van ARRAYS planos, no modelos: un almacén que serializa (`database`, `file`) devolvió la
     * colección de modelos como objeto incompleto en tinker el día que nació esto, y una caché que solo funciona en
     * el proceso que la escribió no es una caché. Los modelos se rehacen al leer con `forceFill()`, que pasa por
     * los casts (`variants` json, fechas).
     *
     * @return Collection<int, Experiment>
     */
    public static function running(): Collection
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_SECONDS,
            static fn (): array => Experiment::query()->where('active', true)->orderBy('id')->get()->toArray(),
        );

        return collect($rows)
            ->map(static fn (array $row): Experiment => (new Experiment)->forceFill($row))
            ->filter(static fn (Experiment $experiment): bool => $experiment->isRunning())
            ->values();
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonInterface;

/**
 * **La reseña del día de la Puerta** (`docs/specs/puerta-nueva.md` §4.4, la P3: D16, D19, D20 y D21; el mockup: «Lo que
 * dicen de vosotros.», con el campo vacío y en el velo, nunca en la ficha).
 *
 * ▶ **Dos fuentes, en el orden de la portada** (`#732`, D16): primero el Perfil de Empresa (`google_business_reviews`,
 * cuando la ficha conecte) y, si ninguna de allí dice las palabras, las COPIADAS de la ficha que el parque tiene activas
 * (`testimonials` con `origin = google`, `#771`: las que enseña hoy la portada). Nunca las opiniones escritas en el panel:
 * la línea dice «en Google».
 * ▶ **En las dos**: escritas en los últimos {@see WINDOW_DAYS} días contados desde la medianoche del PARQUE y con las
 * estrellas del mínimo VIGENTE del panel. ⚠️ El mínimo se reaplica al leer —la pasada solo lo aplica al guardar— porque
 * aquí lo que elige es una PALABRA: elige reseñas que hablan del equipo, no que lo alaben, y el mínimo es lo que impide que
 * salga «los monitores, fatal». Una copiada sin nota no entra.
 * ▶ **Cuál sale: por TURNO, una con cada cliente** (`#910`, el owner, al ver la P3: «¿por qué solo sale una al refrescar?»).
 * El turno lo lleva quien pinta —la pantalla de la Puerta avanza uno cada vez que vuelve a quedar vacía—; aquí solo se
 * cuenta sobre la lista de las que casan, la más nueva primero.
 * ⚠️⚠️ **Sin caché, a propósito** (D21): apagar u ocultar una reseña tiene que quitarla de la Puerta en la pantalla
 * siguiente. Son dos consultas sobre unas decenas de filas, y la segunda solo si la primera no da nada.
 */
final class GateReviewOfTheDay
{
    /** Cuántos días atrás llega (el mockup: «de los últimos 30 días»). */
    public const WINDOW_DAYS = 30;

    /** Dónde se corta el texto, en el último espacio, con «…» (el mockup). */
    public const CUT = 190;

    /** La reseña viene del Perfil de Empresa. */
    public const SOURCE_PROFILE = 'perfil';

    /** La reseña es una copiada de la ficha (`#771`). */
    public const SOURCE_COPIED = 'copiada';

    /**
     * **La de este turno, lista para pintar**, o `null` (sin palabras, o ninguna reseña las dice: el icono del lector).
     *
     * @param  int  $turno  el de la pantalla (`#910`): 0 es la más nueva; pasado el final, vuelve a empezar
     * @return array{id: int, fuente: string, texto: string, autor: ?string, cuando: ?string}|null
     */
    public function forGate(int $turno): ?array
    {
        $palabras = ReviewKeywords::fromSettings();
        if ($palabras->isEmpty()) {
            return null;
        }

        $elegida = self::pick($this->matching($palabras, DisplayTime::today()), $turno);

        return $elegida === null ? null : self::present($elegida);
    }

    /**
     * **Lo que el panel dice bajo las palabras** (D21), de la MISMA consulta que la Puerta: si hay reseñas de Google en la
     * ventana, cuántas dicen las palabras y la primera del turno (la más nueva).
     *
     * @return array{hay: bool, casan: int, primera: array{id: int, fuente: string, texto: string, autor: ?string, cuando: ?string}|null}
     */
    public function summary(ReviewKeywords $palabras): array
    {
        $hoy = DisplayTime::today();
        // El MISMO camino que la Puerta (`matching()` + `pick()`): una ayuda con su propia copia de la regla diría «sale
        // esta» de una que la Puerta ya no enseña.
        $casan = $this->matching($palabras, $hoy);
        $primera = self::pick($casan, 0);

        return [
            // Si ninguna casa, ¿es que no hay reseñas o que no dicen las palabras? Dos consultas más, solo en el panel.
            'hay' => $casan !== [] || $this->profile($hoy) !== [] || $this->copied($hoy) !== [],
            'casan' => count($casan),
            'primera' => $primera === null ? null : self::present($primera),
        ];
    }

    /**
     * Las que dicen las palabras, de la fuente que gana, en su orden (D16, D19).
     *
     * @return list<array{id: int, fuente: string, texto: string, autor: ?string, fecha: ?CarbonInterface}>
     */
    public function matching(ReviewKeywords $palabras, CarbonInterface $hoy): array
    {
        $delPerfil = self::filterMatching($palabras, $this->profile($hoy));

        // La segunda consulta, solo si la primera no da nada: el Perfil va delante, como en la portada.
        return $delPerfil !== [] ? $delPerfil : self::filterMatching($palabras, $this->copied($hoy));
    }

    /**
     * **La de un turno** (D19, `#910`): la del puesto «turno, módulo cuántas hay» de la lista que casa, la más nueva primero.
     * Con N que casan, N turnos seguidos enseñan las N y el siguiente vuelve a la primera. Un turno negativo (no lo da la
     * Puerta) cuenta como su valor absoluto: nunca un índice fuera de la lista.
     *
     * @template T
     *
     * @param  list<T>  $casan
     * @return T|null
     */
    public static function pick(array $casan, int $turno): mixed
    {
        if ($casan === []) {
            return null;
        }

        return $casan[abs($turno) % count($casan)];
    }

    /**
     * **El texto de la cita** (D20, el mockup): sin tocar una palabra, los saltos de línea a un espacio y, si pasa de
     * {@see CUT} caracteres, cortado en el último espacio con «…» (sin espacio, en el {@see CUT}).
     */
    public static function cut(string $texto): string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
        if (mb_strlen($texto) <= self::CUT) {
            return $texto;
        }

        $espacio = mb_strrpos(mb_substr($texto, 0, self::CUT + 1), ' ');
        $cabeza = $espacio === false || $espacio === 0 ? mb_substr($texto, 0, self::CUT) : mb_substr($texto, 0, $espacio);

        return rtrim($cabeza, ' ,;:.·-–—').'…';
    }

    /**
     * Las del Perfil de Empresa que pueden salir hoy: dentro del plazo de Google, de la ventana, del mínimo y sin
     * `text_ambiguous` (una palabra podría casar con la traducción de Google y atribuirle al autor lo que escribió una
     * máquina). El autor, el que pinta la portada: sin una pasada que lo confirme, sin nombre.
     *
     * @return list<array{id: int, fuente: string, texto: string, autor: ?string, fecha: ?CarbonInterface}>
     */
    private function profile(CarbonInterface $hoy): array
    {
        return GoogleBusinessReview::query()
            ->withinRetention()
            // ⚠️ La medianoche del PARQUE, en UTC: la base guarda en UTC y el constructor de consultas no convierte.
            ->where('review_created_at', '>=', self::windowStart($hoy)->utc())
            ->where('star_rating', '>=', GoogleReviewFilter::fromSettings()->minStars)
            ->where('text_ambiguous', false)
            ->orderByDesc('review_created_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (GoogleBusinessReview $r): array => [
                'id' => (int) $r->id,
                'fuente' => self::SOURCE_PROFILE,
                'texto' => (string) $r->comment,
                'autor' => $r->publishableAuthor(),
                'fecha' => $r->review_created_at,
            ])
            ->values()
            ->all();
    }

    /**
     * Las COPIADAS de la ficha (`#771`) que pueden salir hoy: ACTIVAS —el parque eligió publicarlas; sus etiquetas son
     * páginas de la web, no la Puerta—, de la ventana (su fecha, la deducida al copiar) y del mínimo. Su autor, su firma.
     *
     * @return list<array{id: int, fuente: string, texto: string, autor: ?string, fecha: ?CarbonInterface}>
     */
    private function copied(CarbonInterface $hoy): array
    {
        return Testimonial::query()
            ->where('origin', Testimonial::ORIGIN_GOOGLE)
            ->where('is_active', true)
            // ⚠️ `whereDate` y no `where`: la columna es una FECHA, y SQLite la guarda como texto con su hora
            // («2026-09-02 00:00:00»), mayor que «2026-09-02» comparada como texto; el día del borde entraba con `>` y con
            // `>=` por igual en la suite, y en MySQL no (lo destapó el arnés, 02-10). Así los dos motores comparan días.
            ->whereDate('published_at', '>=', self::windowStart($hoy)->toDateString())
            ->where('rating', '>=', GoogleReviewFilter::fromSettings()->minStars)
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (Testimonial $t): array => [
                'id' => (int) $t->id,
                'fuente' => self::SOURCE_COPIED,
                'texto' => (string) $t->tr('text'),
                'autor' => trim((string) $t->author) !== '' ? trim((string) $t->author) : null,
                'fecha' => $t->published_at,
            ])
            ->values()
            ->all();
    }

    /** La medianoche del parque de hace {@see WINDOW_DAYS} días. */
    private static function windowStart(CarbonInterface $hoy): CarbonInterface
    {
        return $hoy->copy()->startOfDay()->subDays(self::WINDOW_DAYS);
    }

    /**
     * @param  list<array{id: int, fuente: string, texto: string, autor: ?string, fecha: ?CarbonInterface}>  $candidatas
     * @return list<array{id: int, fuente: string, texto: string, autor: ?string, fecha: ?CarbonInterface}>
     */
    private static function filterMatching(ReviewKeywords $palabras, array $candidatas): array
    {
        return array_values(array_filter(
            $candidatas,
            static fn (array $c): bool => trim($c['texto']) !== '' && $palabras->matches($c['texto']),
        ));
    }

    /**
     * @param  array{id: int, fuente: string, texto: string, autor: ?string, fecha: ?CarbonInterface}  $c
     * @return array{id: int, fuente: string, texto: string, autor: ?string, cuando: ?string}
     */
    private static function present(array $c): array
    {
        return [
            'id' => $c['id'],
            'fuente' => $c['fuente'],
            'texto' => self::cut($c['texto']),
            'autor' => $c['autor'],
            'cuando' => RelativeAge::of($c['fecha']),
        ];
    }
}

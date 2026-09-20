<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Enums\GoogleBusinessSyncOutcome;
use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\GoogleBusinessReviewSummary;
use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Exceptions\GoogleBusinessApiException;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Services\GoogleBusinessConnectionState;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * **La pasada: recorre la ficha y deja la tabla como está la ficha** (T2·3,
 * `docs/specs/google-business-profile.md` §4.3·1 → §4.3·3; `DECISIONES #524`, `#729`).
 *
 * Es el único sitio que escribe en `google_business_reviews`, y el único que **borra**. Lo segundo es
 * lo delicado: una tabla de reseñas se puede repoblar mañana, pero entre hoy y mañana la portada del
 * parque enseña lo que haya —o no enseña nada—, y eso lo ven sus clientes.
 *
 * ❗❗❗ **LA REGLA QUE MANDA: se ESCRIBE siempre lo que se vio, se BORRA solo con una pasada
 * coherente** ({@see GoogleReviewPass::coherent()}, §4.3·3). Son dos permisos distintos y confundirlos
 * es el defecto caro de esta tanda: una pasada que se quedó sin tiempo, o que se topó con Google
 * moviendo la ficha, ha visto **menos** reseñas de las que hay — y lo que no ha visto se parece
 * exactamente a lo que ya no existe. Guardar lo que sí vio siempre es seguro; borrar lo que no vio,
 * no.
 *
 * ⚠️⚠️ **UN SOLO CANDADO, y en `cache_locks` de la BASE** (§4.3·1). No en el almacén por defecto: el
 * de Redis va con `allkeys-lru` y **puede desalojar la llave bajo presión de memoria**, que es justo
 * el momento en que dos pasadas a la vez harían más daño. Un candado que se puede evaporar no es un
 * candado.
 *
 * ⚠️ **El presupuesto de tiempo y la caducidad del candado son el MISMO número** (§4.3·1): si el
 * candado durase menos, una segunda pasada entraría mientras la primera sigue escribiendo; si durase
 * más, un worker muerto dejaría la sincronización bloqueada hasta que alguien se diera cuenta.
 */
final class GoogleBusinessSync
{
    /** La llave del candado. Una sola, porque una instalación es un parque y un parque es una ficha. */
    public const LOCK_KEY = 'google-business.sync';

    /**
     * El presupuesto de una pasada, en segundos (§4.3·1). Con páginas de 50 y un parque normal, una
     * pasada entera son dos o tres llamadas; los 120 segundos son para que una ficha enorme o una
     * tarde lenta de Google **paren solas** en vez de quedarse colgadas.
     */
    public const BUDGET_SECONDS = 120;

    /**
     * ⚠️ **El almacén se nombra explícitamente y no se hereda del `default`.** Hoy el `default` ya es
     * `database`, pero el día que alguien ponga `CACHE_STORE=redis` —que es lo normal en producción—
     * este candado se mudaría a un almacén que desaloja, y nada avisaría.
     */
    public const LOCK_STORE = 'database';

    public function __construct(private readonly GoogleReviewReader $reader) {}

    /**
     * El candado de la pasada, siempre el mismo y siempre del mismo almacén.
     *
     * ⚠️ **`lock()` no está ni en el contrato `Repository` ni en la clase**: vive en el `Store`, y el
     * repositorio se lo reenvía por `__call` —medido el 2026-09-20, buscándolo en el framework—. Así
     * que se pide donde de verdad está, que además es lo que hace explícito que **el almacén tiene
     * que saber echar candados** ({@see LockProvider}) y no solo guardar valores.
     *
     * ⚠️ Se escribe una sola vez aquí para que no haya dos sitios desde los que alguien pudiera pedir
     * el candado a otro almacén.
     */
    private static function lock(): Lock
    {
        /** @var Repository $repositorio */
        $repositorio = Cache::store(self::LOCK_STORE);

        /** @var LockProvider $almacen */
        $almacen = $repositorio->getStore();

        return $almacen->lock(self::LOCK_KEY, self::BUDGET_SECONDS);
    }

    /** ¿Hay una pasada en curso ahora mismo? Lo pregunta el panel para decirlo (§4.3·1). */
    public static function inProgress(): bool
    {
        $lock = self::lock();

        if (! $lock->get()) {
            return true;
        }

        // Se cogió para mirar, así que se suelta: preguntar no puede dejar el candado echado.
        $lock->release();

        return false;
    }

    public function run(): GoogleBusinessSyncResult
    {
        $conexion = GoogleBusinessConnection::current();
        $estado = GoogleBusinessConnectionState::of($conexion);

        // §4.2·7: de los siete estados, solo «conectada» llama. Reintentar contra una caducada gasta
        // cuota compartida entre todos los parques y no arregla nada.
        if ($estado !== GoogleBusinessStatus::Connected || $conexion === null) {
            return new GoogleBusinessSyncResult(GoogleBusinessSyncOutcome::Idle, $estado);
        }

        $token = $conexion->readToken();
        $parent = $conexion->reviewsParent();

        // `parent` a `null` es una ficha elegida antes de la T1·5, sin cuenta guardada: pedirle las
        // reseñas devolvería un 404 que se leería como «ficha perdida» y mandaría a reconectar para
        // nada (`#726`). Se vuelve a elegir ficha y se arregla solo.
        if ($token === null || $parent === null) {
            return new GoogleBusinessSyncResult(GoogleBusinessSyncOutcome::Idle, $estado);
        }

        $lock = self::lock();

        if (! $lock->get()) {
            return new GoogleBusinessSyncResult(GoogleBusinessSyncOutcome::Busy, $estado);
        }

        try {
            $pasada = $this->reader->pass(
                $token,
                $parent,
                GoogleReviewFilter::fromSettings(),
                now()->getTimestamp() + self::BUDGET_SECONDS,
            );

            // ⚠️⚠️ **La escritura va DENTRO del candado, no después.** Con el `release()` antes de
            // persistir, dos pasadas podrían estar escribiendo la misma tabla a la vez y la segunda
            // borraría con las candidatas de la primera. Se ve leyendo el código sólo si uno se
            // pregunta dónde termina el `try`, y ningún test de un solo hilo lo notaría — por eso
            // hay un caso que comprueba que el candado sigue echado mientras se escribe.
            $resultado = $this->persist($pasada, $conexion, $estado);

            Log::info('google_business.sync', $resultado->toLog());

            return $resultado;
        } catch (GoogleBusinessApiException $e) {
            $this->markStatus($conexion, $token, $e);

            $resultado = new GoogleBusinessSyncResult(
                GoogleBusinessSyncOutcome::Failed,
                GoogleBusinessConnectionState::current(),
            );

            Log::warning('google_business.sync_failed', $resultado->toLog() + ['http' => $e->httpStatus, 'reason' => $e->reason]);

            return $resultado;
        } finally {
            $lock->release();
        }
    }

    /**
     * Guarda la pasada. Lo que se vio se escribe; lo que sobra se retira **solo si se puede creer**.
     */
    private function persist(
        GoogleReviewPass $pasada,
        GoogleBusinessConnection $conexion,
        GoogleBusinessStatus $estado,
    ): GoogleBusinessSyncResult {
        $coherente = $pasada->coherent();

        [$guardadas, $retiradas] = DB::transaction(function () use ($pasada, $conexion, $coherente) {
            $ahora = now();
            $existentes = GoogleBusinessReview::query()->get()->keyBy('review_name');
            $vistas = [];

            foreach ($pasada->candidates as $candidata) {
                $vistas[$candidata->name] = true;

                $atributos = [
                    'author_name' => $candidata->authorName,
                    'anonymous' => $candidata->anonymous,
                    'star_rating' => $candidata->stars,
                    'comment' => $candidata->comment,
                    'text_ambiguous' => $candidata->textAmbiguous,
                    'reply_comment' => $candidata->replyComment,
                    'reply_at' => $candidata->replyAt,
                    'review_created_at' => $candidata->createdAt,
                    'review_updated_at' => $candidata->updatedAt,
                    // ⚠️⚠️ `fetched_at` se renueva **cambie o no la reseña** (§4.3·3): no es «cuándo
                    // se insertó», es «cuándo se la vio por última vez en la ficha», y de eso
                    // dependen los dos plazos de `GoogleBusinessReview`. Una reseña de hace dos años
                    // que sigue publicada está igual de viva que una de ayer.
                    'fetched_at' => $ahora,
                ];

                // ⚠️⚠️ **`author_photo_path` y `photos` NO se tocan aquí**, y es deliberado: la URL
                // que trae `IncomingGoogleReview` es la de Google, y en la base solo cabe una ruta
                // nuestra (§4.3·9). Quien las rellena es la T2·4, después de descargarlas.
                $fila = $existentes->get($candidata->name);

                if ($fila === null) {
                    GoogleBusinessReview::create(['review_name' => $candidata->name] + $atributos);
                } else {
                    $fila->update($atributos);
                }
            }

            $retiradas = 0;

            if ($coherente) {
                foreach ($existentes as $nombre => $fila) {
                    if (isset($vistas[$nombre])) {
                        continue;
                    }

                    // ⚠️ **Fila a fila y por el MODELO**, no con un `whereNotIn(...)->delete()`: un
                    // borrado en masa no instancia nada, y la T2·4 cuelga del evento el borrado del
                    // fichero de la foto. Con doce filas, el ahorro de la consulta única no paga
                    // dejar imágenes huérfanas en el disco.
                    $fila->delete();
                    $retiradas++;
                }

                // El resumen solo se toca con una pasada creíble: una media recogida a mitad de un
                // cambio es una afirmación falsa sobre un tercero, y §4.3·10 la enseña con su fecha.
                GoogleBusinessReviewSummary::query()->updateOrCreate(['singleton' => true], [
                    'average_rating' => $pasada->lastAverage,
                    'total_review_count' => $pasada->lastTotal,
                    // Copiados de la conexión para que la portada no tenga que leer la fila que
                    // lleva el token cifrado (§4.2·5).
                    'maps_uri' => $conexion->maps_uri,
                    'new_review_uri' => $conexion->new_review_uri,
                    'fetched_at' => $ahora,
                ]);
            }

            return [count($pasada->candidates), $retiradas];
        });

        return new GoogleBusinessSyncResult(
            outcome: GoogleBusinessSyncOutcome::Done,
            status: $estado,
            kept: $guardadas,
            deleted: $retiradas,
            seen: $pasada->seen,
            coherent: $coherente,
            ambiguous: $pasada->ambiguous,
        );
    }

    /**
     * Apunta en la conexión el «no» permanente de Google, **si el token sigue siendo el mismo**.
     *
     * ⚠️⚠️ **Comparar-y-escribir, y aquí es donde por fin se usa** (`tokenStillIs()`, §4.2·7): entre
     * que esta pasada cogió el token y que Google contestó `invalid_grant`, el admin puede haber
     * reconectado desde el panel. Marcar «caducada» entonces apagaría una conexión que funciona, y el
     * admin vería su reconexión deshacerse sola sin tocar nada.
     *
     * ⚠️ Un fallo **pasajero** (429, 5xx) no toca el estado: es de Google, no del parque.
     */
    private function markStatus(
        GoogleBusinessConnection $conexion,
        #[\SensitiveParameter] string $token,
        GoogleBusinessApiException $e,
    ): void {
        if ($e->status === null) {
            return;
        }

        // Se relee: la fila que traíamos es de antes de la llamada.
        $actual = GoogleBusinessConnection::current();

        if ($actual === null || ! $actual->tokenStillIs($token)) {
            return;
        }

        $actual->update(['status' => $e->status, 'status_changed_at' => now()]);
    }
}

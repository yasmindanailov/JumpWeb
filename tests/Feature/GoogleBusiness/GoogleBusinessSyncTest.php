<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Content\Enums\GoogleBusinessSyncOutcome;
use App\Domain\Content\Enums\GoogleReviewSuppressionReason;
use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\GoogleBusinessReviewSummary;
use App\Domain\Content\Services\GoogleBusinessSync;
use App\Domain\Content\Services\GoogleReviewImages;
use App\Domain\Content\Services\GoogleReviewSuppressions;
use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\GoogleBusinessApi;
use App\Domain\Platform\Services\GoogleBusinessCredentials;
use App\Domain\Platform\Services\GoogleBusinessOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * **T2·3 · La pasada: persistir lo que se vio, borrar solo lo que se puede creer**
 * (`docs/specs/google-business-profile.md` §4.3·1 → §4.3·3; `DECISIONES #524`, `#729`).
 *
 * ❗❗ Lo que fija este caso es **la diferencia entre escribir y borrar**. Escribir lo que se vio es
 * siempre seguro; borrar lo que no se vio se lleva por delante reseñas que existen, y entre hoy y la
 * pasada de mañana eso lo ven los clientes del parque en su portada.
 *
 * ⚠️ `Http::preventStrayRequests()` en `setUp()`: ningún caso habla con Google.
 */
class GoogleBusinessSyncTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '1//el-refresco';

    private const PARENT = 'accounts/7/locations/9';

    /** @var list<string> */
    private array $registrado = [];

    /**
     * Las páginas que quedan por servir.
     *
     * @var list<array<string,mixed>>
     */
    private array $paginas = [];

    /** Un PNG de 1×1 de verdad: el descargador decide el tipo por los BYTES, no por la cabecera. */
    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n\x2d\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

    /** Cuántas veces se ha pedido `reviews.list` en ESTE caso. */
    private int $peticiones = 0;

    /** Cuántas imágenes se han descargado en ESTE caso. */
    private int $imagenes = 0;

    /** Qué contesta Google al pedirle una imagen. @var callable(): mixed */
    private $imagenContesta;

    /** Quién contesta a `reviews.list`. @var (callable(): mixed)|null */
    private $contesta = null;

    /**
     * ⚠️⚠️ **El doble se registra UNA sola vez, aquí, y lo que cambia es a quién delega.**
     *
     * Medido el 2026-09-20: `Http::fake()` **FUSIONA** los dobles con los que ya hubiera en vez de
     * reemplazarlos, así que llamarlo dos veces en el mismo caso deja el PRIMERO ganando —y si el
     * primero era un `Http::sequence()` ya agotado, la segunda pasada revienta con «response sequence
     * is empty» señalando al código, que no tiene nada que ver. Con un solo doble que delega en una
     * propiedad, cada caso decide qué contesta Google sin tocar el registro.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_ID_KEY], ['value' => 'central.apps.googleusercontent.com', 'group' => 'google']);
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_SECRET_KEY], ['value' => 'GOCSPX-secreto', 'group' => 'google']);
        Setting::flushMemo();

        Storage::fake(GoogleReviewImages::DISK);

        $this->imagenContesta = fn () => Http::response(self::PNG);

        Http::fake([
            GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::response(['access_token' => 'ya29.de-acceso', 'expires_in' => 3600]),
            GoogleBusinessApi::REVIEWS_BASE.'*' => function () {
                $this->peticiones++;

                if ($this->contesta === null) {
                    $this->fail('se ha llamado a `reviews.list` y este caso no esperaba ninguna llamada');
                }

                return ($this->contesta)();
            },
            // ⚠️⚠️ **Las imágenes se fingen APARTE y se cuentan.** Al escribir la T2·4 el descargador
            // llevaba un `catch (Throwable)` que se tragaba el aviso de «petición sin doble», así que
            // estos casos pasaban en verde **sin descargar nada**. El doble es ancho a propósito: si
            // la pasada pidiera una imagen a un host que no es éste, saltaría como petición perdida.
            'https://lh3.googleusercontent.com/*' => function () {
                $this->imagenes++;

                return ($this->imagenContesta)();
            },
        ]);

        Event::listen(MessageLogged::class, function (MessageLogged $evento): void {
            $this->registrado[] = $evento->message.' '.json_encode($evento->context);
        });
    }

    private function conectada(array $overrides = []): GoogleBusinessConnection
    {
        return GoogleBusinessConnection::create(array_merge([
            'status' => GoogleBusinessStatus::Connected,
            'refresh_token' => self::TOKEN,
            'token_fingerprint' => GoogleBusinessConnection::fingerprint(self::TOKEN),
            'location_name' => 'locations/9',
            'account_name' => 'accounts/7',
            'location_title' => 'PlayJump',
            'maps_uri' => 'https://maps.google.com/?cid=1',
            'new_review_uri' => 'https://search.google.com/local/writereview?placeid=ChIJ',
            'connected_at' => now(),
        ], $overrides));
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    private function row(string $id, array $overrides = []): array
    {
        return array_merge([
            'name' => self::PARENT.'/reviews/'.$id,
            'reviewer' => [
                'displayName' => 'Marta R.',
                'profilePhotoUrl' => 'https://lh3.googleusercontent.com/a/ACg8ocK',
                'isAnonymous' => false,
            ],
            'starRating' => 'FIVE',
            'comment' => 'Los niños salieron encantados.',
            'createTime' => '2026-09-01T10:00:00Z',
            'updateTime' => '2026-09-01T10:00:00Z',
        ], $overrides);
    }

    /**
     * Las páginas que Google va a devolver, en orden. Pedir una de más falla el caso en vez de
     * pasarlo en verde.
     *
     * @param  list<array<string,mixed>>  $pages
     */
    private function fakePages(array $pages): void
    {
        $this->paginas = $pages;
        $this->contesta = function () {
            if ($this->paginas === []) {
                $this->fail('se ha pedido una página más de las previstas');
            }

            return Http::response(array_shift($this->paginas));
        };
    }

    /** Para los casos en los que Google contesta algo que no es una página. */
    private function fakeReviews(callable $contesta): void
    {
        $this->contesta = $contesta;
    }

    private function sync(): GoogleBusinessSync
    {
        return app(GoogleBusinessSync::class);
    }

    // ─────────── Escribir lo que se vio (§4.3·2 y §4.3·3) ───────────

    public function test_una_pasada_coherente_guarda_las_candidatas(): void
    {
        $this->conectada();
        $this->fakePages([[
            'reviews' => [$this->row('r1'), $this->row('r2')],
            'averageRating' => 4.8, 'totalReviewCount' => 2,
        ]]);

        $resultado = $this->sync()->run();

        $this->assertSame(GoogleBusinessSyncOutcome::Done, $resultado->outcome);
        $this->assertTrue($resultado->coherent);
        $this->assertSame(2, $resultado->kept);
        $this->assertSame(2, GoogleBusinessReview::query()->count());
    }

    public function test_la_fecha_de_la_ultima_pasada_se_renueva_aunque_la_resena_no_cambie(): void
    {
        $this->conectada();
        $this->travelTo('2026-09-10 08:00:00');
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);
        $this->sync()->run();

        $primera = GoogleBusinessReview::query()->sole();
        $this->assertSame('2026-09-10 08:00:00', $primera->fetched_at->toDateTimeString());

        // La MISMA reseña, sin un byte de diferencia, dos días después.
        $this->travelTo('2026-09-12 08:00:00');
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);
        $this->sync()->run();

        // ⚠️⚠️ §4.3·3 dice «cambie o no», y de esto dependen LOS DOS PLAZOS: `fetched_at` no es
        // «cuándo se insertó», es «cuándo se la vio por última vez en la ficha». Sin renovarla, una
        // reseña que lleva publicada dos años perdería su autor a los tres días.
        $this->assertSame('2026-09-12 08:00:00', $primera->fresh()->fetched_at->toDateTimeString());
        $this->assertSame(1, GoogleBusinessReview::query()->count());
    }

    public function test_la_foto_se_descarga_y_lo_que_se_guarda_es_una_ruta_nuestra(): void
    {
        $this->conectada();
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);

        $this->sync()->run();

        $ruta = GoogleBusinessReview::query()->sole()->author_photo_path;

        // ❗ La reseña TRAE la URL de Google en memoria, y lo que entra en la columna es la ruta del
        // fichero que hemos descargado — nunca la URL. La guarda del modelo lanzaría, pero antes de
        // eso la pasada ni la tiene a mano.
        $this->assertSame(1, $this->imagenes, 'no se ha descargado la foto');
        $this->assertNotNull($ruta);
        $this->assertTrue(GoogleReviewImages::isOwnName($ruta), "«{$ruta}» no tiene la forma de un fichero nuestro");
        Storage::disk(GoogleReviewImages::DISK)->assertExists($ruta);
    }

    public function test_una_foto_ya_descargada_no_se_vuelve_a_pedir(): void
    {
        $this->conectada();
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);
        $this->sync()->run();
        $this->assertSame(1, $this->imagenes);

        // La misma reseña al día siguiente: el fichero se llama por el hash de su contenido, así que
        // ya está bien. Volver a pedirla sería doce imágenes diarias para reescribir lo mismo.
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);
        $this->sync()->run();

        $this->assertSame(1, $this->imagenes, 'se ha vuelto a descargar una foto que ya estaba');
    }

    public function test_al_retirar_una_resena_su_fichero_se_va_con_ella(): void
    {
        $this->conectada();
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);
        $this->sync()->run();
        $ruta = GoogleBusinessReview::query()->sole()->author_photo_path;
        Storage::disk(GoogleReviewImages::DISK)->assertExists($ruta);

        $this->fakePages([['reviews' => [], 'averageRating' => null, 'totalReviewCount' => 0]]);
        $this->sync()->run();

        // ❗❗ **En la misma operación que su fila** (§4.3·6). Si el fichero sobreviviera, la cara de
        // alguien cuya reseña ya no existe seguiría servida por una URL que alguien pudo guardar.
        Storage::disk(GoogleReviewImages::DISK)->assertMissing($ruta);
    }

    public function test_si_la_descarga_falla_la_resena_se_guarda_sin_foto(): void
    {
        $this->conectada();
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);
        // ⚠️ Se cambia el doble por su PROPIEDAD y no con otro `Http::fake()`: ése fusiona, y el
        // primero seguiría ganando. Es la trampa que pagó esta misma tanda.
        $this->imagenContesta = fn () => Http::response('', 404);

        $this->sync()->run();

        // §4.3·6: si la descarga falla, **la inicial, jamás la URL de Google**. Y la reseña se
        // guarda igual: perder una opinión por una foto sería el peor cambio posible.
        $resena = GoogleBusinessReview::query()->sole();
        $this->assertNull($resena->author_photo_path);
        $this->assertSame('Marta R.', $resena->author_name);
    }

    public function test_la_pasada_no_vuelve_a_traer_una_resena_oculta(): void
    {
        $this->conectada();
        $this->fakePages([['reviews' => [$this->row('r1'), $this->row('r2')], 'averageRating' => 5.0, 'totalReviewCount' => 2]]);
        $this->sync()->run();

        app(GoogleReviewSuppressions::class)->hide(
            GoogleBusinessReview::query()->where('review_name', self::PARENT.'/reviews/r1')->sole(),
            GoogleReviewSuppressionReason::AuthorRequest,
        );

        // La ficha las sigue devolviendo las dos: Google no sabe nada de lo que oculta el parque.
        $this->fakePages([['reviews' => [$this->row('r1'), $this->row('r2')], 'averageRating' => 5.0, 'totalReviewCount' => 2]]);
        $this->sync()->run();

        // ❗❗ Sin esto, «ocultar» duraría hasta las 04:40 de la mañana siguiente.
        $this->assertSame([self::PARENT.'/reviews/r2'], GoogleBusinessReview::query()->pluck('review_name')->all());
    }

    // ─────────── Borrar solo con una pasada creíble (§4.3·3) ───────────

    public function test_una_pasada_coherente_retira_lo_que_ya_no_esta(): void
    {
        $this->conectada();
        $this->fakePages([['reviews' => [$this->row('r1'), $this->row('r2')], 'averageRating' => 5.0, 'totalReviewCount' => 2]]);
        $this->sync()->run();
        $this->assertSame(2, GoogleBusinessReview::query()->count());

        // La r2 desaparece de la ficha y el total lo confirma.
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);
        $resultado = $this->sync()->run();

        $this->assertSame(1, $resultado->deleted);
        $this->assertSame([self::PARENT.'/reviews/r1'], GoogleBusinessReview::query()->pluck('review_name')->all());
    }

    public function test_una_pasada_incoherente_guarda_lo_que_vio_y_no_borra_nada(): void
    {
        $this->conectada();
        $this->fakePages([['reviews' => [$this->row('r1'), $this->row('r2')], 'averageRating' => 5.0, 'totalReviewCount' => 2]]);
        $this->sync()->run();

        // La ficha se mueve mientras se pagina: el total de la primera página no es el de la última.
        $this->fakePages([
            ['reviews' => [$this->row('r3')], 'averageRating' => 5.0, 'totalReviewCount' => 2, 'nextPageToken' => 'p2'],
            ['reviews' => [], 'averageRating' => 5.0, 'totalReviewCount' => 9],
        ]);
        $resultado = $this->sync()->run();

        // ❗❗ Lo que vio se guarda —la r3 es una reseña real— pero **r1 y r2 no se tocan**: lo que no
        // ha visto se parece a lo que ya no existe, y no son lo mismo.
        $this->assertFalse($resultado->coherent);
        $this->assertSame(0, $resultado->deleted);
        $this->assertSame(3, GoogleBusinessReview::query()->count());
    }

    public function test_una_pasada_incoherente_no_toca_la_media(): void
    {
        $this->conectada();
        $this->travelTo('2026-09-10 08:00:00');
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 4.8, 'totalReviewCount' => 320]]);
        $this->sync()->run();

        $this->travelTo('2026-09-11 08:00:00');
        $this->fakePages([
            ['reviews' => [$this->row('r1')], 'averageRating' => 4.8, 'totalReviewCount' => 320, 'nextPageToken' => 'p2'],
            ['reviews' => [], 'averageRating' => 1.0, 'totalReviewCount' => 4],
        ]);
        $this->sync()->run();

        // Una media recogida a mitad de un cambio es una afirmación falsa sobre un tercero. Se queda
        // la de ayer, con su fecha, y caduca sola a los tres días.
        $resumen = GoogleBusinessReviewSummary::current();
        $this->assertSame(4.8, $resumen->average_rating);
        $this->assertSame(320, $resumen->total_review_count);
        $this->assertSame('2026-09-10 08:00:00', $resumen->fetched_at->toDateTimeString());
    }

    public function test_el_resumen_sale_de_google_y_los_enlaces_de_la_conexion(): void
    {
        $this->conectada();
        $this->fakePages([[
            'reviews' => [$this->row('r1', ['starRating' => 'FIVE'])],
            'averageRating' => 4.1, 'totalReviewCount' => 320,
        ]]);

        $this->sync()->run();

        $resumen = GoogleBusinessReviewSummary::current();
        // ⚠️ La media NO se compone con lo guardado: hay UNA candidata de cinco estrellas y la cifra
        // dice 4,1 sobre 320, que es lo que cuenta Google sobre TODAS (§4.3·10).
        $this->assertSame(4.1, $resumen->average_rating);
        $this->assertSame(320, $resumen->total_review_count);
        // Y los enlaces se copian para que la portada no lea la fila del token cifrado.
        $this->assertSame('https://maps.google.com/?cid=1', $resumen->maps_uri);
        $this->assertSame('https://search.google.com/local/writereview?placeid=ChIJ', $resumen->new_review_uri);
    }

    public function test_una_ficha_que_se_queda_sin_resenas_vacia_la_tabla(): void
    {
        $this->conectada();
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);
        $this->sync()->run();

        // Cero y cero es coherente: una ficha de verdad vacía. Si esto no vaciara la tabla, las
        // reseñas de ayer vivirían hasta que las alcanzara el plazo.
        $this->fakePages([['reviews' => [], 'averageRating' => null, 'totalReviewCount' => 0]]);
        $resultado = $this->sync()->run();

        $this->assertTrue($resultado->coherent);
        $this->assertSame(1, $resultado->deleted);
        $this->assertSame(0, GoogleBusinessReview::query()->count());
    }

    public function test_lo_retirado_se_borra_por_el_modelo_y_no_en_masa(): void
    {
        $this->conectada();
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);
        $this->sync()->run();

        $borradas = 0;
        GoogleBusinessReview::deleted(function () use (&$borradas): void {
            $borradas++;
        });

        $this->fakePages([['reviews' => [], 'averageRating' => null, 'totalReviewCount' => 0]]);
        $this->sync()->run();

        // ⚠️ Un `whereNotIn(...)->delete()` no instancia nada y no dispara este evento — y la T2·4
        // cuelga de él el borrado del fichero de la foto. Se quedarían huérfanos en el disco.
        $this->assertSame(1, $borradas);
    }

    // ─────────── El presupuesto de tiempo (§4.3·1) ───────────

    public function test_agotar_el_presupuesto_deja_la_pasada_incompleta_y_no_borra(): void
    {
        $this->conectada();
        $this->fakePages([['reviews' => [$this->row('r1'), $this->row('r2')], 'averageRating' => 5.0, 'totalReviewCount' => 2]]);
        $this->sync()->run();

        // La primera página tarda más que todo el presupuesto: no se debe pedir la segunda.
        $this->peticiones = 0;
        $this->fakeReviews(function () {
            $this->travel(GoogleBusinessSync::BUDGET_SECONDS + 1)->seconds();

            return Http::response([
                'reviews' => [$this->row('r3')],
                'averageRating' => 5.0, 'totalReviewCount' => 2,
                // Siempre dice que hay más: lo único que puede parar el recorrido es el presupuesto.
                'nextPageToken' => 'siempre-hay-mas',
            ]);
        });

        $resultado = $this->sync()->run();

        // ❗ Se paró a tiempo, guardó lo que vio y **no borró nada**: una pasada sin tiempo ha visto
        // menos reseñas de las que hay.
        $this->assertSame(1, $this->peticiones, 'se pidió más de una página con el presupuesto agotado');
        $this->assertFalse($resultado->coherent);
        $this->assertSame(0, $resultado->deleted);
        $this->assertSame(3, GoogleBusinessReview::query()->count());
    }

    // ─────────── El candado (§4.3·1) ───────────

    public function test_con_una_pasada_en_curso_no_se_llama_a_google(): void
    {
        $this->conectada();
        // Sin `fakePages()`: el doble de `setUp()` falla el caso si se llega a llamar a Google.

        $ajeno = Cache::store(GoogleBusinessSync::LOCK_STORE)->lock(GoogleBusinessSync::LOCK_KEY, 120);
        $this->assertTrue($ajeno->get(), 'el candado tiene que poder cogerse: si no, el caso no mide nada');

        $resultado = $this->sync()->run();

        $this->assertSame(GoogleBusinessSyncOutcome::Busy, $resultado->outcome);
        Http::assertNothingSent();
        $this->assertTrue(GoogleBusinessSync::inProgress());

        $ajeno->release();
        $this->assertFalse(GoogleBusinessSync::inProgress());
    }

    public function test_el_candado_sigue_echado_mientras_se_escribe(): void
    {
        $this->conectada();
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);

        $echadoAlEscribir = null;
        GoogleBusinessReview::created(function () use (&$echadoAlEscribir): void {
            $echadoAlEscribir = GoogleBusinessSync::inProgress();
        });

        $this->sync()->run();

        // ❗❗ El defecto que esto caza: soltar el candado al terminar de LEER y persistir fuera de él.
        // Dos pasadas podrían escribir la misma tabla a la vez y la segunda borraría con las
        // candidatas de la primera. Ningún caso de un solo hilo lo vería de otra forma.
        $this->assertTrue($echadoAlEscribir, 'se estaba escribiendo con el candado suelto');
    }

    public function test_el_candado_se_suelta_al_terminar(): void
    {
        $this->conectada();
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);

        $this->sync()->run();

        $this->assertFalse(GoogleBusinessSync::inProgress());
        // ⚠️ **Dos veces, y la segunda es la que mide**: `inProgress()` COGE el candado para saber si
        // estaba libre. Si no lo soltara, la primera pregunta diría «libre» —correctamente— y lo
        // dejaría echado, y a partir de ahí nadie podría volver a sincronizar hasta que caducara.
        // Con una sola llamada, esa guarda no la pone en rojo nada.
        $this->assertFalse(GoogleBusinessSync::inProgress(), 'preguntar por el candado lo ha dejado echado');
    }

    public function test_el_candado_se_suelta_aunque_google_diga_que_no(): void
    {
        $this->conectada();
        $this->fakeReviews(fn () => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403));

        $this->sync()->run();

        // Sin esto, un solo 403 dejaría la sincronización bloqueada hasta que caducara el candado.
        $this->assertFalse(GoogleBusinessSync::inProgress());
    }

    // ─────────── Los estados que no llaman (§4.2·7) ───────────

    public function test_sin_conexion_no_se_llama_a_google(): void
    {
        // Sin `fakePages()`: el doble de `setUp()` falla el caso si se llega a llamar a Google.

        $resultado = $this->sync()->run();

        $this->assertSame(GoogleBusinessSyncOutcome::Idle, $resultado->outcome);
        $this->assertSame(GoogleBusinessStatus::ReadyToConnect, $resultado->status);
        Http::assertNothingSent();
    }

    public function test_una_conexion_caducada_no_se_reintenta(): void
    {
        $this->conectada(['status' => GoogleBusinessStatus::Expired]);
        // Sin `fakePages()`: el doble de `setUp()` falla el caso si se llega a llamar a Google.

        $resultado = $this->sync()->run();

        // Reintentar contra una caducada gasta cuota COMPARTIDA entre todos los parques y no arregla
        // nada: lo que falta lo tiene que hacer una persona.
        $this->assertSame(GoogleBusinessSyncOutcome::Idle, $resultado->outcome);
        Http::assertNothingSent();
    }

    public function test_una_ficha_elegida_sin_cuenta_no_se_pide(): void
    {
        $this->conectada(['account_name' => null]);
        // Sin `fakePages()`: el doble de `setUp()` falla el caso si se llega a llamar a Google.

        $resultado = $this->sync()->run();

        // `#726`: sin la cuenta delante, `reviews.list` devuelve un 404 que se leería como «ficha
        // perdida» y mandaría a reconectar para nada. Se arregla volviendo a elegir ficha.
        $this->assertSame(GoogleBusinessSyncOutcome::Idle, $resultado->outcome);
        Http::assertNothingSent();
    }

    // ─────────── Marcar el estado, con comparar-y-escribir (§4.2·7) ───────────

    public function test_un_no_permanente_de_google_apaga_la_conexion(): void
    {
        $conexion = $this->conectada();
        $this->fakeReviews(fn () => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403));

        $resultado = $this->sync()->run();

        $this->assertSame(GoogleBusinessSyncOutcome::Failed, $resultado->outcome);
        $this->assertSame(GoogleBusinessStatus::Forbidden, $conexion->fresh()->status);
    }

    public function test_un_fallo_pasajero_no_apaga_la_conexion(): void
    {
        $conexion = $this->conectada();
        $this->fakeReviews(fn () => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429));

        $this->sync()->run();

        // 429 y 5xx son de Google, no del parque: apagar la conexión por ellos sería confundir un mal
        // minuto con una avería.
        $this->assertSame(GoogleBusinessStatus::Connected, $conexion->fresh()->status);
    }

    /**
     * ⚠️⚠️ **Sin red la pasada FALLA, no revienta** (`#733`): hasta ahora la `ConnectionException`
     * salía del servicio sin pasar por el `catch`, así que el comando diario terminaba con una traza
     * en vez de con un «falló, se reintentará». Y el candado, suelto: si no, mañana diría «ocupada».
     */
    public function test_sin_red_la_pasada_falla_sin_apagar_la_conexion_y_suelta_el_candado(): void
    {
        $conexion = $this->conectada();
        $this->fakeReviews(fn () => Http::failedConnection());

        $resultado = $this->sync()->run();

        $this->assertSame(GoogleBusinessSyncOutcome::Failed, $resultado->outcome);
        $this->assertSame(GoogleBusinessStatus::Connected, $conexion->fresh()->status);
        $this->assertFalse(GoogleBusinessSync::inProgress(), 'el candado se quedó echado tras el corte');
        $this->assertStringContainsString('unreachable', implode("\n", $this->registrado));
    }

    public function test_si_reconectan_mientras_llamabamos_no_se_pisa_la_conexion_nueva(): void
    {
        $this->conectada();

        $this->fakeReviews(function () {
            // El admin reconecta desde el panel mientras esta pasada esperaba a Google.
            GoogleBusinessConnection::current()->update([
                'refresh_token' => '1//el-nuevo',
                'token_fingerprint' => GoogleBusinessConnection::fingerprint('1//el-nuevo'),
                'status' => GoogleBusinessStatus::Connected,
            ]);

            return Http::response(['error' => ['status' => 'UNAUTHENTICATED']], 401);
        });

        $this->sync()->run();

        // ❗❗ El worker llevaba el token VIEJO. Marcar «caducada» apagaría una conexión que funciona,
        // y el admin vería su reconexión deshacerse sola sin tocar nada.
        $this->assertSame(GoogleBusinessStatus::Connected, GoogleBusinessConnection::current()->status);
    }

    // ─────────── El comando diario (§4.3·1) ───────────

    public function test_el_comando_sale_con_cero_cuando_no_hay_que_llamar(): void
    {
        // Un cron que se pone rojo todas las noches en una instalación sin conectar es un cron que
        // nadie vuelve a mirar. «No había nada que hacer» no es un fallo.
        $this->artisan('business-profile:sync')
            ->expectsOutputToContain('ready_to_connect')
            ->assertExitCode(0);
    }

    public function test_el_comando_sale_distinto_de_cero_solo_cuando_google_dice_que_no(): void
    {
        $this->conectada();
        $this->fakeReviews(fn () => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403));

        $this->artisan('business-profile:sync')->assertExitCode(1);
    }

    public function test_el_comando_avisa_de_lo_que_no_se_pudo_separar(): void
    {
        $this->conectada();
        $this->fakePages([[
            'reviews' => [$this->row('r1', ['comment' => '(Original) media pareja de marcadores'])],
            'averageRating' => 5.0, 'totalReviewCount' => 1,
        ]]);

        // §4.3·8: que esto suba es la señal de que hay una variante del marcador sin medir. Si no se
        // dijera en ninguna parte, la columna `text_ambiguous` no la miraría nadie nunca.
        $this->artisan('business-profile:sync')
            ->expectsOutputToContain('no se ha podido separar')
            ->assertExitCode(0);
    }

    public function test_el_comando_no_imprime_ni_textos_ni_nombres(): void
    {
        $this->conectada();
        $this->fakePages([[
            'reviews' => [$this->row('r1', ['comment' => 'UN-TEXTO-QUE-NO-PUEDE-SALIR', 'reviewer' => [
                'displayName' => 'UN-NOMBRE-QUE-NO-PUEDE-SALIR', 'isAnonymous' => false,
            ]])],
            'averageRating' => 5.0, 'totalReviewCount' => 1,
        ]]);

        // Su salida acaba en el log de un cron y en la captura que alguien pega en un chat.
        $this->artisan('business-profile:sync')
            ->doesntExpectOutputToContain('UN-TEXTO-QUE-NO-PUEDE-SALIR')
            ->doesntExpectOutputToContain('UN-NOMBRE-QUE-NO-PUEDE-SALIR')
            ->doesntExpectOutputToContain(self::TOKEN)
            ->assertExitCode(0);
    }

    // ─────────── Lo que no puede salir en un log (`RGPD-02`) ───────────

    public function test_el_log_de_la_pasada_no_lleva_ni_un_texto_ni_un_nombre(): void
    {
        $this->conectada();
        $this->fakePages([[
            'reviews' => [$this->row('r1', ['comment' => 'UN-TEXTO-QUE-NO-PUEDE-SALIR', 'reviewer' => [
                'displayName' => 'UN-NOMBRE-QUE-NO-PUEDE-SALIR', 'isAnonymous' => false,
            ]])],
            'averageRating' => 5.0, 'totalReviewCount' => 1,
        ]]);

        $this->sync()->run();

        $todo = implode("\n", $this->registrado);
        $this->assertNotSame('', $todo, 'la pasada tiene que registrar algo: si no, el caso no mide nada');
        $this->assertStringNotContainsString('UN-TEXTO-QUE-NO-PUEDE-SALIR', $todo);
        $this->assertStringNotContainsString('UN-NOMBRE-QUE-NO-PUEDE-SALIR', $todo);
        $this->assertStringNotContainsString(self::TOKEN, $todo);
    }
}

<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Booking\Models\Order;
use App\Domain\Content\Exceptions\ForeignImageUrlException;
use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\GoogleBusinessReviewSummary;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * **T2·1 · El cimiento de las reseñas: la tabla, el plazo y la guarda de la imagen**
 * (`docs/specs/google-business-profile.md` §4.3·2 → §4.3·9; `DECISIONES #524`, `#727`).
 *
 * Lo que fija este caso no es «que se guarden reseñas»: es **que no se puedan leer pasadas de plazo,
 * que el nombre y la cara de un tercero caduquen antes que su texto, y que una imagen de Google no
 * pueda entrar en la tabla**. Las tres son promesas que la portada no puede comprobar por su cuenta.
 *
 * ⏰ El tiempo se congela en todos los casos de plazo. No es comodidad: los bordes son de un segundo,
 * y sin congelar, un caso que mide «exactamente 29 días» falla el día que el reloj avance entre la
 * escritura y la lectura.
 */
class GoogleBusinessReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Una reseña con lo mínimo que la tabla exige. `fetched_at` se pasa siempre a mano: es la columna
     * que decide todo lo de aquí abajo y un valor por defecto la escondería.
     *
     * @param  array<string,mixed>  $overrides
     */
    private function review(array $overrides = []): GoogleBusinessReview
    {
        return GoogleBusinessReview::create(array_merge([
            'review_name' => 'accounts/111/locations/222/reviews/'.uniqid(),
            'author_name' => 'Marta R.',
            'star_rating' => 5,
            'comment' => 'Los niños salieron encantados.',
            'review_created_at' => now()->subMonths(2),
            'fetched_at' => now(),
        ], $overrides));
    }

    // ── El plazo AL LEER (§4.3·4) ───────────────────────────────────────────────────────────────

    public function test_la_ventana_de_lectura_deja_una_resena_vista_hoy(): void
    {
        $this->freezeTime();
        $this->review();

        $this->assertCount(1, GoogleBusinessReview::query()->withinRetention()->get());
    }

    public function test_la_ventana_de_lectura_llega_justo_hasta_su_borde(): void
    {
        $this->freezeTime();
        $this->review(['fetched_at' => now()->subDays(GoogleBusinessReview::FRESH_DAYS)]);

        // El borde se INCLUYE: si no, la ventana real sería de 28 días y el número escrito en la
        // constante mentiría sobre lo que hace.
        $this->assertCount(1, GoogleBusinessReview::query()->withinRetention()->get());
    }

    public function test_un_segundo_pasado_el_borde_la_resena_ya_no_se_lee(): void
    {
        $this->freezeTime();
        $this->review(['fetched_at' => now()->subDays(GoogleBusinessReview::FRESH_DAYS)->subSecond()]);

        // ❗ Un segundo, no un día: el plazo es un instante y no una fecha, y la diferencia se paga
        // el día que la pasada corra a una hora distinta de la del despliegue.
        $this->assertCount(0, GoogleBusinessReview::query()->withinRetention()->get());
    }

    public function test_la_ventana_de_lectura_cabe_dentro_del_tope_de_la_politica(): void
    {
        // La garantía está en la CONSULTA y la purga es limpieza: si alguien igualara los dos
        // números, un retraso de la purga se vería como un fallo del plazo.
        $this->assertLessThan(
            GoogleBusinessReview::RETENTION_DAYS,
            GoogleBusinessReview::FRESH_DAYS,
            'la ventana de lectura tiene que caber DENTRO del tope de la política'
        );
    }

    // ── El plazo corto del NOMBRE y la CARA (§4.3·4) ────────────────────────────────────────────

    public function test_una_resena_confirmada_hoy_publica_su_autor_y_su_foto(): void
    {
        $this->freezeTime();
        $resena = $this->review(['author_photo_path' => 'google-business/autores/ab12.jpg']);

        $this->assertTrue($resena->identified());
        $this->assertSame('Marta R.', $resena->publishableAuthor());
        $this->assertSame('google-business/autores/ab12.jpg', $resena->publishablePhotoPath());
    }

    public function test_sin_una_pasada_que_la_confirme_el_autor_deja_de_publicarse(): void
    {
        $this->freezeTime();
        $resena = $this->review([
            'author_photo_path' => 'google-business/autores/ab12.jpg',
            'fetched_at' => now()->subDays(GoogleBusinessReview::IDENTIFIED_DAYS)->subSecond(),
        ]);

        // ❗❗ El caso REAL: el autor borró su reseña en Google y la sincronización lleva días rota.
        // Su nombre y su cara no pueden seguir en la portada porque el fallo sea NUESTRO.
        $this->assertFalse($resena->identified());
        $this->assertNull($resena->publishableAuthor());
        $this->assertNull($resena->publishablePhotoPath());
    }

    public function test_el_texto_sobrevive_al_autor_a_proposito(): void
    {
        $this->freezeTime();
        $this->review(['fetched_at' => now()->subDays(GoogleBusinessReview::IDENTIFIED_DAYS + 1)]);

        $resena = GoogleBusinessReview::query()->withinRetention()->get();

        // Caduca la IDENTIDAD, no la opinión: la reseña se sigue leyendo dentro de la ventana larga,
        // pero sin quién la firma. Son dos plazos distintos y este caso los separa.
        $this->assertCount(1, $resena);
        $this->assertNull($resena->first()->publishableAuthor());
    }

    public function test_el_nombre_caduca_antes_que_el_texto(): void
    {
        $this->assertLessThan(
            GoogleBusinessReview::FRESH_DAYS,
            GoogleBusinessReview::IDENTIFIED_DAYS,
            'el nombre tiene que caducar ANTES que el texto, o el segundo plazo no hace nada'
        );
    }

    public function test_una_resena_anonima_no_tiene_autor_que_publicar(): void
    {
        $this->freezeTime();
        // §4.3·5: el `null` entra ANTES del INSERT. Aquí se comprueba que nada lo repone al leer.
        $resena = $this->review(['author_name' => null, 'author_photo_path' => null, 'anonymous' => true]);

        $this->assertTrue($resena->anonymous);
        $this->assertNull($resena->publishableAuthor());
        $this->assertNull($resena->publishablePhotoPath());
    }

    // ── La purga (§4.3·4) ───────────────────────────────────────────────────────────────────────

    public function test_la_purga_solo_se_lleva_lo_que_la_politica_ya_no_permite(): void
    {
        $this->freezeTime();
        $vieja = $this->review(['fetched_at' => now()->subDays(GoogleBusinessReview::RETENTION_DAYS)->subSecond()]);
        $ilegible = $this->review(['fetched_at' => now()->subDays(GoogleBusinessReview::FRESH_DAYS + 1)]);
        $viva = $this->review();

        $podables = (new GoogleBusinessReview)->prunable()->pluck('id');

        $this->assertTrue($podables->contains($vieja->id));
        $this->assertFalse($podables->contains($viva->id));
        // ⚠️ Entre los 29 y los 30 días la fila EXISTE y no se lee. Es el margen del que vive la
        // separación entre el filtro y la purga: si la purga se la llevara, serían el mismo plazo.
        $this->assertFalse($podables->contains($ilegible->id));
    }

    public function test_se_poda_fila_a_fila_y_no_en_masa(): void
    {
        // `MassPrunable` borraría por consulta, sin instanciar el modelo, y la T2·4 cuelga de
        // `pruning()` el borrado de los ficheros de imagen: se quedarían huérfanos en el disco.
        $usos = class_uses_recursive(GoogleBusinessReview::class);

        $this->assertContains(Prunable::class, $usos);
        $this->assertNotContains(MassPrunable::class, $usos);
    }

    // ── La identidad de una reseña (§4.3·3) ─────────────────────────────────────────────────────

    public function test_dos_filas_no_pueden_compartir_el_nombre_de_google(): void
    {
        $this->review(['review_name' => 'accounts/111/locations/222/reviews/abc']);

        // La deduplicación de §4.3·3 se apoya en esto, y como REGLA de la base y no como cuidado del
        // servicio: dos pasadas solapadas insertarían la misma reseña dos veces.
        $this->expectException(QueryException::class);
        $this->review(['review_name' => 'accounts/111/locations/222/reviews/abc']);
    }

    // ── La guarda de la imagen (§4.3·6 y §4.3·9) ────────────────────────────────────────────────

    public function test_una_ruta_de_nuestro_disco_si_se_guarda(): void
    {
        // El control positivo: sin él, una guarda que lo rechazara TODO también pasaría los casos de
        // abajo, y no habría forma de notarlo.
        $resena = $this->review([
            'author_photo_path' => 'google-business/autores/ab12.jpg',
            'photos' => ['google-business/fotos/cd34.webp', 'google-business/fotos/ef56.png'],
        ]);

        $this->assertSame('google-business/autores/ab12.jpg', $resena->fresh()->author_photo_path);
        $this->assertCount(2, $resena->fresh()->photos);
    }

    public function test_la_foto_del_autor_no_puede_ser_una_url_de_google(): void
    {
        // ❗❗ El atajo que deshace la tanda entera sin romper nada visible: la foto se vería igual y
        // el visitante volvería a pedírsela a Google.
        $this->expectException(ForeignImageUrlException::class);

        $this->review(['author_photo_path' => 'https://lh3.googleusercontent.com/a/ACg8ocK']);
    }

    public function test_una_foto_de_la_resena_no_puede_ser_una_url_de_google(): void
    {
        $this->expectException(ForeignImageUrlException::class);

        $this->review(['photos' => ['google-business/fotos/cd34.webp', 'https://lh3.googleusercontent.com/p/AF1Qip']]);
    }

    public function test_una_url_sin_esquema_tambien_se_rechaza(): void
    {
        // ⚠️ `//lh3.googleusercontent.com/…` no tiene esquema y PARECE una ruta: el navegador le pone
        // el de la página y sale igualmente a Google. Una guarda que solo buscara «https://» la
        // dejaría pasar, y ésa es la forma en que este defecto vuelve.
        $this->expectException(ForeignImageUrlException::class);

        $this->review(['author_photo_path' => '//lh3.googleusercontent.com/a/ACg8ocK']);
    }

    public function test_una_imagen_incrustada_tambien_se_rechaza(): void
    {
        // `data:` no es un host de tercero, pero tampoco es una ruta de nuestro disco: sería la
        // imagen metida dentro del HTML, que es «manipular» el contenido y engorda cada portada.
        $this->expectException(ForeignImageUrlException::class);

        $this->review(['author_photo_path' => 'data:image/png;base64,iVBORw0KGgo=']);
    }

    public function test_la_guarda_tambien_muerde_al_actualizar(): void
    {
        $resena = $this->review(['author_photo_path' => 'google-business/autores/ab12.jpg']);

        // Va en `saving()` y no en `creating()` a propósito: la re-sincronización ACTUALIZA filas que
        // ya existen, y ése es justo el camino por el que entraría la URL.
        $this->expectException(ForeignImageUrlException::class);

        $resena->update(['author_photo_path' => 'https://lh3.googleusercontent.com/a/ACg8ocK']);
    }

    // ── El aislamiento de la tabla (§4.3·11) ────────────────────────────────────────────────────

    public function test_la_tabla_de_resenas_no_tiene_ninguna_clave_ajena(): void
    {
        // ⚠️⚠️ **CONTROL PRIMERO.** Una lista vacía es lo que devuelve tanto «no hay FK» como «este
        // lector no sabe leerlas en SQLite», y las dos pintan el caso de verde. Así que antes de
        // fiarse del instrumento se le pide una tabla que SÍ tiene una: `google_business_connections`
        // apunta a `users` por `connected_by_user_id`.
        $control = collect(Schema::getForeignKeys('google_business_connections'))->pluck('foreign_table');
        $this->assertContains('users', $control->all(), 'el lector de FK no funciona: el caso de abajo no mediría nada');

        // Y ahora sí: §4.3·11 prohíbe cruzar las reseñas con clientes o pedidos, y la forma de que no
        // se escriba por accidente es que no haya por dónde.
        $this->assertSame([], Schema::getForeignKeys('google_business_reviews'));
        $this->assertSame([], Schema::getForeignKeys('google_business_review_summaries'));
    }

    public function test_el_modelo_no_declara_ninguna_relacion(): void
    {
        // ⚠️⚠️ **CONTROL PRIMERO**, por lo mismo: un bucle que no recorriera nada —o un
        // `is_subclass_of` que no reconociera el tipo— saldría verde sin mirar. `Order` tiene
        // relaciones de sobra.
        $this->assertNotEmpty(
            $this->relacionesDe(Order::class),
            'el detector de relaciones no funciona: el caso de abajo no mediría nada'
        );

        // La otra mitad de §4.3·11: sin FK en el esquema, pero también sin relación en el modelo, que
        // es lo que un `join` escrito a mano seguiría necesitando declarar.
        $this->assertSame([], $this->relacionesDe(GoogleBusinessReview::class));
        $this->assertSame([], $this->relacionesDe(GoogleBusinessReviewSummary::class));
    }

    /**
     * Los métodos públicos de un modelo que devuelven una relación de Eloquent.
     *
     * @param  class-string  $modelo
     * @return list<string>
     */
    private function relacionesDe(string $modelo): array
    {
        $relaciones = [];

        foreach ((new ReflectionClass($modelo))->getMethods(ReflectionMethod::IS_PUBLIC) as $metodo) {
            $tipo = $metodo->getReturnType();

            if ($tipo instanceof ReflectionNamedType && ! $tipo->isBuiltin() && is_subclass_of($tipo->getName(), Relation::class)) {
                $relaciones[] = $metodo->getName();
            }
        }

        return $relaciones;
    }

    // ── El resumen (§4.3·2 y §4.3·10) ───────────────────────────────────────────────────────────

    public function test_un_segundo_resumen_es_imposible(): void
    {
        GoogleBusinessReviewSummary::create(['average_rating' => 4.8, 'total_review_count' => 320, 'fetched_at' => now()]);

        // Una instalación es un parque y un parque es una ficha. Como invariante de BD: dos filas
        // serían un estado que nadie sabe leer.
        $this->expectException(QueryException::class);
        GoogleBusinessReviewSummary::create(['average_rating' => 4.1, 'total_review_count' => 9, 'fetched_at' => now()]);
    }

    public function test_un_resumen_reciente_se_puede_publicar(): void
    {
        $this->freezeTime();
        $resumen = GoogleBusinessReviewSummary::create([
            'average_rating' => 4.8, 'total_review_count' => 320,
            'maps_uri' => 'https://maps.google.com/?cid=1', 'fetched_at' => now(),
        ]);

        $this->assertTrue($resumen->publishable());
        $this->assertSame(4.8, $resumen->average_rating);
        $this->assertSame(320, $resumen->total_review_count);
    }

    public function test_un_resumen_sin_pasada_no_se_publica(): void
    {
        // `null` no es un cero: es «todavía no ha habido pasada». La portada los pinta distinto.
        $resumen = GoogleBusinessReviewSummary::create(['total_review_count' => 0]);

        $this->assertFalse($resumen->publishable());
        $this->assertNull($resumen->average_rating);
    }

    public function test_un_resumen_viejo_no_se_publica(): void
    {
        $this->freezeTime();
        $resumen = GoogleBusinessReviewSummary::create([
            'average_rating' => 4.8, 'total_review_count' => 320,
            'fetched_at' => now()->subDays(GoogleBusinessReviewSummary::FRESH_DAYS)->subSecond(),
        ]);

        // Una media que lleva días sin comprobarse es una afirmación sobre un tercero que ya no
        // consta: se deja de enseñar, no se enseña vieja.
        $this->assertFalse($resumen->publishable());
    }

    public function test_la_cifra_no_se_compone_con_las_candidatas(): void
    {
        $this->freezeTime();
        // Doce candidatas de cinco estrellas y un resumen que dice 4,1 sobre 320: la media NO se
        // filtra (§4.3·10) y no se recalcula con lo que hay guardado. Si alguien la compusiera aquí,
        // la portada diría 5,0 y estaría atribuyéndole a Google un número que Google no ha dado.
        for ($i = 0; $i < 12; $i++) {
            $this->review(['star_rating' => 5]);
        }

        $resumen = GoogleBusinessReviewSummary::create([
            'average_rating' => 4.1, 'total_review_count' => 320, 'fetched_at' => now(),
        ]);

        $this->assertSame(4.1, $resumen->fresh()->average_rating);
        $this->assertSame(320, $resumen->fresh()->total_review_count);
    }
}

<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Content\Services\GoogleReviewFilter;
use App\Domain\Content\Services\GoogleReviewReader;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\GoogleBusinessApi;
use App\Domain\Platform\Services\GoogleBusinessCredentials;
use App\Domain\Platform\Services\GoogleBusinessOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * **T2·2 · Recorrer la ficha y quedarse con las candidatas**
 * (`docs/specs/google-business-profile.md` §4.3·1 → §4.3·3, §4.3·5 y §4.3·10; `DECISIONES #524`, `#728`).
 *
 * Lo que fija este caso es lo que la T2·3 va a dar por bueno al persistir: **qué se trae, qué se tira,
 * y sobre todo cuándo la pasada NO se puede creer**. Lo último es lo caro: de ahí depende si se borra
 * la tabla de reseñas o no.
 *
 * ⚠️ `Http::preventStrayRequests()` en `setUp()`: ningún caso habla con Google.
 */
class GoogleReviewReaderTest extends TestCase
{
    use RefreshDatabase;

    private const PARENT = 'accounts/1/locations/9';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_ID_KEY], ['value' => 'central.apps.googleusercontent.com', 'group' => 'google']);
        Setting::updateOrCreate(['key' => GoogleBusinessCredentials::CLIENT_SECRET_KEY], ['value' => 'GOCSPX-secreto', 'group' => 'google']);
    }

    /**
     * Una fila de `reviews.list`, con lo que Google manda de verdad.
     *
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
     * Encadena las páginas que Google va a devolver. ⚠️ Es una SECUENCIA: si el recorrido pidiera más
     * páginas de las previstas, el caso se cae en vez de pasar en verde.
     *
     * @param  list<array<string,mixed>>  $pages
     */
    private function fakePages(array $pages): void
    {
        $secuencia = Http::sequence();

        foreach ($pages as $pagina) {
            $secuencia->push($pagina);
        }

        Http::fake([
            GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::response(['access_token' => 'ya29.de-acceso', 'expires_in' => 3600]),
            GoogleBusinessApi::REVIEWS_BASE.'*' => $secuencia,
        ]);
    }

    private function reader(): GoogleReviewReader
    {
        return new GoogleReviewReader(new GoogleBusinessApi);
    }

    private function filter(int $minStars = 1, int $keep = GoogleReviewFilter::KEEP): GoogleReviewFilter
    {
        return new GoogleReviewFilter($minStars, $keep);
    }

    // ─────────── El recorrido (§4.3·1) ───────────

    public function test_se_recorren_todas_las_paginas_y_se_juntan_las_candidatas(): void
    {
        $this->fakePages([
            ['reviews' => [$this->row('r1'), $this->row('r2')], 'averageRating' => 4.8, 'totalReviewCount' => 4, 'nextPageToken' => 'p2'],
            ['reviews' => [$this->row('r3'), $this->row('r4')], 'averageRating' => 4.8, 'totalReviewCount' => 4],
        ]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter());

        $this->assertCount(4, $pasada->candidates);
        $this->assertSame(4, $pasada->seen);
        $this->assertTrue($pasada->complete);
    }

    public function test_la_pagina_se_pide_con_el_tamano_maximo_y_el_testigo_de_la_anterior(): void
    {
        $this->fakePages([
            ['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 2, 'nextPageToken' => 'EL-TESTIGO'],
            ['reviews' => [$this->row('r2')], 'averageRating' => 5.0, 'totalReviewCount' => 2],
        ]);

        $this->reader()->pass('1//refresco', self::PARENT, $this->filter());

        Http::assertSent(fn ($peticion) => str_contains($peticion->url(), 'pageSize=50'));
        Http::assertSent(fn ($peticion) => str_contains($peticion->url(), 'pageToken=EL-TESTIGO'));
    }

    public function test_un_testigo_de_pagina_vacio_termina_el_recorrido(): void
    {
        // ⚠️⚠️ «No hay más» y «hay más, pero el testigo viene vacío» son lo mismo para quien pagina.
        // Tratarlas distinto es pedir la MISMA primera página para siempre: un bucle infinito que
        // solo se nota cuando un worker se queda clavado en producción.
        $this->fakePages([
            ['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1, 'nextPageToken' => ''],
        ]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter());

        $this->assertTrue($pasada->complete);
        $this->assertCount(1, $pasada->candidates);
        Http::assertSentCount(2); // la del token y UNA sola página
    }

    public function test_una_resena_repetida_entre_paginas_se_deduplica(): void
    {
        // §4.3·3: la ficha puede moverse mientras se pagina y traer la misma reseña dos veces.
        $this->fakePages([
            ['reviews' => [$this->row('r1'), $this->row('r2')], 'averageRating' => 5.0, 'totalReviewCount' => 2, 'nextPageToken' => 'p2'],
            ['reviews' => [$this->row('r2')], 'averageRating' => 5.0, 'totalReviewCount' => 2],
        ]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter());

        $this->assertCount(2, $pasada->candidates);
        // ⚠️ `seen` cuenta FILAS, no reseñas distintas: es el testigo de que el recorrido trajo algo.
        $this->assertSame(3, $pasada->seen);
    }

    public function test_el_tope_de_paginas_corta_y_la_pasada_queda_incompleta(): void
    {
        // Un `nextPageToken` que no termina nunca. Sin tope, esto es un bucle infinito en un worker.
        Http::fake([
            GoogleBusinessOAuth::TOKEN_ENDPOINT => Http::response(['access_token' => 'ya29.de-acceso', 'expires_in' => 3600]),
            GoogleBusinessApi::REVIEWS_BASE.'*' => Http::response([
                'reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 99, 'nextPageToken' => 'siempre-hay-mas',
            ]),
        ]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter());

        $this->assertFalse($pasada->complete);
        // ❗❗ Y lo que de verdad importa: con la pasada incompleta **no se puede borrar**.
        $this->assertFalse($pasada->coherent());
        Http::assertSentCount(GoogleReviewReader::MAX_PAGES + 1); // +1: la petición del token
    }

    // ─────────── El filtro (§4.3·2 y §4.3·10) ───────────

    public function test_las_que_no_llegan_al_minimo_de_estrellas_se_quedan_fuera(): void
    {
        $this->fakePages([[
            'reviews' => [
                $this->row('r1', ['starRating' => 'FIVE']),
                $this->row('r2', ['starRating' => 'TWO']),
                $this->row('r3', ['starRating' => 'FOUR']),
            ],
            'averageRating' => 3.7, 'totalReviewCount' => 3,
        ]]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter(minStars: 4));

        $this->assertCount(2, $pasada->candidates);
        // ⚠️⚠️ Pero las TRES se vieron. Si `seen` contara solo las candidatas, un filtro estricto se
        // leería como una ficha vacía y §4.3·3 borraría la tabla por el motivo equivocado.
        $this->assertSame(3, $pasada->seen);
    }

    public function test_una_resena_sin_texto_no_es_candidata_pero_cuenta_como_vista(): void
    {
        $this->fakePages([[
            'reviews' => [$this->row('r1'), $this->row('r2', ['comment' => '   '])],
            'averageRating' => 5.0, 'totalReviewCount' => 2,
        ]]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter());

        $this->assertCount(1, $pasada->candidates);
        $this->assertSame(2, $pasada->seen);
    }

    public function test_una_resena_sin_nota_no_sirve(): void
    {
        // `STAR_RATING_UNSPECIFIED` no es cero estrellas: es que no hay nota.
        $this->fakePages([[
            'reviews' => [$this->row('r1', ['starRating' => 'STAR_RATING_UNSPECIFIED'])],
            'averageRating' => null, 'totalReviewCount' => 1,
        ]]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter(minStars: 1));

        $this->assertSame([], $pasada->candidates);
    }

    public function test_una_resena_con_una_fecha_ilegible_no_sirve(): void
    {
        // Sin fecha no se puede ordenar (§4.3·10), y `parse()` LANZA con una cadena que no entiende:
        // una pasada entera no se puede caer por una reseña con una fecha rara.
        $this->fakePages([[
            'reviews' => [$this->row('r1', ['createTime' => 'el martes pasado']), $this->row('r2')],
            'averageRating' => 5.0, 'totalReviewCount' => 2,
        ]]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter());

        $this->assertCount(1, $pasada->candidates);
        $this->assertSame(self::PARENT.'/reviews/r2', $pasada->candidates[0]->name);
    }

    public function test_se_guardan_las_mas_recientes_y_se_recorta_al_tope(): void
    {
        $filas = [];
        for ($i = 1; $i <= 5; $i++) {
            $filas[] = $this->row('r'.$i, ['createTime' => sprintf('2026-09-%02dT10:00:00Z', $i)]);
        }
        $this->fakePages([['reviews' => $filas, 'averageRating' => 5.0, 'totalReviewCount' => 5]]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter(keep: 3));

        // ⚠️ Se ordena ANTES de recortar: recortar primero guardaría tres cualesquiera.
        $this->assertCount(3, $pasada->candidates);
        $this->assertSame(
            [self::PARENT.'/reviews/r5', self::PARENT.'/reviews/r4', self::PARENT.'/reviews/r3'],
            array_map(fn ($r) => $r->name, $pasada->candidates)
        );
    }

    public function test_el_minimo_de_estrellas_sale_del_panel_y_se_acota(): void
    {
        Setting::updateOrCreate(['key' => GoogleReviewFilter::MIN_STARS_KEY], ['value' => '3', 'group' => 'reviews']);
        Setting::flushMemo();
        $this->assertSame(3, GoogleReviewFilter::fromSettings()->minStars);

        // Un 0 apagaría el filtro sin decirlo y un 7 vaciaría la sección para siempre. Las dos cosas
        // se verían como «las reseñas no salen» y ninguna llevaría a la casilla que las causó.
        Setting::updateOrCreate(['key' => GoogleReviewFilter::MIN_STARS_KEY], ['value' => '0', 'group' => 'reviews']);
        Setting::flushMemo();
        $this->assertSame(1, GoogleReviewFilter::fromSettings()->minStars);

        Setting::updateOrCreate(['key' => GoogleReviewFilter::MIN_STARS_KEY], ['value' => '7', 'group' => 'reviews']);
        Setting::flushMemo();
        $this->assertSame(5, GoogleReviewFilter::fromSettings()->minStars);
    }

    public function test_sin_ajuste_el_minimo_es_el_que_decidio_el_owner(): void
    {
        Setting::flushMemo();

        $this->assertSame(GoogleReviewFilter::DEFAULT_MIN_STARS, GoogleReviewFilter::fromSettings()->minStars);
        $this->assertSame(4, GoogleReviewFilter::DEFAULT_MIN_STARS);
    }

    public function test_se_guardan_mas_de_las_que_se_ensenan(): void
    {
        // Sin margen, «Ocultar» o una reseña borrada en Google dejarían la sección corta hasta la
        // pasada siguiente, que es un día entero.
        $this->assertGreaterThan(GoogleReviewFilter::SHOWN, GoogleReviewFilter::KEEP);
    }

    // ─────────── Lo que se trae de cada reseña (§4.3·5 y §4.3·8) ───────────

    public function test_una_anonima_llega_sin_nombre_y_sin_foto(): void
    {
        $this->fakePages([[
            'reviews' => [$this->row('r1', ['reviewer' => [
                'displayName' => 'A Google User',
                'profilePhotoUrl' => 'https://lh3.googleusercontent.com/a/ACg8ocK',
                'isAnonymous' => true,
            ]])],
            'averageRating' => 5.0, 'totalReviewCount' => 1,
        ]]);

        $resena = $this->reader()->pass('1//refresco', self::PARENT, $this->filter())->candidates[0];

        // ❗❗ El `null` entra AQUÍ (§4.3·5), no al pintar: así no hay ningún camino —ni una consulta,
        // ni un volcado— por el que su nombre exista en nuestra base. Se tira aunque Google mande algo.
        $this->assertTrue($resena->anonymous);
        $this->assertNull($resena->authorName);
        $this->assertNull($resena->authorPhotoSourceUrl);
    }

    public function test_una_foto_que_no_es_de_un_host_de_google_no_se_acepta(): void
    {
        $fotos = [
            // Acaba en otra cosa: es el clásico que se cuela con `str_contains`.
            'sufijo-pegado' => 'https://lh3.googleusercontent.com.malo.net/a/x',
            // ⚠️ Éste ACABA en el host permitido: se cuela con `str_ends_with`, y por eso la
            // comparación es ENTERA. Un host que no está en la lista no está en la lista, aunque
            // cuelgue del dominio de Google — es la misma regla dura de la T1·3b.
            'delante-pegado' => 'https://evil.lh3.googleusercontent.com/a/x',
            'sin-https' => 'http://lh3.googleusercontent.com/a/x',
            // El control positivo: sin él, una guarda que lo rechazara TODO pasaría igual de verde.
            'buena' => 'https://lh3.googleusercontent.com/a/bueno',
        ];

        $filas = [];
        foreach ($fotos as $id => $url) {
            $filas[] = $this->row($id, ['reviewer' => ['displayName' => 'A', 'profilePhotoUrl' => $url, 'isAnonymous' => false]]);
        }
        $this->fakePages([['reviews' => $filas, 'averageRating' => 5.0, 'totalReviewCount' => count($filas)]]);

        $porNombre = [];
        foreach ($this->reader()->pass('1//refresco', self::PARENT, $this->filter())->candidates as $resena) {
            $porNombre[$resena->name] = $resena->authorPhotoSourceUrl;
        }

        $this->assertNull($porNombre[self::PARENT.'/reviews/sufijo-pegado']);
        $this->assertNull($porNombre[self::PARENT.'/reviews/delante-pegado']);
        $this->assertNull($porNombre[self::PARENT.'/reviews/sin-https']);
        $this->assertSame('https://lh3.googleusercontent.com/a/bueno', $porNombre[self::PARENT.'/reviews/buena']);
    }

    public function test_las_fotos_de_la_resena_llegan_sin_los_videos(): void
    {
        $this->fakePages([[
            'reviews' => [$this->row('r1', ['reviewMediaItems' => [
                ['thumbnailUrl' => 'https://lh3.googleusercontent.com/p/FOTO-1'],
                // ⚠️⚠️ Un elemento con `videoUrl` se descarta ENTERO, también su miniatura (§4.3·6:
                // «los vídeos no»). Traer solo la miniatura enseñaría un FOTOGRAMA como si fuera una
                // foto que hizo el autor, y no lo es: es el primer cuadro de un vídeo que no se va
                // a poder ver.
                ['thumbnailUrl' => 'https://lh3.googleusercontent.com/p/MINIATURA-DE-VIDEO', 'videoUrl' => 'https://video/x'],
                ['thumbnailUrl' => 'https://ejemplo.net/p/DE-FUERA'],
                ['thumbnailUrl' => 'https://lh4.googleusercontent.com/p/FOTO-2'],
            ]])],
            'averageRating' => 5.0, 'totalReviewCount' => 1,
        ]]);

        $resena = $this->reader()->pass('1//refresco', self::PARENT, $this->filter())->candidates[0];

        $this->assertSame([
            'https://lh3.googleusercontent.com/p/FOTO-1',
            'https://lh4.googleusercontent.com/p/FOTO-2',
        ], $resena->photoSourceUrls);
    }

    public function test_una_resena_sin_fotos_llega_con_la_lista_vacia(): void
    {
        // El control: sin él, un mapeador que devolviera siempre `[]` pasaría el caso de arriba.
        $this->fakePages([['reviews' => [$this->row('r1')], 'averageRating' => 5.0, 'totalReviewCount' => 1]]);

        $this->assertSame([], $this->reader()->pass('1//refresco', self::PARENT, $this->filter())->candidates[0]->photoSourceUrls);
    }

    public function test_la_respuesta_del_parque_viaja_con_la_resena(): void
    {
        $this->fakePages([[
            'reviews' => [$this->row('r1', ['reviewReply' => ['comment' => '¡Gracias, Marta!', 'updateTime' => '2026-09-02T09:00:00Z']])],
            'averageRating' => 5.0, 'totalReviewCount' => 1,
        ]]);

        $resena = $this->reader()->pass('1//refresco', self::PARENT, $this->filter())->candidates[0];

        $this->assertSame('¡Gracias, Marta!', $resena->replyComment);
        $this->assertSame('2026-09-02 09:00:00', $resena->replyAt?->toDateTimeString());
    }

    public function test_el_texto_llega_ya_separado_de_su_traduccion_y_lo_dudoso_se_cuenta(): void
    {
        $this->fakePages([[
            'reviews' => [
                $this->row('r1', ['comment' => '(Translated by Google) Great park (Original) Gran parque']),
                $this->row('r2', ['comment' => '(Original) Solo media pareja']),
            ],
            'averageRating' => 5.0, 'totalReviewCount' => 2,
        ]]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter());
        $porNombre = [];
        foreach ($pasada->candidates as $resena) {
            $porNombre[$resena->name] = $resena;
        }

        $this->assertSame('Gran parque', $porNombre[self::PARENT.'/reviews/r1']->comment);
        $this->assertFalse($porNombre[self::PARENT.'/reviews/r1']->textAmbiguous);
        $this->assertTrue($porNombre[self::PARENT.'/reviews/r2']->textAmbiguous);
        // §4.3·8 pide contarlo: es la señal de que un formato nuevo necesita una mirada.
        $this->assertSame(1, $pasada->ambiguous);
    }

    // ─────────── Cuándo la pasada se puede creer (§4.3·3) ───────────

    public function test_una_pasada_quieta_y_completa_se_puede_creer(): void
    {
        $this->fakePages([[
            'reviews' => [$this->row('r1')], 'averageRating' => 4.8, 'totalReviewCount' => 320,
        ]]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter());

        // El control positivo de esta sección: sin él, un `coherent()` que devolviera siempre `false`
        // pasaría los tres casos de abajo.
        $this->assertTrue($pasada->coherent());
        $this->assertSame(4.8, $pasada->firstAverage);
        $this->assertSame(320, $pasada->firstTotal);
    }

    public function test_si_el_total_cambia_entre_la_primera_pagina_y_la_ultima_no_se_puede_creer(): void
    {
        $this->fakePages([
            ['reviews' => [$this->row('r1')], 'averageRating' => 4.8, 'totalReviewCount' => 320, 'nextPageToken' => 'p2'],
            ['reviews' => [$this->row('r2')], 'averageRating' => 4.8, 'totalReviewCount' => 180],
        ]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter());

        // ❗❗ Lo recogido es una mezcla de dos momentos. Borrar con esto se llevaría por delante
        // reseñas que existen.
        $this->assertSame(320, $pasada->firstTotal);
        $this->assertSame(180, $pasada->lastTotal);
        $this->assertFalse($pasada->coherent());
    }

    public function test_si_la_media_cambia_entre_la_primera_pagina_y_la_ultima_no_se_puede_creer(): void
    {
        $this->fakePages([
            ['reviews' => [$this->row('r1')], 'averageRating' => 4.8, 'totalReviewCount' => 320, 'nextPageToken' => 'p2'],
            ['reviews' => [$this->row('r2')], 'averageRating' => 4.2, 'totalReviewCount' => 320],
        ]);

        $this->assertFalse($this->reader()->pass('1//refresco', self::PARENT, $this->filter())->coherent());
    }

    public function test_una_lista_vacia_con_total_mayor_que_cero_no_se_puede_creer(): void
    {
        // §4.3·3, literal. Es el caso que separa «el parque se quedó sin reseñas» —que no pasa— de
        // «algo falló», que es lo que pasa de verdad.
        $this->fakePages([[
            'reviews' => [], 'averageRating' => 4.8, 'totalReviewCount' => 320,
        ]]);

        $pasada = $this->reader()->pass('1//refresco', self::PARENT, $this->filter());

        $this->assertSame(0, $pasada->seen);
        $this->assertFalse($pasada->coherent());
    }

    public function test_una_ficha_de_verdad_sin_resenas_si_se_puede_creer(): void
    {
        // Y su contrario: cero y cero es una ficha nueva, no una avería. Si esto no se pudiera creer,
        // la tabla no se vaciaría nunca y las reseñas de ayer vivirían para siempre.
        $this->fakePages([[
            'reviews' => [], 'averageRating' => null, 'totalReviewCount' => 0,
        ]]);

        $this->assertTrue($this->reader()->pass('1//refresco', self::PARENT, $this->filter())->coherent());
    }
}

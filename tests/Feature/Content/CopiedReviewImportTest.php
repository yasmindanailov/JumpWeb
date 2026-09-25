<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Services\CmsSocialProof;
use App\Domain\Content\Services\CopiedReviewImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * **Las reseñas COPIADAS de la ficha de Google, importadas** (`DECISIONES #771`).
 *
 * Lo que se vigila: que una nueva entre APAGADA y sin páginas (publicar es una elección), que reimportar actualice lo
 * de Google y CONSERVE lo que eligió el parque, que una sin texto no entre, que la fecha relativa se entienda, que las
 * imágenes se traigan solo de Google y se sirvan desde casa, y que la cascada de siempre no las pinte como propias.
 */
class CopiedReviewImportTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1A\n".'resto';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Testimonial::IMAGE_DISK);
        // Una respuesta NUEVA por petición: el descargador lee el flujo, y una respuesta compartida llega vacía la segunda vez.
        Http::fake([
            'lh3.googleusercontent.com/*' => fn () => Http::response(self::PNG, 200),
            '*' => fn () => Http::response('no', 404),
        ]);
    }

    /** @return array<string, mixed> */
    private function copia(array $resenas): array
    {
        return ['place_url' => 'https://www.google.com/maps/place/Play+Jump+Park/@37.65,-1.71,17z', 'copied_at' => '2026-09-26T10:00:00+02:00', 'reviews' => $resenas];
    }

    /** @return array<string, mixed> */
    private function resena(array $extra = []): array
    {
        return [
            'id' => 'ChdDSUhN', 'author' => 'Lucía Pérez', 'author_meta' => 'Local Guide · 12 reseñas',
            'avatar_url' => 'https://lh3.googleusercontent.com/a/avatar=s160-c', 'rating' => 5, 'when' => 'Hace 2 meses',
            'text' => 'Mis hijos de 5 y 7 años disfrutaron muchísimo en Kids.', 'photos' => ['https://lh3.googleusercontent.com/p/foto=w1200'],
            'reply' => '¡Gracias, Lucía!', ...$extra,
        ];
    }

    public function test_a_new_review_comes_in_switched_off_with_its_images_at_home(): void
    {
        $cuenta = app(CopiedReviewImport::class)->import($this->copia([$this->resena(), $this->resena(['id' => 'SoloEstrellas', 'text' => ''])]));

        $this->assertSame(['nuevas' => 1, 'actualizadas' => 0, 'sin_texto' => 1, 'imagenes' => 2], $cuenta);
        $o = Testimonial::firstOrFail();
        $this->assertSame([Testimonial::ORIGIN_GOOGLE, 'ChdDSUhN', false, null], [$o->origin, $o->source_ref, $o->is_active, $o->tags], 'nueva: de Google, apagada y en ninguna página');
        $this->assertSame(['es' => 'Mis hijos de 5 y 7 años disfrutaron muchísimo en Kids.'], $o->text);
        $this->assertSame('2026-07-26', $o->published_at?->toDateString(), '«Hace 2 meses» desde el día de la copia');
        $this->assertStringStartsWith('resenas/', (string) $o->avatar);
        Storage::disk(Testimonial::IMAGE_DISK)->assertExists($o->avatar);
        $this->assertSame(asset('uploads/'.$o->avatar), $o->avatarUrl(), 'la foto se sirve desde casa, nunca desde Google');
        $this->assertSame('https://www.google.com/maps/place/Play+Jump+Park/@37.65,-1.71,17z', $o->source_url);
    }

    public function test_reimporting_updates_what_comes_from_google_and_keeps_what_the_park_chose(): void
    {
        $importador = app(CopiedReviewImport::class);
        $importador->import($this->copia([$this->resena()]));
        Testimonial::firstOrFail()->update(['is_active' => true, 'tags' => ['kids'], 'position' => 3]);

        $cuenta = $importador->import($this->copia([$this->resena(['text' => 'Texto editado por su autora.', 'rating' => 4, 'when' => 'Hace un año'])]));

        $this->assertSame(1, $cuenta['actualizadas']);
        $this->assertSame(1, Testimonial::count(), 'reimportar no duplica');
        $o = Testimonial::firstOrFail();
        $this->assertSame([true, ['kids'], 3], [$o->is_active, $o->tags, $o->position], 'lo que eligió el parque se queda');
        $this->assertSame([['es' => 'Texto editado por su autora.'], 4], [$o->text, $o->rating]);
        $this->assertSame('2026-07-26', $o->published_at?->toDateString(), 'la primera fecha, la más precisa, se conserva');
    }

    public function test_images_come_only_from_google_hosts(): void
    {
        app(CopiedReviewImport::class)->import($this->copia([$this->resena(['avatar_url' => 'https://evil.example/a.png', 'photos' => ['http://lh3.googleusercontent.com/p/x']])]));

        $o = Testimonial::firstOrFail();
        $this->assertSame([null, null], [$o->avatar, $o->photos], 'ni otro host ni `http`: la tarjeta pinta la inicial');
        Http::assertNothingSent();
    }

    public function test_relative_dates_in_spanish_and_english(): void
    {
        $momento = Carbon::parse('2026-09-26 10:00', 'Europe/Madrid');
        $casos = ['Hace un día' => '2026-09-25', 'Hace 3 semanas' => '2026-09-05', 'Editado hace 5 meses' => '2026-04-26', 'Hace 2 años' => '2024-09-26', 'a month ago' => '2026-08-26', 'Nuevo' => null];

        foreach ($casos as $texto => $esperado) {
            $this->assertSame($esperado, CopiedReviewImport::fecha($texto, $momento)?->toDateString(), $texto);
        }
    }

    public function test_the_old_cascade_does_not_paint_a_copy_as_its_own(): void
    {
        Testimonial::create(['author' => 'Escrita', 'text' => ['es' => 'Propia'], 'is_active' => true]);
        Testimonial::create(['origin' => Testimonial::ORIGIN_GOOGLE, 'author' => 'Copiada', 'text' => ['es' => 'De Google'], 'is_active' => true]);

        $this->assertSame(['Escrita'], app(CmsSocialProof::class)->testimonials()->pluck('author')->all());
    }

    public function test_an_image_shared_by_two_reviews_survives_while_one_still_uses_it(): void
    {
        Storage::disk(Testimonial::IMAGE_DISK)->put('resenas/compartida.png', 'x');
        $una = Testimonial::create(['author' => 'Una', 'text' => ['es' => 'a'], 'avatar' => 'resenas/compartida.png']);
        $otra = Testimonial::create(['author' => 'Otra', 'text' => ['es' => 'b'], 'photos' => ['resenas/compartida.png']]);

        $una->delete();
        Storage::disk(Testimonial::IMAGE_DISK)->assertExists('resenas/compartida.png');

        $otra->delete();
        Storage::disk(Testimonial::IMAGE_DISK)->assertMissing('resenas/compartida.png');
    }
}

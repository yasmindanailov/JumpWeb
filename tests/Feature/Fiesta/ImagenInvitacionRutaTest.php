<?php

namespace Tests\Feature\Fiesta;

use App\Http\Fiesta\ImagenInvitacion;
use App\Http\Instancia\InstanceViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * **LA IMAGEN DE LA INVITACIÓN AL COMPARTIR, servida** (`#815`, `#816`; `fiesta-sistema-nuevo.md` §4.19, la I2): la
 * `og:image` de la página apunta a la imagen GENERADA cuando la instancia trae el kit, con su idioma y su huella en la URL;
 * la ruta la dibuja en cada petición con el mismo portero que la página, y no guarda nada.
 *
 * ⚠️ El kit, con DejaVu de la Sail en un paquete de prueba FUERA del árbol (`SEC-12`) y una carpeta propia bajo
 * `public/instancia/`, que se borra al acabar.
 */
class ImagenInvitacionRutaTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    private string $paquete;

    private string $carpeta;

    private bool $publicoExistia;

    /** El disco por defecto de TODA la clase, en una carpeta propia: un mutante que guarde la imagen no ensucia `storage/`. */
    private string $disco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disco = sys_get_temp_dir().'/disco-de-la-imagen-'.getmypid();
        File::ensureDirectoryExists($this->disco);
        config(['filesystems.disks.local.root' => $this->disco]);
        Storage::forgetDisk('local');

        $this->paquete = sys_get_temp_dir().'/instancia-imagen-ruta-'.getmypid();
        $this->carpeta = 'prueba-imagen-ruta-'.getmypid();
        $this->publicoExistia = is_dir(public_path(InstanceViews::PUBLICO));
        File::ensureDirectoryExists($this->paquete.'/'.InstanceViews::SUBCARPETA);
        File::ensureDirectoryExists(public_path(InstanceViews::PUBLICO."/{$this->carpeta}/fuentes"));
        File::copy('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', public_path(InstanceViews::PUBLICO."/{$this->carpeta}/fuentes/negrita.ttf"));
        File::copy('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', public_path(InstanceViews::PUBLICO."/{$this->carpeta}/fuentes/normal.ttf"));
        config(['instancia.ruta' => $this->paquete]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->disco);
        File::deleteDirectory($this->paquete);
        File::deleteDirectory(public_path(InstanceViews::PUBLICO."/{$this->carpeta}"));
        if (! $this->publicoExistia) {
            File::deleteDirectory(public_path(InstanceViews::PUBLICO));
        }

        parent::tearDown();
    }

    private function conKit(bool $con = true): void
    {
        $c = $this->carpeta;
        $manifiesto = ['contrato' => InstanceViews::CONTRATO] + ($con ? ['fuentes' => ['imagen' => [
            'titular' => "{$c}/fuentes/negrita.ttf", 'texto' => "{$c}/fuentes/normal.ttf", 'etiqueta' => "{$c}/fuentes/negrita.ttf",
        ]]] : []);
        File::put($this->paquete.'/'.InstanceViews::MANIFIESTO, (string) json_encode($manifiesto));
    }

    /** @return array{0: string, 1: ?string, 2: ?string} la `og:image` de la página y sus dos medidas */
    private function vistaPrevia(string $token): array
    {
        $html = (string) $this->get('/invitacion/'.$token)->assertOk()->getContent();
        preg_match('/property="og:image" content="([^"]+)"/', $html, $imagen);
        preg_match('/property="og:image:width" content="([^"]+)"/', $html, $ancho);
        preg_match('/property="og:image:height" content="([^"]+)"/', $html, $alto);

        return [html_entity_decode($imagen[1] ?? ''), $ancho[1] ?? null, $alto[1] ?? null];
    }

    public function test_without_the_kit_the_preview_keeps_the_old_image_and_the_route_answers_404(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $this->conKit(false);

        [$imagen] = $this->vistaPrevia($invitation->token);
        $this->assertStringNotContainsString('/imagen.jpg', $imagen, 'Sin kit, la de antes: la instalación sigue igual.');
        $this->get('/invitacion/'.$invitation->token.'/imagen.jpg')->assertNotFound();
    }

    public function test_with_the_kit_the_preview_points_to_the_generated_image_and_the_route_draws_it(): void
    {
        ['invitation' => $invitation, 'reservation' => $reservation] = $this->mountParty();
        $this->conKit();

        [$imagen, $ancho, $alto] = $this->vistaPrevia($invitation->token);
        $estilo = ImagenInvitacion::estilo($invitation->safeTheme());
        $this->assertNotNull($estilo);
        $datos = ImagenInvitacion::datosDe($invitation, $reservation, $estilo);
        $this->assertNotNull($datos);
        $this->assertSame(route('invitation.image', ['token' => $invitation->token, 'l' => 'es', 'v' => ImagenInvitacion::huella($datos, $estilo)]), $imagen,
            'La `og:image`: la de ESTA invitación, con el idioma del `og:title` y su huella.');
        $this->assertSame(['1200', '630'], [$ancho, $alto], 'Sus medidas, que son nuestras.');

        $respuesta = $this->get($imagen)->assertOk();
        $this->assertSame('image/jpeg', $respuesta->headers->get('Content-Type'));
        $this->assertSame('noindex', $respuesta->headers->get('X-Robots-Tag'), 'Lleva el nombre y la edad de un menor.');
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'), '`RGPD-04`, como la página.');
        $medidas = getimagesizefromstring((string) $respuesta->getContent());
        $this->assertSame([1200, 630], [$medidas[0], $medidas[1]]);
    }

    public function test_the_fingerprint_follows_the_invitation_and_the_language_comes_from_the_url(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $this->conKit();

        [$antes] = $this->vistaPrevia($invitation->token);
        $invitation->forceFill(['honoree_name' => 'Valentina'])->save();
        [$despues] = $this->vistaPrevia($invitation->token);
        $this->assertNotSame($antes, $despues, 'Al personalizar cambia la huella: WhatsApp vuelve a pedirla.');

        // ⚠️ En una prueba la aplicación se reutiliza entre peticiones (en producción cada una arranca de cero): un idioma
        // que no está se compara con NINGÚN idioma pedido justo antes, no con «es».
        $sin = (string) $this->get('/invitacion/'.$invitation->token.'/imagen.jpg')->assertOk()->getContent();
        $raro = (string) $this->get('/invitacion/'.$invitation->token.'/imagen.jpg?l=xx')->assertOk()->getContent();
        $this->assertSame($sin, $raro, 'Un idioma que no está es como no pedir ninguno.');
        $es = (string) $this->get('/invitacion/'.$invitation->token.'/imagen.jpg?l=es')->assertOk()->getContent();
        $en = (string) $this->get('/invitacion/'.$invitation->token.'/imagen.jpg?l=en')->assertOk()->getContent();
        $this->assertNotSame($es, $en, 'El robot no trae sesión: el idioma lo dice la URL.');
    }

    public function test_an_unknown_token_or_a_name_the_font_cannot_write_gets_no_image(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $this->conKit();

        $this->get('/invitacion/AAAAAAAAAAAA/imagen.jpg')->assertNotFound();

        $invitation->forceFill(['honoree_name' => '🎈🎈'])->save();
        [$imagen] = $this->vistaPrevia($invitation->token);
        $this->assertStringNotContainsString('/imagen.jpg', $imagen, 'Sin un nombre que escribir, la de antes: nunca una imagen rota.');
        $this->get('/invitacion/'.$invitation->token.'/imagen.jpg')->assertNotFound();
    }

    /**
     * `#816`: se dibuja en cada petición y NO se guarda (una invitación se borra sin eventos: art. 17).
     * ⚠️ Medido con su mutante: contar ficheros de `storage/app` no veía nada, porque otras pruebas escriben el MISMO
     * fichero antes. El disco por defecto es una carpeta propia y vacía ({@see setUp}), y `storage/app` se compara entero.
     */
    public function test_drawing_the_image_leaves_nothing_on_disk(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $this->conKit();
        $instantanea = static function (): array {
            $ficheros = [];
            foreach (File::allFiles(storage_path('app'), true) as $f) {
                $ficheros[$f->getRelativePathname()] = $f->getSize().'@'.$f->getMTime();
            }
            ksort($ficheros);

            return $ficheros;
        };

        $antes = $instantanea();
        $this->get('/invitacion/'.$invitation->token.'/imagen.jpg')->assertOk();

        $this->assertSame([], File::allFiles($this->disco, true), 'Nada en el disco por defecto: la imagen no se guarda.');
        $this->assertSame($antes, $instantanea(), 'Ni un fichero nuevo ni tocado en `storage/app`.');
    }
}

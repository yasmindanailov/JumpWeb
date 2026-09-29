<?php

namespace Tests\Feature\Mail;

use App\Domain\Content\Services\ThemeSettings;
use App\Http\Instancia\InstanceViews;
use App\Notifications\Support\MailTheme;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * **Los ROLES del correo** (`specs/correos-rediseno.md` §4.1.1, la R1a): neutros en el producto y redefinidos por la hoja
 * `hojas.correo` de la instancia, validados antes de escribirse en línea (lo que pidió plataforma el 29-09).
 *
 * ⚠️ El paquete de prueba vive FUERA del árbol (`SEC-12`) y la hoja en una carpeta PROPIA bajo `public/instancia/`, que
 * se borra al acabar —y `public/instancia/` también si no existía—, como `InstanceSheetsTest`.
 * ⚠️ Los valores esperados van escritos A MANO: leerlos de `MailTheme::COLORES` probaría la tabla contra sí misma.
 */
class MailThemeRolesTest extends TestCase
{
    private string $paquete;

    private string $carpeta;

    private bool $publicoExistia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paquete = sys_get_temp_dir().'/instancia-correo-'.getmypid();
        File::ensureDirectoryExists($this->paquete.'/'.InstanceViews::SUBCARPETA);
        $this->publicoExistia = is_dir(public_path(InstanceViews::PUBLICO));
        $this->carpeta = 'prueba-correo-'.getmypid();
        File::ensureDirectoryExists(public_path(InstanceViews::PUBLICO.'/'.$this->carpeta));

        config(['instancia.ruta' => $this->paquete]);
        MailTheme::olvidar();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->paquete);
        File::deleteDirectory(public_path(InstanceViews::PUBLICO.'/'.$this->carpeta));
        if (! $this->publicoExistia) {
            File::deleteDirectory(public_path(InstanceViews::PUBLICO));
        }
        MailTheme::olvidar();

        parent::tearDown();
    }

    /** Escribe las hojas y las declara en `hojas.correo`, en su orden. */
    private function hojas(string ...$css): void
    {
        $rutas = [];
        foreach ($css as $i => $contenido) {
            $rutas[] = $ruta = "{$this->carpeta}/correo-{$i}.css";
            File::put(public_path(InstanceViews::PUBLICO.'/'.$ruta), $contenido);
        }
        File::put($this->paquete.'/'.InstanceViews::MANIFIESTO, (string) json_encode([
            'contrato' => InstanceViews::CONTRATO, 'hojas' => ['correo' => $rutas],
        ]));
    }

    public function test_without_a_package_the_mail_comes_out_with_the_product_neutrals(): void
    {
        config(['instancia.ruta' => null]);
        $t = MailTheme::current();

        $this->assertSame('#FFFFFF', $t->claro('fondo'));
        $this->assertSame('#101418', $t->claro('fuerte'));
        $this->assertSame('#101418', $t->oscuro('fondo'));
        $this->assertSame('#FFFFFF', $t->oscuro('fuerte'));
        $this->assertSame([], $t->franja(), 'la franja es de la MARCA: sin hoja, ninguna');
        $this->assertSame(10, $t->radio('md'));
        $this->assertSame(16, $t->radio('lg'));
        $this->assertSame(999, $t->radio('pildora'));

        // La acción, del panel: sin color de acción declarado, la marca; y su letra, la que da AA.
        $marca = ThemeSettings::brand();
        $this->assertSame($marca, $t->claro('accion'));
        $this->assertSame(ThemeSettings::onAction($marca), $t->claro('accion-letra'));
        $this->assertSame($t->claro('accion'), $t->oscuro('accion'), 'la acción no cambia de relleno en oscuro');
    }

    public function test_the_instance_sheet_redefines_each_role_in_light_and_dark(): void
    {
        $this->hojas(<<<'CSS'
            /* Un comentario con --correo-fuerte: #000000; no cuenta. */
            :root {
                --correo-tinta: #0b2e4a;
                --correo-fuerte: var(--correo-tinta);
                --correo-fuerte-oscuro: #eef;
                --correo-accion: #FF6A13;
                --correo-accion-letra: #0B2E4A;
                --correo-franja-1: #17c8f5; --correo-franja-2: #b6e80f;
                --correo-franja-3: #ffc400; --correo-franja-4: #ff3d8b;
                --correo-radio-md: 14px;
                --correo-radio-lg: 20px;
            }
            CSS);

        $t = MailTheme::current();

        $this->assertSame('#0B2E4A', $t->claro('fuerte'), 'una indirección dentro de la hoja se resuelve');
        // ⚠️ Un valor DISTINTO del neutro oscuro (`#FFFFFF`): con `#fff`, una pareja oscura ignorada daba el mismo resultado
        // y la mutación sobrevivía (la regla 8 del arnés: un valor trivial no está fijado).
        $this->assertSame('#EEEEFF', $t->oscuro('fuerte'), 'la pareja oscura, con su nombre plano; #eef se normaliza');
        $this->assertSame('#FF6A13', $t->claro('accion'), 'la hoja manda sobre el panel');
        $this->assertSame('#0B2E4A', $t->claro('accion-letra'));
        $this->assertSame(['#17C8F5', '#B6E80F', '#FFC400', '#FF3D8B'], $t->franja());
        $this->assertSame(14, $t->radio('md'));
        $this->assertSame(20, $t->radio('lg'));
        $this->assertSame('#3B434B', $t->claro('cuerpo'), 'lo que la hoja no declara conserva su neutro');
    }

    public function test_what_does_not_fit_stays_out_with_a_warning_and_the_role_keeps_its_neutral(): void
    {
        Log::spy();
        $this->hojas(<<<'CSS'
            :root {
                --correo-fuerte: rgba(0, 0, 0, .9);
                --correo-cuerpo: red;
                --correo-apagado: url(https://x.test/a.png);
                --correo-radio-md: 2em;
                --correo-inventado: #123456;
                --correo-a: var(--correo-b);
                --correo-b: var(--correo-c);
                --correo-c: #222222;
                --correo-filete: var(--correo-a);
            }
            @media (prefers-color-scheme: dark) {
                :root { --correo-fondo: #000000; }
            }
            html:root { --correo-sutil: #010101; }
            CSS);

        $t = MailTheme::current();

        $this->assertSame('#101418', $t->claro('fuerte'), 'rgba no es un color de correo');
        $this->assertSame('#3B434B', $t->claro('cuerpo'), 'un nombre de color no se escribe en línea');
        $this->assertSame('#626A72', $t->claro('apagado'), 'una url nunca llega al estilo en línea');
        $this->assertSame(10, $t->radio('md'), 'un radio es un entero en px');
        $this->assertSame('#D6D8D4', $t->claro('filete'), 'dos vueltas de indirección no se siguen');
        $this->assertSame('#FFFFFF', $t->claro('fondo'), 'lo de dentro de un @media no es de este lector');
        $this->assertSame('#F4F4F1', $t->claro('sutil'), 'solo el :root de primer nivel');

        Log::shouldHaveReceived('warning')->withArgs(
            static fn (string $msg, array $ctx): bool => ($ctx['rol'] ?? '') === '--correo-fuerte'
        )->once();
        foreach (['--correo-cuerpo', '--correo-apagado', '--correo-radio-md', '--correo-inventado', '--correo-a'] as $rol) {
            Log::shouldHaveReceived('warning')->withArgs(
                static fn (string $msg, array $ctx): bool => ($ctx['rol'] ?? '') === $rol
            )->once();
        }
    }

    public function test_a_stripe_with_a_missing_colour_is_no_stripe(): void
    {
        $this->hojas(':root{--correo-franja-1:#17c8f5;--correo-franja-2:#b6e80f;--correo-franja-3:#ffc400;}');

        $this->assertSame([], MailTheme::current()->franja(), 'tres de cuatro no es la franja del parque');
    }

    /**
     * ⚠️ Una hoja que cambia la acción y no su letra no puede dejar el botón ilegible: la letra se calcula.
     * `#FFD400` (amarillo claro) pide letra de tinta; el blanco daría 1,4.
     */
    public function test_an_action_without_its_letter_gets_the_legible_one(): void
    {
        $this->hojas(':root{--correo-accion:#FFD400;}');
        $t = MailTheme::current();

        $this->assertSame('#FFD400', $t->claro('accion'));
        $this->assertSame('#14130F', $t->claro('accion-letra'));
        $this->assertSame('#14130F', $t->oscuro('accion-letra'));
    }

    /** La hoja se lee UNA vez por firma (ruta + `filemtime`), y un cambio de la hoja cambia la firma. */
    public function test_the_sheet_is_read_once_per_signature_and_again_when_it_changes(): void
    {
        $this->hojas(':root{--correo-fuerte:#111111;}');
        $this->assertSame('#111111', MailTheme::current()->claro('fuerte'));

        $ruta = public_path(InstanceViews::PUBLICO."/{$this->carpeta}/correo-0.css");
        $antes = (int) filemtime($ruta);
        File::put($ruta, ':root{--correo-fuerte:#222222;}');
        touch($ruta, $antes);   // misma firma: lo leído se conserva
        clearstatcache();
        $this->assertSame('#111111', MailTheme::current()->claro('fuerte'), 'con la misma firma no se relee');

        touch($ruta, $antes + 5);
        clearstatcache();
        $this->assertSame('#222222', MailTheme::current()->claro('fuerte'), 'otra firma: se lee la hoja nueva');
    }
}

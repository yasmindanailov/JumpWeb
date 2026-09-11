<?php

namespace Tests\Feature\Mail;

use App\Domain\Identity\Models\User;
use App\Notifications\PasswordReset;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Auth\Notifications\ResetPassword as FrameworkResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail as FrameworkVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * EL MOLDE de los correos — que los 23 que lee un cliente salgan del mismo sitio (`#503`, `#508`).
 *
 * ⚠️ **Eran 21 hasta `#508`**, y la cifra no subió por un correo nuevo: subió porque el inventario
 * del artboard contó `app/Notifications/` + `app/Mail/` y **los dos del framework no vivían en
 * ninguna carpeta**. Salían de `Illuminate\Auth\Notifications` —el enlace de restablecer contraseña
 * y el de verificar el correo de una cuenta nueva—, los recibe todo el mundo, y estuvieron fuera del
 * carril entero. Hoy son subclases propias y entran solas en este censo.
 *
 * ⚠️⚠️ **Se comprueba sobre las FUENTES y no renderizando.** Renderizar los 23 exige montar el
 * fixture de cada uno —un pedido, una reserva con franja, una firma, un pago— y un caso que no se
 * puede construir acaba no escribiéndose: es así como estos veintiuno llegaron a tener veintiún
 * moldes distintos. Aquí se lee el código, que es exhaustivo por definición.
 */
class MailMoldTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Los DOS únicos correos que VENDEN. El mapa del naranja: el relleno de acción solo significa
     * comprar (`[DECIDIDO owner, 2026-09-10]`).
     */
    private const VENDEN = [
        'OrderPaymentDeclined',       // «Reintentar el pago»
        'OrderExpiredWithoutPayment', // «Hacer una nueva reserva»
    ];

    /**
     * Correos que NO pasan por el molde, cada uno con su motivo.
     * ⚠️ Esta lista **solo encoge**.
     */
    private const FUERA_DEL_MOLDE = [
        // Los dos avisos internos al PARQUE: se pintan con vista propia (`emails/*.blade.php`), no
        // con `MailMessage`, así que no tienen dónde encajar una cabecera. Su texto no se toca
        // (regla del canvas) y su vestido sí se hizo en la T1.
        'ContactMessageMail' => 'aviso interno con vista propia',
        'PaymentIncidentMail' => 'aviso interno con vista propia',
    ];

    /** @return array<string,string> nombre corto → código fuente */
    private function correos(): array
    {
        $fuentes = [];
        foreach (array_merge(glob(app_path('Notifications/*.php')), glob(app_path('Mail/*.php'))) as $f) {
            $nombre = basename($f, '.php');
            if (isset(self::FUERA_DEL_MOLDE[$nombre])) {
                continue;
            }
            $fuentes[$nombre] = (string) file_get_contents($f);
        }

        return $fuentes;
    }

    public function test_the_scan_sees_the_whole_family(): void
    {
        // ⚠️ **23, y el número subió en `#508`**: el inventario del artboard decía 23 contando
        // `app/Notifications/` + `app/Mail/`, y **los dos correos del framework no vivían en ninguna
        // carpeta** —salían de `Illuminate\Auth\Notifications`—, así que eran 25 y nadie los contaba.
        // Hoy son subclases propias y entran solas en este censo.
        $this->assertGreaterThanOrEqual(
            23, count($this->correos()) + count(self::FUERA_DEL_MOLDE),
            'el escaneo ve menos correos de los que hay: ¿han cambiado de carpeta?'
        );
    }

    /**
     * ❗❗❗ **LOS DOS DEL FRAMEWORK SE SIGUEN MANDANDO DESDE `User`**, y sin este caso volverían a
     * salir los de Laravel **sin que nada fallara**: las subclases seguirían existiendo —así que
     * `test_every_customer_facing_mail_declares_its_hero` pasaría en verde— y simplemente no las
     * usaría nadie. Es el modo de fallo exacto que dejó estos dos correos fuera del carril durante
     * todo `#500`→`#507`.
     *
     * ⚠️ Se comprueba por CONDUCTA —qué notificación se encola— y no leyendo `User.php`: que el
     * método esté escrito no es que mande la nuestra.
     */
    public function test_the_two_framework_mails_are_sent_in_our_own_shape(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->sendPasswordResetNotification('tok');
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, PasswordReset::class);
        Notification::assertSentTo($user, VerifyEmailAddress::class);

        // …y NO las del framework, que es la otra mitad: sin esto, mandar las dos pasaría igual.
        Notification::assertNotSentTo($user, FrameworkResetPassword::class);
        Notification::assertNotSentTo($user, FrameworkVerifyEmail::class);
    }

    /**
     * ❗❗ **TODO CORREO QUE LEE UN CLIENTE ABRE CON SU CABECERA.** Sin ella el correo empieza con el
     * saludo de Laravel —«¡Hola!»— y pierde las tres cosas que el molde pone arriba: el estado en una
     * chapa, el titular y el resguardo. Medido antes de la tanda: **los 23 se anunciaban con un
     * saludo**, y por eso en la bandeja no se distinguían.
     */
    public function test_every_customer_facing_mail_declares_its_hero(): void
    {
        $sin = [];
        foreach ($this->correos() as $nombre => $src) {
            if (! str_contains($src, '->hero(')) {
                $sin[] = $nombre;
            }
        }

        $this->assertSame([], $sin,
            "correos sin CABECERA:\n  ".implode("\n  ", $sin)."\n\n".
            'Se declara con `->hero(\'grupo.del.diccionario\', \'tono\', $resguardo)` sobre un '.
            '`BrandedMailMessage`, o el correo entra en `FUERA_DEL_MOLDE` con su motivo escrito.');
    }

    /**
     * ⚠️ Y usan el TIPO del molde. Un `new MailMessage` no tiene `hero()` ni `notice()`, así que
     * este caso es lo que impide que alguien vuelva a escribir `viewData['hero'] = [...]` a mano y
     * se deje una clave — que sale sin cabecera **sin que nada falle**.
     */
    public function test_they_all_use_the_mold_type(): void
    {
        $sueltos = [];
        foreach ($this->correos() as $nombre => $src) {
            if (str_contains($src, 'new MailMessage')) {
                $sueltos[] = $nombre;
            }
            if (str_contains($src, "viewData['hero'] = [")) {
                $sueltos[] = $nombre.' (compone el hero a mano)';
            }
        }

        $this->assertSame([], array_unique($sueltos),
            'estos construyen su correo fuera del molde: '.implode(', ', $sueltos));
    }

    /**
     * ❗ **La cabecera SUSTITUYE al saludo, no se suma.** Un correo con las dos cosas tiene dos
     * aperturas, y el artboard no dibuja «¡Hola!» en ninguno.
     */
    public function test_no_mail_keeps_its_greeting(): void
    {
        $con = [];
        foreach ($this->correos() as $nombre => $src) {
            if (str_contains($src, '->greeting(')) {
                $con[] = $nombre;
            }
        }

        $this->assertSame([], $con, 'estos siguen poniendo saludo además de cabecera: '.implode(', ', $con));
    }

    /**
     * ❗❗❗ **EL MAPA DEL NARANJA: SOLO DOS CORREOS VENDEN.** El relleno de acción significa comprar;
     * los otros diecinueve llevan a mirar, a rellenar o a firmar y van en TINTA. Medido antes de la
     * tanda: **15 correos con botón y ninguno declaraba `level`**, así que «ver mis reservas»
     * gritaba igual que «reintentar el pago».
     *
     * ⚠️ El caso vigila las DOS direcciones: que ningún tercero se apunte, y que ninguno de los dos
     * se caiga — apagar el mapa entero es el defecto simétrico y se ve igual de poco.
     */
    public function test_exactly_two_mails_carry_the_selling_button(): void
    {
        $venden = [];
        foreach ($this->correos() as $nombre => $src) {
            if (str_contains($src, "->level('sell')")) {
                $venden[] = $nombre;
            }
        }
        sort($venden);
        $esperados = self::VENDEN;
        sort($esperados);

        $this->assertSame($esperados, $venden,
            'el mapa del naranja se ha movido. Solo VENDEN «Reintentar el pago» y «Hacer una nueva '.
            'reserva»; cambiar esta lista es una decisión del owner, no un ajuste.');
    }

    /**
     * ❗❗ **UNA LÍNEA DE CIERRE SE NORMALIZA IGUAL QUE UNA DEL CUERPO** (`#507`).
     *
     * `outro()` existe porque el orden de las llamadas no es el orden de la pintura, y por dentro
     * tiene que hacer **lo mismo** que `->line()`: pasar por `formatLine()`, que colapsa los saltos
     * de línea. Sin ese colapso Markdown lee cada línea del bloque como su propio párrafo.
     *
     * ⚠️⚠️ **Este caso existe porque la defensa NO TENÍA SUJETO y la mutación lo dijo**: hoy el único
     * texto multilínea que pasa por `outro()` es el LIBRO del pedido, que al ser `Htmlable` sale de
     * `formatLine()` intacto — así que quitar la llamada no cambiaba nada y la mutación sobrevivía.
     * La defensa protege el uso con un STRING multilínea, que es el que aún no existe. Aquí lo tiene.
     */
    public function test_a_closing_line_is_normalised_like_a_body_line(): void
    {
        $multilinea = "primera línea\nsegunda línea\r\ntercera";

        $conCierre = (new BrandedMailMessage)->outro($multilinea);
        $conCuerpo = (new BrandedMailMessage)->line($multilinea);

        $this->assertStringNotContainsString("\n", (string) $conCierre->outroLines[0],
            'una línea de cierre con saltos rompe el bloque al pasar por Markdown');
        $this->assertSame(
            (string) $conCuerpo->introLines[0],
            (string) $conCierre->outroLines[0],
            'el cierre y el cuerpo tienen que normalizar igual: `outro()` no puede ser un atajo de `line()`'
        );
    }
}

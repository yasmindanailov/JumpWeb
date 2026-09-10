<?php

namespace Tests\Feature\Mail;

use Tests\TestCase;

/**
 * EL MOLDE de los correos — que los 21 que lee un cliente salgan del mismo sitio (`#503`).
 *
 * ⚠️⚠️ **Se comprueba sobre las FUENTES y no renderizando.** Renderizar los 21 exige montar el
 * fixture de cada uno —un pedido, una reserva con franja, una firma, un pago— y un caso que no se
 * puede construir acaba no escribiéndose: es así como estos veintiuno llegaron a tener veintiún
 * moldes distintos. Aquí se lee el código, que es exhaustivo por definición.
 */
class MailMoldTest extends TestCase
{
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
        $this->assertGreaterThanOrEqual(
            21, count($this->correos()) + count(self::FUERA_DEL_MOLDE),
            'el escaneo ve menos correos de los que hay: ¿han cambiado de carpeta?'
        );
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
}

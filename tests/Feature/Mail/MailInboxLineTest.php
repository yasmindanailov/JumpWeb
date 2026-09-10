<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Notifications\AccountAlreadyExists;
use App\Notifications\OrderConfirmation;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * LO QUE SE LEE EN LA BANDEJA: el asunto y la línea de adelanto (`DECISIONES #506`; artboard
 * `Correos PJP`, decisiones 2 y 23 del canvas).
 *
 * Las dos cosas se leen JUNTAS, en la misma línea de una lista, antes de que nadie abra nada — y
 * hasta esta tanda ninguna de las dos hacía su trabajo. Medido antes:
 *
 *   · **cero líneas de adelanto** en los 23, así que el gestor de correo cogía lo primero del
 *     cuerpo y **los veintiuno se anunciaban con el saludo**;
 *   · **18 de 21 asuntos pasaban de 35 caracteres** —lo que se lee en la lista de un móvil— y
 *     **12 de los 13 que llevan un dato lo perdían en el corte**, porque iba al final.
 *
 * ❗❗❗ **POR QUÉ ESTA GUARDA EXISTE Y NO BASTA `MailMoldTest`.** Aquélla comprueba que el correo
 * DECLARA su cabecera. Y `#504` la dejó en verde con un defecto que el cliente veía: las claves
 * `badge` y `headline` del correo de identidad social estaban anidadas dentro de `providers`, así
 * que `__()` devolvía la clave y **la cabecera decía literalmente
 * «account.social_link_mail.badge»**. ▶ *Declarar una pieza no es que diga algo.* Aquí se
 * comprueba el CONTENIDO, y en los tres idiomas.
 */
class MailInboxLineTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que previsualiza un gestor de correo detrás del asunto (`doc/correos.md`). */
    private const TOPE_ADELANTO = 85;

    /**
     * Los grupos del diccionario que gobiernan un correo, LEÍDOS DE LA FUENTE y no de una lista a
     * mano: una lista escrita aquí envejece en silencio, y un correo nuevo tiene que entrar solo.
     *
     * @return array<string,string> notificación → grupo (`OrderCancelled` → `emails.order_cancelled`)
     */
    private function grupos(): array
    {
        $grupos = [];
        foreach ((array) glob(app_path('Notifications/*.php')) as $f) {
            $src = (string) file_get_contents((string) $f);
            if (preg_match("/->hero\(\s*'([^']+)'/", $src, $m) === 1) {
                $grupos[basename((string) $f, '.php')] = $m[1];
            }
        }

        return $grupos;
    }

    /** Los `:placeholder` de una cadena de traducción. */
    private function placeholders(string $texto): array
    {
        preg_match_all('/:([a-zA-Z][a-zA-Z0-9_]*)/', $texto, $m);

        return array_values(array_unique($m[1]));
    }

    public function test_the_scan_sees_the_whole_family(): void
    {
        $this->assertGreaterThanOrEqual(
            21,
            count($this->grupos()),
            'el escaneo ve menos correos de los que hay: ¿han dejado de usar `->hero()`?'
        );
    }

    /**
     * ❗❗❗ **TODA PIEZA DE LA BANDEJA EXISTE EN LOS TRES IDIOMAS.**
     *
     * ⚠️⚠️ **El tercer parámetro de `Lang::has()` es el que hace que esto mida algo**: por defecto
     * cae al idioma de RESPALDO —aquí `en`—, así que una clave que faltara en español pero
     * estuviera en inglés pasaba en verde y el correo salía en el idioma equivocado sin fallar.
     * Lo destapó la mutación, no la lectura: con la guarda escrita «bien» a ojo, borrar las claves
     * de `lang/es/` SOBREVIVÍA.
     * `hero()` sólo pinta la línea de
     * adelanto si la clave existe —falla hacia invisible, no hacia feo—, así que un olvido aquí NO
     * rompe nada: deja el correo anunciándose otra vez con lo primero del cuerpo. La chapa y el
     * titular no tienen esa red: sin clave, `__()` devuelve la clave y se le enseña al cliente.
     *
     * ▶ Este caso es el que habría puesto en rojo el defecto de `#504`.
     */
    public function test_every_mail_has_its_inbox_pieces_in_the_three_languages(): void
    {
        $faltan = [];
        foreach ($this->grupos() as $notificacion => $grupo) {
            foreach (['es', 'en', 'fr'] as $loc) {
                foreach (['preheader', 'badge', 'headline', 'subject'] as $pieza) {
                    if (! Lang::has($grupo.'.'.$pieza, $loc, false)) {
                        $faltan[] = "$notificacion ($loc): $grupo.$pieza";
                    }
                }
            }
        }

        $this->assertSame([], $faltan, "piezas de bandeja sin escribir:\n".implode("\n", $faltan));
    }

    /**
     * ❗❗ **LA LÍNEA DE ADELANTO NO LLEVA DATOS, y eso es la regla, no una casualidad.**
     *
     * Dos motivos, y el segundo es el que duele: (1) el asunto ya lleva el dato delante, así que
     * repetirlo desperdicia la única frase que puede COMPLETARLO; (2) `hero()` la resuelve sin
     * reemplazos, de modo que un `:code` escrito aquí **saldría literal en la bandeja de un
     * cliente y no fallaría nada**.
     */
    public function test_no_inbox_line_carries_a_placeholder(): void
    {
        $con = [];
        foreach ($this->grupos() as $grupo) {
            foreach (['es', 'en', 'fr'] as $loc) {
                $texto = (string) Lang::get($grupo.'.preheader', [], $loc);
                foreach ($this->placeholders($texto) as $p) {
                    $con[] = "$grupo ($loc): :$p";
                }
            }
        }

        $this->assertSame([], $con, "líneas de adelanto con dato variable:\n".implode("\n", $con));
    }

    /** Lo que no cabe en la previsualización no se lee: se corta a media frase. */
    public function test_no_inbox_line_is_longer_than_the_preview(): void
    {
        $largas = [];
        foreach ($this->grupos() as $grupo) {
            foreach (['es', 'en', 'fr'] as $loc) {
                $texto = (string) Lang::get($grupo.'.preheader', [], $loc);
                if (mb_strlen($texto) > self::TOPE_ADELANTO) {
                    $largas[] = sprintf('%s (%s): %d', $grupo, $loc, mb_strlen($texto));
                }
            }
        }

        $this->assertSame([], $largas, 'líneas de adelanto por encima de '.self::TOPE_ADELANTO.":\n".implode("\n", $largas));
    }

    /**
     * ❗❗❗ **TODO `:placeholder` DEL ASUNTO LO PASA SU NOTIFICACIÓN.** Si no, `__()` lo deja tal cual
     * y el cliente recibe «Confirma tu email · :code» en la lista de su bandeja.
     *
     * ⚠️ **No es hipotético: pasó en esta misma tanda.** Al reescribir los asuntos, el de confirmar
     * el correo de una compra ganó `:code` y su notificación seguía llamando a `__()` sin datos.
     * **Lo cazó renderizar, no leer** — y por eso el reemplazo se busca DENTRO de la llamada
     * `__()` del asunto, balanceando paréntesis: mirar «¿aparece 'code' => en el fichero?» habría
     * pasado en verde, porque esa notificación sí lo pasa… en otra línea, a otra clave.
     */
    public function test_every_subject_placeholder_is_passed_by_its_notification(): void
    {
        $sueltos = [];
        foreach ($this->grupos() as $notificacion => $grupo) {
            $src = (string) file_get_contents(app_path("Notifications/$notificacion.php"));

            foreach (['subject', 'subject_no_date'] as $clave) {
                if (! Lang::has($grupo.'.'.$clave, 'es')) {
                    continue;
                }
                $pedidos = $this->placeholders((string) Lang::get($grupo.'.'.$clave, [], 'es'));
                if ($pedidos === []) {
                    continue;
                }
                $llamada = $this->llamadaQueResuelve($src, $grupo.'.'.$clave);
                if ($llamada === null) {
                    $sueltos[] = "$notificacion: no encuentro dónde se compone $grupo.$clave";

                    continue;
                }
                foreach ($pedidos as $p) {
                    if (! str_contains($llamada, "'$p' =>")) {
                        $sueltos[] = "$notificacion: el asunto pide :$p y su __() no lo pasa";
                    }
                }
            }
        }

        $this->assertSame([], $sueltos, "asuntos que saldrían con el dato en crudo:\n".implode("\n", $sueltos));
    }

    /**
     * El texto de la llamada `__('<clave>', [...])` que resuelve esa clave, con los paréntesis
     * balanceados — acotar importa: los 300 caracteres siguientes alcanzan a la línea de al lado.
     */
    private function llamadaQueResuelve(string $src, string $clave): ?string
    {
        $pos = strpos($src, "'".$clave."'");
        if ($pos === false) {
            return null;
        }
        $abre = strrpos(substr($src, 0, $pos), '__(');
        if ($abre === false) {
            return null;
        }
        $i = $abre + 3;
        $nivel = 1;
        $len = strlen($src);
        while ($i < $len && $nivel > 0) {
            if ($src[$i] === '(') {
                $nivel++;
            } elseif ($src[$i] === ')') {
                $nivel--;
            }
            $i++;
        }

        return substr($src, $abre, $i - $abre);
    }

    /**
     * ❗❗ **EL NOMBRE DEL NEGOCIO NO VA EN EL ASUNTO** (`[DECIDIDO owner, 2026-09-10]`): en la lista
     * de la bandeja se lee **al lado**, porque desde `#501` el remitente sale del panel. Cinco de
     * los 21 lo repetían y gastaban ~13 caracteres del corte en un dato que ya está a la vista.
     */
    public function test_no_subject_repeats_the_business_name(): void
    {
        $con = [];
        foreach ($this->grupos() as $grupo) {
            foreach (['es', 'en', 'fr'] as $loc) {
                foreach (['subject', 'subject_no_date'] as $clave) {
                    if (! Lang::has($grupo.'.'.$clave, $loc)) {
                        continue;
                    }
                    if (in_array('park', $this->placeholders((string) Lang::get($grupo.'.'.$clave, [], $loc)), true)) {
                        $con[] = "$grupo ($loc).$clave";
                    }
                }
            }
        }

        $this->assertSame([], $con, "asuntos que repiten el nombre del negocio:\n".implode("\n", $con));
    }

    /**
     * ❗❗❗ **LA LÍNEA VA ANTES DE LA CABECERA, Y AHÍ ESTÁ TODO EL MECANISMO.** Lo primero que lee un
     * gestor de correo es lo primero del DOCUMENTO, y encima del cuerpo va la cabecera, cuyo
     * logotipo lleva `alt="{nombre del negocio}"`. Metida en el cuerpo —que es donde la pondría
     * cualquiera— la bandeja leería el nombre del parque y luego la frase.
     *
     * ⚠️ Y el CONTROL importa: se comprueba también que la cabecera está en el documento. Sin él,
     * un correo que dejara de pintar cabecera pasaría este caso en verde por no tener rival.
     */
    public function test_the_inbox_line_is_rendered_before_the_header(): void
    {
        $html = $this->renderConfirmation();

        $linea = (string) Lang::get('emails.order_confirmation.preheader', [], 'es');
        $posLinea = strpos($html, $linea);
        $posCabecera = strpos($html, 'class="header"');

        $this->assertNotFalse($posCabecera, 'CONTROL: el correo no pinta cabecera, así que este caso no mide nada');
        $this->assertNotFalse($posLinea, 'la línea de adelanto no llega al correo');
        $this->assertLessThan($posCabecera, $posLinea, 'la línea de adelanto va DESPUÉS de la cabecera: la bandeja leería el logotipo');
    }

    /**
     * ⚠️ **Y NO VIAJA EN LA PARTE DE TEXTO PLANO**, a propósito: ahí no hay bandeja a la que
     * adelantarse, así que pintarla repetiría la primera frase del correo. Todo correo lleva las
     * dos partes, y en esta casa la trampa ya está fichada —«todo componente de correo nace por
     * partida doble»—, así que la ausencia se comprueba en vez de suponerse.
     */
    public function test_the_inbox_line_does_not_travel_in_the_plain_text_part(): void
    {
        $user = User::factory()->create(['locale' => 'es']);
        $mensaje = (new AccountAlreadyExists)->toMail($user);

        $texto = (string) app(Markdown::class)
            ->renderText('notifications::email', $mensaje->data());

        $linea = (string) Lang::get('account.exists_mail.preheader', [], 'es');

        $this->assertNotSame('', $linea, 'CONTROL: sin línea escrita este caso no mide nada');
        $this->assertStringNotContainsString($linea, $texto, 'la línea de adelanto se cuela en el texto plano');
    }

    /**
     * ❗❗❗ **UN CORREO SIN LÍNEA ESCRITA SALE SIN LÍNEA, NO CON LA CLAVE DENTRO.** `hero()` sólo la
     * pinta si existe, y esa comprobación **no tiene sujeto entre los 21** —todos la tienen—, así
     * que sin este caso quitarla no cambia nada y la mutación sobrevive. Con él, el día que alguien
     * añada un correo nuevo y se deje la línea, la bandeja no dirá «emails.lo_que_sea.preheader».
     *
     * ▶ Es exactamente el defecto que `#504` puso delante de un cliente con `badge` y `headline`.
     */
    public function test_a_mail_without_a_written_line_ships_without_one(): void
    {
        $sinEscribir = 'emails.este_grupo_no_existe';
        $this->assertFalse(Lang::has($sinEscribir.'.preheader'), 'CONTROL: el grupo tenía que no existir');

        $mensaje = (new BrandedMailMessage)->hero($sinEscribir, 'info');

        $this->assertArrayNotHasKey('preheader', $mensaje->viewData,
            'un grupo sin línea escrita anunciaría el correo con el nombre de la clave');
    }

    private function renderConfirmation(): string
    {
        Setting::create(['key' => 'business.name', 'value' => 'SaltoPark', 'group' => 'business']);
        Setting::flushMemo();

        $user = User::factory()->create(['locale' => 'es']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-INBOX1',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1500, 'total' => 1500, 'currency' => 'EUR',
        ]);

        return (new OrderConfirmation($order))->toMail($user)->render();
    }
}

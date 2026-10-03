<?php

namespace Tests\Feature\Mail;

use App\Domain\Content\Models\MailText;
use App\Domain\Content\Services\MailTextLoader;
use App\Domain\Content\Services\MailTextRules;
use App\Domain\Content\Services\MailTexts;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Notifications\Support\MailTextCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

/**
 * LOS TEXTOS DE CORREO DEL PARQUE (R1·T de `specs/correos-rediseno.md` §4.2.1, `#802`): el almacén, las reglas y el
 * cargador que los superpone al traductor. Lo que se afirma es lo que CALLA al romperse —un texto que no llega al correo, una
 * variable que desaparece, una fila que pinta lo que no debe, una caché que no se entera— y cada caso lleva su control.
 */
class MailTextsTest extends TestCase
{
    use RefreshDatabase;

    /** Un párrafo con un dato del cliente (`:code`): el del pago denegado. */
    private const CLAVE = 'emails.order_declined.intro';

    public function test_the_translator_goes_through_the_mail_text_loader(): void
    {
        $this->assertInstanceOf(MailTextLoader::class, app('translation.loader'));
    }

    public function test_a_saved_text_reaches_the_mail_in_its_language_only_and_restoring_brings_the_factory_back(): void
    {
        $fabrica = (string) MailTextCatalog::fabrica(self::CLAVE, 'es');
        $this->assertStringContainsString(':code', $fabrica, 'el caso necesita una variable en su texto de fábrica');
        $antes = trans(self::CLAVE, ['code' => 'R-7'], 'es');

        $this->assertNull($this->almacen()->guardar(self::CLAVE, 'es', '¡Hecho! Tu reserva es la **{code}**.', $fabrica, null));

        $this->assertSame('¡Hecho! Tu reserva es la **R-7**.', trans(self::CLAVE, ['code' => 'R-7'], 'es'));
        // CONTROL: los otros idiomas siguen con el de fábrica.
        $this->assertStringNotContainsString('¡Hecho!', trans(self::CLAVE, ['code' => 'R-7'], 'en'));

        $this->assertTrue($this->almacen()->restaurar(self::CLAVE, 'es', null));
        $this->assertSame($antes, trans(self::CLAVE, ['code' => 'R-7'], 'es'));
        $this->assertFalse($this->almacen()->restaurar(self::CLAVE, 'es', null), 'ya estaba de fábrica');
    }

    public function test_saving_the_factory_text_removes_the_row_so_later_product_fixes_arrive(): void
    {
        $fabrica = (string) MailTextCatalog::fabrica(self::CLAVE, 'es');
        $this->almacen()->guardar(self::CLAVE, 'es', 'Propio {code}', $fabrica, null);
        $this->assertSame(1, MailText::query()->count());

        $this->assertNull($this->almacen()->guardar(self::CLAVE, 'es', MailTextRules::aParque($fabrica), $fabrica, null));

        $this->assertSame(0, MailText::query()->count(), 'igual que el de fábrica = de fábrica: una fila congelaría el correo');
    }

    public function test_each_rule_refuses_and_says_why(): void
    {
        $fabrica = 'Tu código es :code y vale :minutes minutos.';
        $clave = 'emails.login_code.validity';
        $motivo = fn (string $texto): ?string => MailTextRules::problema($texto, $fabrica, $clave)['motivo'] ?? null;

        $this->assertNull($motivo('Usa {code}: sirve {minutes} min.'), 'CONTROL: un texto bueno pasa');
        $this->assertNull($motivo('Usa **{code}**: sirve {minutes} min.'), 'la negrita balanceada pasa');
        $this->assertSame('vacio', $motivo('   '));
        $this->assertSame('largo', $motivo(str_pad('{code} {minutes}', MailTextRules::TOPE + 1, 'a')));
        $this->assertNull($motivo(str_pad('{code} {minutes}', MailTextRules::TOPE, 'a')), 'justo el tope, pasa');
        $this->assertSame('html', $motivo('Usa <b>{code}</b> en {minutes}.'));
        $this->assertSame('html', $motivo('Usa <br>{code} en {minutes}.'), 'una etiqueta que solo abre, también');
        $this->assertSame('llaves', $motivo('Usa {code} en {minutes. Ya.'));
        $this->assertSame('desconocida', $motivo('Hola {nombre}, usa {code} en {minutes}.'));
        $this->assertSame('falta', $motivo('Usa {code} cuanto antes.'), 'un dato del cliente no puede desaparecer');
        $this->assertSame('negrita', $motivo('Usa **{code} en {minutes}.'));

        // El problema devuelve lo que hace falta para decirlo.
        $this->assertSame(['motivo' => 'falta', 'variables' => ['minutes']], MailTextRules::problema('Usa {code}.', $fabrica, $clave));
        // Los topes, por lo que es cada bloque.
        $this->assertSame(MailTextRules::TOPE_CORTO, MailTextRules::tope('emails.x.subject_no_date'));
        $this->assertSame(MailTextRules::TOPE_ADELANTO, MailTextRules::tope('emails.x.preheader'));
        $this->assertSame(MailTextRules::TOPE, MailTextRules::tope('emails.x.intro'));
    }

    public function test_bold_is_refused_where_the_mail_paints_the_text_as_is(): void
    {
        // El asunto, la bandeja, la cabecera, los botones, los títulos y las etiquetas salen TAL CUAL: un `**` ahí llegaría al
        // cliente con sus asteriscos (medido el 30-09: 141 de 278 textos). `MailPreviewsTest` lo casa con el molde de verdad.
        foreach (['subject', 'subject_no_date', 'preheader', 'badge', 'headline', 'headline_no_date', 'action', 'action_invite', 'boton', 'notice_title', 'balance_title', 'email_label'] as $bloque) {
            $this->assertFalse(MailTextRules::admiteNegrita('emails.x.'.$bloque), $bloque);
        }
        // CONTROL: los párrafos y los avisos la pintan.
        foreach (['intro', 'line1', 'linea', 'notice_body', 'balance', 'validity', 'ignore', 'outro_invite'] as $bloque) {
            $this->assertTrue(MailTextRules::admiteNegrita('emails.x.'.$bloque), $bloque);
        }

        $fabrica = ':code es tu código para entrar';
        $this->assertSame(['motivo' => 'sin_negrita'], MailTextRules::problema('**{code}** es tu código', $fabrica, 'emails.login_code.subject'));
        // CONTROL: sin la negrita pasa, y un asterisco suelto no es negrita.
        $this->assertNull(MailTextRules::problema('{code} es tu código', $fabrica, 'emails.login_code.subject'));
        $this->assertNull(MailTextRules::problema('{code} es tu código*', $fabrica, 'emails.login_code.subject'));
        // Y en un párrafo, la misma negrita sí.
        $this->assertNull(MailTextRules::problema('Usa **{code}**.', ':code', 'emails.login_code.validity'));
    }

    public function test_an_invalid_text_is_not_saved(): void
    {
        $fabrica = (string) MailTextCatalog::fabrica(self::CLAVE, 'es');

        $problema = $this->almacen()->guardar(self::CLAVE, 'es', 'Sin el código', $fabrica, null);

        $this->assertSame('falta', $problema['motivo'] ?? null);
        $this->assertSame(0, MailText::query()->count());
    }

    public function test_the_factory_text_is_shown_with_braces_and_read_back_with_colons(): void
    {
        $this->assertSame('Del {day_label} al {day}: {code}', MailTextRules::aParque('Del :day_label al :day: :code'), 'la más larga primero');
        $this->assertSame('Tu :code', MailTextRules::aTraductor('Tu {code}', ['code']));
        // CONTROL: una hora no es una variable.
        $this->assertSame([], MailTextRules::variablesDeFabrica('Abrimos a las 17:00.'));
    }

    public function test_a_row_whose_variables_no_longer_match_the_factory_is_not_painted(): void
    {
        // Una fila escrita cuando el texto de fábrica llevaba otros datos (el producto cambió): no se pinta, sale el de fábrica.
        MailText::query()->create(['key' => self::CLAVE, 'locale' => 'es', 'text' => 'Viejo {dia}']);
        $this->invalidar();

        $this->assertStringNotContainsString('Viejo', trans(self::CLAVE, ['code' => 'R-7'], 'es'));

        // CONTROL: con las variables de hoy, sí.
        MailText::query()->where('key', self::CLAVE)->update(['text' => 'Nuevo {code}']);
        $this->invalidar();
        $this->assertSame('Nuevo R-7', trans(self::CLAVE, ['code' => 'R-7'], 'es'));
    }

    /**
     * Los ENLACES POR NOMBRE (la R2, `[escríbenos](whatsapp)`), como las variables: los del texto de fábrica se quedan y no
     * se inventan otros. Lo que se lee entre corchetes, sí se puede cambiar.
     */
    public function test_a_link_by_name_stays_and_none_is_invented(): void
    {
        $fabrica = 'Puedes [escríbenos por WhatsApp](whatsapp) o [llamarnos](tel).';
        $motivo = fn (string $texto): ?string => MailTextRules::problema($texto, $fabrica, 'emails.reserva.cambios')['motivo'] ?? null;

        $this->assertNull($motivo('Si cambian los planes, [mándanos un WhatsApp](whatsapp) o [llámanos](tel).'), 'CONTROL: otro texto, los mismos enlaces');
        $this->assertSame('enlaces', $motivo('Puedes escribirnos o [llamarnos](tel).'), 'falta el de WhatsApp');
        $this->assertSame('enlaces', $motivo('Puedes [escríbenos](whatsapp), [llamarnos](tel) o [mirar](web).'), 'uno inventado');
        $this->assertSame('enlaces', $motivo('Puedes [escríbenos](correo) o [llamarnos](tel).'), 'uno cambiado de nombre');
        $this->assertSame(['tel', 'whatsapp'], MailTextRules::enlaces($fabrica));
    }

    public function test_a_row_that_would_not_pass_the_rules_today_is_not_painted(): void
    {
        // Lo que pidió plataforma (30-09): un correo de código no sale sin su código, ni aunque la fila llegue a la base por
        // otra puerta (desde la R1c el código va en su bloque, que no se edita; el ASUNTO lo sigue llevando). Y una negrita
        // donde el molde no la pinta, tampoco: el cargador aplica las reglas de GUARDAR.
        MailText::query()->create(['key' => 'emails.login_code.subject', 'locale' => 'es', 'text' => 'Tu código para entrar']);
        MailText::query()->create(['key' => 'emails.login_code.code_label', 'locale' => 'es', 'text' => '**Código** de {digits} cifras']);
        $this->invalidar();

        $this->assertSame('482-913 es tu código para entrar', trans('emails.login_code.subject', ['code' => '482-913'], 'es'));
        $this->assertSame('Código de 6 cifras', trans('emails.login_code.code_label', ['digits' => 6], 'es'));

        // CONTROL: las mismas filas, en regla, sí se pintan.
        MailText::query()->where('key', 'emails.login_code.subject')->update(['text' => '{code} para entrar']);
        MailText::query()->where('key', 'emails.login_code.code_label')->update(['text' => 'Tu código, de {digits} cifras']);
        $this->invalidar();
        $this->assertSame('482-913 para entrar', trans('emails.login_code.subject', ['code' => '482-913'], 'es'));
        $this->assertSame('Tu código, de 6 cifras', trans('emails.login_code.code_label', ['digits' => 6], 'es'));
    }

    public function test_a_row_for_a_key_outside_the_catalog_is_never_painted(): void
    {
        // Ni lo legal (el pie), ni el saludo que ya no sale, ni una clave de la WEB: el cargador solo toca lo editable.
        foreach (['emails.pie.fuera' => null, 'emails.order_declined.greeting' => null, 'account.exists_mail.greeting' => null] as $clave => $_) {
            $this->assertFalse(MailTextCatalog::esEditable($clave), $clave);
        }
        $greeting = trans('emails.order_declined.greeting', [], 'es');
        MailText::query()->create(['key' => 'emails.order_declined.greeting', 'locale' => 'es', 'text' => 'PISADO']);
        $this->invalidar();

        $this->assertSame($greeting, trans('emails.order_declined.greeting', [], 'es'));
    }

    public function test_a_row_in_a_language_without_its_factory_text_is_skipped_not_broken(): void
    {
        // Un idioma sin ese texto de fábrica (el producto no lo trae): la fila no se pinta, y el correo sale en el de reserva.
        MailText::query()->create(['key' => self::CLAVE, 'locale' => 'de', 'text' => 'Deutsch {code}']);
        $this->invalidar();

        $deReserva = trans(self::CLAVE, ['code' => 'R-7'], (string) config('app.fallback_locale'));
        $this->assertSame($deReserva, trans(self::CLAVE, ['code' => 'R-7'], 'de'));
        $this->assertStringNotContainsString('Deutsch', $deReserva);
    }

    public function test_a_failing_cache_still_reads_the_park_texts_from_the_database(): void
    {
        MailText::query()->create(['key' => self::CLAVE, 'locale' => 'es', 'text' => 'Propio {code}']);
        Cache::shouldReceive('get')->andThrow(new RuntimeException('la caché se cayó'));

        // Sin caché, de la base: nunca el de fábrica en silencio.
        $this->assertSame([self::CLAVE => 'Propio {code}'], $this->almacen()->enIdioma('es'));
    }

    public function test_the_draft_of_a_preview_is_painted_only_inside_it(): void
    {
        // El traductor ya tiene el grupo en memoria (un proceso que ya pintó un correo): el borrador le obliga a releer.
        trans(self::CLAVE, ['code' => 'R-7'], 'es');

        $dentro = MailTexts::conBorrador('es', [self::CLAVE => 'BORRADOR {code}'], static fn (): string => trans(self::CLAVE, ['code' => 'R-7'], 'es'));

        $this->assertSame('BORRADOR R-7', $dentro);
        $this->assertStringNotContainsString('BORRADOR', trans(self::CLAVE, ['code' => 'R-7'], 'es'), 'fuera, ni rastro');
        $this->assertSame(0, MailText::query()->count(), 'y nada en la base');
    }

    public function test_a_save_is_seen_in_the_same_request_and_leaves_its_trail(): void
    {
        $quien = User::factory()->create();
        $fabrica = (string) MailTextCatalog::fabrica(self::CLAVE, 'es');
        trans(self::CLAVE, ['code' => 'R-7'], 'es'); // el traductor ya tiene el grupo en memoria

        $this->almacen()->guardar(self::CLAVE, 'es', 'Otra {code}', $fabrica, $quien);
        $this->assertSame('Otra R-7', trans(self::CLAVE, ['code' => 'R-7'], 'es'), 'la caché y la memoria del traductor se enteran');

        $rastro = AuditLog::query()->where('action', 'emails.text_updated')->sole();
        $this->assertSame(['key' => self::CLAVE, 'locale' => 'es', 'from' => null, 'to' => 'Otra {code}'], $rastro->payload);
        $this->assertSame($quien->getKey(), MailText::query()->sole()->updated_by);

        $this->almacen()->restaurar(self::CLAVE, 'es', $quien);
        $this->assertSame(1, AuditLog::query()->where('action', 'emails.text_restored')->count());
    }

    private function almacen(): MailTexts
    {
        return app(MailTexts::class);
    }

    /** Lo que hace guardar por la puerta buena: la caché se entera y el traductor relee. */
    private function invalidar(): void
    {
        cache()->forever('mail_texts.version', (int) cache()->get('mail_texts.version', 0) + 1);
        MailTexts::olvidarCargados();
    }
}

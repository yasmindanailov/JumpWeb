<?php

namespace Tests\Feature\Admin;

use App\Domain\Content\Models\MailText;
use App\Domain\Content\Services\MailTextRules;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Pages\AdminSettingsHub;
use App\Filament\Pages\EmailTexts;
use App\Notifications\Support\MailTextCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * «TEXTOS DE LOS CORREOS» EN EL PANEL (R1·T de `specs/correos-rediseno.md` §4.2.1, `#802`): quién entra, qué ve, que
 * guardar sea TODO o NADA, que volver al de fábrica borre la fila, que la vista previa enseñe lo que hay en la pantalla sin
 * guardarlo, y que el permiso se re-exija en cada acción (`SEC-04`).
 */
class EmailTextsPageTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    /** El correo de ejemplo: uno con un PÁRRAFO que lleva el número del pedido y un asunto que también (`{code}`). */
    private const CORREO = 'order_cancelled';

    private const INTRO = 'emails.order_cancelled.intro';

    private const ASUNTO = 'emails.order_cancelled.subject';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_only_who_has_the_permission_gets_in_and_sees_the_card(): void
    {
        $this->actingAs($this->con(['emails.edit_texts']))->get(EmailTexts::getUrl())->assertSuccessful();
        $this->actingAs($this->con(['emails.view']))->get(EmailTexts::getUrl())->assertForbidden();

        // La tarjeta del hub: con el permiso, sí; sin él, no (ocultar NO es autorizar, pero tampoco se enseña lo que no abre).
        $this->actingAs($this->con(['emails.edit_texts', 'settings.manage']));
        $this->assertContains(EmailTexts::getUrl(), $this->tarjetas());
        $this->actingAs($this->con(['settings.manage']));
        $this->assertNotContains(EmailTexts::getUrl(), $this->tarjetas());
    }

    public function test_the_list_shows_each_mail_with_its_state(): void
    {
        $editor = $this->con(['emails.edit_texts']);
        MailText::query()->create(['key' => self::INTRO, 'locale' => 'es', 'text' => 'Propio {code}']);

        $tipos = Livewire::actingAs($editor)->test(EmailTexts::class)->instance()->tipos();

        $this->assertSame(count(MailTextCatalog::TIPOS), count($tipos));
        $this->assertSame(count(MailTextCatalog::CORREOS), array_sum(array_map(static fn (array $t): int => count($t['items']), $tipos)));
        $item = collect($tipos)->flatMap(static fn (array $t) => $t['items'])->firstWhere('correo', self::CORREO);
        $this->assertSame(1, $item['propios']);
        $this->assertSame(['en', 'fr'], $item['sinTraducir'], 'cambió en español y no en inglés ni francés');
        $this->assertSame(0, $item['desfasados']);
        // CONTROL: un correo sin tocar, de fábrica y sin avisos.
        $otro = collect($tipos)->flatMap(static fn (array $t) => $t['items'])->firstWhere('correo', 'login_code');
        $this->assertSame([0, []], [$otro['propios'], $otro['sinTraducir']]);
    }

    public function test_opening_a_mail_loads_the_factory_text_with_braces_and_the_park_text_where_there_is_one(): void
    {
        MailText::query()->create(['key' => self::INTRO, 'locale' => 'en', 'text' => 'Ours {code}']);

        Livewire::withQueryParams(['correo' => self::CORREO])
            ->actingAs($this->con(['emails.edit_texts']))
            ->test(EmailTexts::class)
            ->assertSet('textos.es.'.self::INTRO, MailTextRules::aParque((string) MailTextCatalog::fabrica(self::INTRO, 'es')))
            ->assertSet('textos.en.'.self::INTRO, 'Ours {code}');
    }

    public function test_saving_writes_the_changed_blocks_and_leaves_a_trail(): void
    {
        $propio = __('admin.mail_texts.estado.propio');
        $pagina = Livewire::withQueryParams(['correo' => self::CORREO])
            ->actingAs($this->con(['emails.edit_texts']))
            ->test(EmailTexts::class)
            ->assertDontSee($propio)
            ->set('textos.es.'.self::INTRO, '¡Listo! Tu pedido **{code}**.')
            ->set('textos.fr.'.self::INTRO, 'C’est fait : {code}.')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertNotified(trans_choice('admin.mail_texts.guardado', 2, ['count' => 2]))
            // En la MISMA respuesta, los dos bloques ya dicen «Personalizado».
            ->assertSee($propio);
        $this->assertSame(2, substr_count($pagina->html(), $propio));

        // Guardar otra vez sin tocar nada: nada que guardar.
        $pagina->call('guardar')->assertNotified(__('admin.mail_texts.sin_cambios'));

        $this->assertSame('¡Listo! Tu pedido **{code}**.', MailText::query()->where(['key' => self::INTRO, 'locale' => 'es'])->value('text'));
        $this->assertSame('C’est fait : {code}.', MailText::query()->where(['key' => self::INTRO, 'locale' => 'fr'])->value('text'));
        $this->assertSame(2, MailText::query()->count(), 'solo lo que cambió');
        $this->assertSame(2, AuditLog::query()->where('action', 'emails.text_updated')->count());
    }

    public function test_one_invalid_block_saves_nothing_and_says_why_in_its_field(): void
    {
        Livewire::withQueryParams(['correo' => self::CORREO])
            ->actingAs($this->con(['emails.edit_texts']))
            ->test(EmailTexts::class)
            ->set('textos.es.'.self::INTRO, 'Sin el código del pedido')
            ->set('textos.es.'.self::ASUNTO, 'Asunto propio {code}')
            ->call('guardar')
            ->assertHasErrors(['textos.es.'.self::INTRO]);

        $this->assertSame(0, MailText::query()->count(), 'todo o nada: el asunto bueno tampoco se guarda');
    }

    public function test_the_next_save_closes_the_previous_error_notice(): void
    {
        $pagina = Livewire::withQueryParams(['correo' => self::CORREO])
            ->actingAs($this->con(['emails.edit_texts']))
            ->test(EmailTexts::class)
            ->call('guardar')
            ->assertNotDispatched('close-notification'); // CONTROL: sin un error antes, nada que cerrar

        $pagina->set('textos.es.'.self::INTRO, 'Sin el código')->call('guardar');
        $fallido = $pagina->get('avisoFallido');
        $this->assertIsString($fallido, 'el aviso del error queda apuntado');

        // Arreglado y guardado enseguida: «No se ha guardado nada» se cierra y solo queda «Guardado».
        $pagina->set('textos.es.'.self::INTRO, 'Con **{code}**.')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('close-notification', id: $fallido)
            ->assertSet('avisoFallido', null);
    }

    public function test_back_to_the_factory_text_removes_the_row(): void
    {
        MailText::query()->create(['key' => self::INTRO, 'locale' => 'es', 'text' => 'Propio {code}']);

        Livewire::withQueryParams(['correo' => self::CORREO])
            ->actingAs($this->con(['emails.edit_texts']))
            ->test(EmailTexts::class)
            ->set('textos.es.'.self::INTRO, MailTextRules::aParque((string) MailTextCatalog::fabrica(self::INTRO, 'es')))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(0, MailText::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'emails.text_restored')->count());
    }

    public function test_back_to_factory_button_shows_only_where_it_does_something(): void
    {
        $fabrica = MailTextRules::aParque((string) MailTextCatalog::fabrica(self::INTRO, 'es'));
        // Un botón oculto Filament ni lo registra (`assertActionHidden` no lo encuentra): se cuentan los botones PINTADOS, por
        // su `wire:click` (la acción sale otras dos veces en el `wire:target` de su icono de carga).
        $botones = static fn ($pagina): int => substr_count($pagina->html(), "wire:click=\"mountAction('fabrica'");

        $pagina = Livewire::withQueryParams(['correo' => self::CORREO])
            ->actingAs($this->con(['emails.edit_texts']))
            ->test(EmailTexts::class);
        $this->assertSame(0, $botones($pagina), 'de fábrica, ningún bloque lo enseña');

        $pagina->set('textos.es.'.self::INTRO, 'Otro texto {code}');
        $this->assertSame(1, $botones($pagina), 'solo el bloque que cambió');
        $this->assertStringContainsString('form.es.'.self::INTRO, $pagina->html());

        $pagina->callAction(TestAction::make('fabrica')->schemaComponent('es.'.self::INTRO, schema: 'form'))
            ->assertSet('textos.es.'.self::INTRO, $fabrica);
        $this->assertSame(0, $botones($pagina), 'de vuelta a fábrica, se va');
        $this->assertSame(0, MailText::query()->count(), 'el botón solo cambia el campo: se guarda con «Guardar»');
    }

    public function test_the_preview_paints_what_is_on_screen_without_saving_it(): void
    {
        $this->mountParty();

        $pagina = Livewire::withQueryParams(['correo' => self::CORREO])
            ->actingAs($this->con(['emails.edit_texts']))
            ->test(EmailTexts::class)
            ->set('textos.es.'.self::INTRO, 'SIN GUARDAR {code}')
            ->call('verVista', 'es');

        $this->assertStringContainsString('SIN GUARDAR R-', (string) $pagina->get('vistaHtml'));
        $this->assertStringContainsString('<base target="_blank">', (string) $pagina->get('vistaHtml'), 'inerte, como la de «Correos enviados»');
        $this->assertSame(0, MailText::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'emails.text_previewed')->count());

        // Un bloque que no pasa sus reglas no se pinta con el borrador (sale el de fábrica). Con sus variables bien, para que
        // lo pare la PÁGINA y no el filtro de variables del cargador.
        $pagina->set('textos.es.'.self::INTRO, 'ROTO <i>{code}</i>')->call('verVista', 'es');
        $this->assertStringNotContainsString('ROTO', (string) $pagina->get('vistaHtml'));
    }

    /**
     * LA SITUACIÓN (R1·T2, `#809`): el desplegable sale en un correo con textos que dependen de ella, elegir una pinta lo
     * suyo y deja rastro de cuál; una que no es del correo (llega del navegador) no se pinta: sale la primera.
     */
    public function test_the_preview_paints_the_chosen_situation_and_only_one_of_its_mail(): void
    {
        $this->mountParty();
        $admin = $this->con(['emails.edit_texts']);

        $pagina = Livewire::withQueryParams(['correo' => 'order_item_modified'])->actingAs($admin)->test(EmailTexts::class)
            ->call('verVista', 'es')
            ->assertSeeHtml('data-email-texts-situation');
        // Sin elegir, la primera: la reserva modificada nunca sale sin un cambio.
        $this->assertSame('cambio_dia', $pagina->get('vistaSituacion'));

        $cantidad = trim((string) strtok((string) __('emails.order_item_modified.quantity_change', ['old' => 'QQ', 'new' => 'QQ']), 'Q'));
        $this->assertStringNotContainsString($cantidad, (string) $pagina->get('vistaHtml'), 'CONTROL: la del día no dice la cantidad');
        $pagina->call('verVista', null, null, 'cambio_cantidad');
        $this->assertSame('cambio_cantidad', $pagina->get('vistaSituacion'));
        $this->assertStringContainsString($cantidad, (string) $pagina->get('vistaHtml'));
        $this->assertSame('cambio_cantidad', AuditLog::query()->where('action', 'emails.text_previewed')->latest('id')->value('payload')['situation'] ?? null);

        $pagina->call('verVista', null, null, 'suplemento_nace');
        $this->assertSame('cambio_dia', $pagina->get('vistaSituacion'), 'una situación de otro correo no se pinta');

        // Un correo sin textos que dependan de la situación no enseña el desplegable.
        Livewire::withQueryParams(['correo' => 'login_code'])->actingAs($admin)->test(EmailTexts::class)
            ->call('verVista', 'es')
            ->assertDontSeeHtml('data-email-texts-situation');
    }

    /** Bajo el campo de un texto que solo sale a veces, CUÁNDO sale («Solo sale si…»); bajo uno de siempre, nada de eso. */
    public function test_a_conditional_text_says_when_it_goes_out_under_its_field(): void
    {
        Livewire::withQueryParams(['correo' => 'order_refunded'])
            ->actingAs($this->con(['emails.edit_texts']))
            ->test(EmailTexts::class)
            ->assertSee(__('admin.mail_texts.solo_si.devolucion_a_mano'))
            ->assertSee(__('admin.mail_texts.solo_si.tambien_cancelado'))
            ->assertDontSee(__('admin.mail_texts.solo_si.cambio_dia'));
    }

    public function test_a_broken_block_in_the_preview_shows_what_would_still_go_out(): void
    {
        MailText::query()->create(['key' => self::INTRO, 'locale' => 'es', 'text' => 'GUARDADO {code}']);
        $this->mountParty();

        // Con un bloque roto no se guarda nada (todo o nada): lo que seguiría saliendo es lo GUARDADO, no el de fábrica. Lo
        // decide la página; el cargador, sin ella, pintaría el de fábrica.
        $html = (string) Livewire::withQueryParams(['correo' => self::CORREO])
            ->actingAs($this->con(['emails.edit_texts']))
            ->test(EmailTexts::class)
            ->set('textos.es.'.self::INTRO, 'ROTO <i>{code}</i>')
            ->call('verVista', 'es')
            ->get('vistaHtml');

        $this->assertStringNotContainsString('ROTO', $html);
        $this->assertStringContainsString('GUARDADO R-', $html);
    }

    public function test_the_preview_shows_what_the_inbox_reads_above_the_mail(): void
    {
        // El asunto y el adelanto se editan y el cuerpo no los enseña: van encima, como en la bandeja de entrada.
        Livewire::withQueryParams(['correo' => 'login_code'])
            ->actingAs($this->con(['emails.edit_texts']))
            ->test(EmailTexts::class)
            ->set('textos.es.emails.login_code.subject', 'Entra con {code}')
            ->call('verVista', 'es')
            // El código, como lo enseñan los correos (`LoginCodes::shown()`, «482-913»: `#867`).
            ->assertSet('vistaAsunto', 'Entra con 482-913')
            ->assertSet('vistaAdelanto', 'Escríbelo donde lo pediste. Si no lo has pedido tú, ignora este correo.')
            ->assertSeeHtml('data-email-texts-subject')
            ->assertSee('Entra con 482-913');
    }

    public function test_bold_in_a_block_the_mail_paints_as_is_is_refused_in_its_field(): void
    {
        $pagina = Livewire::withQueryParams(['correo' => self::CORREO])
            ->actingAs($this->con(['emails.edit_texts']))
            ->test(EmailTexts::class)
            ->set('textos.es.'.self::ASUNTO, '**Asunto** propio {code}')
            ->call('guardar')
            ->assertHasErrors(['textos.es.'.self::ASUNTO]);

        $this->assertSame(
            'Aquí no hay negrita: este bloque sale tal cual y se verían los asteriscos. Quítalos.',
            $pagina->errors()->first('textos.es.'.self::ASUNTO),
        );
        $this->assertSame(0, MailText::query()->count());
    }

    public function test_a_saved_text_that_would_not_pass_the_rules_today_is_out_of_date(): void
    {
        // Una fila que hoy no pasaría las reglas (la negrita en el asunto, de antes de la regla) no sale: la lista la cuenta
        // «desfasada» y su bloque lo dice. Es el mismo filtro que el cargador.
        MailText::query()->create(['key' => self::ASUNTO, 'locale' => 'es', 'text' => '**Asunto** propio {code}']);
        $editor = $this->con(['emails.edit_texts']);
        $desfasados = static fn (): int => collect(Livewire::actingAs($editor)->test(EmailTexts::class)->instance()->tipos())
            ->flatMap(static fn (array $t) => $t['items'])->firstWhere('correo', self::CORREO)['desfasados'];

        $this->assertSame(1, $desfasados());
        Livewire::withQueryParams(['correo' => self::CORREO])->actingAs($editor)->test(EmailTexts::class)
            ->assertSee('Desfasado: sale el de fábrica');

        // CONTROL: la misma fila sin la negrita está al día.
        MailText::query()->where('key', self::ASUNTO)->update(['text' => 'Asunto propio {code}']);
        $this->assertSame(0, $desfasados());
    }

    public function test_the_permission_is_asked_again_on_every_action(): void
    {
        $editor = $this->con(['emails.edit_texts']);
        $pagina = Livewire::withQueryParams(['correo' => self::CORREO])->actingAs($editor)->test(EmailTexts::class);

        Role::query()->where('name', 'staff')->firstOrFail()->permissions()->sync([]);

        // Filament vuelve a comprobar el acceso en CADA petición de la página (`hydrateCanAuthorizeAccess`): la siguiente ya
        // no pasa. La página no lo repite (una copia que nunca se ve fallar, medido por el arnés): esta prueba es la guarda.
        $pagina->call('guardar')->assertForbidden();
        $this->assertSame(0, MailText::query()->count());
    }

    public function test_an_unknown_mail_falls_back_to_the_list(): void
    {
        Livewire::withQueryParams(['correo' => 'no_existe'])
            ->actingAs($this->con(['emails.edit_texts']))
            ->test(EmailTexts::class)
            ->assertSet('correo', null);
    }

    /** @return list<string> las direcciones de las tarjetas del hub que ve quien está dentro */
    private function tarjetas(): array
    {
        $urls = [];
        foreach ((new AdminSettingsHub)->visibleAreas() as $area) {
            foreach ($area['items'] as $item) {
                $urls[] = (string) $item['url'];
            }
        }

        return $urls;
    }

    /**
     * Un empleado cuyo rol tiene EXACTAMENTE esos permisos.
     *
     * @param  list<string>  $permisos
     */
    private function con(array $permisos): User
    {
        $rol = Role::query()->where('name', 'staff')->firstOrFail();
        $rol->permissions()->sync(Permission::query()->whereIn('name', $permisos)->pluck('id'));
        $usuario = User::factory()->create();
        $usuario->roles()->sync([$rol->id]);

        return $usuario;
    }
}

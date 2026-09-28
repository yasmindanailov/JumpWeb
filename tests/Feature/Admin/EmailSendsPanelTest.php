<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\EmailSend;
use App\Filament\Resources\EmailSends\EmailSendResource;
use App\Filament\Resources\EmailSends\Pages\ListEmailSends;
use App\Filament\Resources\EmailSends\Tables\EmailSendTable;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **Los correos enviados, en el panel** (`specs/correos-salientes.md` §4.2, `DECISIONES #794`, la C1): la página con su permiso
 * PROPIO, la vista previa TAL CUAL en un `iframe` aislado que deja rastro sin el contenido, y los suyos en la ficha del cliente.
 */
class EmailSendsPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_the_page_needs_its_own_permission_and_staff_does_not_have_it(): void
    {
        $this->actingAs($this->withRole('staff'))->get(EmailSendResource::getUrl('index'))->assertForbidden();
        $this->actingAs($this->withRole('admin'))->get(EmailSendResource::getUrl('index'))->assertOk();
    }

    public function test_the_list_says_who_which_mail_and_whether_it_left(): void
    {
        $cliente = User::factory()->create(['name' => 'Lucía Martín']);
        $this->send($cliente->id, 'order_confirmation', sentAt: now());
        $this->send(null, 'guardian_authorization_signed', sentAt: null, failures: 3, recipient: 'padre@example.test');

        Livewire::actingAs($this->withRole('admin'))->test(ListEmailSends::class)
            ->assertSee('Lucía Martín')
            ->assertSee('Confirmación del pedido')
            ->assertSee('Enviado')
            ->assertSee('padre@example.test')
            ->assertSee('Justificante firmado')
            ->assertSee('No salió (3 intentos)');
    }

    /** La vista previa: el HTML exacto en un `iframe` con `sandbox` VACÍO, y el rastro con el envío y sin el contenido. */
    public function test_the_preview_shows_the_exact_copy_isolated_and_leaves_a_trace_without_content(): void
    {
        $send = $this->send(User::factory()->create()->id, 'order_confirmation', sentAt: now(), html: '<p>Tu pedido <strong>JW-1</strong> & más</p>');

        Livewire::actingAs($this->withRole('admin'))->test(ListEmailSends::class)
            ->mountAction(TestAction::make('preview')->table($send))
            ->assertActionMounted(TestAction::make('preview')->table($send));

        // ⚠️ La prueba de Livewire no pinta el CONTENIDO de un modal de Filament 4 (medido: la acción se monta y el HTML
        // no lo trae); se le pide a la acción de verdad —su `modalContent`— y el navegador lo ve entero en la sonda.
        $html = EmailSendTable::previewAction()->record($send)->getModalContent()?->render() ?? '';
        $this->assertStringContainsString('sandbox=""', $html, 'el iframe va AISLADO: ni scripts ni enlaces que salgan');
        $this->assertStringContainsString('srcdoc="&lt;p&gt;Tu pedido &lt;strong&gt;JW-1&lt;/strong&gt; &amp; más&lt;/p&gt;"', $html, 'la copia, entera y escapada UNA vez para el atributo');

        $trace = AuditLog::query()->where('action', 'emails.previewed')->sole();
        $this->assertSame('email_send', $trace->target_type);
        $this->assertSame((int) $send->id, (int) $trace->target_id);
        $this->assertStringNotContainsString('JW-1', (string) json_encode($trace->payload), 'el rastro no lleva el contenido (RGPD-02)');
    }

    public function test_without_a_copy_there_is_no_preview(): void
    {
        $send = $this->send(User::factory()->create()->id, 'order_confirmation', sentAt: now(), html: null);

        Livewire::actingAs($this->withRole('admin'))->test(ListEmailSends::class)
            ->assertActionHidden(TestAction::make('preview')->table($send));
    }

    /** En la ficha: los suyos y «Ver todos» a la página filtrada; sin el permiso, la sección no está. */
    public function test_the_customer_card_lists_their_mails_only_with_the_permission(): void
    {
        $cliente = $this->withRole('customer');
        $this->send($cliente->id, 'survey_invitation', sentAt: now());

        Livewire::actingAs($this->withRole('admin'))->test(ViewUser::class, ['record' => $cliente->id])
            ->assertSee('Correos')
            ->assertSee('Encuesta')
            ->assertSee(EmailSendResource::urlForUser((int) $cliente->id), false);

        Livewire::actingAs($this->staffWhoSeesCustomers())->test(ViewUser::class, ['record' => $cliente->id])
            ->assertDontSee('Ver todos sus correos');
    }

    public function test_the_customers_list_links_to_the_page_only_with_the_permission(): void
    {
        Livewire::actingAs($this->withRole('admin'))->test(ListUsers::class)
            ->assertSee(EmailSendResource::getUrl('index'), false);

        Livewire::actingAs($this->staffWhoSeesCustomers())->test(ListUsers::class)
            ->assertDontSee(EmailSendResource::getUrl('index'), false);
    }

    // ─── Andamios ────────────────────────────────────────────────────────────────────────────────

    /** Alguien del equipo que ve los clientes pero NO tiene `emails.view` (el control de las secciones). */
    private function staffWhoSeesCustomers(): User
    {
        $user = $this->withRole('staff');
        $user->roles()->first()?->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('name', ['users.view', 'users.manage'])->pluck('id')->all()
        );

        return $user->fresh() ?? $user;
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $role)->value('id')]);

        return $user;
    }

    private function send(?int $userId, string $key, ?\DateTimeInterface $sentAt, int $failures = 0, ?string $recipient = 'cliente@example.test', ?string $html = '<p>Hola</p>'): EmailSend
    {
        $id = DB::table('email_sends')->insertGetId([
            'send_key' => (string) Str::uuid(), 'user_id' => $userId, 'recipient' => $recipient, 'mail_key' => $key,
            'subject' => 'Asunto', 'html' => $html, 'attachments' => '[]', 'failures' => $failures,
            'sent_at' => $sentAt, 'failed_at' => $failures > 0 ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return EmailSend::query()->findOrFail($id);
    }
}

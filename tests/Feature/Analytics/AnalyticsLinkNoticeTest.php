<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Notifications\AnalyticsLinkNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * **El aviso a las cuentas existentes** (`specs/analitica.md` §4.3, T3a·4; arts. 13.3 y 21): `analytics:notify-accounts`
 * escribe UNA vez a cada cuenta de cliente que ya existía —no al equipo, no a quien ya se opuso, no a una fila
 * anónima— y deja la marca ANTES de encolar; el correo lleva el molde de la casa y lleva al área de cliente.
 */
class AnalyticsLinkNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'business.name', 'value' => 'SaltoPark', 'group' => 'business']);
        Setting::flushMemo();
    }

    private function team(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'staff'], ['label' => 'Staff']));

        return $user;
    }

    public function test_the_command_informs_each_existing_customer_account_once(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $already = User::factory()->create(['analytics_notified_at' => now()->subDay()]);
        $opposed = User::factory()->create(['analytics_opt_out' => true]);
        $anonymous = User::factory()->create(['email' => 'deleted_9@'.User::ANONYMIZED_EMAIL_DOMAIN]);
        $staff = $this->team();

        $this->artisan('analytics:notify-accounts')
            ->expectsOutputToContain('Avisadas 2 cuentas')
            ->assertSuccessful();

        Notification::assertSentTo([$customer, $unverified], AnalyticsLinkNotice::class);
        Notification::assertNotSentTo([$already, $opposed, $anonymous, $staff], AnalyticsLinkNotice::class);
        $this->assertNotNull($customer->fresh()->analytics_notified_at, 'la marca queda puesta');
        $this->assertNull($customer->fresh()->analytics_notice_seen_at, 'el aviso del cajón sigue pendiente');

        // La segunda pasada no escribe a nadie: es la idempotencia que permite repetir el runbook.
        $this->artisan('analytics:notify-accounts')->expectsOutputToContain('Avisadas 0 cuentas')->assertSuccessful();
        Notification::assertSentTimes(AnalyticsLinkNotice::class, 2);
    }

    public function test_dry_run_neither_sends_nor_marks(): void
    {
        Notification::fake();
        $customer = User::factory()->create();

        $this->artisan('analytics:notify-accounts', ['--dry-run' => true])
            ->expectsOutputToContain('Avisaría a 1 cuentas')
            ->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($customer->fresh()->analytics_notified_at);
    }

    /**
     * Lo que se manda: cabecera del molde con texto real (no la clave), línea de adelanto, el nombre del negocio
     * en el cuerpo, y el botón al ÍNDICE del área de cliente con la UTM del correo — en los tres idiomas.
     */
    public function test_the_mail_reads_in_the_three_languages_and_leads_to_the_account(): void
    {
        foreach (['es', 'en', 'fr'] as $locale) {
            app()->setLocale($locale);
            $user = User::factory()->create(['locale' => $locale]);

            $mail = (new AnalyticsLinkNotice)->toMail($user);

            $this->assertSame((string) Lang::get('account.analytics_mail.subject', [], $locale), $mail->subject, $locale);
            $this->assertStringStartsNotWith('account.', (string) $mail->viewData['hero']['chapa'], "$locale: la chapa sale como clave");
            $this->assertStringStartsNotWith('account.', (string) $mail->viewData['hero']['titulo'], "$locale: el titular sale como clave");
            $this->assertArrayHasKey('preheader', $mail->viewData, "$locale: sin línea de adelanto");
            $this->assertStringContainsString('/mi-cuenta', (string) $mail->actionUrl, $locale);
            $this->assertStringContainsString('utm_medium=analytics_link_notice', (string) $mail->actionUrl, "$locale: el botón va sin la UTM del correo");

            $html = $mail->render();
            $this->assertStringContainsString('SaltoPark', $html, "$locale: el cuerpo no nombra al negocio");
            $this->assertStringContainsString('class="hero', $html, "$locale: sin cabecera del molde");
            $this->assertStringNotContainsString('account.analytics_mail', $html, "$locale: una clave sin traducir llega al cliente");
        }
    }
}

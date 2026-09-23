<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Notifications\AccountAlreadyExists;
use App\Notifications\CustomerAccountCreated;
use App\Notifications\EmailChangeRequested;
use App\Notifications\GoogleBusinessLocationChanged;
use App\Notifications\PasswordReset;
use App\Notifications\SocialIdentityLinked;
use App\Notifications\VerifyEmailAddress;
use App\Notifications\VerifyEmailForPurchase;
use App\Notifications\VerifyPendingEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **LAS UTM DE LOS CORREOS, `email_sent` y `email_clicked`** (`docs/specs/analitica.md` §4.1 «Los correos», T1c).
 *
 * Tres cosas que no se ven leyendo: que la UTM llegue a CADA enlace de esta casa (botón, logotipo, pie) y a
 * ninguno ajeno; que un enlace FIRMADO con la UTM pegada siga abriendo —y que una clave ajena pegada siga
 * dando 403, porque la firma no se ha relajado, solo ignora la atribución—; y que los dos hechos se cuenten
 * con la misma clave que viaja en la URL.
 *
 * ⚠️ Como `MailMoldTest`, la exhaustividad viene de LEER LAS FUENTES (los 25 `toMail()` pasan `$this`); lo que
 * se renderiza son los correos de cuenta, que solo necesitan un `User`.
 */
class EmailUtmTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_key_of_a_mail_is_the_snake_case_of_its_class(): void
    {
        $this->assertSame('order_confirmation', EmailUtm::keyOf('App\Notifications\OrderConfirmation'));
        $this->assertSame('verify_email_for_purchase', EmailUtm::keyOf(new VerifyEmailForPurchase('R-1')));
        $this->assertTrue(EmailUtm::isCustomerKey('order_confirmation'));
        $this->assertFalse(EmailUtm::isCustomerKey('google_business_location_changed'), 'el aviso al negocio no es audiencia');
        $this->assertFalse(EmailUtm::isCustomerKey('lo_que_sea'), 'una clave que no es de un correo no cuenta');
        $this->assertCount(25, EmailUtm::keys());
    }

    public function test_the_tag_only_touches_our_own_links_and_keeps_the_fragment(): void
    {
        $this->assertSame('http://localhost/entradas?utm_source=email&utm_medium=x', EmailUtm::tag('http://localhost/entradas', 'x'));
        $this->assertSame('http://localhost/a?b=1&utm_source=email&utm_medium=x#gf-invite', EmailUtm::tag('http://localhost/a?b=1#gf-invite', 'x'));
        $this->assertSame('/relativa?utm_source=email&utm_medium=x', EmailUtm::tag('/relativa', 'x'));
        $this->assertSame('https://maps.google.com/?q=parque', EmailUtm::tag('https://maps.google.com/?q=parque', 'x'), 'un enlace ajeno no lleva nuestra UTM');
        $this->assertSame('http://localhost/?utm_source=gbp', EmailUtm::tag('http://localhost/?utm_source=gbp', 'x'), 'una UTM ya puesta no se pisa');
        $this->assertSame('http://localhost/', EmailUtm::tag('http://localhost/', null));
    }

    /** Los 25 `toMail()` le dan `$this` al molde: es de donde sale la clave, y sin ella no hay UTM y nada falla. */
    public function test_every_mail_hands_itself_to_the_mold(): void
    {
        $sin = [];

        foreach (glob(app_path('Notifications/*.php')) ?: [] as $file) {
            $src = (string) file_get_contents($file);
            preg_match_all('/new\s+BrandedMailMessage\(([^)]*)\)/', $src, $m);

            if ($m[1] === [] || array_filter($m[1], static fn (string $arg): bool => trim($arg) !== '$this') !== []) {
                $sin[] = basename($file, '.php');
            }
        }

        $this->assertSame([], $sin, 'estos correos no le dicen al molde quiénes son (`new BrandedMailMessage($this)`): '.implode(', ', $sin));
    }

    /** Ningún acceso por firma valida «a secas»: todos ignoran la MISMA lista de atribución. */
    public function test_no_signature_check_forgets_the_attribution_keys(): void
    {
        $sitios = [
            'app/Http/Concerns/AuthorizesGuestForm.php' => 2,
            'app/Http/Concerns/AuthorizesGuardianAuthorization.php' => 2,
            'app/Http/Controllers/GuardianAuthorizationController.php' => 1,
            'app/Http/Controllers/GuestFormController.php' => 2,
        ];

        foreach ($sitios as $file => $esperados) {
            $src = (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', (string) file_get_contents(base_path($file)));

            $this->assertStringNotContainsString('hasValidSignature()', $src, "$file valida una firma sin ignorar la atribución");
            $this->assertSame($esperados, substr_count($src, 'hasValidSignatureWhileIgnoring(EmailUtm::IGNORED_QUERY)'), "$file: no ignora la lista de `EmailUtm`");
        }

        $bootstrap = (string) file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringContainsString('validateSignatures(except: EmailUtm::IGNORED_QUERY)', $bootstrap, 'el middleware `signed` no ignora la atribución');
    }

    public function test_the_account_mails_carry_the_utm_on_every_link_of_this_house(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $user->forceFill(['pending_email' => 'nuevo@example.com'])->save();

        $correos = [
            new PasswordReset('tok-de-prueba'),
            new VerifyEmailAddress,
            new VerifyEmailForPurchase('R-ABC123'),
            new VerifyPendingEmail,
            new AccountAlreadyExists,
            new CustomerAccountCreated('temporal'),
            new SocialIdentityLinked('google'),
            new EmailChangeRequested('n***@example.com'),
        ];

        foreach ($correos as $notificacion) {
            $key = EmailUtm::keyOf($notificacion);
            $mail = $notificacion->toMail($user);
            $enlaces = $this->enlacesDeCasa((string) $mail->render());

            $this->assertGreaterThanOrEqual(5, count($enlaces), "$key: faltan enlaces (logotipo y cuatro del pie como mínimo)");
            foreach ($enlaces as $href) {
                $this->assertStringContainsString("utm_source=email&utm_medium={$key}", $href, "$key: un enlace de esta casa sin su UTM: $href");
            }
            if ($mail->actionUrl !== null) {
                $this->assertStringContainsString("utm_medium={$key}", (string) $mail->actionUrl, "$key: el botón sin UTM");
                $this->assertContains((string) $mail->actionUrl, $enlaces, "$key: el botón no llega al HTML");
            }
        }
    }

    /** El aviso al NEGOCIO renderiza con el molde y sin una sola UTM: el equipo no es audiencia. */
    public function test_the_notice_to_the_business_carries_no_utm(): void
    {
        $html = (string) (new GoogleBusinessLocationChanged('Ficha A', 'Ficha B', 'Ana'))->toMail(User::factory()->create())->render();

        $this->assertStringNotContainsString('utm_', $html);
        $this->assertNotEmpty($this->enlacesDeCasa($html), 'el pie sigue enlazando a esta casa, solo que sin UTM');
    }

    /**
     * ⚠️⚠️ **La UTM va FUERA de la firma, y esto lo demuestra**: la URL del botón valida ignorando la lista y NO
     * valida a secas. Si alguien la metiera dentro (`temporarySignedRoute(..., ['utm_source' => …])`), la segunda
     * mitad se invertiría — y con ella los accesos por firma, que ignoran esa lista, darían 403.
     */
    public function test_the_utm_travels_outside_the_signature(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $user->forceFill(['pending_email' => 'nuevo@example.com'])->save();

        foreach ([new VerifyEmailAddress, new VerifyPendingEmail] as $notificacion) {
            $url = (string) $notificacion->toMail($user)->actionUrl;

            $this->assertTrue(URL::hasValidSignature(Request::create($url), true, EmailUtm::IGNORED_QUERY), "$url no valida ignorando la atribución");
            $this->assertFalse(URL::hasValidSignature(Request::create($url)), "$url valida a secas: la UTM se ha metido DENTRO de la firma");
        }
    }

    public function test_a_signed_post_form_link_opens_with_the_utm_and_a_foreign_key_still_gets_403(): void
    {
        $item = $this->reservaConPostForm();
        $firmada = $item->guestFormSignedUrl();

        $this->get(EmailUtm::tag($firmada, 'guest_form_request'))->assertOk();
        $this->get($firmada.'&gclid=abc123&fbclid=x')->assertOk();
        $this->get($firmada.'&foo=1')->assertForbidden();
        $this->get(Str::replaceLast('signature=', 'signature=0', EmailUtm::tag($firmada, 'guest_form_request')))->assertForbidden();
    }

    public function test_the_signed_middleware_routes_open_with_the_utm(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $verificacion = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->assertNotSame(403, $this->get(EmailUtm::tag($verificacion, 'verify_email_address'))->status());
        $this->assertTrue($user->fresh()->hasVerifiedEmail(), 'el enlace con UTM verificó de verdad');
        $this->get($verificacion.'&foo=1')->assertForbidden();

        $otro = User::factory()->create();
        // `pending_email_sent_at` es lo que el controlador exige además de la firma (anti-enumeración): sin él, 403.
        $otro->forceFill(['pending_email' => 'cambio@example.com', 'pending_email_sent_at' => now()])->save();
        $confirmacion = (string) (new VerifyPendingEmail)->toMail($otro)->actionUrl;

        $this->assertStringContainsString('utm_medium=verify_pending_email', $confirmacion);
        $this->assertNotSame(403, $this->get($confirmacion)->status());
    }

    public function test_email_sent_is_recorded_with_its_key_and_only_for_customers(): void
    {
        $user = User::factory()->create();

        $user->notify(new CustomerAccountCreated('temporal'));
        NotificationFacade::route('mail', 'equipo@example.com')->notify(new GoogleBusinessLocationChanged('Ficha A', null, 'Ana'));

        $hechos = AnalyticsEvent::query()->where('name', 'email_sent')->get();

        $this->assertCount(1, $hechos, 'el aviso al negocio no se cuenta');
        $this->assertSame(['key' => 'customer_account_created'], $hechos[0]->props);
        $this->assertSame($user->id, $hechos[0]->user_id);
        $this->assertNull($hechos[0]->session_id, 'un envío no ocurre en la petición de nadie');
    }

    public function test_email_clicked_is_recorded_once_per_session_and_only_for_real_mails(): void
    {
        $this->get('/?utm_source=email&utm_medium=order_confirmation')->assertOk();
        $this->get('/?utm_source=email&utm_medium=order_confirmation')->assertOk();
        $this->get('/precios?utm_source=email&utm_medium=guest_form_request')->assertOk();
        $this->get('/?utm_source=email&utm_medium=no_existe')->assertOk();
        $this->get('/?utm_source=google&utm_medium=order_confirmation')->assertOk();
        $this->get('/?utm_source=email&utm_medium=google_business_location_changed')->assertOk();

        $claves = AnalyticsEvent::query()->where('name', 'email_clicked')->orderBy('occurred_at')->get()->map(fn (AnalyticsEvent $e): string => $e->props['key'])->all();

        $this->assertSame(['order_confirmation', 'guest_form_request'], $claves);
    }

    /** @return list<string> los `href` a esta casa, decodificados */
    private function enlacesDeCasa(string $html): array
    {
        preg_match_all('/href="([^"]+)"/', $html, $m);

        return array_values(array_filter(
            array_map(static fn (string $h): string => html_entity_decode($h), $m[1]),
            static fn (string $h): bool => str_starts_with($h, (string) config('app.url')),
        ));
    }

    /** Un pack con `guest_fields` en un pedido PAGADO: la reserva que acepta post-form (molde: `MeAccountContextTest`). */
    private function reservaConPostForm(): OrderItem
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump', 'color' => '#FF5B22', 'position' => 1, 'is_active' => true]);
        $pack = TicketType::create([
            'name' => ['es' => 'Cumple'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id, 'duration_min' => 60,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_fields' => [['key' => 'nombre', 'label' => ['es' => 'Nombre'], 'type' => 'text', 'phase' => 'booking', 'required' => true]],
        ]);
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $slot = Slot::create(['zone_id' => $zone->id, 'date' => now()->addDays(10)->toDateString(), 'start_time' => '10:00:00', 'end_time' => '23:00:00', 'capacity' => 10, 'online_capacity' => 10]);

        return $order->items()->create(['ticket_type_id' => $pack->id, 'slot_id' => $slot->id, 'parent_item_id' => null, 'quantity' => 2, 'seats' => 2, 'unit_price' => 1000]);
    }
}

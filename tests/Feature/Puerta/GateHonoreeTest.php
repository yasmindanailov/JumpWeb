<?php

namespace Tests\Feature\Puerta;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Contracts\GateProfileData;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\GateProfile;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverStatus;
use App\Domain\Platform\Models\Setting;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * F7c de `specs/fiesta-sistema-nuevo.md` §4.13 (`[DECIDIDO owner]` `#752`): **LA PUERTA LEE A QUIEN CUMPLE COMO LA LISTA**.
 *
 * Hasta F7 la puerta trataba su ficha como la de un invitado más: la emparejaba POR NOMBRE con cualquier justificante o
 * respuesta —un «Noa García» invitado la daba por firmada— y no veía a su menor a cargo, porque las asignaciones solo se
 * leían en las entradas. Aquí se afirma lo que lo sustituye: quien cumple va el primero de su fiesta, con `honoree`, su
 * estado sale de lo que lo CUBRE por la atadura (`GuardianPlaces::honoreeCoveragesOf()`), y ni su ficha ni su justificante
 * se emparejan por nombre con nadie.
 */
class GateHonoreeTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    public function test_uncovered_the_honoree_goes_first_with_the_name_of_the_card_and_unresolved(): void
    {
        [$r, $host] = $this->fiesta(6, ['Noa', 'Mateo']);

        $rows = $this->profile($host)->guestMinors;

        $this->assertTrue($rows[0]['honoree'], 'quien cumple, el primero de su fiesta');
        $this->assertSame('Noa', $rows[0]['name']);
        $this->assertSame('unresolved', $rows[0]['entry']);
        $this->assertNull($rows[0]['age'], 'sin prueba no hay fecha de nacimiento');
        $this->assertSame(['Mateo'], array_column(array_filter($rows, static fn (array $g): bool => ! $g['honoree']), 'name'));
        $this->assertSame(['signed' => 0, 'expected' => 6], $this->profile($host)->guestMinorsCount);
    }

    public function test_covered_by_the_dependent_it_reads_the_dependent_and_counts_as_signed(): void
    {
        [$r, $host] = $this->fiesta(6, ['Noa', 'Mateo']);
        $noa = $this->hijo($host, 'Noa');
        $this->assertTrue(app(DependentAssigner::class)->assignHonoree($host, $r->order_id, $r->id, $noa->id)->ok());

        $profile = $this->profile($host);
        $honoree = $profile->guestMinors[0];

        $this->assertTrue($honoree['honoree']);
        $this->assertSame('signed', $honoree['entry'], 'su menor a cargo, con su descargo, lo cubre');
        $this->assertSame(WaiverStatus::MINOR_CURRENT, $honoree['waiver']);
        $this->assertSame(7, $honoree['age'], 'la edad del día, de la fecha de nacimiento de su menor a cargo');
        $this->assertSame(['signed' => 1, 'expected' => 6], $profile->guestMinorsCount);
        $this->assertCount(1, array_filter($profile->guestMinors, static fn (array $g): bool => $g['honoree']), 'una sola vez');
    }

    public function test_covered_by_the_justificante_it_shows_once_and_counts(): void
    {
        [$r, $host] = $this->fiesta(6, ['Noa', 'Mateo']);
        $this->firmar($r, $host, 'Noa María', 'Ruiz Pla', true);

        $profile = $this->profile($host);
        $names = array_column($profile->guestMinors, 'name');

        $this->assertSame('signed', $profile->guestMinors[0]['entry']);
        $this->assertSame('Noa María', $profile->guestMinors[0]['name'], 'manda el nombre de su prueba, como con un invitado');
        // ⚠️ Su justificante ya sale en su fila: no se repite abajo entre las firmas sueltas.
        $this->assertSame(['Noa María', 'Mateo'], $names);
        $this->assertSame(['signed' => 1, 'expected' => 6], $profile->guestMinorsCount);
    }

    public function test_the_justificante_of_a_guest_named_like_the_honoree_does_not_cover_it(): void
    {
        [$r, $host] = $this->fiesta(6, ['Noa', 'Mateo']);
        // El padre de OTRA Noa, invitada, firma su justificante suelto (sin atar a nada).
        $this->firmar($r, $host, 'Noa', 'García', false, 'Pablo García');

        $rows = $this->profile($host)->guestMinors;

        $this->assertTrue($rows[0]['honoree']);
        $this->assertSame('unresolved', $rows[0]['entry'], 'antes, el nombre bastaba para darlo por firmado');
        // CONTROL: la firma sigue saliendo, como la de un invitado que el anfitrión no apuntó.
        $this->assertContains('Noa', array_column(array_filter($rows, static fn (array $g): bool => ! $g['honoree'] && $g['entry'] === 'signed'), 'name'));
    }

    public function test_the_honoree_justificante_does_not_sign_a_guest_with_the_same_name(): void
    {
        [$r, $host] = $this->fiesta(6, ['Noa', 'Noa']);
        $this->firmar($r, $host, 'Noa', 'Ruiz Pla', true);

        $rows = $this->profile($host)->guestMinors;
        $guest = array_values(array_filter($rows, static fn (array $g): bool => ! $g['honoree']));

        $this->assertSame('signed', $rows[0]['entry']);
        $this->assertCount(1, $guest);
        $this->assertSame('unresolved', $guest[0]['entry'], 'la Noa invitada no sale firmada con el justificante de la que cumple');
        $this->assertSame(['signed' => 1, 'expected' => 6], $this->profile($host)->guestMinorsCount);
    }

    public function test_a_reply_named_like_the_honoree_is_a_guest_not_the_honoree(): void
    {
        ['reservation' => $r, 'host' => $host, 'invitation' => $invitation] = $this->fiestaConInvitacion(6, ['Noa']);
        $this->replyOf($invitation, $r, 'Noa Pérez');

        $rows = $this->profile($host)->guestMinors;

        $this->assertCount(2, $rows, 'quien cumple no contesta a su propia invitación: ese «sí» es otra niña');
        $this->assertTrue($rows[0]['honoree']);
        $this->assertSame('Noa', $rows[0]['name']);
        $this->assertSame('Noa Pérez', $rows[1]['name']);
    }

    public function test_without_the_seal_there_is_no_honoree_row_and_nothing_is_asked(): void
    {
        [$r, $host] = $this->fiesta(6, ['Noa', 'Mateo'], sello: false);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $rows = $this->profile($host)->guestMinors;
        $queries = array_map(static fn (array $q): string => (string) $q['query'], DB::getQueryLog());
        DB::disableQueryLog();

        // Sin sello, la ficha 0 es la de un invitado como las demás (las reservas de antes de F3).
        $this->assertSame(['Noa', 'Mateo'], array_column($rows, 'name'));
        $this->assertSame([false, false], array_column($rows, 'honoree'));
        // ⚠️ Un escaneo que no tiene a quien cumple no paga ni una consulta por él.
        $this->assertSame([], array_values(array_filter($queries, static fn (string $q): bool => str_contains($q, 'honoree'))));
    }

    public function test_the_budget_holds_with_the_honoree(): void
    {
        [$r, $host] = $this->fiesta(12, ['Noa', 'Mateo', 'Ana', 'Hugo', 'Leo']);
        $this->assertTrue(app(DependentAssigner::class)->assignHonoree($host, $r->order_id, $r->id, $this->hijo($host, 'Noa')->id)->ok());

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->profile($host);
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(28, $n, "el techo de la puerta es 28 consultas (§7.2·R16): {$n}");
    }

    public function test_outside_the_internal_mode_it_shows_without_a_state(): void
    {
        [$r, $host] = $this->fiesta(6, ['Noa']);

        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => 'externo']);
        Setting::flushMemo();
        $this->assertTrue($this->profile($host)->guestMinors[0]['honoree']);
        $this->assertNull($this->profile($host)->guestMinors[0]['entry']);

        // CONTROL: en el modo interno, sí.
        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_INTERNAL]);
        Setting::flushMemo();
        $this->assertSame('unresolved', $this->profile($host)->guestMinors[0]['entry']);
    }

    public function test_the_door_screen_marks_the_honoree(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        [$r, $host] = $this->fiesta(6, ['Noa', 'Mateo']);
        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);
        RateLimiter::clear("puerta:lookup:user:{$staff->id}");

        $html = Livewire::actingAs($staff)->test(ValidarRegistro::class)->set('input', $host->email)->call('search')->html();

        $this->assertSame(1, substr_count($html, 'data-gate-guest-minor-honoree'), 'solo su fila');
        $this->assertStringContainsString(__('admin.puerta.validar.profile.guest_honoree'), $html);
    }

    // ── Montaje ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * Una fiesta con justificante, con sus fichas escritas, y el reloj en su día (la puerta mira HOY).
     *
     * @param  list<string>  $fichas
     * @return array{0: OrderItem, 1: User}
     */
    private function fiesta(int $quantity, array $fichas, bool $sello = true): array
    {
        ['reservation' => $r, 'host' => $host] = $this->fiestaConInvitacion($quantity, $fichas, $sello);

        return [$r, $host];
    }

    /**
     * @param  list<string>  $fichas
     * @return array<string, mixed>
     */
    private function fiestaConInvitacion(int $quantity, array $fichas, bool $sello = true): array
    {
        $party = $this->mountParty();
        /** @var OrderItem $r */
        $r = $party['reservation'];
        $r->ticketType->forceFill(['guardian_authorization' => TicketType::GUARDIAN_OPTIONAL])->save();
        $r->forceFill([
            'quantity' => $quantity, 'seats' => $quantity, 'honoree_row' => $sello,
            'guest_data' => array_map(static fn (string $n): array => ['name' => $n], $fichas),
        ])->save();
        $this->travelTo(Carbon::parse($r->slot->date->toDateString().' 10:00:00', 'Europe/Madrid'));

        $party['reservation'] = $r->fresh(['ticketType', 'slot', 'order.user']) ?? $r;

        return $party;
    }

    private function profile(User $host): GateProfileData
    {
        return app(GateProfile::class)->for($host, CarbonImmutable::parse(now('Europe/Madrid')->toDateString()), 1);
    }

    private function hijo(User $host, string $nombre): Dependent
    {
        return app(DependentRegistry::class)->add(
            $host, $nombre, now()->subYears(7)->subDay()->toDateString(), 'Pérez', 'mother',
            LegalDocumentVersion::query()->where('slug', WaiverSettings::SLUG)->orderByDesc('id')->firstOrFail(),
            WaiverSignatureRequest::web('127.0.0.1', 'test'),
        );
    }

    private function firmar(OrderItem $r, User $host, string $nombre, string $apellidos, bool $cumple, string $adulto = 'Marta Pérez'): void
    {
        app(GuardianAuthorizationSigner::class)->sign($host, $r->id, LegalDocumentVersion::query()->where('slug', WaiverSettings::SLUG)->orderByDesc('id')->firstOrFail(), [
            'minor_name' => $nombre, 'minor_surname' => $apellidos, 'minor_born_on' => now()->subYears(7)->subDay()->toDateString(),
            'guardian_name' => $adulto, 'guardian_surname' => '', 'guardian_relationship' => 'mother',
            'guardian_email' => null, 'guardian_phone' => '600111222',
        ], WaiverSignatureRequest::web('127.0.0.1', 'test'), null, $cumple);
    }
}

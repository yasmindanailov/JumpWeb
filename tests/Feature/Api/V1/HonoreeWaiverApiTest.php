<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Api\ApiTestCase;
use Tests\Support\MountsAParty;

/**
 * F7c de `specs/fiesta-sistema-nuevo.md` §4.13 (`[DECIDIDO owner]` `#752`, contrato 1.42.0): **EL DESCARGO DE QUIEN CUMPLE
 * POR LA API** — lo que la app necesita para hacer lo mismo que la web: verlo como lo pinta la lista
 * (`GuestForm.honoree_waiver`) y atarle un menor a cargo del titular (`PUT …/honoree-waiver`), solo con su identidad.
 */
class HonoreeWaiverApiTest extends ApiTestCase
{
    use MountsAParty;

    public function test_the_guest_form_says_what_the_list_says(): void
    {
        [$r, $host] = $this->fiesta();

        $waiver = $this->actingAs($host)->getJson(self::ROOT."/reservations/{$r->id}/guest-form")
            ->assertOk()->assertValidResponse(200)->json('honoree_waiver');

        $this->assertSame(['covered' => false, 'signed' => false, 'via' => 'none', 'name' => null, 'dependent_id' => null], array_diff_key($waiver, ['authorization_url' => 0]));
        // La vía sin cuenta: la página web del justificante de quien cumple, firmada.
        $this->assertStringContainsString('para=cumple', (string) $waiver['authorization_url']);
        $this->actingAs($host)->get((string) $waiver['authorization_url'])->assertOk()->assertSee(__('fiesta.autorizacion.titular_cumple', ['n' => 'Noa']), false);
    }

    public function test_without_the_seal_or_the_internal_mode_there_is_nothing(): void
    {
        [$r, $host] = $this->fiesta(sello: false);
        $this->actingAs($host)->getJson(self::ROOT."/reservations/{$r->id}/guest-form")
            ->assertOk()->assertValidResponse(200)->assertJsonPath('honoree_waiver', null);

        [$r2, $host2] = $this->fiesta();
        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => 'externo']);
        Setting::flushMemo();
        $this->actingAs($host2)->getJson(self::ROOT."/reservations/{$r2->id}/guest-form")
            ->assertOk()->assertJsonPath('honoree_waiver', null);
    }

    public function test_the_holder_ties_a_child_and_the_answer_says_it(): void
    {
        [$r, $host] = $this->fiesta();
        $noa = $this->hijo($host, 'Noa');

        $this->actingAs($host)->putJson(self::ROOT."/reservations/{$r->id}/honoree-waiver", ['dependent_id' => $noa->id])
            ->assertOk()->assertValidRequest()->assertValidResponse(200)
            ->assertExactJson(['honoree_waiver' => [
                'covered' => true, 'signed' => true, 'via' => 'dependent', 'name' => 'Noa',
                'dependent_id' => $noa->id, 'authorization_url' => null,
            ]]);

        $this->assertSame([$noa->id], DependentAssignment::query()->where('order_item_id', $r->id)->pluck('dependent_id')->map(fn ($id): int => (int) $id)->all());
        // Y el formulario lo lee igual.
        $this->actingAs($host)->getJson(self::ROOT."/reservations/{$r->id}/guest-form")
            ->assertOk()->assertValidResponse(200)->assertJsonPath('honoree_waiver.via', 'dependent');
    }

    public function test_only_the_holder_ties_and_a_stranger_learns_nothing(): void
    {
        [$r, $host] = $this->fiesta();
        $noa = $this->hijo($host, 'Noa');

        $this->putJson(self::ROOT."/reservations/{$r->id}/honoree-waiver", ['dependent_id' => $noa->id])->assertUnauthorized();

        $ajeno = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($ajeno)->putJson(self::ROOT."/reservations/{$r->id}/honoree-waiver", ['dependent_id' => $noa->id])
            ->assertForbidden()->assertValidResponse(403);
        // Una reserva que no existe responde igual: el 403 no cuenta qué reservas hay.
        $this->actingAs($ajeno)->putJson(self::ROOT.'/reservations/999999/honoree-waiver', ['dependent_id' => $noa->id])->assertForbidden();

        // ⚠️ Ni con una firma VÁLIDA de esta misma ruta (la que abre el formulario sin cuenta): no es la identidad del
        // titular. Firmar la de OTRA ruta no probaba esto —esa firma ya no vale aquí— y el arnés lo cazó.
        $firmada = URL::temporarySignedRoute('api.v1.reservations.honoree-waiver.update', now()->addDay(), [
            'reservation' => $r->id, 'v' => $r->guestFormLinkVersion(),
        ]);
        $this->actingAs($ajeno)->putJson($firmada, ['dependent_id' => $noa->id])->assertForbidden();

        $this->assertSame(0, DependentAssignment::query()->where('order_item_id', $r->id)->count());
    }

    public function test_the_domain_reasons_come_back_by_field(): void
    {
        [$r, $host] = $this->fiesta();

        $otro = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($host)->putJson(self::ROOT."/reservations/{$r->id}/honoree-waiver", ['dependent_id' => $this->hijo($otro, 'Leo')->id])
            ->assertUnprocessable()->assertValidResponse(422)
            ->assertJsonPath('error.fields.dependent_id.0', __('api.dependents.not_yours'));

        // Ya lo cubre el justificante de su padre o madre.
        app(GuardianAuthorizationSigner::class)->sign($host, $r->id, $this->documento(), [
            'minor_name' => 'Noa', 'minor_surname' => 'Ruiz', 'minor_born_on' => now()->subYears(7)->toDateString(),
            'guardian_name' => 'Pablo', 'guardian_surname' => 'Ruiz', 'guardian_relationship' => 'father',
            'guardian_email' => null, 'guardian_phone' => '600111222',
        ], WaiverSignatureRequest::web('127.0.0.1', 'test'), null, true);
        $this->actingAs($host)->putJson(self::ROOT."/reservations/{$r->id}/honoree-waiver", ['dependent_id' => $this->hijo($host, 'Noa')->id])
            ->assertUnprocessable()->assertJsonPath('error.fields.dependent_id.0', __('api.dependents.honoree_covered'));

        [$sinSello, $host2] = $this->fiesta(sello: false);
        $this->actingAs($host2)->putJson(self::ROOT."/reservations/{$sinSello->id}/honoree-waiver", ['dependent_id' => $this->hijo($host2, 'Noa')->id])
            ->assertUnprocessable()->assertJsonPath('error.fields.dependent_id.0', __('api.dependents.not_honoree'));
    }

    public function test_a_party_that_passed_or_a_waiver_outside_is_a_409(): void
    {
        [$r, $host] = $this->fiesta();
        $noa = $this->hijo($host, 'Noa');

        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => 'externo']);
        Setting::flushMemo();
        $this->actingAs($host)->putJson(self::ROOT."/reservations/{$r->id}/honoree-waiver", ['dependent_id' => $noa->id])
            ->assertStatus(409)->assertValidResponse(409)->assertJsonPath('error.code', 'waiver_not_internal');

        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_INTERNAL]);
        Setting::flushMemo();
        $this->travelTo(Carbon::parse($r->slot->date->toDateString().' 23:00:00', 'Europe/Madrid')->addDay());
        $this->actingAs($host)->putJson(self::ROOT."/reservations/{$r->id}/honoree-waiver", ['dependent_id' => $noa->id])
            ->assertStatus(409)->assertJsonPath('error.code', 'guest_form_closed');

        $this->assertSame(0, DependentAssignment::query()->where('order_item_id', $r->id)->count());
    }

    // ── Montaje ─────────────────────────────────────────────────────────────────────────────────────

    /** @return array{0: OrderItem, 1: User} */
    private function fiesta(bool $sello = true): array
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $r->forceFill(['honoree_row' => $sello, 'guest_data' => [['name' => 'Noa'], ['name' => 'Mateo']]])->save();

        return [$r->fresh(['ticketType', 'slot', 'order.user']) ?? $r, $host];
    }

    private function documento(): LegalDocumentVersion
    {
        return LegalDocumentVersion::query()->where('slug', WaiverSettings::SLUG)->orderByDesc('id')->firstOrFail();
    }

    private function hijo(User $host, string $nombre): Dependent
    {
        return app(DependentRegistry::class)->add(
            $host, $nombre, now()->subYears(7)->toDateString(), 'Pérez', 'mother',
            $this->documento(), WaiverSignatureRequest::web('127.0.0.1', 'test'),
        );
    }
}

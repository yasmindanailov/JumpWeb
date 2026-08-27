<?php

namespace Tests\Feature\Waiver;

use App\Domain\Identity\Exceptions\ImmutableRecordException;
use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverChain;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 6 · waiver — conservación con tratamiento restringido (`docs/specs/waiver-probatorio.md`
 * §4.6, `RGPD-01`): **`User::anonymize()` conserva el registro de waiver** y **sigue borrando todo
 * lo demás que ya borraba** — el segundo assert es tan importante como el primero (§6·3)—, y el
 * registro **se purga solo al vencer su plazo**, verificado con el reloj congelado (`SUITE-03`).
 *
 * Es la guarda que cita la restricción de `RGPD-01` (paso 2 de `#156` §8.3).
 */
class WaiverRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function version(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    private function sign(User $holder, ?LegalDocumentVersion $version = null, ?Dependent $dependent = null): WaiverSignature
    {
        $request = new WaiverSignatureRequest(channel: WaiverSignature::CHANNEL_WEB, ip: '10.0.0.7', userAgent: 'test');
        if ($dependent !== null) {
            $request = $request->forDependent($dependent->id);
        }

        return app(WaiverSigner::class)->sign($holder, $version ?? $this->version(), $request);
    }

    private function dependentFor(User $holder, string $bornOn = '2017-03-12'): Dependent
    {
        return Dependent::create(['user_id' => $holder->id, 'name' => 'Lucas', 'born_on' => $bornOn]);
    }

    private function retention(?int $months): void
    {
        Setting::updateOrCreate(['key' => 'waiver.retention_months'], ['value' => $months === null ? '' : (string) $months, 'group' => 'waiver']);
    }

    private function dependentRetention(?int $months): void
    {
        Setting::updateOrCreate(['key' => 'waiver.dependent_retention_months'], ['value' => $months === null ? '' : (string) $months, 'group' => 'waiver']);
    }

    /** §6·3 — los DOS asserts: conserva la prueba Y purga lo demás. */
    public function test_anonymize_keeps_the_waiver_signature_and_still_purges_everything_else(): void
    {
        $holder = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '600111222']);
        $holder->roles()->attach(Role::create(['name' => 'customer', 'label' => 'Cliente']));
        $holder->consents()->create(['type' => 'privacy', 'accepted_at' => now(), 'ip' => '127.0.0.1', 'version' => Consent::CURRENT_VERSION]);
        $signature = $this->sign($holder);
        $this->assertSame(2, $holder->consents()->count(), 'privacy + la fila visible del waiver');

        $this->assertTrue($holder->fresh()->anonymize());

        // (1) La prueba se conserva, VINCULADA y verificable.
        $kept = WaiverSignature::find($signature->id);
        $this->assertNotNull($kept, 'el registro probatorio tiene que sobrevivir al art. 17');
        $this->assertSame($holder->id, $kept->user_id);
        $this->assertTrue($kept->verifyHash());
        $this->assertTrue(WaiverChain::verify($holder->fresh())['ok']);
        // `#161` (owner): la prueba sigue identificando a la persona por la copia que lleva dentro.
        $this->assertSame('Ana', $kept->holder_name);
        $this->assertSame('ana@example.com', $kept->holder_email);
        $this->assertSame('Ana', $kept->holderName(), 'no puede caer a «Cliente eliminado»');

        // (2) Y todo lo demás sigue purgándose exactamente como antes.
        $anonymised = $holder->fresh();
        $this->assertTrue($anonymised->isAnonymized());
        $this->assertSame('Cliente eliminado', $anonymised->name);
        $this->assertNull($anonymised->phone);
        $this->assertNull($anonymised->waiver_accepted_at, 'el sello es presentación, no prueba: se nulifica');
        $this->assertDatabaseMissing('consents', ['user_id' => $holder->id]);
        $this->assertDatabaseMissing('role_user', ['user_id' => $holder->id]);
    }

    public function test_nothing_is_pruned_while_the_retention_period_is_not_set(): void
    {
        $this->travelTo(Carbon::parse('2026-01-15 10:00:00'));
        $signature = $this->sign(User::factory()->create());
        $this->retention(null);

        $this->travelTo(Carbon::parse('2046-01-15 10:00:00'));
        $this->artisan('model:prune', ['--model' => [WaiverSignature::class]])->assertSuccessful();

        $this->assertDatabaseHas('waiver_signatures', ['id' => $signature->id]);
    }

    public function test_signatures_older_than_the_period_are_pruned_and_newer_ones_kept(): void
    {
        $holder = User::factory()->create();
        $version = $this->version();
        $this->retention(24);

        $this->travelTo(Carbon::parse('2026-01-15 10:00:00'));
        $old = $this->sign($holder, $version);

        $this->travelTo(Carbon::parse('2028-03-01 10:00:00'));
        $new = $this->sign($holder, $this->version()); // v2: re-firmar la MISMA versión es idempotente (`#169`)
        $this->assertSame($old->hash, $new->prev_hash);

        $this->artisan('model:prune', ['--model' => [WaiverSignature::class]])->assertSuccessful();

        $this->assertDatabaseMissing('waiver_signatures', ['id' => $old->id]);
        $this->assertDatabaseHas('waiver_signatures', ['id' => $new->id]);
        // La cadena que QUEDA sigue verificando: la poda mueve el inicio, no rompe la prueba.
        $this->assertTrue(WaiverChain::verify($holder)['ok']);
    }

    public function test_the_prune_path_is_the_only_delete_the_guard_allows(): void
    {
        $this->retention(1);
        $this->travelTo(Carbon::parse('2026-01-15 10:00:00'));
        $signature = $this->sign(User::factory()->create());
        $this->travelTo(Carbon::parse('2026-06-15 10:00:00'));

        try {
            $signature->delete();
            $this->fail('un delete() directo tiene que lanzar aunque la firma haya vencido');
        } catch (ImmutableRecordException) {
        }
        $this->assertDatabaseHas('waiver_signatures', ['id' => $signature->id]);

        $this->artisan('model:prune', ['--model' => [WaiverSignature::class]])->assertSuccessful();
        $this->assertDatabaseMissing('waiver_signatures', ['id' => $signature->id]);
    }

    /** El plazo del titular NO alcanza a las firmas de menor: tienen el suyo (`#197`), y sin valor no se podan. */
    public function test_dependent_signatures_are_not_pruned_until_their_own_period_exists(): void
    {
        $this->retention(1);
        $this->dependentRetention(null);
        $this->travelTo(Carbon::parse('2026-01-15 10:00:00'));
        $holder = User::factory()->create();
        $forMinor = $this->sign($holder, null, $this->dependentFor($holder, '2008-08-27'));

        $this->travelTo(Carbon::parse('2040-01-15 10:00:00')); // el menor tiene 31 años: sin plazo, se queda
        $this->artisan('model:prune', ['--model' => [WaiverSignature::class]])->assertSuccessful();

        $this->assertDatabaseHas('waiver_signatures', ['id' => $forMinor->id]);
    }

    /** `[DECIDIDO owner]` `#197`: la firma de un menor se conserva N meses DESPUÉS de su 18.º cumpleaños. */
    public function test_a_dependents_signature_is_pruned_n_months_after_their_18th_birthday(): void
    {
        $this->dependentRetention(12);
        $this->travelTo(Carbon::parse('2026-01-15 10:00:00', 'Europe/Madrid'));
        $holder = User::factory()->create();
        $forMinor = $this->sign($holder, null, $this->dependentFor($holder, '2008-08-27')); // cumple 18 el 2026-08-27
        $own = $this->sign($holder, $this->version()); // v2 (la v1 ya la firmó el menor; el titular firma la vigente)

        // 18 años + 12 meses = 2027-08-27: la víspera se conserva, el día siguiente se poda.
        $this->travelTo(Carbon::parse('2027-08-26 10:00:00', 'Europe/Madrid'));
        $this->artisan('model:prune', ['--model' => [WaiverSignature::class]])->assertSuccessful();
        $this->assertDatabaseHas('waiver_signatures', ['id' => $forMinor->id]);

        $this->travelTo(Carbon::parse('2027-08-28 10:00:00', 'Europe/Madrid'));
        $this->artisan('model:prune', ['--model' => [WaiverSignature::class]])->assertSuccessful();
        $this->assertDatabaseMissing('waiver_signatures', ['id' => $forMinor->id]);
        // La del titular tiene OTRO plazo (sin fijar): se queda.
        $this->assertDatabaseHas('waiver_signatures', ['id' => $own->id]);
        $this->assertTrue(WaiverChain::verify($holder)['ok']);
    }

    /** NUC-3 (`DEUDA.md`), cerrada por `#197`: podar las firmas de un sujeto nunca rompe la cadena del otro. */
    public function test_pruning_one_subject_never_breaks_the_others_chain(): void
    {
        $holder = User::factory()->create();
        $dependent = $this->dependentFor($holder, '2008-08-27');

        // Intercaladas: H1 · D1 · H2 · D2 (dos versiones, porque re-firmar la misma es idempotente).
        $this->travelTo(Carbon::parse('2026-01-15 10:00:00', 'Europe/Madrid'));
        $v1 = $this->version();
        $h1 = $this->sign($holder, $v1);
        $d1 = $this->sign($holder, $v1, $dependent);
        $this->travelTo(Carbon::parse('2026-02-15 10:00:00', 'Europe/Madrid'));
        $v2 = $this->version();
        $h2 = $this->sign($holder, $v2);
        $d2 = $this->sign($holder, $v2, $dependent);
        $this->assertSame(2, WaiverChain::verify($holder)['chains']);

        // (a) Se podan las del TITULAR (24 meses); las del menor no tienen plazo.
        $this->retention(24);
        $this->travelTo(Carbon::parse('2028-06-01 10:00:00', 'Europe/Madrid'));
        $this->artisan('model:prune', ['--model' => [WaiverSignature::class]])->assertSuccessful();
        $this->assertDatabaseMissing('waiver_signatures', ['id' => $h1->id]);
        $this->assertDatabaseMissing('waiver_signatures', ['id' => $h2->id]);
        $this->assertDatabaseHas('waiver_signatures', ['id' => $d1->id]);
        $this->assertDatabaseHas('waiver_signatures', ['id' => $d2->id]);
        $verdict = WaiverChain::verify($holder);
        $this->assertTrue($verdict['ok'], 'con la cadena por titular, D2 apuntaría a H2 y la poda la habría roto: '.implode(' · ', $verdict['problems']));
        $this->assertSame(2, $verdict['count']);
        $this->assertSame(1, $verdict['chains']);

        // (b) Y al revés: se podan las del MENOR (1 mes tras los 18 = 2026-09-27) y las del titular quedan.
        $holder2 = User::factory()->create();
        $dependent2 = $this->dependentFor($holder2, '2008-08-27');
        $this->retention(null);
        $this->dependentRetention(1);
        $this->travelTo(Carbon::parse('2026-03-15 10:00:00', 'Europe/Madrid'));
        $v3 = $this->version();
        $this->sign($holder2, $v3);
        $this->sign($holder2, $v3, $dependent2);
        $this->travelTo(Carbon::parse('2026-04-15 10:00:00', 'Europe/Madrid'));
        $v4 = $this->version();
        $this->sign($holder2, $v4);
        $this->sign($holder2, $v4, $dependent2);
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'Europe/Madrid'));
        $this->artisan('model:prune', ['--model' => [WaiverSignature::class]])->assertSuccessful();
        $this->assertSame(0, WaiverSignature::where('user_id', $holder2->id)->where('subject_type', 'dependent')->count());
        $this->assertSame(2, WaiverSignature::where('user_id', $holder2->id)->where('subject_type', 'holder')->count());
        $this->assertTrue(WaiverChain::verify($holder2)['ok']);
    }

    public function test_an_invalid_retention_value_means_no_pruning(): void
    {
        foreach (['0', '-3', 'abc', '601'] as $bad) {
            Setting::updateOrCreate(['key' => 'waiver.retention_months'], ['value' => $bad, 'group' => 'waiver']);
            $this->assertNull(WaiverSettings::retentionMonths(), "«{$bad}» no puede activar la poda");
        }
    }

    public function test_the_prune_is_wired_in_the_scheduler(): void
    {
        $events = collect(app(Schedule::class)->events());

        $prune = $events->first(fn ($event): bool => str_contains((string) $event->command, 'model:prune')
            && str_contains((string) $event->command, 'WaiverSignature'));

        $this->assertNotNull($prune, 'la poda del waiver tiene que estar programada (routes/console.php)');
    }
}

<?php

namespace Tests\Feature\Dependents;

use App\Domain\Identity\Contracts\DependentRemoval;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\AccountPrivacy;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 6 · menores a cargo — lo que el RGPD exige de una tabla con datos de MENORES
 * (`docs/specs/menores-a-cargo.md` §4.4, §5): en `User::anonymize()` cada persona a cargo sigue el
 * régimen de su waiver (`RGPD-01` ampliada), el export del art. 20 lleva las activas (`RGPD-04`),
 * la fila desvinculada que se queda sin firma se poda sola, y la limpieza de go-live la borra
 * ANTES que a su titular (`user_id` RESTRICT).
 */
class DependentPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-27 12:00:00', 'Europe/Madrid'));
    }

    private function add(User $holder, string $name, string $bornOn): Dependent
    {
        return app(DependentRegistry::class)->add($holder, $name, $bornOn);
    }

    private function signFor(User $holder, Dependent $dependent): WaiverSignature
    {
        $version = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();

        return app(WaiverSigner::class)->sign($holder, $version, new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB,
            ip: '10.0.0.7',
            userAgent: 'test',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT,
            subjectId: (int) $dependent->getKey(),
        ));
    }

    /** §5 (`RGPD-01` ampliada) — el dependiente sigue el régimen de su waiver. */
    public function test_anonymize_deletes_unreferenced_dependents_and_unlinks_the_signed_ones(): void
    {
        $holder = User::factory()->create();
        $plain = $this->add($holder, 'Sin firma', '2017-03-12');
        $signed = $this->add($holder, 'Con firma', '2016-05-05');
        $signature = $this->signFor($holder, $signed);

        $this->assertTrue($holder->fresh()->anonymize());

        // Sin firma: PII de un menor sin nada que la justifique → se borra de verdad.
        $this->assertDatabaseMissing('dependents', ['id' => $plain->id]);

        // Con firma: se CONSERVA vinculada y desvinculada de toda superficie, como la propia firma.
        $kept = Dependent::find($signed->id);
        $this->assertNotNull($kept, 'la fila con firma detrás sobrevive al art. 17 bajo el régimen restringido');
        $this->assertTrue($kept->isRemoved());
        $this->assertSame('Con firma', $kept->name, 'desvincular no anonimiza: el registro tiene que seguir identificando al sujeto');
        $this->assertSame($holder->id, $kept->user_id);
        $this->assertTrue($signature->fresh()->verifyHash());

        $anonymised = $holder->fresh();
        $this->assertTrue($anonymised->isAnonymized());
        $this->assertSame(0, $anonymised->dependents()->active()->count());
        $this->assertFalse($anonymised->anonymize(), 'sigue siendo idempotente');
        $this->assertDatabaseHas('dependents', ['id' => $signed->id]);
    }

    /** `RGPD-04` / spec §5 — el documento de portabilidad lleva las activas, y solo las activas. */
    public function test_the_export_document_carries_the_active_dependents_only(): void
    {
        $holder = User::factory()->create();
        $active = $this->add($holder, 'Lucas', '2017-03-12');
        $unlinked = $this->add($holder, 'Vera', '2019-11-02');
        $this->signFor($holder, $unlinked);
        $this->assertSame(DependentRemoval::Unlinked, app(DependentRegistry::class)->remove($holder, $unlinked->id));

        $export = app(AccountPrivacy::class)->exportFor($holder->fresh());

        $this->assertSame([[
            'name' => 'Lucas',
            'born_on' => '2017-03-12',
            'added_at' => $active->created_at->toIso8601String(),
        ]], $export['dependents']);
        $this->assertStringNotContainsString('Vera', json_encode($export), 'la retirada con firma vive bajo el régimen restringido, fuera del art. 20');
    }

    /** §4.4 + §5 — la fila DESVINCULADA que se queda sin firma se poda; la que aún la tiene y la activa, no. */
    public function test_orphan_unlinked_dependents_are_pruned_and_the_rest_are_kept(): void
    {
        $holder = User::factory()->create();
        $registry = app(DependentRegistry::class);

        $orphan = $this->add($holder, 'Huérfana', '2017-03-12');
        $this->signFor($holder, $orphan);
        $registry->remove($holder, $orphan->id);

        $kept = $this->add($holder, 'Con firma', '2016-05-05');
        $this->signFor($holder, $kept);
        $registry->remove($holder, $kept->id);

        $active = $this->add($holder, 'Activa', '2018-01-01');

        // La poda por plazo se lleva la firma de la primera (por debajo del modelo, como `Prunable`).
        DB::table('waiver_signatures')
            ->where('subject_type', WaiverSignature::SUBJECT_DEPENDENT)
            ->where('subject_id', $orphan->id)
            ->delete();

        $this->artisan('model:prune', ['--model' => [Dependent::class]])->assertSuccessful();

        $this->assertDatabaseMissing('dependents', ['id' => $orphan->id]);
        $this->assertDatabaseHas('dependents', ['id' => $kept->id]);
        $this->assertDatabaseHas('dependents', ['id' => $active->id, 'removed_at' => null]);
    }

    public function test_the_prune_is_wired_in_the_scheduler_after_the_signatures(): void
    {
        $events = collect(app(Schedule::class)->events());

        $prune = $events->first(fn ($event): bool => str_contains((string) $event->command, 'model:prune')
            && str_contains((string) $event->command, 'Dependent'));

        $this->assertNotNull($prune, 'la poda de personas a cargo tiene que estar programada (routes/console.php)');
        $command = (string) $prune->command;
        $this->assertLessThan(
            strpos($command, 'Dependent'),
            strpos($command, 'WaiverSignature'),
            'las firmas se podan ANTES que las filas que referencian: en la misma pasada, una firma vencida deja huérfana a su fila',
        );
    }

    /** La limpieza de go-live borra las personas a cargo ANTES que los usuarios (`user_id` RESTRICT). */
    public function test_the_go_live_purge_removes_the_dependents_of_purged_holders(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $admin = User::factory()->create(['email' => 'admin-keep@x.test']);
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);
        $customer = User::factory()->create(['email' => 'cliente@x.test']);
        $dependent = $this->add($customer, 'Lucas', '2017-03-12');
        $this->signFor($customer, $dependent);

        $this->artisan('app:purge-customers', ['--keep' => ['admin-keep@x.test'], '--force' => true])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'cliente@x.test']);
        $this->assertDatabaseMissing('dependents', ['id' => $dependent->id]);
        $this->assertDatabaseMissing('waiver_signatures', ['subject_id' => $dependent->id]);
        $this->assertDatabaseHas('users', ['email' => 'admin-keep@x.test']);
    }
}

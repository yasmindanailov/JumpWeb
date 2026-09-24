<?php

namespace Tests\Feature\Waiver;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Identity\Services\WaiverStatus;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\DeclaresDependents;
use Tests\TestCase;

/**
 * Fase 6 · menores a cargo, tanda 5 (el PANEL, spec §9.10.1·7) — `WaiverStatus::forDependents()`: la
 * misma respuesta que `forDependent()` para varios menores, en un número CONSTANTE de consultas.
 *
 * La paridad se prueba menor a menor porque es la única forma de que la versión por lotes no se
 * separe de la unitaria sin que nadie lo vea: la ficha del pedido usa una y el PDF la otra.
 */
class WaiverStatusBatchTest extends TestCase
{
    use DeclaresDependents;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-08-27 12:00:00', 'Europe/Madrid'));
    }

    private function mode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
        Setting::flushMemo();
    }

    private function publish(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    private function sign(User $holder, Dependent $dependent, LegalDocumentVersion $version): WaiverSignature
    {
        return app(WaiverSigner::class)->sign($holder, $version, new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB,
            ip: '10.0.0.7',
            userAgent: 'test',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT,
            subjectId: (int) $dependent->getKey(),
        ));
    }

    private function add(User $holder, string $name): Dependent
    {
        // ⚠️ `#441` · el sujeto de este fichero es un menor SIN firma —asevera
        // `[false,true,false,true,false,true]`—, y desde que declarar exige aceptar, ese estado solo
        // nace donde no había nada que aceptar. `DeclaresDependents` reproduce la ficha heredada
        // pasando por el escritor de verdad, no saltándoselo.
        return $this->declareLegacyDependent($holder, $name, '2017-03-12');
    }

    public function test_for_dependents_matches_for_dependent_one_by_one_in_internal_mode(): void
    {
        $this->mode('interno');
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $lucas = $this->add($holder, 'Lucas');
        $vera = $this->add($holder, 'Vera');
        $max = $this->add($holder, 'Max');
        $foreign = $this->add($other, 'Ajeno');

        $v1 = $this->publish();
        $this->sign($holder, $lucas, $v1);           // firmará en v1 y quedará «anterior»
        $v2 = $this->publish();
        $this->sign($holder, $vera, $v2);            // vigente
        $this->sign($other, $foreign, $v2);          // de otro titular, también vigente
        // Max no firma.

        $batch = WaiverStatus::forDependents([$lucas, $vera, $max, $foreign]);

        $this->assertSame([$lucas->id, $vera->id, $max->id, $foreign->id], array_keys($batch));
        foreach ([$lucas, $vera, $max, $foreign] as $dependent) {
            $this->assertEquals(WaiverStatus::forDependent($dependent), $batch[$dependent->id], "paridad para {$dependent->name}");
        }
        $this->assertTrue($batch[$lucas->id]->signed);
        $this->assertTrue($batch[$lucas->id]->isOutdated());
        $this->assertTrue($batch[$vera->id]->signed);
        $this->assertFalse($batch[$vera->id]->isOutdated());
        $this->assertFalse($batch[$max->id]->signed);
        $this->assertTrue($batch[$foreign->id]->signed);
    }

    /** Presupuesto: las firmas, sus versiones y la versión vigente — TRES consultas sean 1 o 20 los menores. */
    public function test_for_dependents_runs_a_constant_number_of_queries(): void
    {
        $this->mode('interno');
        $holder = User::factory()->create();
        $version = $this->publish();
        $dependents = [];
        foreach (range(1, 6) as $i) {
            $dependents[] = $d = $this->add($holder, "Menor {$i}");
            if ($i % 2 === 0) {
                $this->sign($holder, $d, $version);
            }
        }
        WaiverSettings::mode(); // calienta la memo del ajuste: no es parte del presupuesto

        DB::enableQueryLog();
        DB::flushQueryLog();
        $batch = WaiverStatus::forDependents($dependents);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(6, $batch);
        $this->assertSame(3, $queries, 'firmas + versiones (eager) + versión vigente');
        $this->assertSame([false, true, false, true, false, true], array_map(fn (Dependent $d): bool => $batch[$d->id]->signed, $dependents));
    }

    public function test_for_dependents_outside_internal_mode_answers_unsigned_without_looking_at_signatures(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create();
        $lucas = $this->add($holder, 'Lucas');
        WaiverSettings::mode();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $batch = WaiverStatus::forDependents([$lucas]);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queries);
        $this->assertFalse($batch[$lucas->id]->signed);
        $this->assertSame('externo', $batch[$lucas->id]->mode);
        $this->assertEquals(WaiverStatus::forDependent($lucas), $batch[$lucas->id]);
        $this->assertSame([], WaiverStatus::forDependents([]));
    }

    /**
     * 24-09 · **una firma cuya VERSIÓN ya no está no tumba la ficha.** La FK es RESTRICT y la versión es
     * inmutable, pero la BD local trajo firmas huérfanas (retocadas por debajo) y la ficha del cliente caía
     * con «Attempt to read property "version" on null». Sin texto contra el que probarla, la firma cuenta
     * como de una versión ANTERIOR: señalada, sin bloquear, y la re-firma se pide en la siguiente compra
     * (§4.8). Vale para el titular (`for()`) y para el lote de menores (`build()` es común).
     *
     * ⚠️ El instrumento: dentro de la transacción de `RefreshDatabase`, `foreign_keys = OFF` es un NO-OP en
     * SQLite (`InvitationPrivacyTest` lo midió); lo que sí vale es DIFERIR la comprobación al commit, que aquí
     * no llega. Se comprueba que la fila quedó huérfana de verdad antes de preguntar. Quita la guarda de
     * `build()` y este caso rompe por la propia excepción: se discrimina solo.
     */
    public function test_a_signature_whose_version_is_gone_answers_outdated_instead_of_crashing(): void
    {
        $this->mode('interno');
        $holder = User::factory()->create();
        $lucas = $this->add($holder, 'Lucas');
        $version = $this->publish();

        $holderSignature = app(WaiverSigner::class)->sign($holder, $version, new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB,
            ip: '10.0.0.7',
            userAgent: 'test',
        ));
        $minorSignature = $this->sign($holder, $lucas, $version);
        $ids = [(int) $holderSignature->getKey(), (int) $minorSignature->getKey()];

        DB::getDriverName() === 'sqlite'
            ? DB::statement('PRAGMA defer_foreign_keys = ON')
            : Schema::disableForeignKeyConstraints();
        DB::table('waiver_signatures')->whereIn('id', $ids)->update(['legal_document_version_id' => 999999]);

        $this->assertNull(LegalDocumentVersion::query()->find(999999));
        $this->assertSame([999999, 999999], DB::table('waiver_signatures')->whereIn('id', $ids)->pluck('legal_document_version_id')->map(fn ($v): int => (int) $v)->all(),
            'El instrumento no huérfano las firmas: sin eso el caso no mide nada.');

        $status = WaiverStatus::for($holder);
        $this->assertTrue($status->signed);
        $this->assertNull($status->version);
        $this->assertNull($status->locale);
        $this->assertFalse($status->isCurrent);
        $this->assertTrue($status->isOutdated());
        $this->assertSame((int) $holderSignature->getKey(), $status->signatureId);

        $batch = WaiverStatus::forDependents([$lucas]);
        $this->assertSame(WaiverStatus::MINOR_OUTDATED, $batch[$lucas->id]->minorState());
        $this->assertEquals(WaiverStatus::forDependent($lucas), $batch[$lucas->id]);
    }
}

<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Contracts\AuthorizableReservations;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Contracts\HonoreeCoverage;
use App\Domain\Identity\Exceptions\GuardianAuthorizationRefusedException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\GuardianPlaces;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * F7a de `specs/fiesta-sistema-nuevo.md` §4.13 (`[DECIDIDO owner]` `#752`): **LA EXENCIÓN DE QUIEN CUMPLE, en el dominio**.
 *
 * Lo medido antes (§4.8, con transacciones deshechas en `JW-OJO-F5`): su justificante se RECHAZABA con la fiesta llena y
 * gastaba DOS plazas con sitio, y la lista lo daba por firmado emparejando su nombre con un hijo del titular. Aquí se
 * afirma lo que lo sustituye: quien cumple queda CUBIERTO por UNA prueba atada por id —su menor a cargo asignado a la
 * línea del pack, o un justificante con `honoree`—, una sola vez, y su plaza cuenta una sola vez.
 */
class ExencionDeQuienCumpleTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    public function test_the_honoree_seat_counts_once_whoever_covers_it(): void
    {
        [$r, $host, $doc] = $this->fiesta(6);
        $this->assertSame(1, $this->places()->takenIn($r->id), 'su plaza, sin cubrir');

        $this->firmar($r, $host, $doc, 'Lucía', 'Madre', true);
        $this->assertSame(1, $this->places()->takenIn($r->id), 'su justificante OCUPA su plaza: no suma otra (antes, 2)');

        // CONTROL: un justificante de un invitado sí suma.
        $this->firmar($r, $host, $doc, 'Mateo', 'Gil', false, 'Ana Gil');
        $this->assertSame(2, $this->places()->takenIn($r->id));

        // Y con su menor a cargo, igual: una plaza.
        [$r2, $host2] = $this->fiesta(6);
        $hijo = $this->hijo($host2, 'Lucía');
        $this->assertTrue(app(DependentAssigner::class)->assignHonoree($host2, $r2->order_id, $r2->id, $hijo->id)->ok());
        $this->assertSame(1, $this->places()->takenIn($r2->id), 'su asignación OCUPA su plaza: no suma otra');
    }

    public function test_with_the_party_full_the_honoree_can_still_be_signed(): void
    {
        [$r, $host, $doc] = $this->fiesta(1);
        $this->assertSame(0, $this->places()->freeIn(app(AuthorizableReservations::class)->find($r->id)), 'solo quien cumple: llena');

        // CONTROL: un invitado no cabe.
        try {
            $this->firmar($r, $host, $doc, 'Mateo', 'Gil', false, 'Ana Gil');
            $this->fail('un justificante suelto entró en una fiesta llena');
        } catch (GuardianAuthorizationRefusedException $e) {
            $this->assertSame(GuardianAuthorizationRefusedException::REASON_FULL, $e->reason);
            $this->assertStringNotContainsString('justificantes, que es toda su capacidad', $e->getMessage(), 'el mensaje de antes mentía');
        }

        $result = $this->firmar($r, $host, $doc, 'Lucía', 'Madre', true);
        $this->assertTrue($result['created']);
        $this->assertTrue($result['authorization']->fresh()?->honoree);
    }

    public function test_the_honoree_is_covered_once_by_either_proof(): void
    {
        [$r, $host, $doc] = $this->fiesta(6);
        $this->firmar($r, $host, $doc, 'Lucía', 'Madre', true);

        // El otro progenitor, con otro nombre escrito: no tapa la prueba que ya hay.
        $this->assertRefused(GuardianAuthorizationRefusedException::REASON_HONOREE_COVERED, fn () => $this->firmar($r, $host, $doc, 'Lucia', 'Padre Otro', true, 'Pedro Padre'));
        // Ni su menor a cargo.
        $hijo = $this->hijo($host, 'Lucía');
        $out = app(DependentAssigner::class)->assignHonoree($host, $r->order_id, $r->id, $hijo->id);
        $this->assertSame(['' => DependentAssigner::REASON_HONOREE_COVERED], $out->rejections);

        // Y al revés: asignado su menor a cargo, su justificante no entra.
        [$r2, $host2, $doc2] = $this->fiesta(6);
        $this->assertTrue(app(DependentAssigner::class)->assignHonoree($host2, $r2->order_id, $r2->id, $this->hijo($host2, 'Lucía')->id)->ok());
        $this->assertRefused(GuardianAuthorizationRefusedException::REASON_HONOREE_COVERED, fn () => $this->firmar($r2, $host2, $doc2, 'Lucía', 'Madre', true));
    }

    public function test_a_reservation_without_the_seal_ties_nothing(): void
    {
        [$r, $host, $doc] = $this->fiesta(6, false);

        $this->assertNull($this->places()->honoreeCoverage($r->id));
        $this->assertRefused(GuardianAuthorizationRefusedException::REASON_NOT_HONOREE, fn () => $this->firmar($r, $host, $doc, 'Lucía', 'Madre', true));
        $out = app(DependentAssigner::class)->assignHonoree($host, $r->order_id, $r->id, $this->hijo($host, 'Lucía')->id);
        $this->assertSame(['' => DependentAssigner::REASON_NOT_HONOREE], $out->rejections);
        $this->assertSame(0, $this->places()->takenIn($r->id), 'sin sello, ninguna plaza de quien cumple');
    }

    public function test_assigning_the_honoree_keeps_the_rules_of_a_dependent(): void
    {
        [$r, $host] = $this->fiesta(6);
        $assigner = app(DependentAssigner::class);

        // Ajeno.
        $ajeno = $this->hijo(User::factory()->create(['email_verified_at' => now()]), 'Otro');
        $this->assertSame(['0' => DependentAssigner::REASON_NOT_YOURS], $assigner->assignHonoree($host, $r->order_id, $r->id, $ajeno->id)->rejections);
        // Sin su exención firmada.
        $sinFirma = Dependent::query()->create(['user_id' => $host->id, 'name' => 'Lucía', 'born_on' => now()->subYears(7)->toDateString()]);
        $this->assertSame(['0' => DependentAssigner::REASON_WAIVER_UNSIGNED], $assigner->assignHonoree($host, $r->order_id, $r->id, $sinFirma->id)->rejections);

        $lucia = $this->hijo($host, 'Lucía');
        $this->assertSame(1, $assigner->assignHonoree($host, $r->order_id, $r->id, $lucia->id)->added);
        $this->assertSame(1, $assigner->assignHonoree($host, $r->order_id, $r->id, $lucia->id)->kept, 'el mismo, idempotente');

        // Otro hijo: SUSTITUYE (el titular eligió mal), no se suma.
        $mateo = $this->hijo($host, 'Mateo');
        $out = $assigner->assignHonoree($host, $r->order_id, $r->id, $mateo->id);
        $this->assertSame([1, 1], [$out->added, $out->removed]);
        $this->assertSame($mateo->id, $this->places()->honoreeCoverage($r->id)?->dependentId);
        $this->assertSame(1, DB::table('dependent_assignments')->where('order_item_id', $r->id)->count());

        // Un pack sigue sin admitir menores por el camino de las entradas (el mostrador).
        $this->assertSame(['' => DependentAssigner::REASON_ENTRIES_ONLY], $assigner->sync($host, $r->order_id, $r->id, [$lucia->id])->rejections);
    }

    public function test_the_coverage_reads_the_tie_and_never_the_name(): void
    {
        [$r, $host, $doc] = $this->fiesta(6);
        // CONTROL contra la regla de F3b: un hijo del titular llamado como quien cumple NO lo cubre.
        $lucia = $this->hijo($host, 'Lucía');
        $cov = $this->places()->honoreeCoverage($r->id);
        $this->assertSame(HonoreeCoverage::NONE, $cov?->via);
        $this->assertFalse($cov->signed());

        app(DependentAssigner::class)->assignHonoree($host, $r->order_id, $r->id, $lucia->id);
        $cov = $this->places()->honoreeCoverage($r->id);
        $this->assertSame([HonoreeCoverage::DEPENDENT, 'Lucía', WaiverStatus::MINOR_CURRENT], [$cov?->via, $cov?->name, $cov?->waiver]);
        $this->assertTrue($cov->signed());

        // Un texto nuevo del descargo: sigue cubierto (la plaza es suya), pero ya no «firmada».
        $this->publicar();
        $cov = $this->places()->honoreeCoverage($r->id);
        $this->assertSame(WaiverStatus::MINOR_OUTDATED, $cov?->waiver);
        $this->assertTrue($cov->covered());
        $this->assertFalse($cov->signed());
        $this->assertSame(1, $this->places()->takenIn($r->id));

        // Por el justificante, en otra fiesta: el nombre que dice SU prueba.
        [$r2, $host2, $doc2] = $this->fiesta(6);
        $this->firmar($r2, $host2, $doc2, 'Lucía', 'Pérez', true);
        $cov = $this->places()->honoreeCoverage($r2->id);
        $this->assertSame([HonoreeCoverage::AUTHORIZATION, 'Lucía', WaiverStatus::MINOR_CURRENT], [$cov?->via, $cov?->name, $cov?->waiver]);
    }

    public function test_a_loose_authorization_of_the_same_child_is_tied_once(): void
    {
        [$r, $host, $doc] = $this->fiesta(6);
        // Su madre firmó por el enlace de siempre (suelto) y luego llega por el de quien cumple.
        $suelta = $this->firmar($r, $host, $doc, 'Lucía', 'Pérez', false);
        $this->assertNull($suelta['authorization']->honoree);
        $this->assertSame(2, $this->places()->takenIn($r->id), 'CONTROL: suelta, gasta su plaza y la de quien cumple sigue aparte');

        $atada = $this->firmar($r, $host, $doc, 'Lucía', 'Pérez', true);
        $this->assertFalse($atada['created'], 'la misma autorización, no otra');
        $this->assertSame($suelta['authorization']->id, $atada['authorization']->id);
        $this->assertTrue($atada['authorization']->fresh()?->honoree);
        $this->assertSame(1, $this->places()->takenIn($r->id), 'atada, una plaza');
        $this->assertSame(1, GuardianAuthorization::query()->where('order_item_id', $r->id)->count());
    }

    public function test_the_database_backs_one_honoree_authorization_per_reservation(): void
    {
        [$r] = $this->fiesta(6);
        $fila = static fn (string $key, ?bool $honoree): array => [
            'order_item_id' => $r->id, 'honoree' => $honoree, 'minor_name' => 'X', 'minor_surname' => $key, 'minor_key' => $key,
            'minor_born_on' => now()->subYears(7)->toDateString(), 'guardian_name' => 'G', 'guardian_surname' => '',
            'guardian_relationship' => 'mother', 'created_at' => now(),
        ];
        // CONTROL: dos NULL (dos invitados) conviven, y una de quien cumple también.
        DB::table('guardian_authorizations')->insert($fila('a', null));
        DB::table('guardian_authorizations')->insert($fila('b', null));
        DB::table('guardian_authorizations')->insert($fila('c', true));

        // ⚠️ La excepción de la CLAVE ÚNICA y no cualquier `QueryException`: con una columna olvidada, el primer insert
        // fallaba por otra cosa y este caso pasaba en verde sin medir nada (visto al escribirlo).
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('guardian_authorizations')->insert($fila('d', true));
    }

    // ── Montaje ─────────────────────────────────────────────────────────────────────────────────────

    /** @return array{0: OrderItem, 1: User, 2: LegalDocumentVersion} */
    private function fiesta(int $quantity, bool $sello = true): array
    {
        ['reservation' => $r, 'host' => $host, 'document' => $doc] = $this->mountParty();
        $r->forceFill(['quantity' => $quantity, 'seats' => $quantity, 'honoree_row' => $sello])->save();

        return [$r->fresh() ?? $r, $host, $doc];
    }

    private function places(): GuardianPlaces
    {
        return app(GuardianPlaces::class);
    }

    /** Un hijo a cargo del titular, con su exención firmada al darlo de alta (el titular tiene el correo verificado). */
    private function hijo(User $host, string $nombre): Dependent
    {
        return app(DependentRegistry::class)->add(
            $host, $nombre, now()->subYears(7)->toDateString(), 'Pérez', 'mother',
            LegalDocumentVersion::query()->where('slug', WaiverSettings::SLUG)->orderByDesc('id')->firstOrFail(),
            WaiverSignatureRequest::web('127.0.0.1', 'test'),
        );
    }

    /** @return array{authorization: GuardianAuthorization, signature: mixed, created: bool} */
    private function firmar(OrderItem $r, User $host, LegalDocumentVersion $doc, string $nombre, string $apellidos, bool $cumple, string $adulto = 'Marta Pérez'): array
    {
        return app(GuardianAuthorizationSigner::class)->sign($host, $r->id, LegalDocumentVersion::query()->where('slug', WaiverSettings::SLUG)->orderByDesc('id')->firstOrFail(), [
            'minor_name' => $nombre, 'minor_surname' => $apellidos, 'minor_born_on' => now()->subYears(7)->toDateString(),
            'guardian_name' => $adulto, 'guardian_surname' => '', 'guardian_relationship' => 'mother',
            'guardian_email' => null, 'guardian_phone' => '600111222',
        ], WaiverSignatureRequest::web('127.0.0.1', 'test'), null, $cumple);
    }

    /** Un texto nuevo del descargo: las firmas de antes quedan «anteriores». */
    private function publicar(): void
    {
        app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención de responsabilidad', 'body' => [['h' => 'Riesgo asumido', 'p' => 'Texto nuevo, v2.']]],
        ]);
    }

    private function assertRefused(string $reason, callable $accion): void
    {
        try {
            $accion();
            $this->fail("se esperaba el rechazo «{$reason}»");
        } catch (GuardianAuthorizationRefusedException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }
}

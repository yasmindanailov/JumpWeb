<?php

namespace Tests\Feature\Waiver;

use App\Domain\Identity\Exceptions\ImmutableRecordException;
use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Exceptions\WaiverEmailUnverifiedException;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverChain;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/**
 * Fase 6 · waiver — el registro de firma (`docs/specs/waiver-probatorio.md` §4.3, §4.7, §8.4, §8.5):
 * append-only, con hash canónico y `prev_hash` encadenado POR TITULAR. Verificado por MUTACIÓN:
 * alterar o borrar lanza; alterar por debajo del modelo rompe la verificación.
 *
 * ⚠️ La serialización canónica está FIJADA aquí como texto literal (`test_the_canonical_…`): si
 * alguien cambia un campo, su orden o su formato, este test cae — y tiene que caer, porque lo ya
 * firmado dejaría de verificar.
 */
class WaiverSignatureChainTest extends TestCase
{
    use RefreshDatabase;

    private function version(string $slug = 'waiver'): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish($slug, [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    private function sign(User $holder, ?LegalDocumentVersion $version = null, ?WaiverSignatureRequest $request = null): WaiverSignature
    {
        return app(WaiverSigner::class)->sign(
            $holder,
            $version ?? $this->version(),
            $request ?? WaiverSignatureRequest::web('10.0.0.7', 'Mozilla/5.0 (test)'),
        );
    }

    public function test_signing_writes_the_probatory_row_the_visible_consent_and_the_stamp(): void
    {
        $holder = User::factory()->create(['waiver_accepted_at' => null]);
        $version = $this->version();

        $signature = $this->sign($holder, $version);

        $this->assertSame($holder->id, $signature->user_id);
        $this->assertSame(WaiverSignature::SUBJECT_HOLDER, $signature->subject_type);
        $this->assertSame($version->id, $signature->legal_document_version_id);
        $this->assertSame($version->body_hash, $signature->document_hash);
        $this->assertSame('web', $signature->channel);
        $this->assertSame('10.0.0.7', $signature->ip);
        $this->assertSame('Mozilla/5.0 (test)', $signature->user_agent);
        $this->assertSame('Europe/Madrid', $signature->accepted_tz);
        $this->assertNull($signature->prev_hash, 'la primera firma del titular no tiene anterior');
        $this->assertNull($signature->declared_by_user_id);
        // `#161` (owner): la identidad del firmante viaja EN la firma, tal y como está al firmar.
        $this->assertSame($holder->name, $signature->holder_name);
        $this->assertSame($holder->email, $signature->holder_email);
        $this->assertSame(2, $signature->fresh()->canonical_version);
        $this->assertTrue($signature->fresh()->verifyHash());

        // Lo que el titular VE (art. 7.1) y el sello heredado: presentación, no prueba.
        $this->assertDatabaseHas('consents', ['user_id' => $holder->id, 'type' => 'waiver', 'version' => 'v1·es', 'ip' => '10.0.0.7']);
        $this->assertNotNull($holder->fresh()->waiver_accepted_at);

        $log = AuditLog::where('action', 'waiver.signed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($signature->id, $log->payload['signature_id']);
        $this->assertSame('web', $log->payload['channel']);
        $this->assertArrayNotHasKey('ip', $log->payload);
    }

    public function test_the_chain_links_each_signature_to_the_previous_one_of_the_same_holder(): void
    {
        $ana = User::factory()->create();
        $bea = User::factory()->create();
        $v1 = $this->version();
        $first = $this->sign($ana, $v1);
        // La cadena crece con VERSIONES nuevas: re-firmar la misma es idempotente (`#169`) — y la v1 se firma
        // ANTES de publicarse la v2, porque una versión superada ya no se firma (S-3, `#181`).
        $v2 = $this->version();
        $second = $this->sign($ana, $v2);
        $other = $this->sign($bea, $v2);

        $this->assertSame($first->hash, $second->prev_hash);
        $this->assertNull($other->prev_hash, 'la cadena es POR TITULAR: la de Bea empieza de cero');
        $this->assertNotSame($first->hash, $second->hash);

        $this->assertTrue(WaiverChain::verify($ana)['ok']);
        $this->assertSame(2, WaiverChain::verify($ana)['count']);
        $this->assertTrue(WaiverChain::verify($bea)['ok']);
    }

    /** §4.3 — append-only, por mutación: ni update ni delete. */
    public function test_a_signature_cannot_be_updated_or_deleted(): void
    {
        $signature = $this->sign(User::factory()->create());

        try {
            $signature->update(['ip' => '1.1.1.1']);
            $this->fail('update() sobre una firma tiene que lanzar');
        } catch (ImmutableRecordException) {
        }

        try {
            $signature->delete();
            $this->fail('delete() sobre una firma tiene que lanzar');
        } catch (ImmutableRecordException) {
        }

        $this->assertDatabaseHas('waiver_signatures', ['id' => $signature->id, 'ip' => '10.0.0.7']);
    }

    public function test_tampering_under_the_model_breaks_the_verification(): void
    {
        $holder = User::factory()->create();
        $signature = $this->sign($holder);
        $this->assertTrue($signature->fresh()->verifyHash());

        DB::table('waiver_signatures')->where('id', $signature->id)->update(['ip' => '9.9.9.9']);

        $this->assertFalse($signature->fresh()->verifyHash());
        $verdict = WaiverChain::verify($holder);
        $this->assertFalse($verdict['ok']);
        $this->assertStringContainsString('hash no coincide', $verdict['problems'][0]);
    }

    public function test_a_forged_link_breaks_the_chain(): void
    {
        $holder = User::factory()->create();
        $this->sign($holder, $this->version());
        $second = $this->sign($holder, $this->version()); // v2: el segundo eslabón

        DB::table('waiver_signatures')->where('id', $second->id)->update(['prev_hash' => str_repeat('0', 64)]);

        $verdict = WaiverChain::verify($holder);
        $this->assertFalse($verdict['ok']);
        $this->assertStringContainsString('no enlaza', implode(' ', $verdict['problems']));
    }

    public function test_the_signer_refuses_a_version_of_another_document(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sign(User::factory()->create(), $this->version('condiciones'));
    }

    public function test_an_anonymised_account_cannot_sign(): void
    {
        $holder = User::factory()->create();
        $holder->anonymize();

        $this->expectException(LogicException::class);
        $this->sign($holder->fresh());
    }

    /** §8.4 — el alta presencial produce una firma DECLARADA por el operador, y el registro lo dice. */
    public function test_a_declared_signature_records_the_operator_and_is_audited_apart(): void
    {
        $holder = User::factory()->create();
        $operator = User::factory()->create();

        $signature = $this->sign($holder, null, WaiverSignatureRequest::declaredAtCounter($operator, '192.168.1.20'));

        $this->assertSame($operator->id, $signature->declared_by_user_id);
        $this->assertSame('panel', $signature->channel);
        $this->assertTrue($signature->isDeclaredByOperator());
        $this->assertTrue($signature->fresh()->verifyHash());
        $this->assertDatabaseHas('audit_logs', ['action' => 'waiver.declared']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'waiver.signed']);
    }

    /** §4.3 — el sujeto puede ser un menor a cargo: cuelga del titular, pero no le pone el sello a él. */
    public function test_a_dependent_signature_hangs_from_the_holder_without_stamping_him(): void
    {
        $holder = User::factory()->create(['waiver_accepted_at' => null]);

        $signature = $this->sign($holder, null, new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_API,
            ip: '10.0.0.1',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT,
            subjectId: 42,
        ));

        $this->assertSame('dependent', $signature->subject_type);
        $this->assertSame(42, $signature->subject_id);
        $this->assertFalse($signature->isForHolder());
        $this->assertNull($holder->fresh()->waiver_accepted_at);
        $this->assertSame(0, $holder->consents()->count());
        $this->assertTrue(WaiverChain::verify($holder)['ok']);
    }

    public function test_a_dependent_signature_needs_the_dependent_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sign(User::factory()->create(), null, new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_API,
            subjectType: WaiverSignature::SUBJECT_DEPENDENT,
        ));
    }

    /**
     * La serialización canónica, FIJADA como literal POR VERSIÓN (spec §4.7: «definir qué campos
     * entran en el hash y en qué orden, desde el primer commit»). Una fila leída de BD (strings) y
     * una recién construida (ints, Carbon) tienen que dar el mismo texto. Y una fila v1 sigue
     * verificando con SU esquema aunque el vigente sea v2: subir la versión no invalida lo firmado.
     */
    public function test_the_canonical_serialisation_is_fixed_per_version(): void
    {
        $v1 = [
            'user_id' => '7',
            'subject_type' => 'holder',
            'subject_id' => null,
            'legal_document_version_id' => '3',
            'document_hash' => str_repeat('a', 64),
            'accepted_at' => '2026-08-25 18:30:00',
            'accepted_tz' => 'Europe/Madrid',
            'ip' => '10.0.0.1',
            'user_agent' => 'UA/1',
            'channel' => 'web',
            'declared_by_user_id' => null,
            'prev_hash' => null,
            'canonical_version' => '1',
            'hash' => 'no entra en el hash',
            'id' => 99,
        ];
        $common = '"user_id":7,"subject_type":"holder","subject_id":null,"legal_document_version_id":3,'
            .'"document_hash":"'.str_repeat('a', 64).'","accepted_at":"2026-08-25T18:30:00Z","accepted_tz":"Europe/Madrid",'
            .'"ip":"10.0.0.1","user_agent":"UA/1","channel":"web","declared_by_user_id":null,"prev_hash":null';

        $expectedV1 = '{"v":1,'.$common.'}';
        $this->assertSame($expectedV1, WaiverSignature::canonical($v1));
        $this->assertSame(hash('sha256', $expectedV1), WaiverSignature::computeHash($v1));

        $v2 = $v1;
        $v2['canonical_version'] = 2;
        $v2['holder_name'] = 'Ana Pérez';
        $v2['holder_email'] = 'ana@example.com';
        $expectedV2 = '{"v":2,'.$common.',"holder_name":"Ana Pérez","holder_email":"ana@example.com"}';
        $this->assertSame($expectedV2, WaiverSignature::canonical($v2));
        $this->assertSame(hash('sha256', $expectedV2), WaiverSignature::computeHash($v2));

        // Sin `canonical_version` se usa la vigente (v2); v1 y v2 no pueden dar el mismo texto.
        unset($v2['canonical_version']);
        $this->assertSame($expectedV2, WaiverSignature::canonical($v2));
        $this->assertSame(2, WaiverSignature::CANONICAL_VERSION);

        $asBuilt = $v1;
        $asBuilt['user_id'] = 7;
        $asBuilt['legal_document_version_id'] = 3;
        $asBuilt['canonical_version'] = 1;
        $asBuilt['accepted_at'] = now()->setDateTime(2026, 8, 25, 18, 30, 0);
        $this->assertSame($expectedV1, WaiverSignature::canonical($asBuilt));

        $this->expectException(InvalidArgumentException::class);
        WaiverSignature::canonical(['canonical_version' => 99]);
    }

    /**
     * `#169` §10.2·4 — idempotencia por VERSIÓN: firmar otra vez la versión vigente (en cualquier
     * idioma: el texto publicado es el mismo) devuelve la fila que hay y no escribe nada; una versión
     * NUEVA sí crea el siguiente eslabón.
     */
    public function test_signing_the_same_version_again_returns_the_existing_row_and_writes_nothing(): void
    {
        $holder = User::factory()->create();
        $rows = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
            'en' => ['title' => 'Waiver', 'body' => [['h' => 'Risk', 'p' => 'Jumping is risky.']]],
        ]);
        $es = $rows->firstWhere('locale', 'es');
        $en = $rows->firstWhere('locale', 'en');

        $first = $this->sign($holder, $es);
        $again = $this->sign($holder, $es);
        $otherLocale = $this->sign($holder, $en);

        $this->assertSame($first->getKey(), $again->getKey());
        $this->assertSame($first->getKey(), $otherLocale->getKey(), 'otro idioma de la MISMA versión es el mismo texto');
        $this->assertSame(1, WaiverSignature::where('user_id', $holder->id)->count());
        $this->assertSame(1, $holder->consents()->where('type', 'waiver')->count());
        $this->assertSame(1, AuditLog::where('action', 'waiver.signed')->count());

        $v2 = $this->version();
        $next = $this->sign($holder, $v2);

        $this->assertNotSame($first->getKey(), $next->getKey());
        $this->assertSame($first->hash, $next->prev_hash, 'la versión nueva sí encadena');
        $this->assertSame(2, WaiverSignature::where('user_id', $holder->id)->count());
    }

    /** La idempotencia es POR SUJETO: la firma del titular y la de un menor a su cargo no se confunden. */
    public function test_idempotency_is_per_subject(): void
    {
        $holder = User::factory()->create();
        $version = $this->version();

        $own = $this->sign($holder, $version);
        $forDependent = $this->sign($holder, $version, new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB, ip: '10.0.0.7', userAgent: 'test',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT, subjectId: 17,
        ));
        $forDependentAgain = $this->sign($holder, $version, new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB, ip: '10.0.0.7', userAgent: 'test',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT, subjectId: 17,
        ));

        $this->assertNotSame($own->getKey(), $forDependent->getKey());
        $this->assertSame($forDependent->getKey(), $forDependentAgain->getKey());
        $this->assertSame(2, WaiverSignature::where('user_id', $holder->id)->count());
    }

    /** `#179` (spec §7·5): el titular firma solo con el correo verificado; la firma DECLARADA en mostrador queda fuera. */
    public function test_an_unverified_holder_cannot_sign_unless_the_signature_is_declared(): void
    {
        $holder = User::factory()->create(['email_verified_at' => null]);
        $version = $this->version();

        try {
            $this->sign($holder, $version);
            $this->fail('sin correo verificado no se firma');
        } catch (WaiverEmailUnverifiedException) {
        }
        $this->assertSame(0, WaiverSignature::where('user_id', $holder->id)->count());

        $operator = User::factory()->create();
        $declared = $this->sign($holder, $version, WaiverSignatureRequest::declaredAtCounter($operator, '10.0.0.7'));

        $this->assertSame($operator->id, $declared->declared_by_user_id, 'el mostrador declara: la identidad la asegura el operador');
    }

    /** §10.6 (NUC-8, `#180`): la cadena también cruza cada firma con su VERSIÓN — una versión alterada por debajo rompe la cadena. */
    public function test_a_version_tampered_under_the_model_breaks_the_chain(): void
    {
        $holder = User::factory()->create();
        $version = $this->version();
        $this->sign($holder, $version);
        $this->assertTrue(WaiverChain::verify($holder)['ok']);

        DB::table('legal_document_versions')->where('id', $version->id)->update([
            'body' => json_encode([['h' => 'Riesgo', 'p' => 'Texto alterado por debajo.']]),
        ]);

        $verdict = WaiverChain::verify($holder);
        $this->assertFalse($verdict['ok']);
        $this->assertStringContainsString('no coincide con su versión', implode(' ', $verdict['problems']));
    }

    /** S-3 (`#181`): la vigencia se re-comprueba DENTRO del lock — una versión superada no se firma aunque pasara la puerta. */
    public function test_signing_a_version_that_is_no_longer_current_is_refused_inside_the_lock(): void
    {
        $holder = User::factory()->create();
        $v1 = $this->version();
        $this->version(); // v2: la v1 deja de ser la vigente

        $this->expectException(WaiverDocumentStaleException::class);
        $this->sign($holder, $v1);
    }
}

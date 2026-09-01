<?php

namespace Tests\Feature\Waiver;

use App\Domain\Identity\Exceptions\DependentNotFoundException;
use App\Domain\Identity\Exceptions\DependentNotMinorException;
use App\Domain\Identity\Exceptions\ImmutableRecordException;
use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Exceptions\WaiverEmailUnverifiedException;
use App\Domain\Identity\Models\Dependent;
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
 * append-only, con hash canónico y `prev_hash` encadenado POR (TITULAR, SUJETO) desde `DECISIONES
 * #197`: el titular tiene su cadena y cada menor a su cargo la suya. Verificado por MUTACIÓN:
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

    private function dependentFor(User $holder, string $name = 'Lucas', string $bornOn = '2017-03-12'): Dependent
    {
        return Dependent::create(['user_id' => $holder->id, 'name' => $name, 'born_on' => $bornOn]);
    }

    private function forDependent(Dependent $dependent): WaiverSignatureRequest
    {
        return WaiverSignatureRequest::web('10.0.0.7', 'Mozilla/5.0 (test)')->forDependent($dependent->id);
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
        // El literal es deliberado: subir la versión canónica tiene que traer a alguien AQUÍ a
        // confirmarlo, porque de eso depende que lo ya firmado siga verificando.
        $this->assertSame(4, $signature->fresh()->canonical_version);
        $this->assertNull($signature->subject_name, 'en la firma del titular la identidad del sujeto va a null (v3)');
        $this->assertNull($signature->subject_authorization_id, 'y el sujeto del justificante tampoco (v4)');
        $this->assertNull($signature->signer_name, 'el titular firma por sí mismo: no hay firmante aparte');
        $this->assertNull($signature->subject_born_on);
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

    /**
     * §4.3 — el sujeto puede ser un menor a cargo: cuelga del titular, pero no le pone el sello a él, y
     * su identidad de ese momento viaja EN la firma (`menores-a-cargo.md` §4.2; esquema v3, `#197`).
     */
    public function test_a_dependent_signature_hangs_from_the_holder_without_stamping_him(): void
    {
        $holder = User::factory()->create(['waiver_accepted_at' => null]);
        $dependent = $this->dependentFor($holder);

        $signature = $this->sign($holder, null, $this->forDependent($dependent));

        $this->assertSame('dependent', $signature->subject_type);
        $this->assertSame($dependent->id, $signature->subject_id);
        $this->assertFalse($signature->isForHolder());
        $this->assertSame('Lucas', $signature->subject_name);
        $this->assertSame('2017-03-12', $signature->fresh()->subject_born_on->toDateString());
        $this->assertSame('Lucas', $signature->subjectName());
        $this->assertSame(4, $signature->fresh()->canonical_version);
        $this->assertNull($signature->subject_authorization_id, 'un menor A CARGO no cuelga de una autorización');
        $this->assertTrue($signature->fresh()->verifyHash());
        $this->assertNull($holder->fresh()->waiver_accepted_at);
        $this->assertSame(0, $holder->consents()->count());
        $this->assertTrue(WaiverChain::verify($holder)['ok']);
    }

    /** `#197` — UNA cadena por sujeto: la del menor empieza de cero aunque el titular ya haya firmado. */
    public function test_each_subject_has_its_own_chain(): void
    {
        $holder = User::factory()->create();
        $dependent = $this->dependentFor($holder);
        $v1 = $this->version();
        $h1 = $this->sign($holder, $v1);
        $d1 = $this->sign($holder, $v1, $this->forDependent($dependent));
        $v2 = $this->version();
        $h2 = $this->sign($holder, $v2);
        $d2 = $this->sign($holder, $v2, $this->forDependent($dependent));

        $this->assertNull($d1->prev_hash, 'la cadena del menor no cuelga de la del titular');
        $this->assertSame($h1->hash, $h2->prev_hash);
        $this->assertSame($d1->hash, $d2->prev_hash);
        $this->assertNotSame($h2->hash, $d2->prev_hash);

        $verdict = WaiverChain::verify($holder);
        $this->assertTrue($verdict['ok']);
        $this->assertSame(4, $verdict['count']);
        $this->assertSame(2, $verdict['chains']);
    }

    /** `menores-a-cargo.md` §4.9 aplicado a la firma: el `subject_id` llega del cliente y se decide bajo el lock. */
    public function test_signing_for_a_foreign_removed_or_adult_dependent_is_refused(): void
    {
        $ana = User::factory()->create();
        $bea = User::factory()->create();
        $version = $this->version();

        $ofBea = $this->dependentFor($bea);
        try {
            $this->sign($ana, $version, $this->forDependent($ofBea));
            $this->fail('un menor de otra cuenta no existe para Ana');
        } catch (DependentNotFoundException) {
        }

        $removed = $this->dependentFor($ana);
        $removed->unlink();
        try {
            $this->sign($ana, $version, $this->forDependent($removed));
            $this->fail('un menor retirado no existe');
        } catch (DependentNotFoundException) {
        }

        $adult = $this->dependentFor($ana, 'Mayor', '2000-01-01');
        try {
            $this->sign($ana, $version, $this->forDependent($adult));
            $this->fail('a los 18 el waiver del adulto ya no le cubre');
        } catch (DependentNotMinorException) {
        }

        $this->assertSame(0, WaiverSignature::count());
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

        // v3 (`#197`): + la identidad del SUJETO; en una firma del titular los dos campos van a null.
        $v3 = $v2;
        $v3['canonical_version'] = 3;
        $v3['subject_name'] = null;
        $v3['subject_born_on'] = null;
        $expectedV3 = '{"v":3,'.$common.',"holder_name":"Ana Pérez","holder_email":"ana@example.com","subject_name":null,"subject_born_on":null}';
        $this->assertSame($expectedV3, WaiverSignature::canonical($v3));

        // Y en nombre de un menor: la fecha SIN hora, venga como venga («Y-m-d 00:00:00» de SQLite).
        $forMinor = $v3;
        $forMinor['subject_type'] = 'dependent';
        $forMinor['subject_id'] = '17';
        $forMinor['subject_name'] = 'Lucas';
        $forMinor['subject_born_on'] = '2017-03-12 00:00:00';
        $commonMinor = str_replace('"subject_type":"holder","subject_id":null', '"subject_type":"dependent","subject_id":17', $common);
        $expectedMinor = '{"v":3,'.$commonMinor.',"holder_name":"Ana Pérez","holder_email":"ana@example.com","subject_name":"Lucas","subject_born_on":"2017-03-12"}';
        $this->assertSame($expectedMinor, WaiverSignature::canonical($forMinor));
        $forMinor['subject_born_on'] = now()->setDate(2017, 3, 12);
        $this->assertSame($expectedMinor, WaiverSignature::canonical($forMinor));

        // v4 (`specs/waiver-por-reserva.md` §4.3): + el sujeto MENOR INVITADO
        // (`subject_authorization_id`, que va justo detrás de `subject_id`) y la identidad de QUIEN
        // FIRMA, que en un justificante no es el titular de la cuenta.
        $commonV4 = str_replace(
            '"subject_id":null,"legal_document_version_id":3',
            '"subject_id":null,"subject_authorization_id":null,"legal_document_version_id":3',
            $common,
        );
        $v4 = $v3;
        $v4['canonical_version'] = 4;
        $v4['subject_authorization_id'] = null;
        $v4['signer_name'] = null;
        $v4['signer_email'] = null;
        $v4['signer_phone'] = null;
        $v4['signer_relationship'] = null;
        $expectedV4 = '{"v":4,'.$commonV4.',"holder_name":"Ana Pérez","holder_email":"ana@example.com",'
            .'"subject_name":null,"subject_born_on":null,"signer_name":null,"signer_email":null,'
            .'"signer_phone":null,"signer_relationship":null}';
        $this->assertSame($expectedV4, WaiverSignature::canonical($v4));
        $this->assertSame(hash('sha256', $expectedV4), WaiverSignature::computeHash($v4));

        // Y un justificante REAL: el sujeto no tiene `subject_id` —lo tiene la autorización— y quien
        // firma no es el titular. Los ids llegan como cadena desde la BD y salen como entero.
        $guest = $v4;
        $guest['subject_type'] = 'guest_minor';
        $guest['subject_authorization_id'] = '42';
        $guest['subject_name'] = 'Ana Gómez Ruiz';
        $guest['subject_born_on'] = '2018-05-04';
        $guest['signer_name'] = 'Marta Ruiz Díaz';
        $guest['signer_email'] = 'marta@example.com';
        $guest['signer_phone'] = '600111222';
        $guest['signer_relationship'] = 'mother';
        $commonGuest = str_replace(
            '"subject_type":"holder","subject_id":null,"subject_authorization_id":null',
            '"subject_type":"guest_minor","subject_id":null,"subject_authorization_id":42',
            $commonV4,
        );
        $expectedGuest = '{"v":4,'.$commonGuest.',"holder_name":"Ana Pérez","holder_email":"ana@example.com",'
            .'"subject_name":"Ana Gómez Ruiz","subject_born_on":"2018-05-04","signer_name":"Marta Ruiz Díaz",'
            .'"signer_email":"marta@example.com","signer_phone":"600111222","signer_relationship":"mother"}';
        $this->assertSame($expectedGuest, WaiverSignature::canonical($guest));

        // Sin `canonical_version` se usa la vigente (v4); ninguna versión puede dar el mismo texto que
        // otra — es lo que garantiza que subirla NO invalide lo ya firmado.
        $withoutVersion = $v4;
        unset($withoutVersion['canonical_version']);
        $this->assertSame($expectedV4, WaiverSignature::canonical($withoutVersion));
        $this->assertSame(4, WaiverSignature::CANONICAL_VERSION);
        $this->assertSame(
            4,
            collect([$expectedV1, $expectedV2, $expectedV3, $expectedV4])->unique()->count(),
            'dos versiones canónicas que serialicen igual harían indistinguibles dos pruebas distintas',
        );

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
        $dependent = $this->dependentFor($holder);

        $own = $this->sign($holder, $version);
        $forDependent = $this->sign($holder, $version, $this->forDependent($dependent));
        $forDependentAgain = $this->sign($holder, $version, $this->forDependent($dependent));

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

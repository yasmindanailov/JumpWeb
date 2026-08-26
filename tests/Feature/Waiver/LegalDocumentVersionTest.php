<?php

namespace Tests\Feature\Waiver;

use App\Domain\Identity\Exceptions\DraftCannotBePublishedException;
use App\Domain\Identity\Exceptions\ImmutableRecordException;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Fase 6 · waiver — la primera guarda del subsistema (`docs/specs/waiver-probatorio.md` §6·1):
 * **una versión publicada no se puede modificar ni borrar**, y publicar crea una fila por idioma con
 * su hash. Verificado por MUTACIÓN: intentarlo lanza y la fila queda intacta.
 *
 * Y el bloqueante de la revisión (§8.1) como mecanismo: un texto con marcador de borrador —el que
 * hoy sirve el seeder, en los tres idiomas— NO se publica.
 */
class LegalDocumentVersionTest extends TestCase
{
    use RefreshDatabase;

    private const TEXTS = [
        'es' => ['title' => 'Exención de responsabilidad', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos. Los aceptas.']]],
        'en' => ['title' => 'Waiver', 'body' => [['h' => 'Risk', 'p' => 'Jumping involves risks. You accept them.']]],
        'fr' => ['title' => 'Décharge', 'body' => [['h' => 'Risque', 'p' => 'Sauter comporte des risques. Vous les acceptez.']]],
    ];

    public function test_publishing_creates_one_immutable_row_per_locale_with_its_hash(): void
    {
        $admin = User::factory()->create();

        $rows = app(LegalDocumentPublisher::class)->publish('waiver', self::TEXTS, $admin);

        $this->assertCount(3, $rows);
        $this->assertSame(['en', 'es', 'fr'], $rows->pluck('locale')->sort()->values()->all());
        foreach ($rows as $row) {
            $this->assertSame(1, $row->version);
            $this->assertSame('waiver', $row->slug);
            $this->assertTrue($row->verifyHash(), "el hash de {$row->locale} no verifica");
            $this->assertSame($admin->id, $row->published_by);
            $this->assertNotNull($row->published_at);
            $this->assertNotNull($row->created_at);
        }
        $this->assertSame('v1·es', $rows->firstWhere('locale', 'es')->label());
        // Sin `updated_at`: la fila no tiene ni columna con la que cambiar.
        $this->assertFalse(in_array('updated_at', array_keys($rows->first()->getAttributes()), true));
    }

    public function test_a_second_publication_increments_the_version_and_keeps_the_previous_one(): void
    {
        $publisher = app(LegalDocumentPublisher::class);
        $publisher->publish('waiver', self::TEXTS);

        $texts = self::TEXTS;
        $texts['es']['body'][0]['p'] = 'Texto revisado.';
        $second = $publisher->publish('waiver', $texts);

        $this->assertSame(2, $second->first()->version);
        $this->assertSame(2, LegalDocuments::latestVersionNumber('waiver'));
        $this->assertSame(6, LegalDocumentVersion::count(), 'la v1 tiene que seguir ahí: publicar CREA, no sustituye');
        $this->assertSame('Texto revisado.', LegalDocuments::current('waiver', 'es')->sections()[0]['p']);
        $this->assertSame('Saltar implica riesgos. Los aceptas.', LegalDocumentVersion::where('version', 1)->where('locale', 'es')->first()->sections()[0]['p']);
    }

    public function test_the_version_counter_is_per_document(): void
    {
        $publisher = app(LegalDocumentPublisher::class);
        $publisher->publish('waiver', self::TEXTS);
        $publisher->publish('waiver', self::TEXTS);
        $terms = $publisher->publish('condiciones', ['es' => self::TEXTS['es']]);

        $this->assertSame(1, $terms->first()->version);
        $this->assertSame(2, LegalDocuments::latestVersionNumber('waiver'));
    }

    /** §6·1 — mutación: editar una versión publicada lanza y no cambia nada. */
    public function test_a_published_version_cannot_be_updated(): void
    {
        $row = app(LegalDocumentPublisher::class)->publish('waiver', self::TEXTS)->firstWhere('locale', 'es');

        try {
            $row->update(['title' => 'Otro título']);
            $this->fail('update() sobre una versión publicada tiene que lanzar');
        } catch (ImmutableRecordException $e) {
            $this->assertStringContainsString('inmutable', $e->getMessage());
        }

        try {
            $row->title = 'Otro título';
            $row->save();
            $this->fail('save() sobre una versión publicada tiene que lanzar');
        } catch (ImmutableRecordException) {
            // esperado
        }

        $this->assertSame('Exención de responsabilidad', $row->fresh()->title);
        $this->assertTrue($row->fresh()->verifyHash());
    }

    /** §6·1 — mutación: borrar una versión publicada lanza y la fila sigue. */
    public function test_a_published_version_cannot_be_deleted(): void
    {
        $row = app(LegalDocumentPublisher::class)->publish('waiver', self::TEXTS)->firstWhere('locale', 'es');

        $this->expectException(ImmutableRecordException::class);
        try {
            $row->delete();
        } finally {
            $this->assertDatabaseHas('legal_document_versions', ['id' => $row->id]);
        }
    }

    /**
     * La guarda es de MODELO y `DB::table()` la salta —como toda guarda de este repo—. Por eso la
     * fila lleva su hash: la alteración por debajo no pasa desapercibida, se DETECTA.
     */
    public function test_tampering_under_the_model_is_detected_by_the_hash(): void
    {
        $row = app(LegalDocumentPublisher::class)->publish('waiver', self::TEXTS)->firstWhere('locale', 'es');
        $this->assertTrue($row->verifyHash());

        DB::table('legal_document_versions')->where('id', $row->id)->update(['title' => 'Título alterado por debajo']);

        $this->assertFalse($row->fresh()->verifyHash());
    }

    public function test_the_current_version_falls_back_to_spanish_when_the_locale_was_not_published(): void
    {
        app(LegalDocumentPublisher::class)->publish('waiver', ['es' => self::TEXTS['es']]);

        $this->assertSame('es', LegalDocuments::current('waiver', 'fr')->locale);
        $this->assertSame('es', LegalDocuments::current('waiver', 'en')->locale);
        $this->assertNull(LegalDocuments::current('condiciones', 'es'));
    }

    public function test_empty_locales_are_skipped_and_an_all_empty_publication_is_refused(): void
    {
        $texts = self::TEXTS;
        $texts['fr']['body'] = [['h' => '  ', 'p' => '']];
        $rows = app(LegalDocumentPublisher::class)->publish('waiver', $texts);
        $this->assertSame(['en', 'es'], $rows->pluck('locale')->sort()->values()->all());

        $this->expectException(InvalidArgumentException::class);
        app(LegalDocumentPublisher::class)->publish('waiver', ['es' => ['title' => 'Vacío', 'body' => []]]);
    }

    /**
     * §8.1 como mecanismo: el texto que sirve HOY `LandingContentSeeder` dice de sí mismo que es un
     * borrador. Publicarlo grabaría un borrador en la cadena para siempre → se rechaza, en cualquier
     * caja, y no queda ninguna fila.
     */
    public function test_a_text_with_a_draft_marker_is_refused(): void
    {
        $texts = self::TEXTS;
        $texts['en']['body'][] = ['h' => 'Acceptance', 'p' => 'Este texto es un borrador y será revisado por un asesor legal antes de su publicación. [PENDIENTE: redacción definitiva].'];

        try {
            app(LegalDocumentPublisher::class)->publish('waiver', $texts);
            $this->fail('un borrador no se puede publicar');
        } catch (DraftCannotBePublishedException $e) {
            $this->assertStringContainsString('en', $e->getMessage());
        }
        $this->assertSame(0, LegalDocumentVersion::count(), 'no puede quedar NINGÚN idioma publicado a medias');

        // También el `[pendiente]` neutro que deja `LegalIdentity::interpolate` sin datos fiscales.
        $texts = self::TEXTS;
        $texts['es']['title'] = 'Exención de [pendiente]';
        $this->expectException(DraftCannotBePublishedException::class);
        app(LegalDocumentPublisher::class)->publish('waiver', $texts);
    }

    public function test_publication_is_audited_without_the_text(): void
    {
        app(LegalDocumentPublisher::class)->publish('waiver', self::TEXTS);

        $log = AuditLog::where('action', 'legal.version_published')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('waiver', $log->payload['slug']);
        $this->assertSame(1, $log->payload['version']);
        $this->assertSame(['es', 'en', 'fr'], $log->payload['locales']);
        $this->assertArrayHasKey('es', $log->payload['hashes']);
        $this->assertStringNotContainsString('Saltar implica', json_encode($log->payload));
    }

    /** `#169` §10.4 — los marcadores de los TRES idiomas del seeder bloquean, no solo el castellano. */
    public function test_the_english_and_french_draft_markers_are_refused_too(): void
    {
        foreach (['[PENDING: final wording]', '[À COMPLÉTER : rédaction définitive]', '[pending]', '[a completer]'] as $marker) {
            $texts = self::TEXTS;
            $texts['en']['body'][] = ['h' => 'Acceptance', 'p' => "This text is a draft. {$marker}"];

            try {
                app(LegalDocumentPublisher::class)->publish('waiver', $texts);
                $this->fail("«{$marker}» tendría que bloquear la publicación");
            } catch (DraftCannotBePublishedException) {
                $this->assertSame(0, LegalDocumentVersion::count());
            }
        }
    }

    /** Las PALABRAS («borrador», «draft», «brouillon») no bloquean: se enseñan como aviso, por idioma. */
    public function test_draft_words_are_reported_per_locale_but_do_not_block(): void
    {
        $texts = self::TEXTS;
        $texts['es']['body'][] = ['h' => 'Aceptación', 'p' => 'Este texto es un borrador y será revisado por un asesor legal.'];
        $texts['en']['body'][] = ['h' => 'Acceptance', 'p' => 'This text is a draft.'];

        $this->assertSame(['es', 'en'], LegalDocumentPublisher::mentionsDraftWords($texts));
        $this->assertSame([], LegalDocumentPublisher::mentionsDraftWords(self::TEXTS));
        $this->assertSame([], LegalDocumentPublisher::mentionsDraftWords(['es' => ['title' => 'Borradores S.L.', 'body' => [['h' => 'x', 'p' => 'brouillonnage']]]]), 'solo la palabra entera');

        $rows = app(LegalDocumentPublisher::class)->publish('waiver', $texts);
        $this->assertCount(count(self::TEXTS), $rows, 'avisar no es bloquear');
    }
}

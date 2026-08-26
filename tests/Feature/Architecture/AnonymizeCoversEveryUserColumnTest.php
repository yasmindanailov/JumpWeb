<?php

namespace Tests\Feature\Architecture;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * **«CUALQUIER PII NUEVA QUE SE PERSISTA DEBE AÑADIRSE AQUÍ» — HECHO EJECUTABLE.**
 *
 * Esa frase es la última línea de `RGPD-01`, y hasta hoy era **una petición, no una guarda**: nada
 * impedía que alguien añadiera una columna a `users` y `anonymize()` la dejara intacta. El art. 17
 * no se incumple con un fallo ruidoso — se incumple con una columna nueva que nadie recordó.
 *
 * ⚠️ **Lo que este fichero NO hace, y es deliberado**: no repite lo que ya cubre
 * `Account\PrivacyTest` (que el nombre queda neutro, que los consents y los roles se van, que los
 * pedidos sobreviven). Aquí se vigila **el CENSO**: que la lista de columnas de `users` sea
 * exactamente la declarada, y que cada una haga lo que su declaración dice.
 *
 * **Cómo funciona, y por qué así:**
 *  · El censo es **simétrico y falsable en las dos direcciones**. Una columna `SCRUBBED` que dejara
 *    de limpiarse pone el test en rojo — pero también una `PRESERVED` que empezara a limpiarse. Un
 *    censo que solo comprobara una dirección se convertiría en un comentario con el tiempo.
 *  · **Una columna nueva en `users` pone el test en ROJO hasta que alguien la declare.** Ése es el
 *    mecanismo entero: obliga a una decisión consciente, que es justo lo que `RGPD-01` pedía por
 *    escrito y nadie garantizaba.
 *
 * ❗ **Si este test se pone rojo al añadir una columna, la pregunta NO es «cómo lo callo».** Es:
 * ¿ese dato identifica a la persona? Si sí → va a `anonymize()`. Si no → entra en `PRESERVED` **con
 * su razón escrita**, que es lo que se enseña el día que alguien pregunte.
 *
 * `INVARIANTES.md` → `RGPD-01`. Ver `DECISIONES #159`.
 */
class AnonymizeCoversEveryUserColumnTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Columnas cuyo valor `anonymize()` **tiene que cambiar**: identifican al titular.
     *
     * ⚠️ **`waiver_accepted_at` está aquí porque hoy SE BORRA**, y eso es lo que hace el código —no
     * lo que debería hacer—. El subsistema del waiver (`specs/waiver-probatorio.md` §4.6 y §8.2) va
     * a moverla a `PRESERVED`: el registro firmado se conserva bajo régimen restringido, porque **no
     * se puede anonimizar un documento y que siga sirviendo de prueba**. Cuando eso pase, este test
     * se pondrá rojo **y tiene que ponerse**: es la señal de que el cambio llegó, y quien lo haga
     * mueve la columna de lista con su razón.
     */
    private const SCRUBBED = [
        'name',
        'email',
        'pending_email',
        'pending_email_sent_at',
        'phone',
        'locale',
        'last_login_at',
        'marketing_opt_in',
        'privacy_accepted_at',
        'terms_accepted_at',
        'waiver_accepted_at',
        'waiver_pending_document_id',
        'waiver_pending_channel',
        'waiver_pending_ip',
        'waiver_pending_user_agent',
        'email_verified_at',
        'password',
        'remember_token',
    ];

    /**
     * Columnas que `anonymize()` **NO puede tocar**, cada una con el porqué. No es una lista de
     * descartes: es una lista de decisiones.
     *
     * @var array<string, string>
     */
    private const PRESERVED = [
        'id' => 'La FK de los pedidos es RESTRICT y la factura tiene que seguir vinculada (art. 17 vs. deber fiscal AEAT). Es la razón de que esto sea anonimizar y no borrar.',
        'created_at' => 'Cuándo se abrió la cuenta. Sobre una fila ya anónima no identifica a nadie, y es dato contable del alta.',
        'panel_locale' => 'Preferencia de idioma del PANEL (staff). Sobre una fila ya anonimizada no identifica a nadie. ⚠️ La asimetría con `locale` —que sí se normaliza a «es»— es INCIDENTAL: `locale` se toca por higiene de la fila neutra, no por privacidad.',
    ];

    /**
     * Columnas que cambian por el propio hecho de guardar, y que no se comparan.
     *
     * @var array<string, string>
     */
    private const INCIDENTAL = [
        'updated_at' => '`anonymize()` guarda la fila, así que se mueve siempre. Compararla no diría nada de la purga.',
    ];

    /**
     * ⚠️ **La guarda de la guarda.** El censo solo vale si de verdad cubre la tabla: si alguien
     * añade una columna, este caso cae **antes** que los demás y con un mensaje que dice qué hacer.
     */
    public function test_the_census_covers_every_column_of_the_users_table(): void
    {
        $live = Schema::getColumnListing('users');
        sort($live);

        $declared = array_merge(self::SCRUBBED, array_keys(self::PRESERVED), array_keys(self::INCIDENTAL));
        sort($declared);

        $undeclared = array_diff($live, $declared);
        $ghost = array_diff($declared, $live);

        $this->assertSame([], array_values($undeclared),
            'Hay columnas en `users` que el censo de RGPD-01 no declara: '.implode(', ', $undeclared)."\n"
            ."La pregunta no es cómo callar este test: ¿ese dato identifica al titular?\n"
            ."  · Sí  → va a `User::anonymize()`, y la columna entra en SCRUBBED.\n"
            .'  · No  → entra en PRESERVED **con su razón escrita**.');

        $this->assertSame([], array_values($ghost),
            'El censo declara columnas que ya no existen en `users`: '.implode(', ', $ghost)
            .'. Un censo con fantasmas deja de ser un inventario.');
    }

    /**
     * El censo, ejercitado contra la purga real. Simétrico: cada columna hace lo que declara, y solo
     * lo que declara.
     */
    public function test_every_declared_column_behaves_as_the_census_says(): void
    {
        $user = $this->userWithEveryColumnPopulated();
        $id = (int) $user->getKey();

        $before = (array) DB::table('users')->where('id', $id)->first();

        $this->assertTrue($user->anonymize(), 'La purga tiene que ejecutarse para que este caso mida algo.');

        $after = (array) DB::table('users')->where('id', $id)->first();

        foreach (self::SCRUBBED as $column) {
            $this->assertNotEquals(
                $before[$column], $after[$column],
                "`users.{$column}` está declarada SCRUBBED y `anonymize()` la dejó intacta "
                ."(«{$before[$column]}»). O se purga, o se mueve a PRESERVED con su razón."
            );
        }

        foreach (self::PRESERVED as $column => $why) {
            $this->assertEquals(
                $before[$column], $after[$column],
                "`users.{$column}` está declarada PRESERVED y `anonymize()` la cambió. "
                ."Si el cambio es correcto, muévela a SCRUBBED; si no, es una regresión. Razón declarada: {$why}"
            );
        }
    }

    /**
     * ⚠️ **Y que el instrumento sepa FALLAR.** Sin este caso, un censo que comparase mal —o una purga
     * que no se ejecutara— dejaría verde el anterior sin mirar nada: bastaría con que `anonymize()`
     * devolviera `false` en silencio.
     */
    public function test_the_instrument_would_notice_a_purge_that_did_nothing(): void
    {
        $user = $this->userWithEveryColumnPopulated();
        $id = (int) $user->getKey();

        $before = (array) DB::table('users')->where('id', $id)->first();
        $user->anonymize();
        $after = (array) DB::table('users')->where('id', $id)->first();

        // Un solo campo basta para demostrar que la comparación DISCRIMINA: si `before` y `after`
        // fueran la misma foto, el bucle de arriba sería un no-op silencioso.
        $this->assertNotEquals($before['email'], $after['email'],
            'La comparación no discrimina: `before` y `after` describen la misma fila.');

        // Y la idempotencia: una segunda purga no vuelve a ejecutarse (`isAnonymized()`), así que
        // tampoco puede «arreglar» una columna que la primera dejó sucia.
        $this->assertFalse($user->fresh()->anonymize(),
            '`anonymize()` tiene que ser idempotente: la segunda pasada no ejecuta nada.');
    }

    /**
     * Un titular con **todas** las columnas del censo con valor, para que la comparación tenga algo
     * que ver en cada una. Una columna que llegara `null` a la purga saldría `null` y el caso
     * pasaría sin haber medido nada.
     */
    private function userWithEveryColumnPopulated(): User
    {
        $user = User::factory()->create([
            'name' => 'Ana Pérez',
            'email' => 'ana.censo@example.com',
            'phone' => '600111222',
            'locale' => 'fr',
            'marketing_opt_in' => true,
        ]);

        // Fase 6 · waiver (#179): la aceptación PENDIENTE del alta también es PII y se purga; la FK exige
        // una versión firmable real.
        $version = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();

        $user->forceFill([
            'waiver_pending_document_id' => $version->getKey(),
            'waiver_pending_channel' => 'web',
            'waiver_pending_ip' => '10.0.0.9',
            'waiver_pending_user_agent' => 'Alta/1.0 (test)',
            'pending_email' => 'ana.nueva@example.com',
            'pending_email_sent_at' => now()->subHour(),
            'panel_locale' => 'en',
            'last_login_at' => now()->subDay(),
            'privacy_accepted_at' => now()->subMonth(),
            'terms_accepted_at' => now()->subMonth(),
            'waiver_accepted_at' => now()->subMonth(),
            'email_verified_at' => now()->subMonth(),
            'remember_token' => 'centinela-remember-token',
        ])->save();

        return $user->fresh();
    }
}

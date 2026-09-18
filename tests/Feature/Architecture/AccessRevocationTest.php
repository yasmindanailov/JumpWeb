<?php

namespace Tests\Feature\Architecture;

use App\Domain\Identity\Models\User;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Fase 3 · paso 3a — **la invalidación de credenciales tiene UN solo sitio** (`RGPD-01`, `SEC-04`).
 *
 * El paso 3a encontró la purga de la tabla `sessions` copiada en cuatro ficheros y ninguna de las
 * copias tocaba `personal_access_tokens`: cuando se escribieron, Sanctum no existía en el proyecto.
 * Ese es exactamente el fallo que se repite solo — la quinta copia también se olvidaría de los
 * tokens, y de lo que venga después.
 *
 * La defensa no es recordarlo: es que solo `User` sepa hacerlo. Esta guarda comprueba que nadie
 * más escribe contra esas dos tablas, con las excepciones declaradas por nombre y con su motivo,
 * igual que las baselines de `ModuleBoundariesTest`.
 *
 * Se escanea con el TOKENIZADOR y comparando la cadena COMPLETA: un docblock que mencione
 * `sessions` no es una consulta, y `'account.account.sessions.current_password'` es una clave de
 * traducción, no un nombre de tabla.
 */
class AccessRevocationTest extends TestCase
{
    /**
     * Literales que delatan una escritura contra las tablas de credenciales.
     *
     * @var list<string>
     */
    private const CREDENTIAL_TABLES = ['sessions', 'session.table', 'personal_access_tokens', 'customer_cards'];

    /**
     * Quién PUEDE nombrarlas, y por qué. La lista solo encoge.
     *
     * @var array<string, string>
     */
    private const ALLOWED = [
        // El punto único: `revokeAllAccess()` / `revokeOtherAccess()`.
        'app/Domain/Identity/Models/User.php' => 'la implementación de la invalidación',
        // Purga masiva de go-live: opera por CONJUNTO (`whereNotIn(keptIds)`), no sobre un titular,
        // así que no puede delegar en un método de instancia. Sus tres tablas sin FK
        // —password_reset_tokens, sessions, personal_access_tokens— se borran a mano y a propósito.
        'app/Console/Commands/PurgeCustomerData.php' => 'borrado por conjunto en la limpieza de go-live',
        // ⚠️ NO escribe en ninguna tabla — no tiene ni una consulta: es el modelo de lectura del arranque
        // del cajón (F4 · T1) y el literal que ve el escáner es la CLAVE del subgrupo de traducción
        // `account.account.sessions` («cerrar las demás sesiones»). Hasta el 2026-09-18 vivía dentro de
        // `layout.blade.php`, que este escaneo no mira; al mudarse a PHP el falso positivo se hizo visible.
        'app/Http/Sidebar/SidebarBoot.php' => 'la clave de traducción `sessions` del arranque del cajón, no una tabla',
    ];

    /** La invalidación centralizada existe con los dos nombres que el resto del código espera. */
    public function test_the_single_point_of_invalidation_exists(): void
    {
        $this->assertTrue(method_exists(User::class, 'revokeAllAccess'));
        $this->assertTrue(method_exists(User::class, 'revokeOtherAccess'));
    }

    /** El escaneo nunca puede pasar en vacío: sin esto, un glob roto daría verde sin mirar nada. */
    public function test_the_scan_sees_the_allowed_files(): void
    {
        $scanned = array_map(
            fn (string $file): string => mb_substr($file, mb_strlen(base_path()) + 1),
            $this->phpFiles(),
        );

        foreach (array_keys(self::ALLOWED) as $path) {
            $this->assertContains($path, $scanned, "«{$path}» ya no se escanea: ¿se movió o se borró?");
        }
    }

    public function test_only_the_single_point_of_invalidation_writes_to_the_credential_tables(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $relative = mb_substr($file, mb_strlen(base_path()) + 1);

            if (isset(self::ALLOWED[$relative])) {
                continue;
            }

            foreach ($this->credentialTableLiteralsIn($file) as $line => $literal) {
                $violations[] = "  {$relative}:{$line} — «{$literal}»";
            }
        }

        $this->assertSame(
            [], $violations,
            "Código que nombra las tablas de credenciales fuera del punto único:\n".implode("\n", $violations)."\n\n".
            'Cerrar sesiones y revocar tokens se hace con `User::revokeAllAccess()` o '.
            '`revokeOtherAccess()`. Cada copia suelta es una que se olvidará de la próxima '.
            'credencial, igual que las cuatro que había se olvidaron de los tokens de API.'
        );
    }

    /**
     * Literales de tabla de credenciales del fichero, indexados por línea.
     *
     * @return array<int, string>
     */
    private function credentialTableLiteralsIn(string $file): array
    {
        $found = [];

        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $value = trim($token[1], "'\"");

            if (in_array($value, self::CREDENTIAL_TABLES, true)) {
                $found[$token[2]] = $value;
            }
        }

        return $found;
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}

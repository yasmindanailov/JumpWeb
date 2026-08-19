<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `scripts/deploy.sh`, hecho FALSABLE (hermano de {@see PrePushGateTest} y {@see CriticalPathGateTest}).
 *
 * El razonamiento es el mismo que llevó a `PrePushGateTest`: **un paso que desaparece de un script de
 * shell no lo nota nadie hasta el día que hacía falta, y ese día es tarde**. Aquí el «día que hacía
 * falta» es un despliegue, donde los modos de fallo son caros y algunos SILENCIOSOS —una guarda que
 * cae sin que nada avise—.
 *
 * ⚠️ **El caso estrella es `test_the_minimum_php_matches_what_the_lock_actually_requires`**, y no es
 * teórico: `DECISIONES #103(f)` midió que `ENTORNOS.md` afirmaba «PHP 8.3 cumple `composer.json`
 * (`^8.3`)» —cierto sobre el `.json` y **falso sobre lo que se instala**, porque el requisito efectivo
 * lo fija el LOCK—. El sitio se había aprovisionado en 8.3 y `composer install --no-dev` habría
 * abortado a medias. Ese desfase vivió en la doc **sin que nada lo cazara**. Este caso lo DERIVA del
 * lock en cada ejecución, así que la próxima vez que `composer update` suba el suelo de PHP, el rojo
 * llega aquí y no en mitad de un despliegue.
 */
class DeployScriptGateTest extends TestCase
{
    private const SCRIPT = 'scripts/deploy.sh';

    private function script(): string
    {
        $path = base_path(self::SCRIPT);

        $this->assertFileExists($path, 'No existe `'.self::SCRIPT.'`: el canal de despliegue es el script, no la memoria de nadie.');

        return (string) file_get_contents($path);
    }

    /**
     * El script SIN sus líneas de comentario.
     *
     * ⚠️ Existe por un fallo propio medido el 2026-08-19: mutando `deploy.sh` salieron **tres casos
     * INERTES** —quitar la reposición del `robots.txt`, quitar el drenaje de cola y romper el orden de
     * `composer install`— porque las agujas se encontraban en los COMENTARIOS que explican por qué
     * cada paso está ahí. Un script bien documentado hacía que su propio test dejara de morder: el
     * texto sobrevivía al código. La lección es general y vale para cualquier test sobre un fichero
     * de texto: **asevera sobre lo EJECUTABLE, y ancla las agujas al sitio de llamada.**
     */
    private function executable(): string
    {
        $lines = preg_split('/\R/', $this->script()) ?: [];

        return implode("\n", array_filter($lines, static fn (string $l): bool => ! preg_match('/^\s*#/', $l)));
    }

    public function test_the_deploy_script_exists_and_is_executable(): void
    {
        $path = base_path(self::SCRIPT);

        $this->assertFileExists($path);
        $this->assertTrue(is_executable($path), '`'.self::SCRIPT.'` no tiene permiso de ejecución.');
    }

    // ── El caso que nace de un fallo REAL (#103(f)) ───────────────────────────────────────────────

    public function test_the_minimum_php_matches_what_the_lock_actually_requires(): void
    {
        $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true, 512, JSON_THROW_ON_ERROR);

        // El suelo REAL es el mayor `>=` que exija cualquier paquete de PRODUCCIÓN (`packages`, no
        // `packages-dev`: `--no-dev` no instala esos).
        $required = [0, 0, 0];
        $blame = null;

        foreach ($lock['packages'] as $package) {
            $constraint = $package['require']['php'] ?? null;

            if ($constraint === null) {
                continue;
            }

            preg_match_all('/>=\s*(\d+)\.(\d+)(?:\.(\d+))?/', (string) $constraint, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $version = [(int) $match[1], (int) $match[2], (int) ($match[3] ?? 0)];

                if ($version > $required) {
                    $required = $version;
                    $blame = $package['name'].' ('.$constraint.')';
                }
            }
        }

        $this->assertNotNull($blame, 'Ningún paquete de producción declara un `php >=`: el parseo del lock se ha roto.');

        preg_match('/^readonly PHP_MIN="([\d.]+)"/m', $this->executable(), $declared);

        $this->assertNotEmpty($declared, 'No se encuentra `PHP_MIN` en '.self::SCRIPT.'.');

        $this->assertGreaterThanOrEqual(
            0,
            version_compare($declared[1], implode('.', $required)),
            sprintf(
                "El PHP_MIN de %s (%s) es MENOR que lo que exige composer.lock (%s, por %s).\n".
                'Eso es exactamente el fallo de DECISIONES #103(f): el requisito lo fija el LOCK, no '.
                'composer.json. Con un suelo mal puesto, `composer install --no-dev` aborta A MEDIAS en '.
                'el servidor y el despliegue muere con el sitio ya sincronizado.',
                self::SCRIPT,
                $declared[1],
                implode('.', $required),
                $blame,
            ),
        );
    }

    // ── Las guardas: lo valioso del script es lo que NO deja hacer ────────────────────────────────

    /**
     * Cada guarda con el fallo medido que la justifica. Si alguna desaparece del script, el rojo
     * explica POR QUÉ estaba, no solo que falta.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guardProvider(): array
    {
        return [
            'dry-run por defecto' => ['GO=0', 'Sin dry-run por defecto, un tecleo despliega. Misma convención que `app:purge-customers`.'],
            'exige --go para ejecutar' => ['--go) GO=1', 'Si no hay una bandera explícita para ejecutar, el dry-run no protege de nada.'],
            'suelo de PHP' => ['PHP_MIN', 'Sin comprobar la versión, `composer install` aborta a medias (#103(f)).'],
            'valida el .env remoto' => ['guard_errors', 'Las seis guardas de ENTORNOS §2 se comprueban, no se suponen.'],
            'nunca sube el .env' => ["--exclude='/.env'", 'Subir el .env local tumbaría cuatro guardas de golpe y metería secretos de dev.'],
            'guarda 3 · el correo no sale' => ['r_mail=$(env_get MAIL_MAILER)', 'Con SMTP real, los 22 ShouldQueue envían a las direcciones del seed.'],
            'guarda 1 · Redsys no en live' => ['redsys_env=$(remote_php', 'Es el único fallo de la lista que cuesta DINERO: cobraría con tarjetas reales.'],
            'guarda 4 · repone el robots.txt' => ["> '\$REMOTE_ROOT/public/robots.txt'", 'El del repo PERMITE indexar a propósito: cada rsync tumba la guarda si no se repone.'],
            'guarda 4 · lo verifica por HTTP' => ['Disallow: /', 'Verificar el fichero no basta: si el docroot no fuera el esperado, nadie avisaría.'],
            'protege storage/ del rsync' => ["--exclude='/storage/'", 'Llevaría logs y cachés del servidor por delante, y traería basura de test.'],
            'protege las subidas del panel' => ["--exclude='/public/uploads/'", 'Con `--delete`, el segundo despliegue borraría las imágenes subidas desde el panel.'],
            'excluye public/hot' => ["--exclude='/public/hot'", 'Si llega, Vite sirve todo desde localhost:5274 y la web queda muda SIN error de servidor.'],
            'excluye vendor/' => ["--exclude='/vendor/'", 'Lo construye composer allí; excluirlo además lo salva del `--delete`.'],
            'excluye node_modules/' => ["--exclude='/node_modules/'", '97 MB que no sirven de nada: en el servidor no hay node.'],
            'excluye tests/' => ["--exclude='/tests/'", 'Cero valor en runtime y lleva fixtures y credenciales de prueba.'],
            'drena la cola' => ['remote_php "artisan queue:work --stop-when-empty', 'Un job con FQCN viejo cae a failed_jobs: el cliente pagó y no recibe nada.'],
            'comprueba la salud al terminar' => ['"$SITE_URL/up"', 'No dar por hecho que fue bien es la mitad del valor del script.'],
        ];
    }

    #[DataProvider('guardProvider')]
    public function test_the_script_keeps_its_guards(string $needle, string $why): void
    {
        $this->assertStringContainsString(
            $needle,
            $this->executable(),
            "Falta «{$needle}» en ".self::SCRIPT.". Estaba por algo: {$why}",
        );
    }

    // ── El ORDEN, que en un despliegue no es preferencia sino corrección ──────────────────────────

    /**
     * Posición del SITIO DE LLAMADA real, no de cualquier mención.
     *
     * ⚠️ Escrito así tras un falso rojo propio (2026-08-19): buscar `'artisan up'` a secas encontraba
     * primero el mensaje del manejador de errores («levántalo a mano con: … artisan up»), que está en
     * la cabecera del script. El caso medía el ORDEN DE LOS COMENTARIOS, no el del despliegue. Un test
     * de orden tiene que anclarse en la invocación —`remote_php "artisan …"`—, nunca en el texto.
     */
    private function callSite(string $script, string $artisanCommand): int
    {
        $needle = 'remote_php "artisan '.$artisanCommand;
        $at = strpos($script, $needle);

        $this->assertNotFalse($at, "No se encuentra la llamada `{$needle}…` en ".self::SCRIPT.'.');

        return $at;
    }

    public function test_maintenance_mode_comes_before_the_rsync(): void
    {
        $s = $this->executable();

        $this->assertLessThan(
            strpos($s, 'rsync_run ""'),
            $this->callSite($s, 'down'),
            '`artisan down` debe ir ANTES del rsync: `public/index.php` mira `maintenance.php` antes '.
            'del autoloader, así que la 503 sobrevive a un `vendor/` roto a mitad de `composer install`. '.
            'Al revés, un fallo deja el sitio con un fatal de PHP y sin página de error.',
        );
    }

    public function test_composer_install_comes_before_optimize(): void
    {
        $s = $this->executable();

        $this->assertLessThan(
            $this->callSite($s, 'optimize'),
            strpos($s, '&& composer install --no-dev'),
            '`composer install` dispara `post-autoload-dump` → `filament:upgrade`, que hace '.
            '`config:clear`/`route:clear`/`view:clear`. Si `optimize` fuera antes, se perdería entero.',
        );
    }

    public function test_migrations_run_before_the_site_is_brought_back_up(): void
    {
        $s = $this->executable();

        $this->assertLessThan(
            $this->callSite($s, 'up'),
            $this->callSite($s, 'migrate --force'),
            'Migrar ANTES de servir tráfico: el morphMap de Fase 2 es requisito y, con '.
            '`APP_ENV=production`, `tableExists()` NO comprueba nada — servir antes de migrar da 500 '.
            'duro en todas las vistas, no degradación elegante.',
        );
    }

    public function test_the_redsys_guard_runs_after_migrating_and_before_serving(): void
    {
        $s = $this->executable();
        $guard = strpos($s, 'GUARDA 1 · redsys_environment');

        $this->assertNotFalse($guard, 'Falta la comprobación de `redsys_environment` tras migrar.');
        $this->assertLessThan($guard, $this->callSite($s, 'migrate --force'),
            '`redsys_environment` es un Setting de BD: antes de migrar no se puede leer.');
        $this->assertLessThan($this->callSite($s, 'up'), $guard,
            'Si el entorno fuera `live`, el sitio NO puede levantarse: cobraría de verdad.');
    }
}

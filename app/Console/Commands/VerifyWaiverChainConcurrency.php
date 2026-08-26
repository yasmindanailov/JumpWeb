<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Identity\Services\WaiverChain;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Fase 6 · waiver — verificador de CONCURRENCIA de la cadena de hashes
 * (`docs/specs/waiver-probatorio.md` §8.5), hermano de `purchase:verify-oversell`.
 *
 * N procesos reales (`pcntl_fork`) firman a la vez para el MISMO titular contra MySQL. El invariante:
 * N filas, UNA cadena lineal —cada `prev_hash` enlaza con la anterior y ningún `prev_hash` se
 * repite—. Si el lock de `WaiverSigner` no serializa, dos firmas leen la misma cabeza y la cadena
 * se BIFURCA en silencio: no falla, no avisa, y una cadena bifurcada no prueba nada.
 *
 * ⚠️ Cada proceso firma como un SUJETO distinto (un menor a cargo por proceso, `subject_id` = i):
 * desde `#169` re-firmar la MISMA versión por el mismo sujeto es idempotente (devuelve la fila que
 * hay), así que N firmas iguales darían UNA fila y no probarían nada de la cadena. La propiedad que
 * se mide —que el lock del titular serialice la lectura de la cabeza— es la misma.
 *
 * ⚠️ Un verde solo vale si el instrumento se ha visto FALLAR (`#147`): con el `lockForUpdate()`
 * retirado del firmador, este comando tiene que cazar la bifurcación. Solo dev/local.
 */
class VerifyWaiverChainConcurrency extends Command
{
    protected $signature = 'waiver:verify-chain
        {--workers=8 : Nº de firmas concurrentes (procesos) del MISMO titular}
        {--keep : No borrar los datos de prueba al terminar}';

    protected $description = 'Verifica empíricamente (fork real + MySQL InnoDB) que N firmas simultáneas del mismo titular producen UNA cadena lineal de hashes, sin bifurcar. Solo dev/local.';

    public function handle(): int
    {
        if ($this->getLaravel()->isProduction()) {
            $this->error('Abortado: NO ejecutar en producción (crea y borra datos).');

            return self::FAILURE;
        }
        if (! \extension_loaded('pcntl')) {
            $this->error('Falta la extensión pcntl: no se puede forkar para concurrencia real.');

            return self::FAILURE;
        }
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            $this->warn("⚠ Conexión '{$driver}': SQLite NO reproduce los locks de InnoDB. Ejecuta contra MySQL para una prueba VÁLIDA.");
        }

        $workers = max(2, (int) $this->option('workers'));
        $resultsDir = storage_path('app/waiver-chain-concurrency');
        File::ensureDirectoryExists($resultsDir);
        File::cleanDirectory($resultsDir);

        $seed = $this->seed();
        $this->line("Titular #{$seed['user']->getKey()} · versión firmable v{$seed['version']->version}·{$seed['version']->locale} (#{$seed['version']->getKey()}).");

        // La guarda del instrumento: una firma EN SERIE tiene que funcionar. Si no, lo que fallara
        // abajo no sería la carrera, y el verificador estaría midiendo otra cosa.
        try {
            app(WaiverSigner::class)->sign($seed['user'], $seed['version'], WaiverSignatureRequest::api('127.0.0.1', 'waiver:verify-chain/probe'));
        } catch (\Throwable $e) {
            $this->error('El escenario NO permite firmar ni una vez: '.$e->getMessage());
            $this->cleanup($seed, $resultsDir);

            return self::FAILURE;
        }

        $this->line("Disparando <fg=yellow>{$workers}</> firmas <options=bold>CONCURRENTES</> del mismo titular sobre {$driver}…");

        try {
            $this->forkWorkers($seed, $workers, microtime(true) + 0.5, $resultsDir);

            DB::reconnect();
            $verdict = $this->evaluate($seed, $workers, $resultsDir);
        } finally {
            DB::reconnect();
            if ($this->option('keep')) {
                $this->warn("--keep: datos de prueba NO borrados (titular #{$seed['user']->getKey()}).");
            } else {
                $this->cleanup($seed, $resultsDir);
            }
        }

        return $verdict ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Un titular desechable y la versión vigente del waiver; si no hay ninguna publicada, se
     * publica una de prueba (y se retira al limpiar).
     *
     * @return array{user:User, version:LegalDocumentVersion, created_version:bool}
     */
    private function seed(): array
    {
        return DB::transaction(function (): array {
            $user = User::create([
                'name' => 'Verificador de cadena',
                'email' => 'waiver-chain-'.Str::lower(Str::random(8)).'@verify.local',
                'password' => Str::random(32),
                'locale' => 'es',
                'marketing_opt_in' => false,
            ]);
            // Desde `#179` el titular firma solo con el correo verificado.
            $user->forceFill(['email_verified_at' => now()])->save();

            $version = LegalDocuments::current(WaiverSettings::SLUG, 'es');
            $created = false;
            if ($version === null) {
                $version = app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
                    'es' => [
                        'title' => 'Waiver (verificador de cadena)',
                        'body' => [['h' => 'Prueba', 'p' => 'Texto del verificador de concurrencia de la cadena.']],
                    ],
                ])->first();
                $created = true;
            }

            return ['user' => $user, 'version' => $version, 'created_version' => $created];
        });
    }

    /**
     * @param  array{user:User, version:LegalDocumentVersion}  $seed
     */
    private function forkWorkers(array $seed, int $workers, float $startAt, string $resultsDir): void
    {
        DB::disconnect(); // el socket MySQL del padre NO debe compartirse entre forks

        $pids = [];
        for ($i = 0; $i < $workers; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->error('pcntl_fork falló.');
                break;
            }
            if ($pid === 0) {
                // ---- HIJO ----
                DB::reconnect();
                $wait = (int) (($startAt - microtime(true)) * 1_000_000);
                if ($wait > 0) {
                    usleep($wait);
                }
                $outcome = 'ERROR';
                try {
                    $holder = User::findOrFail($seed['user']->getKey());
                    $version = LegalDocumentVersion::findOrFail($seed['version']->getKey());
                    $signature = app(WaiverSigner::class)->sign($holder, $version, new WaiverSignatureRequest(
                        channel: WaiverSignature::CHANNEL_API,
                        ip: '127.0.0.1',
                        userAgent: "waiver:verify-chain/{$i}",
                        subjectType: WaiverSignature::SUBJECT_DEPENDENT,
                        subjectId: $i + 1,
                    ));
                    $outcome = 'signed:'.$signature->getKey();
                } catch (\Throwable $e) {
                    $outcome = 'EXCEPTION: '.$e->getMessage();
                }
                File::put($resultsDir.'/'.$i.'.txt', $outcome);
                exit(0);
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
    }

    /**
     * @param  array{user:User, version:LegalDocumentVersion}  $seed
     */
    private function evaluate(array $seed, int $workers, string $resultsDir): bool
    {
        $outcomes = collect(File::files($resultsDir))
            ->map(fn ($file): string => trim(File::get($file->getPathname())));
        $signed = $outcomes->filter(fn (string $o): bool => str_starts_with($o, 'signed:'));
        $errors = $outcomes->reject(fn (string $o): bool => str_starts_with($o, 'signed:'));

        $rows = WaiverSignature::query()->where('user_id', $seed['user']->getKey())->orderBy('id')->get();
        $chain = WaiverChain::verify(User::findOrFail($seed['user']->getKey()));
        $prevHashes = $rows->pluck('prev_hash');
        $forks = $prevHashes->count() - $prevHashes->unique()->count(); // dos filas con el mismo prev_hash = bifurcación
        $expected = $workers + 1; // + la firma de la sonda

        $this->newLine();
        $this->line('<options=bold>Resultados de las firmas concurrentes:</>');
        $this->line('  '.$signed->count().'× firmada');
        if ($errors->isNotEmpty()) {
            $this->line('  <fg=red>'.$errors->count().'× error inesperado</>');
            $errors->each(fn (string $e) => $this->line('     '.$e));
        }
        $this->line("  filas en la cadena: {$rows->count()} (esperadas {$expected}) · prev_hash repetidos: {$forks} · verificación: ".($chain['ok'] ? 'OK' : 'ROTA'));
        foreach ($chain['problems'] as $problem) {
            $this->line('     <fg=red>'.$problem.'</>');
        }

        $ok = $signed->count() === $workers && $rows->count() === $expected && $forks === 0 && $chain['ok'];
        $this->newLine();
        if ($ok) {
            $this->info("✅ PASA: bajo {$workers} firmas concurrentes del mismo titular la cadena es LINEAL — sin bifurcación, todos los hashes verifican. Verificado sobre InnoDB real.");
        } else {
            $this->error('❌ FALLA: cadena bifurcada o rota. Revisar que el lockForUpdate() de la fila del titular sea la PRIMERA sentencia de la transacción de WaiverSigner.');
        }

        return $ok;
    }

    /**
     * Borrado por `DB::table`: las dos tablas son append-only y sus modelos rechazan `delete()`.
     * Esta limpieza y la de go-live (`PurgeCustomerData`) son las únicas que lo hacen, y solo
     * sobre datos que este propio comando creó.
     *
     * @param  array{user:User, version:LegalDocumentVersion, created_version:bool}  $seed
     */
    private function cleanup(array $seed, string $resultsDir): void
    {
        $userId = $seed['user']->getKey();
        DB::table('waiver_signatures')->where('user_id', $userId)->delete();
        DB::table('consents')->where('user_id', $userId)->delete();
        DB::table('audit_logs')->where('target_type', (new User)->getMorphClass())->where('target_id', $userId)->delete();
        if ($seed['created_version']) {
            DB::table('audit_logs')
                ->where('target_type', (new LegalDocumentVersion)->getMorphClass())
                ->where('target_id', $seed['version']->getKey())
                ->delete();
            DB::table('legal_document_versions')->where('id', $seed['version']->getKey())->delete();
        }
        DB::table('users')->where('id', $userId)->delete();
        File::deleteDirectory($resultsDir);
    }
}

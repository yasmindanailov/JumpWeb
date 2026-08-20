<?php

namespace App\Console\Commands;

use App\Domain\Platform\Models\Setting;
use Illuminate\Console\Command;

/**
 * Escribe un ajuste de `settings` desde la CLI. Hermano de {@see CreateAdmin}, y nace del mismo
 * hueco: **hay ajustes que el panel NO expone, y hasta hoy solo se podían tocar a mano** con `tinker`
 * o SQL en el servidor — un paso no probado que cada instalación de cliente necesita.
 *
 * Los tres que lo hacían falta, medidos:
 *  · `security.turnstile_site_key` / `security.turnstile_secret` — el anti-bot se lee SOLO de
 *    `settings`, y la página del panel los excluye **a propósito** («NUNCA editables aquí»). Sin ellos
 *    el anti-bot se autodesactiva **en silencio** (`DECISIONES #107`).
 *  · `sidebar.engine` — el flag que elige motor del cajón. Tampoco está en el panel, y la única receta
 *    escrita para cambiarlo usaba `docker compose exec`, que en un servidor real no existe.
 *
 * ⚠️ **Es una puerta trasera al panel, así que trae guardas.** No basta con «escribe lo que te digan»:
 * las tres claves de {@see self::PROTECTED_KEYS} pueden costar dinero o corromper la numeración de
 * pedidos, así que exigen `--force` escrito a mano. La lista espeja lo que el propio panel se niega a
 * editar; no es una precaución genérica.
 *
 * ⚠️ **Y nunca imprime un secreto.** El valor se enmascara si la clave parece un secreto: este comando
 * se ejecuta por SSH y su salida acaba en el log del despliegue.
 */
class SetSetting extends Command
{
    protected $signature = 'app:set-setting
        {key : La clave, tal cual está en la tabla (p. ej. `sidebar.engine`).}
        {value : El valor. Cadena vacía = se guarda vacío, que NO es lo mismo que borrar la fila.}
        {--group= : Grupo de la fila. Solo se usa al CREARLA; si ya existe, se respeta el suyo.}
        {--force : Obligatorio para las claves protegidas (ver PROTECTED_KEYS).}';

    protected $description = 'Escribe un ajuste que el panel no expone (anti-bot, motor del cajón…). Idempotente.';

    /**
     * Claves que exigen `--force`, cada una con el daño que hace tocarla a ciegas.
     *
     * @var array<string, string>
     */
    private const PROTECTED_KEYS = [
        'redsys_environment' => 'en `live` la instalación COBRA de verdad, con tarjetas de verdad. Es el único ajuste de esta tabla que cuesta dinero.',
        'redsys_secret_key' => 'la clave del comercio vive en el vault/`.env`, no en BD: escribirla aquí la deja en una tabla que se vuelca en cada backup.',
        'redsys_next_gateway_order' => 'es un contador OPERATIVO que gestiona el flujo de pago; retrocederlo colisiona pedidos en la pasarela (SIS0051/0913).',
    ];

    /** Fragmentos que delatan un secreto: su valor no se imprime jamás. */
    private const SECRET_HINTS = ['secret', 'password', 'token'];

    public function handle(): int
    {
        $key = trim((string) $this->argument('key'));
        $value = (string) $this->argument('value');

        if ($key === '') {
            $this->error('La clave no puede estar vacía.');

            return self::FAILURE;
        }

        if (isset(self::PROTECTED_KEYS[$key]) && ! $this->option('force')) {
            $this->error("«{$key}» es una clave PROTEGIDA y no se toca sin --force.");
            $this->warn('Motivo: '.self::PROTECTED_KEYS[$key]);
            $this->line('Si de verdad sabes lo que haces, repite el comando con --force.');

            return self::FAILURE;
        }

        $existing = Setting::query()->where('key', $key)->first();
        $group = $existing?->group ?? (trim((string) $this->option('group')) ?: 'general');

        if ($existing !== null && (string) $existing->value === $value) {
            $this->info("Sin cambios: «{$key}» ya vale {$this->display($key, $value)}.");

            return self::SUCCESS;
        }

        $before = $existing?->value;

        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);

        // El memo es POR PROCESO y `saved()` ya lo purga; se llama igual para que la lectura de abajo
        // no dependa del orden de los eventos.
        Setting::flushMemo();

        $this->info($existing === null
            ? "CREADA «{$key}» (grupo «{$group}») = {$this->display($key, $value)}."
            : "ACTUALIZADA «{$key}»: {$this->display($key, (string) $before)} → {$this->display($key, $value)}.");

        // Se relee de la BD en vez de confiar en lo que acabamos de escribir: si algo la pisara, este
        // comando lo diría en vez de dar un verde que no significa nada.
        $this->line('  comprobado en BD: '.$this->display($key, (string) Setting::value($key, '')));

        return self::SUCCESS;
    }

    /** Enmascara el valor si la clave parece un secreto. La salida acaba en el log del despliegue. */
    private function display(string $key, string $value): string
    {
        if ($value === '') {
            return '(vacío)';
        }

        foreach (self::SECRET_HINTS as $hint) {
            if (str_contains(mb_strtolower($key), $hint)) {
                return '«'.mb_substr($value, 0, 4).'…'.mb_substr($value, -4).'» ('.mb_strlen($value).' chars, enmascarado)';
            }
        }

        return "«{$value}»";
    }
}

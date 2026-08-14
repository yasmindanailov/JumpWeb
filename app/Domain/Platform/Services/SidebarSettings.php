<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\Setting;

/**
 * Qué MOTOR pinta el cajón de reservas (Fase 4 · paso 4.1, `docs/specs/sidebar-spa.md` §4.9).
 *
 * La SPA y el sidebar Livewire conviven mientras dure la transcripción, y el interruptor está en la
 * base de datos porque su función es poder **comparar los dos en vivo** y volver atrás sin
 * desplegar: `CE-1` exige paridad demostrada antes de retirar nada.
 *
 * Helper DEFENSIVO, como el resto de ajustes: un valor corrupto o ausente devuelve `livewire`, que
 * es el motor que hoy funciona. Esa asimetría es deliberada — el fallback no puede ser el motor en
 * construcción.
 *
 * ⚠️ **La bifurcación no es solo qué componente se pinta** (§4.9): alcanza al `@vite` del entry, al
 * payload de i18n y a la fachada de intención. Lo que NO cambia son `@livewireStyles`/`@livewireScripts`,
 * que se quedan en los dos modos: los modales de auth y `account-context` son Livewire, y **Alpine lo
 * trae Livewire**.
 *
 * ⚠️ Y hace que el significado de la suite dependa de una fila: hay tests que afirman marcado del
 * cajón desde `GET /`. El fixture fija el motor explícitamente y los casos de SPA se **añaden**, no
 * sustituyen a los existentes.
 */
class SidebarSettings
{
    public const ENGINE_KEY = 'sidebar.engine';

    /** El sidebar Livewire de siempre. **Es el default y el fallback**: es el que funciona. */
    public const ENGINE_LIVEWIRE = 'livewire';

    /** La SPA de Vue (Fase 4). Solo se activa a propósito. */
    public const ENGINE_SPA = 'spa';

    /** @var list<string> */
    public const ENGINES = [self::ENGINE_LIVEWIRE, self::ENGINE_SPA];

    /**
     * Motor configurado, o `livewire` si falta o no se reconoce.
     *
     * No lanza ni avisa ante un valor desconocido a propósito: un typo en el panel no puede dejar
     * la web sin cajón de reservas, que es la única superficie que vende.
     */
    public static function engine(): string
    {
        $raw = Setting::value(self::ENGINE_KEY);

        return in_array($raw, self::ENGINES, true) ? (string) $raw : self::ENGINE_LIVEWIRE;
    }

    public static function usesSpa(): bool
    {
        return self::engine() === self::ENGINE_SPA;
    }
}

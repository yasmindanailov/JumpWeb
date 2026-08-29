<script setup>
/**
 * **EL MARCADOR DE UN PRODUCTO, en un solo sitio** (`DECISIONES #140`).
 *
 * ⚠️⚠️ Antes esto era un `v-if="line.is_pack"` con **la geometría entera escrita dentro**, repetido
 * en `CartStep.vue` y en `SummaryLine.vue`. Cuatro copias de dos dibujos, sostenidas por
 * `SidebarIconParityTest` — que comprueba que coinciden con el set de Blade, pero **no puede impedir
 * que se multipliquen**.
 *
 * ▶ Y la regla que había debajo era peor que la duplicación: «pack ⇒ tarta, lo demás ⇒ entrada»
 * reparte un catálogo entero en **dos dibujos**. La tirolina, la tarta y los calcetines son los tres
 * «no-pack», así que los tres salían como un ticket.
 *
 * Ahora el SERVIDOR manda la clave (`line.icon`, resuelta por `Booking\Services\ProductIcon`) y aquí
 * solo se mapea clave → dibujo. Elegir el icono de un producto dejó de ser tocar Vue.
 *
 * ⚠️ **Las geometrías son copia LITERAL de `resources/views/components/icons/*.blade.php`**, y tienen
 * que seguir siéndolo: `SidebarIconParityTest` las compara byte a byte tras normalizar. Nació de que
 * el cajón se sirvió una vez con 20 `<svg>` VACÍOS sin que ningún gate se enterara.
 *
 * ⚠️ Una clave desconocida cae al ticket en vez de no pintar nada: un hueco mudo en la cesta se lee
 * como un fallo de carga, y el dominio ya garantiza que la clave es del set — esto es la red de
 * abajo, no la decisión.
 */
import { computed } from 'vue';

const props = defineProps({
    /** Clave del set de diseño. La resuelve el servidor; aquí no se deduce de `is_pack`. */
    // ⚠️ Espejo de `ProductIcon::DEFAULT_OTHER`, que desde `#259` es `ticket`. Si los dos se
    //    separan, el cajón sirve un dibujo distinto del que el dominio dice servir.
    icon: { type: String, default: 'ticket' },
});

/** Las que este registro sabe dibujar. Espejo de `Booking\Services\ProductIcon::CHOICES`. */
const KNOWN = [
    'ic-b1', 'ic-b7', 'ic-e2', 'ic-e5', 'socks', 'ticket-tear-off',
    // Los cinco del set del artboard (`#258`). Ver el bloque de plantilla para el porqué.
    'ticket', 'gift', 'pack', 'party', 'school-trip',
];

/**
 * ⚠️ La clave se NORMALIZA aquí y la plantilla no lleva `v-else` genérico, a propósito. Con un
 * `v-else` el respaldo era implícito: `ticket-tear-off` no aparecía escrito en ninguna rama, así que
 * la guarda «el cajón sabe dibujar todo lo que el panel ofrece» no podía comprobarlo — y añadir una
 * opción al desplegable habría servido el genérico sin que nada fallara.
 */
const key = computed(() => (KNOWN.includes(props.icon) ? props.icon : 'ticket'));
</script>

<template>
    <span v-if="key === 'ic-b1'" class="icon ic-b1 prod-ico" aria-hidden="true">
        <svg viewBox="0 0 40 40" width="20" height="20">
            <path d="M 7 33 L 33 33" />
            <path d="M 10 33 L 10 25 Q 10 22 13 22 L 27 22 Q 30 22 30 25 L 30 33" />
            <path d="M 11.5 27.5 L 28.5 27.5" class="dashed thin" />
            <path d="M 20 22 L 20 15" />
            <path class="flame accent-fill" d="M 20 14.5 Q 22.4 11.6 20 8.6 Q 17.6 11.6 20 14.5 Z" />
        </svg>
    </span>

    <span v-else-if="key === 'ic-b7'" class="icon ic-b7 prod-ico" aria-hidden="true">
        <svg viewBox="0 0 40 40" width="20" height="20">
            <g class="pop">
                <path d="M 8 32 L 14.5 15.5 L 27.5 24.5 Z" />
                <path d="M 10.6 25.4 L 15.8 29" class="thin" />
                <path d="M 12.55 20.45 L 21.65 26.75" class="thin" />
            </g>
            <rect class="c1 accent-fill" x="26.5" y="9" width="2.4" height="2.4" rx="0.7" />
            <rect class="c2 filled" x="32.5" y="13.5" width="2" height="2" rx="0.6" />
            <circle class="c3 accent-fill" cx="30.5" cy="5.5" r="1.2" />
            <path d="M 22 13.5 L 25 10.5" class="thin" />
            <path d="M 27.5 19 L 31.5 17" class="thin" />
        </svg>
    </span>

    <span v-else-if="key === 'ic-e2'" class="icon ic-e2 prod-ico" aria-hidden="true">
        <svg viewBox="0 0 50 32" width="22" height="14">
            <g class="tk-back">
                <path d="M 8 3 L 44 3 L 44 7.5 A 2.8 2.8 0 0 0 44 13.1 L 44 19 L 8 19 L 8 13.1 A 2.8 2.8 0 0 0 8 7.5 Z" />
            </g>
            <path class="occ" d="M 6 11 L 42 11 L 42 15.5 A 2.8 2.8 0 0 0 42 21.1 L 42 27 L 6 27 L 6 21.1 A 2.8 2.8 0 0 0 6 15.5 Z" />
            <path d="M 15 14 L 15 24" class="dashed" />
            <path d="M 19.5 16.5 L 36 16.5" />
            <path d="M 19.5 21 L 30 21" class="thin" />
        </svg>
    </span>

    <span v-else-if="key === 'ic-e5'" class="icon ic-e5 prod-ico" aria-hidden="true">
        <svg viewBox="0 0 50 32" width="22" height="14">
            <g class="t-deal">
                <path d="M 12 4 L 46 4 L 46 7 A 2.2 2.2 0 0 0 46 11.4 L 46 16 L 12 16 L 12 11.4 A 2.2 2.2 0 0 0 12 7 Z" />
            </g>
            <path class="occ" d="M 8 9 L 42 9 L 42 12 A 2.2 2.2 0 0 0 42 16.4 L 42 21 L 8 21 L 8 16.4 A 2.2 2.2 0 0 0 8 12 Z" />
            <path class="occ" d="M 4 14 L 38 14 L 38 17 A 2.2 2.2 0 0 0 38 21.4 L 38 26 L 4 26 L 4 21.4 A 2.2 2.2 0 0 0 4 17 Z" />
            <path d="M 11 16.5 L 11 23.5" class="dashed" />
            <path d="M 15 18.5 L 33 18.5" />
            <path d="M 15 22 L 27 22" class="thin" />
        </svg>
    </span>

    <span v-else-if="key === 'socks'" class="icon ic-s1 prod-ico" aria-hidden="true">
        <svg viewBox="0 0 40 40" width="20" height="20">
            <g class="sock-a">
                <path d="M 9 8 L 9 23 Q 9 29 13 29 L 19 29 Q 21.5 29 21.5 26 L 15 24 L 15 8 Z" />
                <path d="M 9 11.5 L 15 11.5" class="thin" />
                <path d="M 11 8 L 11 11.2" class="thin" />
                <path d="M 13 8 L 13 11.2" class="thin" />
                <circle class="grip g1" cx="13.6" cy="27.6" r="0.95" />
                <circle class="grip g2" cx="16.2" cy="27.9" r="0.95" />
                <circle class="grip g3" cx="18.8" cy="27.4" r="0.95" />
            </g>
            <g class="sock-b">
                <path d="M 23 8 L 23 23 Q 23 29 27 29 L 33 29 Q 35.5 29 35.5 26 L 29 24 L 29 8 Z" />
                <path d="M 23 11.5 L 29 11.5" class="thin" />
                <path d="M 25 8 L 25 11.2" class="thin" />
                <path d="M 27 8 L 27 11.2" class="thin" />
                <circle class="grip g4" cx="27.6" cy="27.6" r="0.95" />
                <circle class="grip g5" cx="30.2" cy="27.9" r="0.95" />
                <circle class="grip g6" cx="32.8" cy="27.4" r="0.95" />
            </g>
        </svg>
    </span>

    <!-- ══ LOS CINCO DEL SET DEL ARTBOARD (`#258`) ══════════════════════════════════════════════
         Los seis de arriba son ILUSTRACIONES en su propia escala (40×40 · 50×32 · 60×36) y siguen
         ofreciéndose: son el aspecto que el catálogo ya tiene, y **retirarlos degradaría en silencio
         cada producto que los tenga guardados en `ticket_types.icon`** — `forProduct()` trata una
         clave desconocida como ausente. Estos cinco entran AL LADO, en la rejilla de 24 del set.
         ⚠️ `pack`, `party` y `school-trip` salen del LOTE 2 del artboard, que el cliente dibujó y
         nunca cerró; la variante la elige su propio texto (ver cada componente Blade). -->
    <span v-else-if="key === 'ticket'" class="prod-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M4.6 6.2h9.9a1.5 1.5 0 0 0 3 0h1.9A2.6 2.6 0 0 1 22 8.8v6.4a2.6 2.6 0 0 1-2.6 2.6h-1.9a1.5 1.5 0 0 0-3 0H4.6A2.6 2.6 0 0 1 2 15.2V8.8a2.6 2.6 0 0 1 2.6-2.6zM16 8.9a0.8 0.8 0 0 0-0.8 0.8v4.6a0.8 0.8 0 0 0 1.6 0V9.7a0.8 0.8 0 0 0-0.8-0.8z" />
        </svg>
    </span>

    <span v-else-if="key === 'gift'" class="prod-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M3.4 9.2h17.2v3.4h-1.2v7.2a1.8 1.8 0 0 1-1.8 1.8H6.4a1.8 1.8 0 0 1-1.8-1.8v-7.2H3.4zm7 3.4v6.6h3.2v-6.6z" />
            <circle cx="8.9" cy="5.6" r="2.8" />
            <circle cx="15.1" cy="5.6" r="2.8" />
        </svg>
    </span>

    <span v-else-if="key === 'pack'" class="prod-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M4.4 6.4h2.9a1.5 1.5 0 0 0 3 0h3.4a1.5 1.5 0 0 0 3 0h2.9A2.4 2.4 0 0 1 22 8.8v6.4a2.4 2.4 0 0 1-2.4 2.4h-2.9a1.5 1.5 0 0 0-3 0h-3.4a1.5 1.5 0 0 0-3 0H4.4A2.4 2.4 0 0 1 2 15.2V8.8a2.4 2.4 0 0 1 2.4-2.4zM8.8 9.1a0.8 0.8 0 1 0 0 1.6 0.8 0.8 0 1 0 0-1.6zm0 2.1a0.8 0.8 0 1 0 0 1.6 0.8 0.8 0 1 0 0-1.6zm0 2.1a0.8 0.8 0 1 0 0 1.6 0.8 0.8 0 1 0 0-1.6zm6.4-4.2a0.8 0.8 0 1 0 0 1.6 0.8 0.8 0 1 0 0-1.6zm0 2.1a0.8 0.8 0 1 0 0 1.6 0.8 0.8 0 1 0 0-1.6zm0 2.1a0.8 0.8 0 1 0 0 1.6 0.8 0.8 0 1 0 0-1.6z" />
        </svg>
    </span>

    <span v-else-if="key === 'party'" class="prod-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linejoin="round">
            <path d="M2.6 7.2q9.4 3 18.8 0" fill="none" stroke-width="3" stroke-linecap="round" />
            <path d="M3.4 9.4h4L5.4 14z" />
            <path d="M10 10.2h4l-2 4.6z" />
            <path d="M16.6 9.4h4L18.6 14z" />
        </svg>
    </span>

    <span v-else-if="key === 'school-trip'" class="prod-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M5.6 3.8h12.8a3 3 0 0 1 3 3v8.4a3 3 0 0 1-3 3H5.6a3 3 0 0 1-3-3V6.8a3 3 0 0 1 3-3zm1.2 3.4a1.3 1.3 0 0 0-1.3 1.3v2.2a1.3 1.3 0 0 0 1.3 1.3h10.4a1.3 1.3 0 0 0 1.3-1.3V8.5a1.3 1.3 0 0 0-1.3-1.3z" />
            <circle cx="7.6" cy="19.4" r="2.4" />
            <circle cx="16.4" cy="19.4" r="2.4" />
        </svg>
    </span>

    <!-- La entrada troquelada: el respaldo del dominio para lo que no es pack, y también el
         destino de una clave desconocida (normalizada arriba). Rama EXPLÍCITA, no `v-else`. -->
    <span v-else-if="key === 'ticket-tear-off'" class="tk prod-ico" aria-hidden="true">
        <svg viewBox="0 0 60 36" width="22" height="13" fill="none">
            <g class="body">
                <path d="M 4 4 L 42 4 L 42 8 A 1.4 1.4 0 0 0 42 12 L 42 16 A 1.4 1.4 0 0 0 42 20 L 42 24 A 1.4 1.4 0 0 0 42 28 L 42 32 L 4 32 L 4 28 A 1.4 1.4 0 0 0 4 24 L 4 20 A 1.4 1.4 0 0 0 4 16 L 4 12 A 1.4 1.4 0 0 0 4 8 Z" />
                <line x1="14" y1="14" x2="34" y2="14" class="thin" />
                <line x1="14" y1="20" x2="34" y2="20" class="thin" />
            </g>
            <path class="dashed" d="M 42 5.5 L 42 30.5" />
            <g class="stub">
                <path d="M 42 4 L 56 4 L 56 8 A 1.4 1.4 0 0 0 56 12 L 56 16 A 1.4 1.4 0 0 0 56 20 L 56 24 A 1.4 1.4 0 0 0 56 28 L 56 32 L 42 32 L 42 28 A 1.4 1.4 0 0 1 42 24 L 42 20 A 1.4 1.4 0 0 1 42 16 L 42 12 A 1.4 1.4 0 0 1 42 8 Z" />
                <text class="stubnum" x="49" y="22.5" text-anchor="middle">1</text>
            </g>
        </svg>
    </span>
</template>

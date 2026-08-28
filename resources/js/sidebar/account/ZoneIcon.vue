<script setup>
import { ZONES } from './navigation.js';

/**
 * **El icono de cada entrada del índice de «Mi cuenta»** (`docs/specs/account-context-vue.md`).
 *
 * ⚠️⚠️ **Los cinco dibujos son COPIA EXACTA de un `<x-icons.*>` del sistema de diseño**, y no se
 * escriben a ojo: se generaron desde el render real de Blade. Es lo que exige `SidebarIconParityTest`
 * —cada geometría del cajón tiene que ser, byte a byte tras normalizar, la de un componente— y lo
 * que impide que el día que alguien retoque un icono en Blade el cajón se quede con el viejo.
 *
 * ⚠️ **`ticket-tear-off` NO sirve para «Mis reservas»**, aunque el nombre invite: es la ILUSTRACIÓN
 * del CTA de compra —viewBox 60×36 y con texto dentro— y a 18 px no se lee. De ahí `calendar`, en el
 * idioma pequeño de `user`: 18×18 sobre 24, trazo 1.7 y `currentColor`.
 *
 * ⚠️ **Un componente y no un mapa de datos**: una geometría no es un dato que se pueda guardar en
 * `navigation.js` sin convertirla en una cadena que nadie puede revisar. Aquí cada rama se lee, y el
 * gate de paridad la compara con su fuente.
 */
defineProps({
    /** La zona cuya entrada se está pintando. Una zona sin icono no pinta nada, y no falla. */
    zone: { type: String, default: '' },
});
</script>

<template>
    <!--
      `aria-hidden` en los cinco: el nombre accesible de la entrada lo da su TEXTO, que va justo al
      lado. Un icono anunciado además del rótulo lo diría todo dos veces.
    -->
        <!-- `calendar` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <svg v-if="zone === ZONES.ORDERS" width="18" height="18" viewBox="0 0 24 24" fill="none"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <rect x="3.6" y="5.4" width="16.8" height="14.2" rx="2.2" stroke="currentColor" stroke-width="1.7" />
            <path d="M3.6 10.1h16.8" stroke="currentColor" stroke-width="1.7" />
            <path d="M8.4 3.4v3.4M15.6 3.4v3.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
        </svg>

        <!-- `user` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <svg v-else-if="zone === ZONES.PROFILE" width="18" height="18" viewBox="0 0 24 24" fill="none"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <circle cx="12" cy="8" r="3.4" stroke="currentColor" stroke-width="1.7" />
            <path d="M5 19.5c0-3.4 3.1-5.6 7-5.6s7 2.2 7 5.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
        </svg>

        <!-- `lock` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <svg v-else-if="zone === ZONES.PASSWORD" width="18" height="18" viewBox="0 0 24 24" fill="none"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <rect x="4.6" y="10.4" width="14.8" height="9.2" rx="2.2" stroke="currentColor" stroke-width="1.7" />
            <path d="M8.4 10.4V7.9a3.6 3.6 0 0 1 7.2 0v2.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
        </svg>

        <!-- `devices` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <svg v-else-if="zone === ZONES.SESSIONS" width="18" height="18" viewBox="0 0 24 24" fill="none"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <rect x="2.6" y="5" width="12.6" height="9" rx="1.8" stroke="currentColor" stroke-width="1.7" />
            <path d="M6.2 17.6h5.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            <rect x="16.8" y="9.4" width="4.8" height="9.6" rx="1.4" stroke="currentColor" stroke-width="1.7" />
        </svg>

        <!-- `shield` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <svg v-else-if="zone === ZONES.PRIVACY" width="18" height="18" viewBox="0 0 24 24" fill="none"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <path d="M12 3.1 19.2 6v5.5c0 4.3-3 7.6-7.2 9.4-4.2-1.8-7.2-5.1-7.2-9.4V6z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
        </svg>

        <!-- `users` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). Nace con
             «Menores a cargo» (Fase 6 · C): reutilizar `user` habría dejado dos entradas del índice
             con el MISMO dibujo, justo donde el cliente elige entre «Tus datos» y sus menores. -->
        <svg v-else-if="zone === ZONES.DEPENDENTS" width="18" height="18" viewBox="0 0 24 24" fill="none"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <circle cx="9" cy="8.2" r="3.2" stroke="currentColor" stroke-width="1.7" />
            <path d="M2.8 19.5c0-3.2 2.8-5.3 6.2-5.3s6.2 2.1 6.2 5.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            <circle cx="16.4" cy="9.4" r="2.5" stroke="currentColor" stroke-width="1.7" />
            <path d="M15.4 14.3c3.2.1 5.9 2.2 5.9 5.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
        </svg>

        <!-- `receipt` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`).
             ⚠️ Nace con la zona, como `shield` nació con privacidad: no había ningún icono pequeño
             que dijera «pedido», y reusar el `calendar` de «Mis reservas» habría dejado dos entradas
             del índice con el MISMO dibujo, que es donde el cliente elige entre las dos. -->
        <svg v-else-if="zone === ZONES.PURCHASES" width="18" height="18" viewBox="0 0 24 24" fill="none"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <path d="M5.4 3.6h13.2v16.8l-2.64-1.5-2.64 1.5-2.64-1.5-2.64 1.5-2.64-1.5z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
            <path d="M8.8 8.4h6.4M8.8 12.2h4.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
        </svg>

        <!-- `qr` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). Nace con
             «Mi carné» (Fase 6 · A, `specs/identidad-qr-puerta.md` §9.6 B·3): no había ningún
             icono pequeño que dijera «código», y es la entrada que el cliente busca en la puerta. -->
        <svg v-else-if="zone === ZONES.CARD" width="18" height="18" viewBox="0 0 24 24" fill="none"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <rect x="3.6" y="3.6" width="6.6" height="6.6" rx="1.4" stroke="currentColor" stroke-width="1.7" />
            <rect x="13.8" y="3.6" width="6.6" height="6.6" rx="1.4" stroke="currentColor" stroke-width="1.7" />
            <rect x="3.6" y="13.8" width="6.6" height="6.6" rx="1.4" stroke="currentColor" stroke-width="1.7" />
            <rect x="13.8" y="13.8" width="2.8" height="2.8" rx="0.6" fill="currentColor" />
            <rect x="17.6" y="13.8" width="2.8" height="2.8" rx="0.6" fill="currentColor" />
            <rect x="13.8" y="17.6" width="2.8" height="2.8" rx="0.6" fill="currentColor" />
            <rect x="17.6" y="17.6" width="2.8" height="2.8" rx="0.6" fill="currentColor" />
        </svg>
</template>

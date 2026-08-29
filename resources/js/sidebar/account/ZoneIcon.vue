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
        <!-- ⚠️ Pasa de `calendar` a `booking` (`#258`), y el argumento es del propio artboard: al
             razonar `ui/reserva` escribe que «la landing usa `ui/fecha` para reservar, y **un
             calendario a secas no dice que la plaza ya esté cogida**». Aquí la entrada es «Mis
             reservas» —lo ya reservado—, no un selector de fecha.
             `booking` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <svg v-if="zone === ZONES.ORDERS" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M7.4 2.6a1.6 1.6 0 0 1 1.6 1.6v.6h6v-.6a1.6 1.6 0 0 1 3.2 0v.6h.6a2.8 2.8 0 0 1 2.8 2.8v10.8a2.8 2.8 0 0 1-2.8 2.8H5.2a2.8 2.8 0 0 1-2.8-2.8V7.6a2.8 2.8 0 0 1 2.8-2.8h.6v-.6a1.6 1.6 0 0 1 1.6-1.6zM5.6 10.6v8h12.8v-8z" />
            <path d="M8.4 14.6 10.9 17.1 15.6 12.4" fill="none" stroke="currentColor" stroke-width="2.9" stroke-linecap="round" stroke-linejoin="round" />
        </svg>

        <!-- `user` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <svg v-else-if="zone === ZONES.PROFILE" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <circle cx="12" cy="7.8" r="4.2" />
            <path d="M4.4 20.4a7.6 7.6 0 0 1 15.2 0 1.4 1.4 0 0 1-1.4 1.4H5.8a1.4 1.4 0 0 1-1.4-1.4z" />
        </svg>

        <!-- `lock` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <svg v-else-if="zone === ZONES.PASSWORD" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M4.6 13a2.6 2.6 0 0 1 2.6-2.6h9.6A2.6 2.6 0 0 1 19.4 13v5.8a2.6 2.6 0 0 1-2.6 2.6H7.2a2.6 2.6 0 0 1-2.6-2.6zm7.4 1.8a1.7 1.7 0 0 0-1 3.1v1.1a1 1 0 0 0 2 0v-1.1a1.7 1.7 0 0 0-1-3.1z" />
            <path d="M8.2 10.4V8.2a3.8 3.8 0 0 1 7.6 0v2.2" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>

        <!-- `devices` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <svg v-else-if="zone === ZONES.SESSIONS" width="18" height="18" viewBox="0 0 24 24" fill="none"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <rect x="2.6" y="5" width="12.6" height="9" rx="1.8" stroke="currentColor" stroke-width="1.7" />
            <path d="M6.2 17.6h5.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            <rect x="16.8" y="9.4" width="4.8" height="9.6" rx="1.4" stroke="currentColor" stroke-width="1.7" />
        </svg>

        <!-- `shield` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <svg v-else-if="zone === ZONES.PRIVACY" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2.2 4 5.1v6.6c0 4.9 3.3 8.7 8 10.5 4.7-1.8 8-5.6 8-10.5V5.1zm-1 13.6L7.2 12l2-2 1.8 1.8 4.2-4.2 2 2z" />
        </svg>

        <!-- `users` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). Nace con
             «Menores a cargo» (Fase 6 · C): reutilizar `user` habría dejado dos entradas del índice
             con el MISMO dibujo, justo donde el cliente elige entre «Tus datos» y sus menores. -->
        <svg v-else-if="zone === ZONES.DEPENDENTS" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <circle cx="8.6" cy="7.6" r="3.6" />
            <circle cx="17.2" cy="8.8" r="2.8" />
            <path d="M2.4 19.8a6.2 6.2 0 0 1 12.4 0 1.2 1.2 0 0 1-1.2 1.2H3.6a1.2 1.2 0 0 1-1.2-1.2z" />
            <path d="M16.4 13.6a5.2 5.2 0 0 1 5.2 6.2 1.2 1.2 0 0 1-1.2 1.2h-2.6v-1.2a7.8 7.8 0 0 0-1.6-4.8z" />
        </svg>

        <!-- `receipt` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`).
             ⚠️ Nace con la zona, como `shield` nació con privacidad: no había ningún icono pequeño
             que dijera «pedido», y reusar el `calendar` de «Mis reservas» habría dejado dos entradas
             del índice con el MISMO dibujo, que es donde el cliente elige entre las dos. -->
        <svg v-else-if="zone === ZONES.PURCHASES" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
             class="catalog__ico" aria-hidden="true" focusable="false">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M4.8 3.4a1.6 1.6 0 0 1 1.6-1.6h11.2a1.6 1.6 0 0 1 1.6 1.6v18.8l-3.2-2-3.2 2-3.2-2-3.2 2zm3.4 3.2a1.3 1.3 0 0 0 0 2.6h7.6a1.3 1.3 0 0 0 0-2.6zm0 4.8a1.3 1.3 0 0 0 0 2.6h4.8a1.3 1.3 0 0 0 0-2.6z" />
        </svg>

        <!-- `qr` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). Nace con
             «Mi carné» (Fase 6 · A, `specs/identidad-qr-puerta.md` §9.6 B·3): no había ningún
             icono pequeño que dijera «código», y es la entrada que el cliente busca en la puerta. -->
        <svg v-else-if="zone === ZONES.CARD" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
             stroke="currentColor" stroke-width="3" class="catalog__ico" aria-hidden="true" focusable="false">
            <rect x="3.5" y="3.5" width="6.4" height="6.4" rx="1.4" fill="none" />
            <rect x="14.1" y="3.5" width="6.4" height="6.4" rx="1.4" fill="none" />
            <rect x="3.5" y="14.1" width="6.4" height="6.4" rx="1.4" fill="none" />
            <rect x="13.4" y="13.4" width="3.4" height="3.4" rx="1" stroke="none" />
            <rect x="18.2" y="17.6" width="3.4" height="3.4" rx="1" stroke="none" />
            <rect x="13.4" y="18.2" width="3" height="3" rx="1" stroke="none" />
        </svg>
</template>

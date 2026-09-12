<script setup>
import { t as translate, tp as interpolate } from '../i18n.js';

/**
 * Paso 7 — «revisa tu correo» (Fase 4 · paso 4.4b·1).
 *
 * Tres nodos, y aun así merece explicación: **dentro de la compra esta pantalla casi nunca la ve una
 * persona**. El alta embebida es *pay-first* —crea la cuenta, inicia sesión y sigue al pago—, así que
 * aquí solo se llega cuando el servidor **fingió** un alta: el señuelo actuó. La web hace exactamente
 * lo mismo (`Register::finishGeneric()` → `registration-submitted` → paso 7), y es lo que impide que
 * un bot distinga un alta buena de una fingida.
 *
 * ⚠️ **No lleva salida, y eso es fiel**: el Blade tampoco la tiene. El escape «¿ya tienes cuenta?»
 * vive en la pantalla `sent` del componente Register, que **embebido no llega a verse** —el paso 5
 * deja de renderizarse en cuanto el cajón pasa al 7—. Verificado: el HTML del paso 7 no contiene ni el
 * formulario ni el escape.
 *
 * ⚠️ `role="status"` es contrato: lo anuncia un lector de pantalla sin robar el foco.
 */
const props = defineProps({
    messages: { type: Object, default: () => ({}) },

    /**
     * A qué buzón se ha escrito. Lo sabe el store de auth desde el propio alta (`pendingEmail`), así
     * que no cuesta ni una petición ni un campo de contrato (`#563`).
     *
     * ⚠️ **Vacío es una respuesta, no una falta**: sin correo se pinta la frase genérica. Esta
     * pantalla también se alcanza recargando, y entonces el store nace limpio — inventar un buzón
     * sería peor que no nombrarlo.
     */
    email: { type: String, default: '' },
});

const t = (key) => translate(props.messages, key);
const tp = (key, params) => interpolate(props.messages, key, params);
</script>

<template>
    <div class="purchase__confirm" role="status">
        <!-- ⚠️⚠️ **Superficie, NO pegatina de estado** (`#563`, artboard `Pago y Desenlaces PJP`): las
             cuatro pegatinas del sistema dicen que algo HA PASADO —éxito, error, aviso, espera— y aquí
             no ha pasado nada: se está esperando a una persona. Por eso es una caja de superficie con
             el canto de tarjeta, y no un círculo de color con sombra dura.
             `mail` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <div class="purchase__party purchase__party--inert" aria-hidden="true">
            <span class="state-tile">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M2.6 6.2a1.8 1.8 0 0 1 1.8-1.8h15.2a1.8 1.8 0 0 1 1.8 1.8v11.6a1.8 1.8 0 0 1-1.8 1.8H4.4a1.8 1.8 0 0 1-1.8-1.8zm3.2 1.4 6.2 4.4 6.2-4.4z" />
                </svg>
            </span>
        </div>
        <h3 class="wiz__title">{{ t('verify_title') }}</h3>
        <!-- ⚠️ **A qué correo, que es donde se descubre un correo mal tecleado**: es la única pantalla
             del embudo que lo puede decir, y hasta `#563` no lo decía ninguna. -->
        <p class="purchase__note">{{ email ? tp('verify_intro_sent', { email }) : t('verify_intro') }}</p>
        <!-- ⚠️⚠️ **Esta frase llevaba MESES escrita en el servidor y la pantalla no la pedía** (`#563`):
             `verify_hold` está en los tres idiomas con cero consumidores, así que quien llegaba aquí no
             sabía si su plaza seguía guardada. ▶ *Una frase que el servidor ya tiene escrita y la
             pantalla no pide, no existe* — al transcribir una pantalla se compara con el DICCIONARIO,
             no solo con la pantalla de al lado. -->
        <p class="purchase__note">{{ t('verify_hold') }}</p>
    </div>
</template>

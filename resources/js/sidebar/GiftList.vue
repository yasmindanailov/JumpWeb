<script setup>
/**
 * LOS REGALOS de un producto (`#589`, `[DECIDIDO owner]`): cada uno en su etiqueta amarilla con la caja
 * de regalo delante. Gemelo de `components/site/gifts.blade.php` —mismas clases—, así que la web y el
 * cajón los pintan con UNA regla de CSS.
 *
 * ⚠️ Todo son `<span>`: vive dentro del `<button>` de la tarjeta del catálogo y del `<label>` de un
 * complemento, donde una lista no es contenido válido.
 * ⚠️ El dibujo es `ProductIcon` con la clave `gift` y no una copia del SVG: una copia más sería otra
 * paridad que vigilar (`SidebarIconParityTest`).
 * ⚠️ `gifts` admite `undefined` (su defecto es la lista vacía): un cliente de la API anterior a `#589`
 * no manda el campo, y el cajón no puede romperse por eso.
 */
import ProductIcon from './ProductIcon.vue';

defineProps({
    gifts: { type: Array, default: () => [] },
    /** El prefijo para lector de pantalla («De regalo»); lo traduce quien monta el componente. */
    label: { type: String, default: '' },
});
</script>

<template>
    <span v-if="gifts.length" class="gifts">
        <span v-for="(gift, i) in gifts" :key="i" class="gift">
            <ProductIcon icon="gift" />
            <span><span v-if="label" class="sr-only">{{ label }}: </span>{{ gift }}</span>
        </span>
    </span>
</template>

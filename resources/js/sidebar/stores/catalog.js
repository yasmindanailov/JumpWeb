import { defineStore } from 'pinia';
import { minQuantityFor } from '../offer.js';

/**
 * El CATÁLOGO y el producto elegido — paso 1 (reorganización del SPA, 2026-08-22).
 *
 * ⚠️ **Ninguna regla de producto vive aquí.** Qué se vende, cuál va destacado y desde qué precio lo
 * decide `GET /catalog/products` y llega ya resuelto; agruparlo en las dos secciones es de
 * `catalog.js`. Este store guarda lo que llegó y qué se eligió.
 */
export const useCatalogStore = defineStore('catalog', {
    state: () => ({
        /** Las dos secciones (`entries` · `services`), tal y como las agrupa `catalog.js`. */
        sections: [],

        /** ¿Se enseña el buscador? Lo decide el umbral que publica `GET /config`. */
        searchEnabled: false,

        /** El id del producto elegido, o `null`. */
        selectedId: null,

        /**
         * La FILA del catálogo del producto elegido — nombre y tipo—, disponible sin esperar la ficha.
         *
         * ⚠️ Sale del listado que ya está en memoria y NO de la ficha que se está pidiendo: la banda de
         * progreso los enseña de inmediato al entrar en el calendario, y esperar a la ficha dejaría el
         * contexto en blanco durante el viaje.
         */
        selectedRow: null,

        /** La FICHA completa del producto (mínimo contratable, esquema de campos), o `null`. */
        product: null,

        /**
         * Las ETIQUETAS del esquema de evento, por producto.
         *
         * ⚠️ Se guardan porque **el carrito las necesita más tarde**, cuando ya se está mirando otra
         * cosa: el presupuesto NO devuelve las respuestas del pack (RGPD, son datos de un menor) y sin
         * las etiquetas no hay con qué emparejarlas.
         */
        fieldsByProduct: {},
    }),

    getters: {
        /** El suelo del selector de cantidad. En un pack es su mínimo contratable; si no, 1. */
        minQuantity: (state) => minQuantityFor(state.product),

        isPack: (state) => state.product?.type === 'pack',
    },

    actions: {
        setSections(sections) {
            this.sections = Array.isArray(sections) ? sections : [];
        },

        setSearchEnabled(enabled) {
            this.searchEnabled = enabled === true;
        },

        /** Elige un producto por id y resuelve su fila desde el listado ya descargado. */
        select(id) {
            this.selectedId = id;
            this.selectedRow = this.sections.flatMap((s) => s.items ?? []).find((item) => item.id === id) ?? null;
            this.product = null;
        },

        setProduct(product) {
            this.product = product ?? null;
        },

        /** Recuerda las etiquetas del esquema de un producto sin perder las de los demás. */
        rememberFields(id, fields) {
            this.fieldsByProduct = { ...this.fieldsByProduct, [id]: fields ?? [] };
        },

        /** Deja la elección en blanco. ⚠️ Las etiquetas NO se borran: la cesta puede seguir necesitándolas. */
        clearSelection() {
            this.selectedId = null;
            this.selectedRow = null;
            this.product = null;
        },
    },
});

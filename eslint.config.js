// Análisis estático del cajón (`DECISIONES #625`, `[DECIDIDO owner]` 2026-09-17): ESLint con las reglas de
// Vue sobre `resources/js/sidebar/`, con LÍNEA BASE —los 12 errores del día que entró (2026-09-18) viven en
// `eslint-suppressions.json` y solo corta un error NUEVO—. Corre en el `pre-push` (`npm run lint:js`).
//
// El juego de reglas es `flat/essential` A PROPÓSITO: es el que previene errores (una variable sin definir,
// un import que no se usa, una directiva mal escrita). Medido antes de activarlo: `strongly-recommended` y
// `recommended` añaden 4.364 y 4.375 AVISOS de formato (sangría, atributos por línea) sobre los MISMOS 12
// errores; eso es un formateador, no una red, y el cajón no tiene uno con el que ponerse de acuerdo.
//
// `no-use-before-define` es la regla que pedía `DEUDA.md` (`#210`: un `watch` por encima de su `const`). Entra
// en la variante de coste CERO (`variables: false`: solo el uso en el MISMO ámbito). Medido: con
// `variables: true` salen 16 errores en 3 ficheros y los muestreados son funciones que corren DESPUÉS de que
// la constante exista (falsos positivos que pararían al carril del SPA); el caso del callback síncrono
// (`watchEffect`) lo sigue vigilando `SidebarSetupBindingsTest`. La prop sombreada (`const auth`) la caza
// `vue/no-dupe-keys`, que ya viene en `flat/essential`.
//
// ⚠️ La línea base no se regenera para tapar un error nuevo: se arregla el error. `StaticAnalysisGateTest`
// fija el juego de reglas, el alcance y el trinquete de la línea base (solo baja).
import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import globals from 'globals';

export default [
    js.configs.recommended,
    ...vue.configs['flat/essential'],
    {
        files: ['resources/js/sidebar/**/*.{js,vue}'],
        languageOptions: { ecmaVersion: 'latest', sourceType: 'module', globals: { ...globals.browser } },
        rules: { 'no-use-before-define': ['error', { functions: false, classes: true, variables: false }] },
    },
    {
        files: ['resources/js/sidebar/**/*.test.js'],
        languageOptions: { globals: { ...globals.node } },
    },
];

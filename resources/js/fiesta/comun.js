/*
 * Lo que comparten las páginas de la fiesta (`specs/fiesta-sistema-nuevo.md` §4.5): el idioma (el desplegable nativo de
 * la cabecera lleva en cada opción el enlace que lo cambia), Intro que no envía, y el arranque que pone la clase `js`
 * AL FINAL: si algo falla, el documento se queda en `no-js`, que es un formulario completo.
 */
/* global document, location */

const de = document.documentElement;

export const q = (sel, raiz = document) => raiz.querySelector(sel);
export const qa = (sel, raiz = document) => [...raiz.querySelectorAll(sel)];

/** El idioma: elegir en el desplegable cambia de idioma (`lang.switch`, que vuelve aquí desde la sesión). */
export function idioma() {
    const select = q('[data-idioma-select]');
    if (!select) return;
    select.addEventListener('change', () => {
        const destino = select.value;
        if (destino) location.href = destino;
    });
}

/** Intro cierra el teclado y no envía: la acción es siempre un toque en su botón. */
export function enterNoEnvia(campo) {
    if (!campo) return;
    campo.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); campo.blur(); }
    });
}

/** Arranca la página: todo lo de dentro, y la clase `js` solo si nada falló. */
export function arranca(...pasos) {
    try {
        pasos.forEach((paso) => paso());
        de.classList.remove('no-js');
        de.classList.add('js');
    } catch {
        de.classList.remove('js');
        de.classList.add('no-js');
    }
}

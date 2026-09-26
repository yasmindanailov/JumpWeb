/*
 * Lo que comparten las páginas de la fiesta (`specs/fiesta-sistema-nuevo.md` §4.5): el idioma (el desplegable nativo de
 * la cabecera lleva en cada opción el enlace que lo cambia), Intro que no envía, y el arranque que pone la clase `js`
 * AL FINAL: si algo falla, el documento se queda en `no-js`, que es un formulario completo.
 */
/* global document, location, window, setTimeout */

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

/**
 * LA FIRMA (la página de la autorización y, desde F6a, el recibo): con sesión, el menor se ELIGE —los datos de la
 * opción van a los campos; rellenar no dispara nada más; «a mano» los vacía—.
 */
export function menores() {
    const pick = q('[data-guardian-pick-select]');
    if (!pick) return;
    const pon = (name, valor) => {
        const el = q(`[name="${name}"]`);
        if (el) el.value = valor || '';
    };
    pick.addEventListener('change', () => {
        const opt = pick.options[pick.selectedIndex];
        const elegido = Boolean(opt && opt.value !== '');
        pon('minor_name', elegido ? opt.dataset.name : '');
        pon('minor_surname', elegido ? opt.dataset.surname : '');
        pon('minor_born_on', elegido ? opt.dataset.bornOn : '');
        // La relación es del ADULTO con el menor, pero la ficha del menor a cargo ya la trae: solo se pone si viene.
        if (elegido && opt.dataset.relationship) pon('guardian_relationship', opt.dataset.relationship);
    });
}

/** Firmar: el botón dice «Firmando» y se bloquea mientras el formulario viaja; con errores del servidor, el foco al primero. */
export function firma() {
    const form = q('[data-firma]');
    if (!form) return;
    form.addEventListener('submit', () => {
        const boton = q('[data-firma-boton]', form);
        if (!boton) return;
        boton.setAttribute('aria-busy', 'true');
        boton.disabled = true;
        const texto = form.dataset.firmando || '';
        if (texto) boton.textContent = texto;
    });
    const primero = qa('.pz-campo--error input, .pz-selector--error select, .pz-casilla--error input', form)[0];
    if (!primero) return;
    const enfoca = () => primero.focus({ preventScroll: false });
    // ⚠️ Con un ancla en la URL (el recibo vuelve a `#inv-h-aut`, F6a) el navegador corre al terminar de cargar los
    // «pasos de enfoque» del ancla y, como un título no es enfocable, deja el foco en el documento: medido en navegador,
    // el foco acababa en `body`. Se enfoca DESPUÉS de la carga.
    if (location.hash && document.readyState !== 'complete') {
        window.addEventListener('load', () => setTimeout(enfoca, 0), { once: true });
    } else {
        enfoca();
    }
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

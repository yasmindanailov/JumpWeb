/*
 * Lo que comparten las páginas de la fiesta (`specs/fiesta-sistema-nuevo.md` §4.5): Intro que no envía, la firma, y el
 * arranque que pone la clase `js` AL FINAL: si algo falla, el documento se queda en `no-js`, que es un formulario
 * completo. (El idioma ya no necesita JavaScript: son tres enlaces de texto al pie, `#748`.)
 */

import { sugerirCorreo } from './logica.js';

const de = document.documentElement;

export const q = (sel, raiz = document) => raiz.querySelector(sel);
export const qa = (sel, raiz = document) => [...raiz.querySelectorAll(sel)];

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

/**
 * «¿Querías decir …?» (F9, el zip tercero `#780`: `forms/Field.jsx`, `suggest`): al SALIR de un campo de correo se le
 * propone el bien escrito (`sugerirCorreo`) y un toque lo escribe; al volver a entrar, se esconde. El botón y su texto
 * los pinta la pieza (`x-pieza.campo`); aquí solo se rellena y se enseña.
 */
export function sugerencias(raiz = document) {
    qa('[data-sugerencia]', raiz).forEach((boton) => {
        const campo = document.getElementById(boton.dataset.sugerencia);
        const valor = q('[data-sugerencia-valor]', boton);
        if (!campo || !valor) return;
        const esconde = () => { boton.hidden = true; };
        campo.addEventListener('focus', esconde);
        campo.addEventListener('input', esconde);
        campo.addEventListener('blur', () => {
            const sug = sugerirCorreo(campo.value);
            valor.textContent = sug || '';
            boton.hidden = !sug;
        });
        boton.addEventListener('click', () => {
            if (!valor.textContent) return;
            campo.value = valor.textContent;
            campo.dispatchEvent(new Event('input', { bubbles: true }));
            esconde();
        });
    });
}

/**
 * EL PRIMARIO LLEGA (F9, `core/Button.jsx`, `arrive`): la primera vez que entra en pantalla, el bote del sistema y UN
 * brillo —lo único que se mueve en ese momento—. Una vez por botón, nunca en bucle, nunca sobre tinta (la barra de
 * Guardar, «Vamos»: `data-surface="ink"`) y nada con «reducir movimiento». Los keyframes son los que `isla.css` ya porta
 * (`isla-bote`, `isla-sheen`); la clase, `fiesta.css`.
 */
export function llegadas(raiz = document) {
    if (typeof IntersectionObserver === 'undefined') return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    qa('[data-llega]', raiz).forEach((boton) => {
        if (boton.closest('[data-surface="ink"]')) return;
        const io = new IntersectionObserver((vistos) => {
            if (!vistos.some((v) => v.isIntersecting)) return;
            io.disconnect();
            boton.classList.add('pz-boton--llega');
            setTimeout(() => boton.classList.remove('pz-boton--llega'), 1600);
        }, { threshold: 0.25 });
        io.observe(boton);
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

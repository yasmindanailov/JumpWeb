/*
 * LA AUTORIZACIÓN de un menor invitado — el comportamiento de la página (`specs/fiesta-sistema-nuevo.md` §4.5, T3).
 * Lo poco que `AutPagina` hace con React y aquí se hace a mano: el idioma, el selector de menores a cargo (rellena la
 * ficha y NO envía; «a mano» la vacía), «Firmando» mientras el formulario viaja, y el foco en el primer campo con error
 * cuando el servidor devuelve la hoja. ⚠️ Nada de esto ESCRIBE: escribe el formulario. Sin JavaScript firma igual.
 */
import './fiesta.css';
import { arranca, idioma, q, qa } from './comun.js';

/* ── Con sesión, el menor se ELIGE: los datos de la opción van a los campos; rellenar no dispara nada más. ─────── */
function menores() {
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

/* ── Firmar: el botón dice «Firmando» y se bloquea mientras el formulario viaja. ──────────────────────────────── */
function firma() {
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
    // El servidor devolvió la hoja con errores: el foco, en el primero (el diseño hace lo mismo al validar).
    const primero = qa('.pz-campo--error input, .pz-selector--error select, .pz-casilla--error input', form)[0];
    if (primero) primero.focus({ preventScroll: false });
}

arranca(idioma, menores, firma);

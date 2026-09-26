/*
 * LA LISTA DE INVITADOS del sistema nuevo — el comportamiento de la página (`specs/fiesta-sistema-nuevo.md` §4.5).
 *
 * El port a JavaScript plano de lo que `paginas/lista-invitados/estado.jsx` y las zonas hacen con React, sobre el
 * formulario POSICIONAL de siempre: el borrador en este móvil (sobrevive a cerrar la pestaña, se vacía al guardar),
 * las fichas que se abren y cierran, «Añadir a mano» y «Pegar una lista» (rellenan las fichas vacías, una detrás de
 * otra), «Quitar» con deshacer, las cifras que filtran, el número con «Cambiar», los − y + de las cantidades, la barra
 * de Guardar que dice en qué punto está, copiar el enlace y el recordatorio. La lógica pura vive en `logica.js`.
 *
 * ⚠️ Nada de esto ESCRIBE: escribe el único Guardar, y lo que escribe es el formulario. Sin JavaScript el documento se
 * queda en `no-js` (fichas abiertas, campos numéricos), que es un formulario completo (`#264`). La clase `js` se pone
 * AL FINAL: si algo de arriba falla, la página vuelve a `no-js` y nunca queda atascada.
 */
/* global document, localStorage, setTimeout, location, navigator, Event */
import './fiesta.css';
import { NBSP, capitalizar, choice, clave, euros, limpiar, soloEdad, vistaInvitacion } from './logica.js';

const de = document.documentElement;
const q = (sel, raiz = document) => raiz.querySelector(sel);
const qa = (sel, raiz = document) => [...raiz.querySelectorAll(sel)];
const textos = (() => { try { return JSON.parse(q('[data-textos]')?.dataset.textos ?? '{}'); } catch { return {}; } })();
const t = (ruta, fallback = '') => ruta.split('.').reduce((o, k) => (o && o[k] !== undefined ? o[k] : undefined), textos) ?? fallback;

/* ── Los − y + de las cantidades (`pieza/cantidad`): el campo nativo es el valor; los botones lo mueven. ─────────── */
function cantidades(raiz, alCambiar) {
    qa('[data-cantidad]', raiz).forEach((caja) => {
        const campo = q('[data-cantidad-campo]', caja);
        const menos = q('[data-cantidad-menos]', caja);
        const mas = q('[data-cantidad-mas]', caja);
        const valor = q('[data-cantidad-valor]', caja);
        if (!campo || !menos || !mas || !valor) return;
        const min = Number(caja.dataset.min ?? 0);
        const max = caja.dataset.max === undefined ? Infinity : Number(caja.dataset.max);
        const formato = caja.dataset.formato;
        const pinta = () => {
            const n = parseInt(campo.value, 10) || 0;
            valor.textContent = formato ? formato.replace(':n', String(n)) : String(n);
            menos.disabled = n <= min;
            mas.disabled = n >= max;
            caja.classList.toggle('pz-cantidad--con', n > 0);
        };
        const pon = (n) => {
            campo.value = String(Math.min(max, Math.max(min, n)));
            pinta();
            campo.dispatchEvent(new Event('input', { bubbles: true }));
        };
        menos.addEventListener('click', () => pon((parseInt(campo.value, 10) || 0) - 1));
        mas.addEventListener('click', () => pon((parseInt(campo.value, 10) || 0) + 1));
        campo.addEventListener('input', pinta);
        pinta();
        if (alCambiar) campo.addEventListener('input', () => alCambiar(caja, parseInt(campo.value, 10) || 0));
    });
}

/* ── Copiar (el enlace de la invitación, el recordatorio) ───────────────────────────────────────────────────────── */
function copiar(texto) {
    try { if (navigator.clipboard && texto) return navigator.clipboard.writeText(texto).catch(() => {}); } catch { /* sin portapapeles */ }

    return Promise.resolve();
}
function compartir(raiz) {
    qa('[data-compartir]', raiz).forEach((fila) => {
        const ok = q('[data-compartir-ok]', fila);
        qa('[data-kind="copy"]', fila).forEach((boton) => boton.addEventListener('click', (e) => {
            e.preventDefault();
            copiar(fila.dataset.valor).then(() => {
                if (!ok) return;
                ok.hidden = false;
                setTimeout(() => { ok.hidden = true; }, 2600);
            });
        }));
    });
}

/* ── LA PRIMERA PANTALLA: la invitación se escribe sola mientras se teclea el nombre ────────────────────────────── */
function primero(form) {
    const campo = q('[data-primero-nombre] input, input[data-primero-nombre]', form) || q('#pli-primero-nombre', form);
    const nombre = q('[data-inv-nombre]', form);
    if (campo && nombre) {
        campo.addEventListener('input', () => { nombre.textContent = campo.value.trim() || '…'; });
    }
    form.addEventListener('submit', (e) => {
        if (!campo || campo.value.trim() !== '') { campo.value = capitalizar(campo.value); return; }
        e.preventDefault();
        campo.focus();
        const caja = campo.closest('.pz-campo');
        if (caja && !q('.pz-campo__error', caja)) {
            caja.classList.add('pz-campo--error');
            const err = document.createElement('span');
            err.className = 'pz-campo__error';
            err.textContent = t('primero.error', 'Escribe su nombre.');
            caja.append(err);
        }
    });
}

/* ── LA LISTA ───────────────────────────────────────────────────────────────────────────────────────────────────── */
function lista(form) {
    const codigo = form.dataset.reserva || location.pathname;
    const claveBorrador = `fiesta-lista-${codigo}`;
    const barra = q('[data-barra]', form);
    const filas = () => qa('[data-fila]', form);
    const camposDe = (fila) => ({ name: q('[data-campo="name"]', fila), age: q('[data-campo="age"]', fila), allergies: q('[data-campo="allergies"]', fila) });

    // Lo que había al abrir: para saber qué cambió (el punto en la inicial, la cuenta de la barra) y para deshacer.
    const inicial = new Map();
    const campos = () => qa('input:not([type=hidden]):not([type=submit]), textarea, select, input[type=hidden][name^="guests["]', form)
        .filter((el) => el.name && !['_token', 'expected_version', 'reply', 'with_names'].includes(el.name) && el.form === form);
    const valorDe = (el) => (el.type === 'checkbox' || el.type === 'radio' ? (el.checked ? el.value : '') : el.value);
    campos().forEach((el) => inicial.set(el, valorDe(el)));
    // Cada «Al final viene» pendiente de guardar (su `rejoin[]`) es un cambio más.
    const cambios = () => campos().filter((el) => inicial.has(el) && inicial.get(el) !== valorDe(el)).length
        + qa('input[type="hidden"][name="rejoin[]"]', form).length;

    // ── Cada fila: abrir y cerrar, el resumen que se reescribe al teclear, Listo, Quitar con deshacer ──
    const pintaFila = (fila) => {
        const c = camposDe(fila);
        if (!c.name) return;
        const nombre = c.name.value.trim();
        const edad = (c.age?.value || '').trim();
        const alergias = (c.allergies?.value || '').trim();
        // Quien cumple (F3a) nunca es una ficha vacía: abre la lista aunque aún no tenga nombre.
        const vacia = nombre === '' && edad === '' && alergias === '' && fila.dataset.origen !== 'invitacion' && fila.dataset.origen !== 'cumple';
        fila.dataset.vacia = vacia ? '1' : '0';
        const n = q('[data-fila-nombre]', fila);
        if (n) { n.textContent = nombre || t('la_lista.sin_nombre', 'Sin nombre'); n.style.color = nombre ? 'var(--text-strong)' : 'var(--text-muted)'; }
        const meta = q('[data-fila-meta]', fila);
        if (meta && fila.dataset.respuesta !== 'no') {
            meta.innerHTML = '';
            const s = document.createElement('span');
            if (edad) { s.className = 'pj-num'; s.textContent = `${edad}${NBSP}años`; } else { s.style.cssText = 'color: var(--text-low); font-weight: var(--fw-semibold);'; s.textContent = t('fila.no_age', 'Falta la edad'); }
            meta.append(s);
            if (alergias) {
                const p = document.createElement('span'); p.setAttribute('aria-hidden', 'true'); p.textContent = '·';
                const a = document.createElement('span'); a.style.cssText = 'min-width: 0; overflow-wrap: anywhere;'; a.textContent = alergias;
                meta.append(p, a);
            }
        }
        const sucio = [c.name, c.age, c.allergies].some((el) => el && inicial.has(el) && inicial.get(el) !== el.value);
        let punto = q('[data-fila-punto]', fila);
        if (sucio && !punto) {
            punto = document.createElement('span'); punto.dataset.filaPunto = '';
            punto.style.cssText = 'position: absolute; top: -1px; right: -1px; width: 10px; height: 10px; border-radius: 50%; background: var(--icon-accent); box-shadow: 0 0 0 2px var(--surface-card, #fff);';
            q('.fi-fila__cabecera > span', fila)?.append(punto);
        } else if (!sucio && punto) {
            punto.remove();
        }
    };
    const abre = (fila, si) => {
        fila.classList.toggle('fi-fila--open', si);
        q('[data-fila-abrir]', fila)?.setAttribute('aria-expanded', si ? 'true' : 'false');
    };
    // La última fila VISIBLE de cada lista es la que va sin borde (el diseño calcula `last` sobre las que pinta): con
    // las fichas vacías escondidas por `js` y con el filtro, la marca del servidor (la última posición) se mueve.
    const filaCumple = q('[data-fila][data-origen="cumple"]', form);
    const marcaUltimas = () => {
        [...new Set(filas().map((f) => f.parentElement))].forEach((ul) => {
            const hijas = qa(':scope > [data-fila]', ul);
            const visibles = hijas.filter((f) => !f.hidden && !f.classList.contains('fi-fila--vacia'));
            hijas.forEach((f) => f.classList.toggle('fi-fila--last', f === visibles[visibles.length - 1]));
        });
        // Quien cumple va en su propia lista, pero la de debajo la continúa: sin borde solo si detrás viene «Por repasar»
        // o no viene nadie (el `last` del diseño sobre `[cumple]` o `[cumple, null]`).
        if (filaCumple) {
            const repasarVisible = qa('[data-repasar] [data-fila]', form).some((f) => !f.hidden);
            const restoVisible = qa('[data-filas] > [data-fila]', form).some((f) => !f.hidden && !f.classList.contains('fi-fila--vacia'));
            filaCumple.classList.toggle('fi-fila--last', repasarVisible || !restoVisible);
        }
    };
    // ── Quien cumple (F3a, `#747`): «Personalizar» enseña su nombre y su edad como ESPEJO de su fila, que es la que se
    //    guarda («se escriben una vez», el diseño). Los espejos no tienen `name`: no viajan ni cuentan como cambio. ──
    const espejos = qa('[data-cumple-espejo]', form);
    const copiaEspejo = (desdeFila) => {
        if (!filaCumple) return;
        const c = camposDe(filaCumple);
        espejos.forEach((el) => {
            const campo = c[el.dataset.cumpleEspejo];
            if (!campo) return;
            if (desdeFila) el.value = campo.value; else campo.value = el.value;
        });
        if (!desdeFila) pintaFila(filaCumple);
    };
    form.addEventListener('click', (e) => {
        const cab = e.target.closest('[data-fila-abrir]');
        if (cab) { const fila = cab.closest('[data-fila]'); abre(fila, !fila.classList.contains('fi-fila--open')); return; }
        const act = e.target.closest('[data-act]');
        if (!act) return;
        const fila = act.closest('[data-fila]');
        if (act.dataset.act === 'listo' && fila) { e.preventDefault(); abre(fila, false); }
        if (act.dataset.act === 'quitar' && fila) { e.preventDefault(); quitar(fila); }
        if ((act.dataset.act === 'volver' || act.dataset.act === 'no-viene') && fila) { e.preventDefault(); vuelve(fila, act, act.dataset.act === 'volver'); }
    });
    // Intro en un campo de una ficha pasa al siguiente y, en el último, cierra: nunca guarda.
    form.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' || e.target.tagName !== 'INPUT' || e.target.closest('[data-anadir]')) return;
        e.preventDefault();
        const ficha = e.target.closest('[data-fila-ficha]');
        if (!ficha) return;
        const f = qa('input', ficha);
        const i = f.indexOf(e.target);
        if (i >= 0 && i < f.length - 1) f[i + 1].focus(); else abre(ficha.closest('[data-fila]'), false);
    });
    form.addEventListener('input', (e) => {
        if (e.target.dataset?.cumpleEspejo) copiaEspejo(false);
        else if (filaCumple && filaCumple.contains(e.target)) { copiaEspejo(true); actualizaVista(); }
        const fila = e.target.closest('[data-fila]');
        if (fila) pintaFila(fila);
        actualiza();
        guardaBorrador();
    });

    // «AL FINAL VIENE» (F3c, `#747`): la familia dijo que no y cambia de opinión. Como el `volver()` del diseño, la fila
    // pasa a «viene» y sigue en su sitio hasta guardar; «No viene» lo deshace. Lo que viaja es `rejoin[]` (el servidor lo
    // adopta en su ficha, o en la primera libre). Sin JavaScript el mismo botón ENVÍA el formulario con su `rejoin[]`.
    const vuelve = (fila, boton, si) => {
        const id = boton.dataset.rejoin;
        if (!id) return;
        let oculto = qa('input[type="hidden"][name="rejoin[]"]', form).find((el) => el.value === id);
        if (si && !oculto) { oculto = document.createElement('input'); oculto.type = 'hidden'; oculto.name = 'rejoin[]'; oculto.value = id; form.append(oculto); }
        if (!si && oculto) oculto.remove();
        fila.dataset.respuesta = si ? 'si' : 'no';
        fila.dataset.vuelve = si ? '1' : '0';
        const meta = q('[data-fila-meta]', fila);
        if (meta) {
            if (fila.dataset.metaNo === undefined) fila.dataset.metaNo = meta.textContent;
            meta.textContent = si ? t('la_lista.vuelve', 'Al final viene: entra en la lista al guardar.') : fila.dataset.metaNo;
        }
        const nombre = q('[data-fila-nombre]', fila);
        if (nombre) nombre.style.color = si ? 'var(--text-strong)' : 'var(--text-muted)';
        // El texto del enlace del sistema vive en su `.pz-enlace__texto` (no es un nodo directo del botón).
        const texto = q('.pz-enlace__texto', boton);
        if (texto) {
            if (boton.dataset.textoVolver === undefined) boton.dataset.textoVolver = texto.textContent;
            texto.textContent = si ? t('fila.no_viene', 'No viene') : boton.dataset.textoVolver;
        }
        boton.dataset.act = si ? 'no-viene' : 'volver';
        actualiza();
    };

    // «Quitar» (solo los que añadió el anfitrión): la ficha se vacía y se esconde; «Deshacer» la devuelve.
    const deshacer = q('[data-deshacer]', form);
    let quitado = null;
    const quitar = (fila) => {
        const c = camposDe(fila);
        quitado = { fila, valores: [c.name?.value, c.age?.value, c.allergies?.value] };
        [c.name, c.age, c.allergies].forEach((el) => { if (el) el.value = ''; });
        fila.classList.add('fi-fila--vacia');
        abre(fila, false);
        pintaFila(fila);
        if (deshacer) { q('[data-deshacer-texto]', deshacer).textContent = choice(t('la_lista.quitado', 'Has quitado a :n.'), 1, { n: quitado.valores[0] || '…' }); deshacer.hidden = false; }
        actualiza();
        guardaBorrador();
    };
    q('[data-deshacer-boton]', form)?.addEventListener('click', () => {
        if (!quitado) return;
        const c = camposDe(quitado.fila);
        [c.name, c.age, c.allergies].forEach((el, i) => { if (el) el.value = quitado.valores[i] || ''; });
        quitado.fila.classList.remove('fi-fila--vacia');
        pintaFila(quitado.fila);
        quitado = null;
        if (deshacer) deshacer.hidden = true;
        actualiza();
        guardaBorrador();
    });

    // ── Los paneles: «Añadir a mano» y «Pegar una lista», uno cada vez ──
    const panel = (nombre) => q(`[data-panel="${nombre}"]`, form);
    const abrePanel = (nombre) => {
        ['anadir', 'pegar'].forEach((p) => { const el = panel(p); if (el) el.hidden = p !== nombre; qa(`[data-panel-abrir="${p}"]`, form).forEach((b) => { b.hidden = p === nombre; b.setAttribute('aria-expanded', p === nombre ? 'true' : 'false'); }); });
        if (nombre === 'anadir') q('[data-anadir] [data-campo="name"]', form)?.focus();
        if (nombre === 'pegar') q('[data-pegar-texto]', form)?.focus();
    };
    qa('[data-panel-abrir]', form).forEach((b) => b.addEventListener('click', () => abrePanel(b.dataset.panelAbrir)));
    qa('[data-panel-cerrar], [data-anadir] [data-act="cerrar"]', form).forEach((b) => b.addEventListener('click', () => abrePanel(null)));

    // Una ficha VACÍA es donde entra un niño nuevo: la primera escondida, por su posición.
    const siguienteVacia = () => filas().filter((f) => f.classList.contains('fi-fila--vacia') && f.dataset.origen !== 'cumple' && camposDe(f).name).sort((a, b) => Number(a.dataset.indice) - Number(b.dataset.indice))[0] || null;
    const nombres = () => filas().filter((f) => !f.classList.contains('fi-fila--vacia')).map((f) => camposDe(f).name?.value.trim()).filter(Boolean);
    const mete = (nombre, edad, alergias) => {
        const fila = siguienteVacia();
        if (!fila) return null;
        const c = camposDe(fila);
        c.name.value = nombre; if (c.age) c.age.value = edad || ''; if (c.allergies) c.allergies.value = alergias || '';
        fila.classList.remove('fi-fila--vacia');
        fila.dataset.origen = 'mano';
        pintaFila(fila);
        q('[data-lista-vacia]', form)?.setAttribute('hidden', '');

        return fila;
    };
    const composer = q('[data-anadir]', form);
    if (composer) {
        const c = camposDe(composer);
        const estado = q('[data-anadir-estado]', composer);
        const caja = c.name.closest('.pz-campo');
        const error = (msg) => {
            let err = q('.pz-campo__error', caja);
            if (!msg) { caja.classList.remove('pz-campo--error'); err?.remove(); return; }
            caja.classList.add('pz-campo--error');
            if (!err) { err = document.createElement('span'); err.className = 'pz-campo__error'; caja.append(err); }
            err.textContent = msg;
            c.name.focus();
        };
        const anade = () => {
            const nombre = capitalizar(c.name.value);
            if (!nombre) return error(composer.dataset.error);
            const ya = nombres().find((n) => clave(n) === clave(nombre));
            if (ya) return error(choice(t('anadir.repetido', ':name ya está en la lista.'), 1, { name: ya }));
            const fila = mete(nombre, c.age?.value, c.allergies?.value.trim());
            if (!fila) return error(choice(t('anadir.no_cabe', 'No caben más.'), 1, { n: filas().length }));
            error('');
            if (estado) { estado.innerHTML = ''; estado.textContent = composer.dataset.anadido.replace(':name', nombre); }
            c.name.value = ''; if (c.allergies) c.allergies.value = '';
            c.name.focus();
            actualiza();
            guardaBorrador();
        };
        q('[data-act="anadir"]', composer)?.addEventListener('click', anade);
        composer.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); anade(); }
            if (e.key === 'Escape') { e.preventDefault(); abrePanel(null); }
        });
        c.name.addEventListener('input', () => error(''));
    }
    const pegar = q('[data-pegar-texto]', form);
    if (pegar) {
        const aplicar = q('[data-pegar-aplicar]', form);
        const repetidos = q('[data-pegar-repetidos]', form);
        let r = { nombres: [], repetidos: 0 };
        const previa = () => {
            r = limpiar(pegar.value, nombres());
            aplicar.disabled = r.nombres.length === 0;
            aplicar.textContent = choice(t('pegar.boton', 'Añadir :count'), r.nombres.length);
            if (repetidos) { repetidos.hidden = r.repetidos === 0; repetidos.textContent = choice(t('pegar.repetidos', ':count repetidos'), r.repetidos); }
        };
        pegar.addEventListener('input', previa);
        aplicar?.addEventListener('click', () => {
            previa();
            let puestos = 0;
            for (const nombre of r.nombres) { if (!mete(nombre, '', '')) break; puestos++; }
            const fuera = r.nombres.length - puestos;
            pegar.value = '';
            previa();
            abrePanel(null);
            const vivo = q('[data-aviso-vivo]', form);
            if (vivo) vivo.textContent = choice(t('pegar.anadidos', 'Añadidos :count.'), puestos) + (fuera > 0 ? ' ' + choice(t('pegar.no_caben', 'No caben :count.'), fuera) : '');
            actualiza();
            guardaBorrador();
        });
        previa();
    }

    // ── Las cifras filtran la lista ──
    const chip = q('[data-filtro-chip]', form);
    let filtro = null;
    const categoria = (fila) => (fila.dataset.respuesta === 'si' ? 'confirmados' : fila.dataset.respuesta === 'no' ? 'no' : 'sin');
    const filtra = (k) => {
        filtro = filtro === k ? null : k;
        qa('[data-filtro]', form).forEach((b) => { b.classList.toggle('on', b.dataset.filtro === filtro); b.setAttribute('aria-pressed', b.dataset.filtro === filtro ? 'true' : 'false'); });
        let vistos = 0;
        // Con un filtro, quien cumple se esconde: no es una respuesta (el `conCumple` del diseño).
        filas().forEach((f) => { const oculta = filtro !== null && (f.dataset.origen === 'cumple' || categoria(f) !== filtro || f.classList.contains('fi-fila--vacia')); f.hidden = oculta; if (!oculta && !f.classList.contains('fi-fila--vacia')) vistos++; });
        const nadie = q('[data-nadie]', form);
        if (nadie) nadie.hidden = !(filtro !== null && vistos === 0);
        if (chip) {
            chip.hidden = filtro === null;
            const et = q('[data-filtro-etiqueta]', chip);
            if (et && filtro) et.innerHTML = `${t(`la_lista.filtro.${filtro}`, filtro)}<span class="pz-etiqueta__cuenta">${q(`[data-cuenta="${filtro}"]`, form)?.textContent ?? ''}</span>`;
        }
        marcaUltimas();
        if (filtro !== null) q('[data-la-lista]', form)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    qa('[data-filtro]', form).forEach((b) => b.addEventListener('click', () => filtra(b.dataset.filtro)));
    q('[data-filtro-quitar]', form)?.addEventListener('click', () => filtra(filtro));

    // ── El número: «Cambiar» abre el editor; al bajar, se dice cuántas fichas se pierden ──
    const editor = q('[data-numero-editor]', form);
    const vista = q('[data-numero-vista]', form);
    if (editor && vista) {
        editor.hidden = true;
        q('[data-numero-cambiar]', form)?.addEventListener('click', () => { editor.hidden = false; vista.hidden = true; q('[data-cantidad-campo]', editor)?.focus(); });
        q('[data-numero-hecho]', form)?.addEventListener('click', () => { editor.hidden = true; vista.hidden = false; });
    }
    const aviso = q('#pli-aviso-numero');
    const avisaNumero = () => {
        const campo = q('[data-guest-count] [data-cantidad-campo]', form);
        if (!campo || !aviso) return;
        const quiere = parseInt(campo.value, 10);
        const actual = parseInt(q('[data-guest-count]', form).dataset.actual, 10) || 0;
        if (!quiere || quiere >= actual) { aviso.hidden = true; return; }
        const pierde = filas().filter((f) => Number(f.dataset.indice) >= quiere && f.dataset.vacia !== '1' && camposDe(f).name).length;
        aviso.hidden = pierde === 0;
        q('[data-aviso-numero-texto]', aviso).textContent = choice(aviso.dataset.tpl, pierde, { count: quiere, discarded: pierde });
    };

    // ── Personalizar la invitación: el panel, la nota de «se ven al guardar», el titular y LA VISTA PREVIA EN VIVO (F2) ──
    const pers = q('[data-pers]', form);
    let actualizaVista = () => {};
    if (pers) {
        pers.hidden = true;
        const abrir = q('[data-pers-abrir]', form);
        abrir?.addEventListener('click', () => { pers.hidden = !pers.hidden; abrir.setAttribute('aria-expanded', pers.hidden ? 'false' : 'true'); abrir.classList.toggle('pz-boton--secondary', !pers.hidden); abrir.classList.toggle('pz-boton--ghost', pers.hidden); });
        q('[data-pers-cerrar]', form)?.addEventListener('click', () => { pers.hidden = true; abrir?.setAttribute('aria-expanded', 'false'); abrir?.classList.remove('pz-boton--secondary'); abrir?.classList.add('pz-boton--ghost'); });
        const nota = q('[data-inv-nota]', form);
        const h1 = q('#pli-h1');
        // Sin nombre tecleado, el titular vuelve al de lo guardado (el `nombreDe()` del diseño).
        const titularGuardado = h1 ? h1.textContent : '';
        const invSucia = () => qa('input', pers).some((el) => inicial.has(el) && inicial.get(el) !== valorDe(el));
        const campoInv = (k) => q(`[data-inv-campo="${k}"]`, pers);
        const telefono = q('#pli-tel', pers);
        let temaActual = q('[data-inv-vista]', form)?.dataset.invTema ?? '';
        const lee = () => ({
            nombre: campoInv('name')?.value ?? '',
            edad: campoInv('age')?.value ?? '',
            invita: campoInv('host')?.value ?? '',
            palabras: campoInv('words')?.value ?? '',
            pistas: campoInv('gifts')?.value ?? '',
            telefono: Boolean(telefono?.checked),
            tema: q('input[name="theme"]:checked', pers)?.value ?? temaActual,
        });
        // Lo que la tarjeta enseña (`vistaInvitacion`, las reglas del servidor), escrito en sus marcas `data-inv-*`. Vale
        // para la tarjeta y para las miniaturas del tema, que solo llevan la chapa.
        const pon = (raiz, sel, fn) => qa(sel, raiz).forEach(fn);
        const pinta = (raiz, v) => {
            pon(raiz, '[data-inv-chip]', (el) => { el.hidden = !v.conEdad; });
            pon(raiz, '[data-inv-edad]', (el) => { el.textContent = v.edad; });
            pon(raiz, '[data-inv-nombre]', (el) => { el.textContent = v.nombre; });
            pon(raiz, '[data-inv-resto]', (el) => { el.textContent = v.resto; });
            pon(raiz, '[data-inv-figura]', (el) => { el.hidden = !v.figura; });
            pon(raiz, '[data-inv-con-palabras]', (el) => { el.hidden = !v.conPalabras; });
            pon(raiz, '[data-inv-palabras]', (el) => { el.textContent = v.palabras; });
            pon(raiz, '[data-inv-inicial]', (el) => { el.textContent = v.inicial; });
            pon(raiz, '[data-inv-pie="con"]', (el) => { el.hidden = !v.pieCon; });
            pon(raiz, '[data-inv-pie="sin"]', (el) => { el.hidden = !v.pieSin; });
            pon(raiz, '[data-inv-invita]', (el) => { el.textContent = v.invita; });
            pon(raiz, '[data-inv-llamar]', (el) => { el.hidden = !v.telefono; });
            pon(raiz, '[data-inv-pistas-linea]', (el) => { el.hidden = !v.conPistas; });
            pon(raiz, '[data-inv-pistas]', (el) => { el.textContent = `${el.dataset.rotulo}: ${v.pistas}`; });
        };
        // Otro tema es otra tarjeta: la de su plantilla. Como el diseño al cambiar de tema, el adorno nuevo entra con su
        // animación; la chapa y la burbuja, que ya estaban, no la repiten.
        const cambiaTema = (tema) => {
            if (!tema || tema === temaActual) return;
            const actual = q('[data-inv-vista]', form);
            const nueva = q(`template[data-inv-plantilla="${tema}"]`, form)?.content.firstElementChild?.cloneNode(true);
            if (!actual || !nueva) return;
            qa('[data-inv-chip], [data-inv-palabras]', nueva).forEach((el) => { el.style.animation = 'none'; });
            actual.replaceWith(nueva);
            temaActual = tema;
        };
        actualizaVista = () => {
            const c = lee();
            cambiaTema(c.tema);
            const tarjeta = q('[data-inv-vista]', form);
            if (tarjeta) pinta(tarjeta, vistaInvitacion(c, { restoCon: tarjeta.dataset.restoCon, restoSin: tarjeta.dataset.restoSin }));
            pinta(pers, vistaInvitacion(c));
            if (nota) nota.hidden = !invSucia();
            if (h1) h1.textContent = c.nombre.trim() ? choice(t('titular', 'Los invitados de :n'), 1, { n: c.nombre.trim() }) : titularGuardado;
        };
        pers.addEventListener('input', (e) => {
            // La edad, solo cifras y dos como mucho, como el diseño.
            const edad = campoInv('age');
            if (e.target === edad && soloEdad(edad.value) !== edad.value) edad.value = soloEdad(edad.value);
            actualizaVista();
        });
    }

    // ── La barra de Guardar: en qué punto está lo tecleado ──
    const repasar = Number(q('[data-repasar-n]', form)?.textContent || 0);
    const iconos = q('[data-barra-iconos]', barra || form);
    const pintaBarra = (estado, status, detalle) => {
        if (!barra) return;
        barra.dataset.estado = estado;
        const st = q('[data-barra-estado]', barra); if (st) st.textContent = status;
        let det = q('[data-barra-detalle]', barra);
        if (detalle && !det) { det = document.createElement('span'); det.dataset.barraDetalle = ''; det.style.cssText = 'font: var(--type-mono); font-size: 12px; color: var(--text-muted);'; st?.after(det); }
        if (det) { det.textContent = detalle; det.hidden = !detalle; }
        const boton = q('[data-barra-boton]', barra);
        const nada = estado === 'clean' || estado === 'saved';
        if (boton) { boton.disabled = nada; boton.classList.toggle('pz-boton--primary', !nada); boton.classList.toggle('pz-boton--quiet', nada); }
        const icono = q('[data-barra-icono]', barra);
        const nuevo = iconos?.content.querySelector(`[data-icono="${estado}"]`);
        if (icono && nuevo) { icono.style.color = nuevo.style.color; icono.innerHTML = nuevo.innerHTML; }
        const pegada = estado === 'dirty';
        barra.style.position = pegada ? 'sticky' : 'relative';
        barra.style.bottom = pegada ? 'calc(12px + env(safe-area-inset-bottom, 0px))' : '';
        barra.style.background = pegada ? 'var(--surface-glass-ink-float)' : 'var(--ink-surface)';
        barra.style.boxShadow = pegada ? 'var(--shadow-island-float)' : 'inset 0 1px 0 rgba(255, 255, 255, 0.14)';
    };
    let recuperado = false;
    const actualiza = () => {
        marcaUltimas();
        avisaNumero();
        const n = cambios();
        if (n > 0 || repasar > 0) {
            const status = n > 0 ? choice(t('guardar.cambios', ':count cambios sin guardar'), n) : choice(t('guardar.respuestas', ':count respuestas por repasar'), repasar);
            const detalle = n > 0
                ? [repasar ? choice(t('guardar.respuestas', ''), repasar) : '', recuperado ? t('guardar.recuperado', 'Borrador recuperado de este móvil') : t('guardar.movil', 'Borrador en este móvil')].filter(Boolean).join(' · ')
                : t('guardar.entran', 'Entran en la lista al guardar');
            pintaBarra('dirty', status, detalle);
        } else if (barra?.dataset.estado === 'saved' || barra?.dataset.estadoInicial === 'saved') {
            pintaBarra('saved', t('guardar.guardado', 'Guardado'), '');
        } else {
            pintaBarra('clean', t('guardar.nada', 'Nada que guardar todavía'), '');
        }
    };
    if (barra) barra.dataset.estadoInicial = barra.dataset.estado;

    // ── El borrador, en este móvil: se guarda con cada cambio y se vacía al guardar ──
    const guardaBorrador = () => {
        try {
            const datos = {};
            campos().forEach((el) => { if (inicial.has(el) && inicial.get(el) !== valorDe(el)) datos[el.name + (el.type === 'radio' ? '=' + el.value : '')] = valorDe(el); });
            if (Object.keys(datos).length === 0) localStorage.removeItem(claveBorrador); else localStorage.setItem(claveBorrador, JSON.stringify(datos));
        } catch { /* sin almacenamiento */ }
    };
    const recuperaBorrador = () => {
        try {
            const datos = JSON.parse(localStorage.getItem(claveBorrador) || 'null');
            if (!datos) return;
            campos().forEach((el) => {
                const k = el.name + (el.type === 'radio' ? '=' + el.value : '');
                if (!(k in datos)) return;
                if (el.type === 'checkbox') el.checked = datos[k] !== '';
                else if (el.type === 'radio') el.checked = datos[k] !== '';
                else el.value = datos[k];
                recuperado = true;
            });
            if (recuperado) filas().forEach((f) => { pintaFila(f); if (f.dataset.vacia === '0') f.classList.remove('fi-fila--vacia'); });
        } catch { /* sin almacenamiento */ }
    };
    form.addEventListener('submit', () => {
        try { localStorage.removeItem(claveBorrador); } catch { /* nada */ }
        pintaBarra('saving', t('guardar.guardando', 'Guardando la lista'), '');
    });

    // ── Las cantidades de los extras: el importe de cada tarjeta, anticipado (el que vale lo dice el servidor) ──
    cantidades(form, (caja, n) => {
        const tarjeta = caja.closest('.fi-complemento');
        if (!tarjeta) return;
        tarjeta.classList.toggle('fi-complemento--con', n > 0);
        const precio = Number(tarjeta.dataset.precio || 0);
        const total = q('[data-total]', tarjeta);
        if (total) { total.hidden = n === 0; total.textContent = choice(t('extras.total', ':x en total'), 1, { x: euros(n * precio) }); }
    });

    // ── Compartir el enlace y el recordatorio ──
    compartir(form);
    const rec = q('[data-recordatorio]', form);
    if (rec) {
        const texto = q('[data-recordatorio-texto]', rec);
        const copiado = q('[data-recordatorio-copiado]', rec);
        q('[data-recordatorio-copiar]', rec)?.addEventListener('click', () => copiar(texto?.textContent || '').then(() => { if (copiado) copiado.hidden = false; }));
        if (texto) setTimeout(() => rec.scrollIntoView({ behavior: 'smooth', block: 'center' }), 250);
    }

    // ── Arranque: el borrador, las filas, la primera pendiente abierta, la barra ──
    recuperaBorrador();
    filas().forEach((f) => { pintaFila(f); abre(f, false); });
    const primeraPendiente = filas().find((f) => f.dataset.completa === '0' && !f.classList.contains('fi-fila--vacia') && f.dataset.respuesta !== 'no' && camposDe(f).name);
    if (primeraPendiente) abre(primeraPendiente, true);
    // ⚠️ El mensaje de la lista vacía es `[data-lista-vacia]`: `[data-vacia]` es la marca de CADA fila (`pintaFila`) y
    //    con ese selector se escondía la primera fila con nombre (T1b).
    if (q('[data-lista-vacia]', form) && nombres().length > 0) q('[data-lista-vacia]', form).setAttribute('hidden', '');
    // Los espejos de quien cumple, con lo que traiga su fila (el borrador recuperado, o lo que el navegador restauró).
    copiaEspejo(true);
    // La vista previa, con lo que haya en los campos: el borrador recuperado o lo que el navegador restauró al volver.
    actualizaVista();
    actualiza();
}

try {
    const formPrimero = q('[data-primero]');
    const formLista = q('[data-lista]');
    if (formPrimero) primero(formPrimero);
    if (formLista) lista(formLista);
    de.classList.remove('no-js');
    de.classList.add('js');
} catch {
    de.classList.remove('js');
    de.classList.add('no-js');
}

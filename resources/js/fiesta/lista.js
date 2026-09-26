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
import { NBSP, capitalizar, choice, clave, cubrir, euros, limpiar, soloEdad, vistaInvitacion } from './logica.js';

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
    const campos = () => qa('input:not([type=hidden]):not([type=submit]), textarea, select, input[type=hidden][name^="guests["], input[type=hidden][name="cake_quantity"]', form)
        .filter((el) => el.name && !['_token', 'expected_version', 'reply', 'with_names'].includes(el.name) && el.form === form);
    const valorDe = (el) => (el.type === 'checkbox' || el.type === 'radio' ? (el.checked ? el.value : '') : el.value);
    campos().forEach((el) => inicial.set(el, valorDe(el)));
    // Cada «Al final viene» pendiente de guardar (su `rejoin[]`) es un cambio más. La TARTA (F5) cuenta UNO, como en el
    // diseño (`a.tarta !== b.tarta`), aunque cambiar de opción mueva dos radios y su cantidad.
    const cambios = () => campos().filter((el) => el.name !== 'cake' && el.name !== 'cake_quantity' && inicial.has(el) && inicial.get(el) !== valorDe(el)).length
        + qa('input[type="hidden"][name="rejoin[]"]', form).length
        + tartaCambio();

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
    // F4 (§4.9, `#747`): sin fichas libres, y con el número en plazo, una ficha NUEVA más allá del número —la plantilla del
    // servidor con su posición—, antes de los «no» sueltos. La zona 3 la cuenta en ámbar hasta el «Sí».
    const plantilla = q('template[data-fila-plantilla]', form);
    const creaFila = () => {
        if (!plantilla) return null;
        const indices = filas().map((f) => Number(f.dataset.indice)).filter((n) => Number.isFinite(n));
        const i = (indices.length ? Math.max(...indices) : -1) + 1;
        const caja = document.createElement('template');
        caja.innerHTML = plantilla.innerHTML.replaceAll('__I__', String(i)).trim();
        const fila = caja.content.firstElementChild;
        const lista = q('[data-filas]', form);
        if (!fila || !lista) return null;
        lista.insertBefore(fila, q(':scope > [data-suelto]', lista));
        qa('input, textarea, select', fila).forEach((el) => inicial.set(el, ''));
        return fila;
    };
    const mete = (nombre, edad, alergias) => {
        const fila = siguienteVacia() || creaFila();
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

    // ── El número: «Cambiar» abre el editor; la zona 3 se pinta con (número elegido, niños en la lista) — F4 ──
    const editor = q('[data-numero-editor]', form);
    const vista = q('[data-numero-vista]', form);
    const campoNumero = q('[data-guest-count] [data-cantidad-campo]', form);
    if (editor && vista) {
        editor.hidden = true;
        qa('[data-numero-cambiar]', form).forEach((b) => b.addEventListener('click', () => { editor.hidden = false; vista.hidden = true; campoNumero?.focus(); }));
        q('[data-numero-hecho]', form)?.addEventListener('click', () => { editor.hidden = true; vista.hidden = false; actualiza(); });
    }
    // Los niños que la lista MANDA: las fichas con algo (quien cumple cuenta) y los «Al final viene» sueltos, que al guardar
    // entran en una ficha. ⚠️ Un «no» que empareja con una ficha del anfitrión cuenta: sigue siendo su ficha, porque el
    // emparejado por nombre no es seguro (T6·3, «nadie se quita solo»). Los «no» sueltos no son fichas: no cuentan.
    const enLaLista = () => filas().filter((f) => !f.classList.contains('fi-fila--vacia')
        && (f.dataset.suelto === undefined || f.dataset.vuelve === '1'));
    // El medidor de plazas, como la pieza (`x-fiesta.plazas`, `PlacesMeter.jsx`): casillas hasta 40, barra con más.
    const pintaMedidor = (el, total, confirmadas, pendientes, reservadas, rotulo) => {
        const n = Math.max(0, Math.round(total));
        const c = Math.min(Math.max(0, confirmadas), n);
        const p = Math.min(Math.max(0, pendientes), n - c);
        const corte = reservadas > 0 && reservadas < n ? reservadas : 0;
        el.setAttribute('aria-label', rotulo);
        el.innerHTML = '';
        if (n > 40) {
            el.style.cssText = 'display: flex; height: 12px; border-radius: var(--r-pill); overflow: hidden; background: var(--fiesta-tinta-200);';
            [[c, 'var(--success-500)'], [p, 'var(--warn-500)']].forEach(([x, color]) => {
                const s = document.createElement('span');
                s.style.cssText = `width: ${n > 0 ? (x / n) * 100 : 0}%; background: ${color}; transition: width var(--dur-base) var(--ease-out);`;
                el.append(s);
            });
            return;
        }
        el.style.cssText = `display: flex; gap: ${n > 20 ? '3px' : '4px'}; height: 12px;`;
        for (let i = 0; i < n; i++) {
            const s = document.createElement('span');
            const color = i < c ? 'var(--success-500)' : i < c + p ? 'var(--warn-500)' : 'var(--fiesta-tinta-200)';
            const radio = n === 1 ? 'var(--r-pill)' : i === 0 ? '999px 4px 4px 999px' : i === n - 1 ? '4px 999px 999px 4px' : '4px';
            s.style.cssText = `flex: 1 1 0; min-width: 0; background: ${color}; transition: background var(--dur-base) var(--ease-out); margin-left: ${corte && i === corte ? (n > 20 ? '5px' : '8px') : '0px'}; border-radius: ${radio};`;
            el.append(s);
        }
    };
    let estadoNumero = null;
    // La tarta y lo de los padres (F5) se repintan con cada cambio; se definen más abajo, con sus piezas.
    let pintaExtras = () => {};
    const pintaNumero = () => {
        if (!vista || !campoNumero) return;
        const elegido = parseInt(campoNumero.value, 10) || 0;
        const ninos = enLaLista();
        const n = ninos.length;
        estadoNumero = n < elegido ? 'libres' : n === elegido ? 'listo' : 'mas';
        qa('[data-numero-estado]', vista).forEach((b) => { b.hidden = b.dataset.numeroEstado !== estadoNumero; });
        const medidor = q('[data-plazas]', vista);
        const mas = estadoNumero === 'mas';
        if (medidor) {
            pintaMedidor(medidor, mas ? n : elegido, mas ? elegido : n, mas ? n - elegido : 0, mas ? elegido : 0,
                mas ? choice(t('numero.meter_mas', ''), 1, { n, extra: n - elegido, plazas: elegido }) : choice(t('numero.meter', ''), 1, { n, plazas: elegido }));
        }
        const pon = (sel, texto) => { const el = q(sel, vista); if (el) el.textContent = texto; };
        pon('[data-numero-libres]', choice(t('numero.libres', ''), Math.max(1, elegido - n), { count: Math.max(1, elegido - n) }));
        pon('[data-numero-tienes]', choice(t('numero.tienes', ''), 1, { plazas: elegido, lista: n }));
        pon('[data-numero-listo]', choice(t('numero.listo', ''), 1, { n: elegido }));
        // «Seréis 12: Vera, los 9 confirmados y 2 que añadiste.»
        const confirmados = ninos.filter((f) => f.dataset.origen !== 'cumple' && f.dataset.respuesta === 'si').length;
        const anadidos = ninos.filter((f) => f.dataset.origen !== 'cumple' && f.dataset.respuesta !== 'si').length;
        const nombreCumple = filaCumple ? (camposDe(filaCumple).name?.value.trim() ?? '') : '';
        const partes = [nombreCumple,
            confirmados ? choice(t('numero.confirmados', ''), confirmados, { count: confirmados }) : '',
            anadidos ? choice(t('numero.anadidos', ''), anadidos, { count: anadidos }) : ''].filter(Boolean);
        const listaTexto = partes.length > 1 ? partes.slice(0, -1).join(', ') + t('numero.y', ' y ') + partes[partes.length - 1] : (partes[0] || '');
        pon('[data-numero-frase]', choice(t('numero.frase', ''), 1, { n, lista: listaTexto }));
        const precio = vista.dataset.precio || '';
        pon('[data-numero-mas]', choice(t(precio ? 'numero.mas' : 'numero.mas_sin_precio', ''), Math.max(1, n - elegido), { count: Math.max(1, n - elegido), plazas: elegido, precio }));
        if (!mas) { const aviso = q('[data-numero-confirma]', vista); if (aviso) aviso.hidden = true; }
    };
    // «Sí»: el número pasa a ser el de la lista (lo guarda el Guardar de siempre, que ajusta el número ANTES que las fichas).
    q('[data-numero-si]', form)?.addEventListener('click', () => {
        if (!campoNumero) return;
        const max = parseInt(campoNumero.getAttribute('max') || '', 10);
        const n = enLaLista().length;
        campoNumero.value = String(Number.isFinite(max) ? Math.min(n, max) : n);
        campoNumero.dispatchEvent(new Event('input', { bubbles: true }));
        campoNumero.dispatchEvent(new Event('change', { bubbles: true }));
        actualiza();
    });

    // ── F5 (§4.11, `#749`): LA TARTA (`PliZona4`, `PliAvisoTarta`) ──
    // «¿La tarta?» es un radio `cake` (el id de una tarta, o `none`) y «Añadir otra tarta» sube `cake_quantity`: el servidor
    // lo traduce a las cantidades de siempre. Sin tarta grande (`#749`): si los niños no caben, se propone otra.
    const tarta = q('[data-tarta]', form);
    const radiosTarta = tarta ? qa('input[name="cake"]', tarta) : [];
    const cantidadTarta = tarta ? q('input[name="cake_quantity"]', tarta) : null;
    const datosTartas = (() => { try { return JSON.parse(tarta?.dataset.tartas || '{}'); } catch { return {}; } })();
    const tartaElegida = () => radiosTarta.find((r) => r.checked)?.value ?? '';
    const tartaInicial = { v: tartaElegida(), n: cantidadTarta?.value ?? '' };
    function tartaCambio() {
        return tarta && (tartaElegida() !== tartaInicial.v || (cantidadTarta?.value ?? '') !== tartaInicial.n) ? 1 : 0;
    }
    // Cuántos sois (el `sois` del diseño): el número elegido, o la lista si lo supera.
    const sois = () => Math.max(campoNumero ? (parseInt(campoNumero.value, 10) || 0) : Number(tarta?.dataset.sois || 0), enLaLista().length);
    const pintaTarta = () => {
        if (!tarta) return;
        const v = tartaElegida();
        const d = datosTartas[v] || null;
        const n = Math.max(1, parseInt(cantidadTarta?.value ?? '1', 10) || 1);
        const s = sois();
        // «De 12 raciones», o «De 12 raciones: no llega para 14» (en la elegida, con las que pidió).
        radiosTarta.forEach((r) => {
            const dr = datosTartas[r.value];
            const desc = q('.pz-opciones__desc', r.closest('label'));
            if (!dr || !dr.serves || !desc) return;
            desc.textContent = s > dr.serves * (r.value === v ? n : 1)
                ? choice(t('tarta.no_llega', ''), 1, { r: dr.serves, n: s })
                : choice(t('tarta.raciones', ''), 1, { n: dr.serves });
        });
        const pista = q('.pz-opciones__pista', tarta);
        if (pista) pista.textContent = v ? (tarta.dataset.pistaCambia || '') : (tarta.dataset.pistaPlazo || '');
        const sug = q('[data-tarta-sug]', tarta);
        if (sug) {
            const total = d && d.serves ? d.serves * n : 0;
            const otra = q('[data-tarta-otra]', sug);
            const quitar = q('[data-tarta-quitar]', sug);
            const texto = q('[data-tarta-sug-texto]', sug);
            if (total && s > total) {
                texto.textContent = n === 1 ? choice(t('tarta.poca', ''), 1, { n: s, r: d.serves }) : choice(t('tarta.poca_varias', ''), 1, { n: s, q: n, r: total });
                otra.hidden = n >= (d.max || 1);
                quitar.hidden = n <= 1;
                sug.hidden = false;
            } else if (total && n > 1) {
                texto.textContent = choice(t('tarta.varias', ''), 1, { q: n, r: total });
                otra.hidden = true;
                quitar.hidden = false;
                sug.hidden = false;
            } else {
                sug.hidden = true;
            }
        }
        // El aviso de arriba: elegida y sin guardar, cambia el texto EN EL MISMO HUECO (no se va: la página subiría).
        const aviso = q('[data-aviso-tarta]', form);
        if (aviso) {
            const tx = q('[data-aviso-texto]', aviso);
            const ir = q('[data-aviso-ir]', aviso);
            const irTx = ir ? (q('.pz-enlace__texto', ir) || ir) : null;
            if (aviso.dataset.original === undefined) { aviso.dataset.original = tx?.textContent ?? ''; aviso.dataset.irOriginal = irTx?.textContent ?? ''; }
            if (tx) tx.textContent = v === 'none' ? aviso.dataset.sin : (v ? aviso.dataset.elegida : aviso.dataset.original);
            if (irTx) irTx.textContent = v ? aviso.dataset.ver : aviso.dataset.irOriginal;
            qa('[data-aviso-icono]', aviso).forEach((ic) => { ic.hidden = (ic.dataset.avisoIcono === 'elegida') !== Boolean(v); });
        }
    };
    let tartaAntes = tartaElegida();
    radiosTarta.forEach((r) => r.addEventListener('change', () => {
        // Otra tarta es otra cantidad: se vuelve a una.
        if (cantidadTarta && r.value !== tartaAntes) cantidadTarta.value = '1';
        tartaAntes = r.value;
        actualiza();
        guardaBorrador();
    }));
    const cambiaTartas = (paso) => {
        const d = datosTartas[tartaElegida()];
        if (!d || !cantidadTarta) return;
        cantidadTarta.value = String(Math.min(d.max || 1, Math.max(1, (parseInt(cantidadTarta.value, 10) || 1) + paso)));
        actualiza();
        guardaBorrador();
    };
    q('[data-tarta-otra]', form)?.addEventListener('click', () => cambiaTartas(1));
    q('[data-tarta-quitar]', form)?.addEventListener('click', () => cambiaTartas(-1));

    // ── F5: LO DE LOS PADRES (`PliFamilia`): con los adultos puestos, la cuenta más barata de cada familia ──
    const padres = q('[data-padres]', form);
    const campoAdultos = padres ? q('[data-adultos] [data-cantidad-campo]', padres) : null;
    const pintaPadres = () => {
        if (!padres) return;
        const adultos = parseInt(campoAdultos?.value ?? '0', 10) || 0;
        qa('[data-familia]', padres).forEach((fam) => {
            const sug = q('[data-familia-sug]', fam);
            const ok = q('[data-familia-ok]', fam);
            if (!sug || !ok) return;
            sug.hidden = true;
            ok.hidden = true;
            const tarjetas = qa('[data-variante]', fam).filter((c) => Number(c.dataset.serves || 0) > 0);
            // Lo que ya cubre: lo tecleado en las abiertas y lo pedido en las cerradas.
            const pedido = (c) => { const campo = q('[data-cantidad-campo]', c); return campo ? (parseInt(campo.value, 10) || 0) : Number(c.dataset.pedido || 0); };
            const hay = tarjetas.reduce((suma, c) => suma + pedido(c) * Number(c.dataset.serves), 0);
            const abiertas = tarjetas.filter((c) => q('[data-cantidad-campo]', c));
            if (adultos <= 0 || abiertas.length === 0) return;
            if (hay >= adultos) {
                q('[data-familia-ok-texto]', ok).textContent = choice(t('padres.cubierto', ''), adultos, { count: adultos });
                ok.hidden = false;
                return;
            }
            const vars = abiertas.map((c) => ({ c, para: Number(c.dataset.serves), precio: Number(c.dataset.precio || 0), max: Number(c.dataset.max || 0) }));
            const cuenta = cubrir(vars, adultos);
            if (!cuenta) return;
            const partes = vars.map((v, i) => (cuenta.q[i] ? `${cuenta.q[i]} ${v.c.dataset.nombre || ''}` : '')).filter(Boolean);
            const lista = partes.length > 1 ? partes.slice(0, -1).join(', ') + t('numero.y', ' y ') + partes[partes.length - 1] : partes[0];
            q('[data-familia-sug-texto]', sug).textContent = choice(t('padres.sugerencia', ''), adultos, { count: adultos, partes: lista, precio: euros(cuenta.coste) });
            const poner = q('[data-familia-poner-texto]', sug);
            if (poner) poner.textContent = choice(t('padres.poner', ''), cuenta.uds);
            sug.cuenta = vars.map((v, i) => [q('[data-cantidad-campo]', v.c), cuenta.q[i]]);
            sug.hidden = false;
        });
    };
    // «Ponerlo(s)»: la cuenta propuesta pasa a las cantidades (nunca sola). Cada campo avisa como si se hubiera tocado.
    padres?.addEventListener('click', (e) => {
        const boton = e.target.closest('[data-familia-poner]');
        const sug = boton?.closest('[data-familia-sug]');
        if (!sug || !sug.cuenta) return;
        sug.cuenta.forEach(([campo, n]) => { if (!campo) return; campo.value = String(n); campo.dispatchEvent(new Event('input', { bubbles: true })); });
    });
    pintaExtras = () => { pintaTarta(); pintaPadres(); };

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
        pintaNumero();
        pintaExtras();
        const n = cambios();
        if (n > 0 || repasar > 0) {
            const status = n > 0 ? choice(t('guardar.cambios', ':count cambios sin guardar'), n) : choice(t('guardar.respuestas', ':count respuestas por repasar'), repasar);
            let detalle = n > 0
                ? [repasar ? choice(t('guardar.respuestas', ''), repasar) : '', recuperado ? t('guardar.recuperado', 'Borrador recuperado de este móvil') : t('guardar.movil', 'Borrador en este móvil')].filter(Boolean).join(' · ')
                : t('guardar.entran', 'Entran en la lista al guardar');
            // F5: con la tarta ELEGIDA y sin guardar, y su plazo cerrando hoy o mañana, la barra lo dice (`guardarTarta`).
            const v = tarta ? tartaElegida() : '';
            if (tarta?.dataset.pronto === '1' && v && v !== 'none' && v !== tartaInicial.v && tarta.dataset.guardarTexto) detalle = tarta.dataset.guardarTexto;
            pintaBarra('dirty', status, detalle);
        } else if (barra?.dataset.estado === 'saved' || barra?.dataset.estadoInicial === 'saved') {
            // «Guardado hoy a las 16:05» (F5c): el del servidor, no un «Guardado» a secas.
            pintaBarra('saved', barra?.dataset.guardado || t('guardar.guardado', 'Guardado'), '');
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
            // Las fichas de MÁS que se teclearon y no se guardaron (F4): se crean antes de devolverles lo escrito.
            const hasta = Math.max(-1, ...Object.keys(datos).map((k) => Number(/^guests\[(\d+)\]\[/.exec(k)?.[1] ?? -1)));
            const ultima = () => Math.max(-1, ...filas().map((f) => Number(f.dataset.indice)).filter((n) => Number.isFinite(n)));
            while (ultima() < hasta && creaFila()) { /* una más */ }
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
    form.addEventListener('submit', (e) => {
        // ❗ F4 (`[DECIDIDO owner]` `#747`): con más niños en la lista que el número, NO se envía: sube a la zona 3, lo dice
        // y deja el foco en el «Sí». Ni se cobra sin confirmar ni el saneo tira nombres.
        pintaNumero();
        if (estadoNumero === 'mas') {
            e.preventDefault();
            if (editor) editor.hidden = true;
            if (vista) vista.hidden = false;
            const aviso = q('[data-numero-confirma]', form);
            if (aviso) aviso.hidden = false;
            q('[data-zona="3"]', form)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => q('[data-numero-si]', form)?.focus({ preventScroll: true }), 350);
            return;
        }
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
    tartaAntes = tartaElegida();
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

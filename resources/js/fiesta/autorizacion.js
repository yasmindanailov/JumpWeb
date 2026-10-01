/*
 * LA AUTORIZACIÓN de un menor invitado — el comportamiento de la página (`specs/fiesta-sistema-nuevo.md` §4.5, T3).
 * Lo poco que `AutPagina` hace con React y aquí se hace a mano: el selector de menores a cargo (rellena la ficha y NO
 * envía; «a mano» la vacía), «Firmando» mientras el formulario viaja, y el foco en el primer campo con error cuando el
 * servidor devuelve la hoja. Los dos últimos viven en `comun.js` desde F6a: el recibo firma con el mismo formulario.
 * Desde la L3 de §4.18 (`#814`), «Firmar» es la ISLA (`islaDeLaFirma`). (El idioma, desde `#748`, son enlaces al pie:
 * sin JavaScript.) ⚠️ Nada de esto ESCRIBE: escribe el formulario. Sin JavaScript firma igual.
 */
import './fiesta.css';
import { arranca, firma, llegadas, menores, q, qa, sugerencias } from './comun.js';
import { islaDeEnlace } from './isla.js';
import { faltaParaFirmar } from './logica.js';

/** Lo que la firma exige, por su `name`, con la clave de su nombre en la isla (`fiesta.isla.falta.campos`). */
const EXIGE = {
    minor_name: 'ninoNombre', minor_surname: 'ninoApellidos', minor_born_on: 'nacimiento', guardian_name: 'nombre',
    guardian_relationship: 'relacion', guardian_phone: 'telefono', accept_waiver: 'casilla',
};

/**
 * LA ISLA DE LA FIRMA (L3 de §4.18, `paginas/autorizacion/isla.jsx`): el ÚNICO «Firmar», con lo que falta por su nombre y
 * en el orden del formulario («Faltan tu teléfono y la casilla», «Faltan 4 datos y la casilla») hasta «Todo listo», con el
 * punto lima. Tocarla envía: si falta algo, el servidor lo marca con palabras y el foco va al primero (`firma()`). Firmando,
 * ocupada, y un segundo toque no envía otra vez.
 */
function islaDeLaFirma() {
    const form = q('#aut-form');
    const datos = q('[data-aut-isla]');
    if (!form || !datos) return;
    const isla = islaDeEnlace();
    if (!isla) return;
    let tx = null;
    try { tx = JSON.parse(datos.dataset.falta || 'null'); } catch { /* sin textos, «Firmar» a secas */ }
    const cara = q('[data-isla-cara]', datos)?.dataset.islaCara || 'barra';
    const falta = () => qa('input[name], select[name]', form)
        .filter((el) => EXIGE[el.name] && (el.type === 'checkbox' ? !el.checked : el.value.trim() === ''))
        .map((el) => EXIGE[el.name]);
    let firmando = false;
    const pinta = () => {
        if (!tx || firmando) return;
        const f = falta();
        isla.cara(cara, { sub: faltaParaFirmar(f, tx), punto: f.length === 0 ? 'var(--fiesta-lima-500)' : '' });
    };
    // En `document`: el selector de menores a cargo (fuera del formulario) rellena sin `input`, y su `change` llega aquí
    // DESPUÉS de que `menores()` haya puesto los valores.
    document.addEventListener('input', pinta);
    document.addEventListener('change', pinta);
    form.addEventListener('submit', (e) => {
        if (firmando) { e.preventDefault(); return; }
        firmando = true;
        isla.cara(cara, { label: datos.dataset.firmando || '', sub: '', punto: '', ocupada: true });
    });
    pinta();
}

arranca(menores, firma, sugerencias, llegadas, islaDeLaFirma);

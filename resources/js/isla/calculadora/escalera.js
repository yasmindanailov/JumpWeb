/**
 * **LA ESCALERA DE LA PÁGINA, MARCADA CON LO ELEGIDO** (T6c·6 de `docs/specs/isla-y-landing-nueva.md` §4.19; el `RateTable`
 * con `active` de `paginas/colegios/pieza-6.jsx` del diseño: «se ve de dónde sale el precio»). Contrato página↔calculadora,
 * como `data-jw-calculadora-dia`: la página pinta una escalera por pack (`[data-jw-escalera="<id>"]`, sus filas con
 * `data-jw-tramo` —desde cuántos— y sus celdas con `data-jw-tarifa` —`normal`/`special`—) y la calculadora enseña la de la
 * fila elegida, pone `data-jw-activo` en el tramo de esa gente y, con día, en la celda de su tarifa, con `aria-current`
 * para el lector. Sin escalera de esa fila, no se oculta ninguna. El estilo es de la página.
 *
 * @param {ParentNode|null} raiz  la sección de la calculadora
 * @param {{fila: number, tramo: number|null, tarifa: 'normal'|'special'|null}|null} marca  `vista.escalera`
 */
export function marcarEscaleras(raiz, marca) {
    const escaleras = [...(raiz?.querySelectorAll?.('[data-jw-escalera]') ?? [])];
    const suya = (escalera) => escalera.dataset.jwEscalera === String(marca?.fila);

    if (! marca || ! escaleras.some(suya)) return;
    for (const escalera of escaleras) {
        escalera.hidden = ! suya(escalera);
        for (const fila of escalera.querySelectorAll('[data-jw-tramo]')) {
            const activa = suya(escalera) && fila.dataset.jwTramo === String(marca.tramo);

            fila.toggleAttribute('data-jw-activo', activa);
            for (const celda of fila.querySelectorAll('[data-jw-tarifa]')) {
                const marcada = activa && celda.dataset.jwTarifa === marca.tarifa;

                celda.toggleAttribute('data-jw-activo', marcada);
                if (marcada) celda.setAttribute('aria-current', 'true');
                else celda.removeAttribute('aria-current');
            }
        }
    }
}

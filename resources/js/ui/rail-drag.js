/**
 * ARRASTRAR UN CARRIL CON EL RATÓN · `DECISIONES #549`
 *
 * `[owner]`: *«que se pueda hacer slide también en escritorio, en todo tipo de dispositivo»*.
 *
 * ❗❗ **Con el DEDO no hace falta nada de esto y por eso el módulo lo ignora**: un contenedor con
 * `overflow-x: auto` ya se desliza con inercia y rebote, y eso es del navegador. Lo que un ratón no
 * sabe hacer es *arrastrar* un carril —sin rueda horizontal solo quedan los controles—, así que esto
 * existe **solo para `pointerType === 'mouse'`**. Enganchar también el táctil sería pelearse con el
 * desplazamiento nativo escribiendo `scrollLeft` a mano: peor y más frágil.
 *
 * ⚠️⚠️ **EL AJUSTE POR TARJETA HAY QUE APAGARLO MIENTRAS SE ARRASTRA.** Con `scroll-snap-type: x
 * mandatory` puesto, cada `scrollLeft` que escribe el arrastre hace que el navegador vuelva a encajar
 * en la parada más cercana: el carril se queda pegado y parece que el arrastre no funciona. Se apaga
 * con una clase y **se vuelve a encender al soltar**, que es lo que hace que encaje solo en la tarjeta
 * más cercana — el propio mecanismo de `scroll-snap` re-ajusta cuando la propiedad vuelve.
 * ⚠️ Y con el ajuste se apaga el `scroll-behavior: smooth` de la hoja: si no, cada escritura del
 * arrastre se anima y el carril va por detrás del cursor.
 *
 * ⚠️⚠️ **UN ARRASTRE TERMINA EN UN `click` SOBRE LO QUE HUBIERA DEBAJO.** En estos carriles hay
 * enlaces —el perfil del autor y «Ver en Google», que son requisito de atribución— y botones («Más
 * info»), así que sin tragarse ese clic, deslizar navegaría a Google. Se traga **solo si de verdad
 * hubo arrastre** (más de `UMBRAL` px), para que un clic normal siga funcionando.
 *
 * ⚠️ **El `dragstart` nativo también estorba**: una imagen o un enlace se pueden arrastrar por sí
 * mismos, y entonces el navegador empieza SU gesto y abandona el nuestro a mitad.
 *
 * ❗❗❗ **LA CAPTURA DEL PUNTERO NO SE PIDE AL PULSAR, SINO CUANDO EL ARRASTRE YA ES ARRASTRE — y esto
 * fue un defecto REAL, medido.** `setPointerCapture()` no solo redirige los `pointermove`: el `click`
 * posterior **se dispara sobre el elemento que capturó**, o sea sobre el carril. Con la captura pedida
 * en el `pointerdown`, **ningún control de dentro volvía a funcionar**: el «Más info» de la ficha se
 * quedaba en `aria-expanded="false"` con el panel cerrado, sin un solo error en consola y con la suite
 * en verde. Lo cazó el CONTROL de la sonda —un clic normal después de medir el arrastre—, no una
 * relectura del código.
 * ▶ Por eso la captura se pide en el primer `pointermove` que pasa del umbral: hasta entonces esto es
 * un clic y el navegador lo entrega a quien le toca.
 */

/** Los carriles que se pueden arrastrar. Los dos comparten receta; el CSS pone el resto. */
const CARRILES = ['.addons-rail__track', '.rev__track'];

/** Por debajo de esto no fue un arrastre, fue un clic con pulso. */
const UMBRAL = 4;

function armar(track) {
    let arrastrando = false;
    let capturado = false;
    let inicioX = 0;
    let inicioScroll = 0;
    let recorrido = 0;

    track.addEventListener('pointerdown', (e) => {
        // El dedo y el lápiz ya deslizan solos; el botón secundario abre el menú del navegador.
        if (e.pointerType !== 'mouse' || e.button !== 0) return;
        // Un carril que cabe entero no se arrastra: no hay nada que recorrer.
        if (track.scrollWidth - track.clientWidth <= 4) return;

        arrastrando = true;
        capturado = false;
        recorrido = 0;
        inicioX = e.clientX;
        inicioScroll = track.scrollLeft;
    });

    track.addEventListener('pointermove', (e) => {
        if (!arrastrando) return;

        const delta = e.clientX - inicioX;

        recorrido = Math.max(recorrido, Math.abs(delta));

        // Hasta aquí esto todavía podía ser un clic. A partir del umbral es un arrastre: se apaga el
        // ajuste por tarjeta y se captura el puntero, para que el cursor pueda salirse del carril sin
        // dejar el gesto a medias.
        if (!capturado) {
            if (recorrido <= UMBRAL) return;

            capturado = true;
            track.classList.add('is-arrastrando');
            track.setPointerCapture?.(e.pointerId);
        }

        track.scrollLeft = inicioScroll - delta;
    });

    const soltar = () => {
        if (!arrastrando) return;

        arrastrando = false;
        capturado = false;
        // Al quitar la clase vuelve el `scroll-snap`, y volver a ponerlo es lo que encaja el carril
        // en la tarjeta más cercana. No hay que calcular ninguna parada a mano.
        track.classList.remove('is-arrastrando');
    };

    track.addEventListener('pointerup', soltar);
    track.addEventListener('pointercancel', soltar);
    track.addEventListener('lostpointercapture', soltar);

    // ⚠️ En CAPTURA, para llegar antes que el enlace o el botón que hay debajo.
    track.addEventListener('click', (e) => {
        if (recorrido > UMBRAL) {
            e.preventDefault();
            e.stopPropagation();
        }

        recorrido = 0;
    }, true);

    track.addEventListener('dragstart', (e) => {
        if (arrastrando) e.preventDefault();
    });
}

export function initRailDrag(raiz = document) {
    for (const selector of CARRILES) {
        for (const track of raiz.querySelectorAll(selector)) armar(track);
    }
}

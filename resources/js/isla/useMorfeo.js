/**
 * EL MORFEO (`ParkIsland.jsx`, bloque «Morfeo»): la isla mide su contenido y anima alto y ancho hasta ese tamaño,
 * así que cambiar de situación es una transformación, no un salto.
 *
 * Devuelve la caja medida (`box`: ancho, alto y el ancho disponible `cap`), si ya se puede animar (`animate`: no
 * hasta la primera medida), si toca la animación sin rebote (`calmNow`) y `remedirCuando()`, para volver a medir al
 * cambiar de forma.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

export function useMorfeo({ wrapRef, sizerRef, isOpen, usaAncho = () => true }) {
    const box = ref({ w: 0, h: 0, cap: 0 });
    const animate = ref(false);
    const calm = ref(false);
    // El HUECO que la isla ocupa en la página (`#783`): el alto final de cada cambio —no el de cada fotograma—, y
    // congelado mientras un panel está abierto o cerrándose (la calma). Así la isla crece por encima de la página en vez
    // de empujarla: medido en escritorio, abrir el menú bajaba la página 497px y la recolocaba entera en cada fotograma.
    const reservado = ref(0);
    let medir = null;
    let ro = null;
    let fotograma = 0;

    // Los cambios grandes (abrir o cerrar un panel) van sin rebote, y la calma dura un momento tras cerrar.
    watch(isOpen, (abierta, _antes, alLimpiar) => {
        if (abierta) { calm.value = true; return; }
        const reloj = setTimeout(() => { calm.value = false; }, 450);
        alLimpiar(() => clearTimeout(reloj));
    }, { immediate: true });

    onMounted(() => {
        const sizer = sizerRef.value;
        const wrap = wrapRef.value;
        if (!(sizer && wrap && typeof ResizeObserver !== 'undefined')) return;
        // ⚠️ El ANCHO medido solo cuenta si la isla lo usa (`usaAncho`: en fila, su ancho es el medido; en bloque, el
        // 100 %). En bloque lo medido ocupa el 100 % de la isla, y mientras su ancho se anima (de píldora a panel, el
        // relevo) cambiaba en CADA fotograma y Vue repintaba la isla entera para nada (`#783`, medido: 22 veces en el
        // segundo de abrir el menú en móvil, frente a 3 aislada en el banco).
        const leer = () => {
            fotograma = 0;
            const r = sizer.getBoundingClientRect();
            const cap = wrap.getBoundingClientRect().width;
            const prev = box.value;
            const anchoCambia = usaAncho() && Math.abs(prev.w - r.width) >= 0.5;
            if (anchoCambia || Math.abs(prev.h - r.height) >= 0.5 || Math.abs(prev.cap - cap) >= 0.5) {
                box.value = { w: r.width, h: r.height, cap };
            }
        };
        const programar = () => { if (!fotograma) fotograma = requestAnimationFrame(leer); };
        medir = leer;
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => { if (sizerRef.value) leer(); });
        ro = new ResizeObserver(programar);
        ro.observe(sizer);
        ro.observe(wrap);
        leer();
    });

    onBeforeUnmount(() => {
        if (ro) ro.disconnect();
        if (fotograma) cancelAnimationFrame(fotograma);
    });

    watch(() => box.value.h, (h, _antes, alLimpiar) => {
        if (!h || animate.value) return;
        const f = requestAnimationFrame(() => { animate.value = true; });
        alLimpiar(() => cancelAnimationFrame(f));
    }, { immediate: true });

    // Al cambiar de colocación o de forma se vuelve a medir, y otra vez al acabar la entrada del panel nuevo.
    function remedirCuando(fuentes) {
        watch(fuentes, (_v, _antes, alLimpiar) => {
            if (medir) medir();
            const reloj = setTimeout(() => { if (medir) medir(); }, 420);
            alLimpiar(() => clearTimeout(reloj));
        }, { flush: 'post', immediate: true });
    }

    const calmNow = computed(() => isOpen.value || calm.value);
    // El borde de la isla (1px por lado) va fuera de lo que se mide.
    watch([() => box.value.h, calmNow], ([h, calma]) => { if (h && ! calma) reservado.value = h + 2; }, { immediate: true });

    return { box, animate, calmNow, reservado, remedirCuando };
}

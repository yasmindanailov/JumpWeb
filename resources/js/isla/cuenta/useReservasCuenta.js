/**
 * **LAS RESERVAS DE MI CUENTA, en marcha** (T5b de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #775`): qué se
 * pide al servidor y cuándo. Lo que se pinta de cada reserva lo decide `reservas.js`, puro y probado.
 *
 *   · **Las próximas**, enteras de una vez (`per_page=50`, el tope de la API): son pocas, y Mi cuenta las enseña todas
 *     —la próxima y «Otras reservas»—. **El historial**, de diez en diez y creciendo con «Ver más».
 *     ⚠️ No es el store de pedidos del motor (`stores/orders.js`): aquél pagina de cinco en cinco y SUSTITUYE la página
 *     (la zona del cajón, con anterior y siguiente); aquí la lista crece. Mismos endpoints, mismo contrato.
 *   · **El contacto del parque** (`/site`: el teléfono de «Cambiar o cancelar», la ruta de «Cómo llegar»), solo cuando
 *     hace falta y una vez.
 *   · Se pide al entrar en Mi cuenta y no se repite al reabrirla (una recarga lo trae de nuevo).
 */
import { computed, reactive } from 'vue';
import { api } from '../../sidebar/api.js';
import { cambiarDe, filaHistorial, lineasDe, pagoDe, reservasDeCuenta, tarjetaDe } from './reservas.js';

const POR_PAGINA_HISTORIAL = 10;

export function useReservasCuenta({ textos, locale }) {
    const s = reactive({ proximas: null, pasadas: [], pagina: 0, ultima: 1, cargando: false, cargandoMas: false, sitio: null });
    const deps = { locale, textos };

    async function cargar() {
        if (s.proximas !== null || s.cargando) return;
        s.cargando = true;

        try {
            const [proximas, pasadas] = await Promise.all([
                api.get('/me/reservations/upcoming?per_page=50'),
                api.get(`/me/reservations/past?per_page=${POR_PAGINA_HISTORIAL}&page=1`),
            ]);

            if (proximas.ok) s.proximas = proximas.data?.data ?? [];
            if (pasadas.ok) Object.assign(s, { pasadas: pasadas.data?.data ?? [], pagina: 1, ultima: Number(pasadas.data?.meta?.last_page ?? 1) });
        } finally {
            s.cargando = false;
        }
    }

    async function mas() {
        if (s.cargandoMas || s.pagina >= s.ultima) return;
        s.cargandoMas = true;

        try {
            const r = await api.get(`/me/reservations/past?per_page=${POR_PAGINA_HISTORIAL}&page=${s.pagina + 1}`);

            if (r.ok) Object.assign(s, { pasadas: s.pasadas.concat(r.data?.data ?? []), pagina: s.pagina + 1, ultima: Number(r.data?.meta?.last_page ?? s.ultima) });
        } finally {
            s.cargandoMas = false;
        }
    }

    async function cargarSitio() {
        if (s.sitio !== null) return;
        const r = await api.get('/site');

        s.sitio = r.ok ? (r.data ?? {}) : {};
    }

    const listas = computed(() => reservasDeCuenta({ proximas: s.proximas, pasadas: s.pasadas }));
    const bloque = (card) => (card ? { tarjeta: tarjetaDe(card, deps), lineas: lineasDe(card, deps), pago: pagoDe(card, deps) } : null);

    return {
        s, cargar, mas, cargarSitio, listas, bloque,
        buscar: (id) => [listas.value.proxima, ...listas.value.otras].find((c) => c?.reservation?.id === id) ?? null,
        otras: computed(() => ({
            otras: listas.value.otras.map((c) => ({ id: c.reservation.id, tarjeta: tarjetaDe(c, deps) })),
            historial: listas.value.historial.map((c) => filaHistorial(c, deps)),
            hayMas: s.pagina < s.ultima, cargandoMas: s.cargandoMas,
        })),
        cambiar: (card) => cambiarDe(card, { ...deps, telefono: s.sitio?.contact?.phone ?? '' }),
        rutaAlParque: computed(() => s.sitio?.address?.maps_url ?? ''),
    };
}

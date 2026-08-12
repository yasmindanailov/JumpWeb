// Panel admin — Fase 7.4: calendario unificado (centro de mando).
//
// Componente Alpine `jjCalendar` que monta FullCalendar v6 sobre un nodo
// `wire:ignore` (FullCalendar es dueño de su propio DOM; Livewire no debe
// tocarlo). Se registra en `alpine:init` (+ registro inmediato defensivo por
// si el módulo carga después de arrancar Alpine, p. ej. tras `wire:navigate`).
//
// Eventos = productos individuales (`order_items`) servidos por el feed JSON
// `admin.calendario.eventos`, acotado al rango visible. Color = zona; el estado
// operativo (activo/finalizado) llega como classNames del evento.
// Click en un evento → abre la Action read-only `viewCalendarItem` de la página
// vía `$wire.mountAction` (patrón validado del panel, decisión #162: el objeto
// `$wire` de Alpine SÍ propaga, a diferencia de `wire:click` en subárboles).
//
// FullCalendar v6 autoinyecta su CSS vía JS: no hay import de CSS aquí.

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';
import zhLocale from '@fullcalendar/core/locales/zh-cn';

// P8: iconos de TIPO de producto (Heroicons outline: ticket / cake) inline para pintarlos en cada
// evento del calendario tintados con el color de zona (sustituyen al antiguo borde lateral de color).
// `currentColor` → el span padre fija `color` = color de zona. Mismos iconos que el resto del panel.
const TICKET_ICON_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z"/></svg>';
const CAKE_ICON_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513M15 8.25v-1.5m-6 1.5v-1.5m12 9.75-1.5.75a3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 0-3 0 3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 0-3 0 3.354 3.354 0 0 1-3 0L3 16.5m15-3.379a48.474 48.474 0 0 0-6-.371c-2.032 0-4.034.126-6 .371m12 0c.39.049.777.102 1.163.16 1.07.16 1.837 1.094 1.837 2.175v5.169c0 .621-.504 1.125-1.125 1.125H4.125A1.125 1.125 0 0 1 3 20.625v-5.17c0-1.08.768-2.014 1.837-2.174A47.78 47.78 0 0 1 6 13.12"/></svg>';

function jjCalendar(config = {}) {
    return {
        calendar: null,
        // Filtro de tipo de producto: 'all' | 'entry' | 'pack'.
        filter: config.initialFilter || 'all',

        init() {
            const el = this.$refs.calendar;
            if (!el) {
                return;
            }

            this.calendar = new Calendar(el, {
                plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
                initialView: config.initialView || 'dayGridMonth',
                // Deep-link `?date=YYYY-MM-DD` (#179): arranca en ese día; si no
                // se pasa, FullCalendar usa hoy.
                initialDate: config.initialDate || undefined,
                locales: [esLocale, zhLocale],
                locale: config.locale || 'es',
                // Horas como reloj de pared del parque (el feed manda ISO sin offset).
                timeZone: 'local',
                firstDay: 1, // semana empieza en lunes
                nowIndicator: true,
                dayMaxEvents: true, // mes: agrupa en "+N más" cuando hay muchos
                fixedWeekCount: false, // sin forzar 6 filas → menos huecos vacíos
                expandRows: true,
                height: 'auto',
                stickyHeaderDates: true,
                // Eventos como píldoras de bloque también en mes (look moderno),
                // no como puntito + texto.
                eventDisplay: 'block',
                // Números de día y cabeceras clicables → saltan a la vista de DÍA.
                navLinks: true,
                navLinkDayClick: (date) => {
                    this.calendar.changeView('timeGridDay', date);
                },
                // Punto 7 (decisión clienta 7.4 iter2): en la vista MES, pulsar
                // CUALQUIER hueco de la celda de un día (no solo el número) salta
                // a la vista de ese día — más intuitivo. En semana/día NO se hace:
                // pulsar una franja vacía no debe cambiar de vista.
                dateClick: (info) => {
                    if (info.view.type === 'dayGridMonth') {
                        this.calendar.changeView('timeGridDay', info.date);
                    }
                },
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
                },
                slotMinTime: config.slotMinTime || '08:00:00',
                slotMaxTime: config.slotMaxTime || '24:00:00',
                dayHeaderFormat: { weekday: 'short' },
                slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                moreLinkClick: 'popover',
                // Feed JSON acotado al rango visible (FullCalendar añade start/end).
                events: {
                    url: config.feedUrl,
                    method: 'GET',
                    extraParams: () => ({ type: this.filter }),
                },
                // Contenido del card (decisión clienta 7.4 iter2): nombre del
                // producto + primer nombre del cliente + meta (nº invitados /
                // cantidad + duración). El ESTADO operativo (activo / finalizado)
                // se ve por el color de fondo de la píldora que aplican las
                // `classNames` del feed.
                eventContent: (arg) => this.renderEventContent(arg),
                // (El color de la ZONA ya NO se expone como variable CSS por evento: tras retirar el
                // borde lateral y el punto de la vista agenda, el ICONO de tipo es su único portador
                // —se tinta directamente en `renderEventContent` con `icon.style.color`.)
                eventClick: (info) => {
                    info.jsEvent.preventDefault();
                    const id = parseInt(info.event.id, 10);
                    if (id && this.$wire) {
                        this.$wire.mountAction('viewCalendarItem', { item: id });
                    }
                },
            });

            this.$nextTick(() => this.calendar.render());
        },

        // Construye el DOM del evento (orden: producto · cliente · meta). En mes
        // se muestra en línea (la meta se oculta por falta de sitio); en semana/
        // día, donde el evento tiene altura, el CSS apila los datos hacia abajo
        // (punto 1) y la meta sí se ve (punto 3). El estado operativo (activo /
        // finalizado) lo indica el color de fondo de la píldora.
        renderEventContent(arg) {
            const p = arg.event.extendedProps || {};

            const root = document.createElement('div');
            root.className = 'jj-evt';

            // P8: icono del TIPO de producto (entrada → ticket, cumpleaños → tarta), tintado con el
            // color de zona. Sustituye al antiguo borde lateral de color; puede convivir con el badge
            // de post-form (segundo icono, ámbar/verde) cuando el pack lo pide.
            const icon = document.createElement('span');
            icon.className = 'jj-evt-icon';
            icon.setAttribute('aria-hidden', 'true');
            if (p.zoneColor) {
                icon.style.color = p.zoneColor;
            }
            icon.innerHTML = p.isPack ? CAKE_ICON_SVG : TICKET_ICON_SVG;
            root.appendChild(icon);

            // Indicador del post-form por-niño (#217): badge SEPARADO (no toca el fondo
            // activo/finalizado), solo en packs que lo piden. Verde ✓ = relleno; ámbar ! = pendiente.
            if (p.formStatus) {
                const form = document.createElement('span');
                form.className = 'jj-evt-form jj-evt-form--' + p.formStatus;
                if (p.formStatusLabel) {
                    form.title = p.formStatusLabel;
                }
                form.setAttribute('aria-hidden', 'true');
                root.appendChild(form);
            }

            const title = document.createElement('span');
            title.className = 'jj-evt-title';
            title.textContent = arg.event.title;
            root.appendChild(title);

            if (p.customerFirstName) {
                const cust = document.createElement('span');
                cust.className = 'jj-evt-cust';
                cust.textContent = p.customerFirstName;
                root.appendChild(cust);
            }

            if (p.meta) {
                const meta = document.createElement('span');
                meta.className = 'jj-evt-meta';
                meta.textContent = p.meta;
                root.appendChild(meta);
            }

            return { domNodes: [root] };
        },

        // Cambia el filtro de tipo y vuelve a pedir los eventos del rango actual.
        setFilter(value) {
            this.filter = value;
            if (this.calendar) {
                this.calendar.refetchEvents();
            }
        },

        // Limpieza al salir de la página (Livewire SPA `wire:navigate`).
        destroy() {
            if (this.calendar) {
                this.calendar.destroy();
                this.calendar = null;
            }
        },
    };
}

const register = (Alpine) => Alpine.data('jjCalendar', jjCalendar);

document.addEventListener('alpine:init', () => register(window.Alpine));

// Defensa anti-carrera: si Alpine ya estaba arrancado cuando este módulo se
// evalúa, `alpine:init` no volverá a dispararse — registramos en el acto.
if (window.Alpine) {
    register(window.Alpine);
}

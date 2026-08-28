import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useDateStore } from './date.js';

/**
 * La red del store del DÍA.
 *
 * ⚠️ **Lo que NO se prueba aquí, y es el reparto que hace barato tener stores**: el reparto en
 * semanas, los meses navegables, los rótulos y el precio del día son de `calendar.js` y `offer.js`,
 * que ya tienen sus casos y su paridad contra el servidor (`SidebarCalendarParityTest`). Repetirlos
 * aquí sería una segunda fuente de verdad que envejecería sola. Esto prueba **el estado**: qué se
 * guarda, qué se deriva y —sobre todo— **que lo derivado se REFRESCA**.
 *
 * ⚠️⚠️ **Y por eso casi todos los casos leen ANTES y DESPUÉS.** Es la lección de `DECISIONES #118`,
 * que costó una señal muerta en producción: un getter de Pinia es un `computed`, y **uno que nunca se
 * ha evaluado no puede estar rancio**. Leerlo una sola vez, después del cambio, siempre da el valor
 * bueno y no prueba nada. La primera lectura de cada caso no es decorativa: es la que crea la caché.
 */

const OFERTA = [
    { date: '2026-09-28', price_cents: 990 },
    { date: '2026-10-03', price_cents: 1200 },
    { date: '2026-11-01', price_cents: 1500 },
];

function store() {
    setActivePinia(createPinia());

    return useDateStore();
}

describe('el store del día', () => {
    test('arranca vacío y sin mes', () => {
        const d = store();

        assert.deepEqual(d.offered, []);
        assert.equal(d.selected, null);
        assert.equal(d.month, null);
        assert.deepEqual(d.weeks, [], 'sin mes no hay rejilla que pintar');
    });

    test('recibir la oferta abre en el PRIMER mes con oferta, no en el actual', () => {
        const d = store();

        assert.equal(d.month, null);            // ← crea la caché
        d.setOffer(OFERTA);

        assert.equal(d.month, '2026-09', 'abrir en «hoy» enseñaría una rejilla vacía');
        assert.deepEqual(d.navigableMonths, ['2026-09', '2026-10', '2026-11']);
    });

    test('una oferta que no es lista no rompe nada', () => {
        const d = store();

        for (const basura of [null, undefined, 'nope', 42, {}]) {
            d.setOffer(basura);
            assert.deepEqual(d.offered, [], `«${JSON.stringify(basura)}» debería dejar la oferta vacía`);
        }
    });

    test('la rejilla se REFRESCA al cambiar de mes', () => {
        const d = store();
        d.setOffer(OFERTA);

        const septiembre = d.weeks;             // ← la caché
        assert.ok(septiembre.length > 0);

        d.shift(1);

        assert.equal(d.month, '2026-10');
        assert.notDeepEqual(d.weeks, septiembre, 'otro mes tiene que dar otra rejilla');
    });

    test('los topes de navegación se REFRESCAN con el mes', () => {
        const d = store();
        d.setOffer(OFERTA);

        assert.equal(d.canPrev, false, 'en el primer mes con oferta no hay anterior');
        assert.equal(d.canNext, true);

        d.shift(1);
        assert.equal(d.canPrev, true, 'y al avanzar sí lo hay');

        d.shift(1);
        assert.equal(d.month, '2026-11');
        assert.equal(d.canNext, false, 'no se ofrece pasear por meses vacíos');
    });

    test('el precio del día se REFRESCA al elegir otro', () => {
        const d = store();
        d.setOffer(OFERTA);

        assert.equal(d.priceCents, null, 'sin día elegido no hay precio');

        d.select('2026-10-03');
        assert.equal(d.priceCents, 1200);

        d.select('2026-11-01');
        assert.equal(d.priceCents, 1500, 'el precio lo trae la oferta, no se deriva del catálogo');

        d.select('2026-12-25');
        assert.equal(d.priceCents, null, 'un día fuera de la oferta no tiene precio');
    });

    test('el rótulo del mes se REFRESCA, y con el idioma también', () => {
        const d = store();
        d.setOffer(OFERTA);

        const enEspanol = d.monthLabel;
        assert.ok(enEspanol.length > 0);

        d.setLocale('en');
        assert.notEqual(d.monthLabel, enEspanol, 'cambiar de idioma tiene que recomponer el rótulo');
        assert.notDeepEqual(d.weekdayHeaders, []);

        d.setLocale('');
        assert.equal(d.locale, 'es', 'un idioma vacío cae al de casa');
    });

    test('olvidar el día elegido CONSERVA la oferta', () => {
        const d = store();
        d.setOffer(OFERTA);
        d.select('2026-10-03');

        assert.equal(d.priceCents, 1200);       // ← la caché

        d.clearSelection();

        assert.equal(d.selected, null);
        assert.equal(d.priceCents, null);
        assert.equal(d.offered.length, 3, 'volver atrás dentro del mismo producto no vuelve a pedir los días');
        assert.equal(d.month, '2026-09', 'ni recoloca el calendario');
    });
});

// ── La TIRA y el calendario plegable (`DECISIONES #239`) ──────────────────────────────────────────

describe('la tira de días reservables', () => {
    /** ⚠️ Antes-y-después, como el resto del fichero: un getter sin evaluar no puede estar rancio. */
    test('se REFRESCA al elegir día y al recibir oferta nueva', () => {
        const d = store();
        d.setOffer(OFERTA);

        assert.deepEqual(d.strip.flatMap((g) => g.days.map((x) => x.selected)), [false, false, false]);

        d.select('2026-10-03');
        assert.deepEqual(d.strip.flatMap((g) => g.days.map((x) => x.selected)), [false, true, false]);

        d.setOffer([{ date: '2027-01-05', price_cents: 500 }]);
        assert.equal(d.strip.length, 1, 'la tira sigue a la oferta nueva');
        assert.equal(d.strip[0].days.length, 1);
    });

    test('el calendario nace CERRADO', () => {
        assert.equal(store().calendarOpen, false);
    });

    test('«ver más fechas» lo abre y lo cierra', () => {
        const d = store();

        d.toggleCalendar();
        assert.equal(d.calendarOpen, true);

        d.toggleCalendar();
        assert.equal(d.calendarOpen, false);
    });

    /**
     * ⚠️ Un producto nuevo devuelve el paso a su forma por defecto. Sin esto, quien despliega el
     * calendario en una entrada se encuentra la pantalla densa al mirar el pack siguiente, por una
     * decisión que tomó para otra cosa.
     */
    test('una oferta nueva vuelve a cerrar el calendario', () => {
        const d = store();
        d.setOffer(OFERTA);
        d.toggleCalendar();

        d.setOffer([{ date: '2027-01-05', price_cents: 500 }]);

        assert.equal(d.calendarOpen, false);
    });
});

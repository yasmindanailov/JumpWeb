import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { abrirEnlaceDeCuenta, enlaceDeCuenta } from './enlace-cuenta.js';

/** El enlace que abre Mi cuenta en la isla (T5a, `DECISIONES #773`): qué zona del motor y qué bloque pide. */
describe('el enlace a Mi cuenta', () => {
    test('#mi-cuenta abre el inicio, sin bloque', () => {
        assert.deepEqual(enlaceDeCuenta('#mi-cuenta'), { zona: 'home', bloque: '' });
    });

    test('con un bloque, el inicio en ese bloque (lo sitúa la isla)', () => {
        assert.deepEqual(enlaceDeCuenta('#mi-cuenta/antes'), { zona: 'home', bloque: 'antes' });
        assert.deepEqual(enlaceDeCuenta('#mi-cuenta/proxima'), { zona: 'home', bloque: 'proxima' });
    });

    test('Tu QR es otra vista y tiene su zona: la del carné', () => {
        assert.deepEqual(enlaceDeCuenta('#mi-cuenta/qr'), { zona: 'card', bloque: 'qr' });
        assert.deepEqual(enlaceDeCuenta('#mi-cuenta/mi-qr'), { zona: 'card', bloque: 'mi-qr' });
    });

    test('lo que no es un enlace a Mi cuenta no abre nada', () => {
        for (const hash of ['', '#', '#precio', '#mi-cuentas', '#mi-cuenta/', 'mi-cuenta', null, undefined]) {
            assert.equal(enlaceDeCuenta(hash), null, String(hash));
        }
    });

    test('el bloque es un NOMBRE: ni selectores, ni HTML, ni rutas', () => {
        for (const hash of ['#mi-cuenta/a b', '#mi-cuenta/<img>', '#mi-cuenta/antes/otra', '#mi-cuenta/.x', `#mi-cuenta/${'a'.repeat(41)}`]) {
            assert.equal(enlaceDeCuenta(hash), null, hash);
        }
    });
});

/** Abrir con el enlace: solo con la isla, en la zona que pide, como apertura «desde el enlace»; sabida o traída. */
describe('abrir Mi cuenta con el enlace', () => {
    const cajonDe = (carcasa, { abierto = false } = {}) => {
        const cajon = {
            isOpen: abierto, carcasa, aperturas: [], traidas: 0,
            carcasaActual() { return this.carcasa ?? 'cajon'; },
            openAccount(_e, zona, opciones) { this.isOpen = true; this.aperturas.push({ zona, ...opciones }); },
            async bootSpaEngine() { this.traidas += 1; this.carcasa = 'isla'; return null; },
        };

        return cajon;
    };

    test('con la isla sabida, abre en su zona y desde el enlace', () => {
        const cajon = cajonDe('isla');

        abrirEnlaceDeCuenta(cajon, { hash: '#mi-cuenta/qr', carcasaSabida: true, isla: 'isla' });

        assert.deepEqual(cajon.aperturas, [{ zona: 'card', desde: 'enlace' }]);
        assert.equal(cajon.traidas, 0, 'sabida la carcasa, no se trae nada');
    });

    test('con el cajón lateral, no abre', () => {
        const cajon = cajonDe('cajon');

        abrirEnlaceDeCuenta(cajon, { hash: '#mi-cuenta', carcasaSabida: true, isla: 'isla' });

        assert.deepEqual(cajon.aperturas, []);
    });

    test('sin carcasa sabida (una página ajena), trae el arranque y decide con él', async () => {
        const cajon = cajonDe(null);

        await abrirEnlaceDeCuenta(cajon, { hash: '#mi-cuenta/antes', carcasaSabida: false, isla: 'isla' });

        assert.equal(cajon.traidas, 1);
        assert.deepEqual(cajon.aperturas, [{ zona: 'home', desde: 'enlace' }]);
    });

    test('ya abierto, o sin enlace de verdad, no hace nada', () => {
        const abierto = cajonDe('isla', { abierto: true });
        abrirEnlaceDeCuenta(abierto, { hash: '#mi-cuenta', carcasaSabida: true, isla: 'isla' });
        assert.deepEqual(abierto.aperturas, []);

        const raro = cajonDe('isla');
        abrirEnlaceDeCuenta(raro, { hash: '#mi-cuenta/<x>', carcasaSabida: true, isla: 'isla' });
        assert.deepEqual(raro.aperturas, []);
    });
});

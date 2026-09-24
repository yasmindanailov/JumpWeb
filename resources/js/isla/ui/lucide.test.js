import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { transformar } from './lucide.js';

// La gemela de `LucideIconTest` del lado PHP: mismas sustituciones, mismo orden, solo la primera aparición.
test('solo cambia la primera aparición de cada cadena, como el diseño', () => {
    const svg = '<svg width="24" height="24" fill="none"><rect width="24" height="24" fill="none"/></svg>';
    assert.equal(transformar(svg), '<svg width="100%" height="100%" fill="none"><rect width="24" height="24" fill="none"/></svg>');
    assert.equal(transformar(svg, true), '<svg width="100%" height="100%" fill="currentColor"><rect width="24" height="24" fill="none"/></svg>');
});

test('sobre un icono real del set versionado, sale igual que en PHP', () => {
    const svg = readFileSync(new URL('../../../icons/lucide/icons/ticket.svg', import.meta.url), 'utf8');
    const t = transformar(svg);
    assert.match(t, /class="lucide lucide-ticket"/);
    assert.match(t, /width="100%"/);
    assert.doesNotMatch(t, /width="24"/);
});

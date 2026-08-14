/**
 * El formato de IMPORTES del cajón (Fase 4 · paso 4.3·1).
 *
 * **Espeja `number_format($euros, N, ',', '.')` de PHP, que es lo que usa el motor Livewire en sus 20
 * llamadas del blade y en `Purchase::money()`.** No es una preferencia de estilo: los dos motores
 * conviven bajo un feature-flag y tienen que pintar el mismo importe.
 *
 * ⚠️ **Y el gate de paridad es CIEGO a esto.** `SidebarDomContractTest::describe()` descarta todo nodo
 * que no sea un elemento —«el contrato es la estructura, no la copia»—, así que un motor que pinte
 * «1000,00 €» donde el otro pinta «1.000,00 €» pasa VERDE. Por eso este módulo trae su propia red:
 * `SidebarMoneyParityTest` compara su salida con la de `number_format` sobre un barrido de importes.
 *
 * ⚠️ **Las dos salidas «obvias» de JavaScript están MEDIDAS y las dos fallan**:
 *  - `(c/100).toFixed(2).replace('.', ',')` no agrupa nunca: 100000 → «1000,00» (PHP: «1.000,00»);
 *  - `Intl.NumberFormat('es-ES')` no agrupa ENTRE 1.000 y 9.999, porque el español declara
 *    `minimumGroupingDigits: 2`: 100000 → «1000,00» y 1000000 → «10.000,00». Es decir, arregla los
 *    importes de cinco cifras y deja rotos justo los de cuatro, que son los que más aparece en una
 *    cesta (un pack de 20 invitados cruza los 1.000 € a diario).
 *
 * Por eso se formatea a mano y **en aritmética ENTERA sobre céntimos**: dividir por 100 en coma
 * flotante y redondear después es la otra forma conocida de que un importe salga con un céntimo de
 * menos, y aquí no hace ninguna falta.
 */

/**
 * Agrupa de tres en tres con el separador de millares, como hace `number_format`.
 *
 * ⚠️ PHP agrupa SIEMPRE a partir de cuatro dígitos; no tiene el `minimumGroupingDigits` de ICU.
 */
function group(digits) {
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/**
 * Céntimos → la cadena de `number_format(céntimos / 100, decimales, ',', '.')`.
 *
 * Se opera en enteros: `whole` y `frac` salen de una división entera y de un módulo, así que no hay
 * ningún flotante intermedio que pueda redondear a la baja.
 *
 * @param {number} cents
 * @param {0|2} decimals
 * @returns {string}
 */
function format(cents, decimals) {
    const value = Math.trunc(Number(cents) || 0);
    const abs = Math.abs(value);

    const body = decimals === 0
        // `number_format(x, 0)` redondea al alza el medio (0,5 → 1). Se hace con enteros para que
        // 99950 dé «1.000» y no «999» por un flotante.
        ? group(String(Math.floor((abs + 50) / 100)))
        : group(String(Math.floor(abs / 100))) + ',' + String(abs % 100).padStart(2, '0');

    // ⚠️ **El signo se decide sobre el RESULTADO, no sobre la entrada**, y esto lo destapó el barrido
    // de `SidebarMoneyParityTest`, no la lectura: `number_format(-0.05, 0)` devuelve «0», no «-0»,
    // porque el redondeo se ha comido la magnitud. Un signo puesto a la entrada da «-0€».
    const isZero = ! /[1-9]/.test(body);

    return (value < 0 && ! isZero ? '-' : '') + body;
}

/**
 * Un importe del cajón: dos decimales y « €» con espacio delante.
 *
 * Es el formato de `Purchase::money()` y de las 17 líneas del blade que formatean dinero.
 */
export function money(cents) {
    return format(cents, 2) + ' €';
}

/**
 * El precio de una celda del calendario: SIN decimales y con el «€» PEGADO.
 *
 * ⚠️ No es «el mismo formato con menos decimales» y las dos diferencias son deliberadas: en una
 * rejilla de siete columnas los céntimos no caben, y el símbolo va pegado porque así lo emite el
 * blade (`{{ number_format(...) }}€`), al contrario que en el resto del cajón.
 *
 * ⚠️ Redondea, no trunca: 990 céntimos se pinta «10€». El repo tiene un precedente de lo contrario
 * (`TicketType::euros()` usa `intdiv`), así que conviene no copiarlo por costumbre.
 */
export function dayPrice(cents) {
    return format(cents, 0) + '€';
}

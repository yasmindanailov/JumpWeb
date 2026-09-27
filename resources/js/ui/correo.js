/**
 * **«¿Querías decir…?»: la regla del correo mal escrito, UNA para todo el sistema** (`forms/Field.jsx`, `suggest`, del zip
 * tercero): la usan la fiesta del SPA (F9, `fiesta/logica.js` la reexporta tal cual) y los campos de correo de la isla
 * (`isla/ui/CampoSistema.vue`, `isla-y-landing-nueva.md` §4.16). Vive en su propio módulo, y no en `fiesta/logica.js`,
 * por PESO, medido el 27-09: importada desde allí, la isla se llevaba el módulo entero de la fiesta (un trozo compartido
 * de 4.480 B) en sus pasos de compra y en Mi cuenta, por una función que ocupa una fracción. Movida sin tocar un carácter.
 */

/*
 * EL CORREO MAL ESCRITO (F9, el zip tercero `#780`: `forms/Field.jsx`, `suggest`), portado TAL CUAL. La copia del
 * justificante llega por correo, y un «gmial.com» la pierde sin que nadie se entere. Se compara el dominio con los
 * proveedores habituales en España; solo se sugiere si el nombre está a una letra (a dos en los de seis o más; ninguna en
 * los de cuatro o menos) o si la terminación no existe («.con»). Un dominio real con otra terminación («orange.fr») no se
 * toca, y los reales parecidos a otros («mail», «ya», «me») están para que nunca se «corrijan».
 */
const PROVEEDORES = {
    gmail: ['com'], googlemail: ['com'], hotmail: ['com', 'es'], outlook: ['com', 'es'], live: ['com'], yahoo: ['com', 'es'],
    icloud: ['com'], msn: ['com'], telefonica: ['net'], movistar: ['es'], orange: ['es'], vodafone: ['es'], protonmail: ['com'],
    gmx: ['es', 'com'],
    mail: ['com'], aol: ['com'], ono: ['com'], terra: ['es'], jazztel: ['es'], euskaltel: ['net'], me: ['com'], ya: ['com'],
};
const UNICA = { gmail: 1, googlemail: 1, icloud: 1, msn: 1, protonmail: 1 };
const TLDS = ['com', 'es', 'net', 'org', 'eu', 'fr', 'it', 'de', 'pt', 'uk', 'co.uk', 'cat', 'info', 'me', 'io'];

/** La distancia de edición con trasposición (Damerau): «gmial» está a una de «gmail». */
function distancia(a, b) {
    const d = Array.from({ length: a.length + 1 }, (_, i) => [i].concat(Array(b.length).fill(0)));
    for (let j = 1; j <= b.length; j += 1) d[0][j] = j;
    for (let i = 1; i <= a.length; i += 1) {
        for (let j = 1; j <= b.length; j += 1) {
            d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
            if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) d[i][j] = Math.min(d[i][j], d[i - 2][j - 2] + 1);
        }
    }

    return d[a.length][b.length];
}

/** El correo bien escrito, o `null` si no hay nada que proponer. */
export function sugerirCorreo(valor) {
    const v = (valor || '').trim();
    const at = v.lastIndexOf('@');
    if (at < 1 || at === v.length - 1 || /\s/.test(v)) return null;
    const local = v.slice(0, at);
    const dom = v.slice(at + 1).toLowerCase();
    const dot = dom.indexOf('.');
    const nombre = dot > -1 ? dom.slice(0, dot) : dom;
    const tld = dot > -1 ? dom.slice(dot + 1) : '';
    const arreglar = (p) => {
        const t = PROVEEDORES[p];
        if (t.indexOf(tld) > -1) return tld;

        return (UNICA[p] || TLDS.indexOf(tld) < 0) ? t[0] : tld;
    };
    if (PROVEEDORES[nombre]) {
        const t = arreglar(nombre);

        return t === tld ? null : `${local}@${nombre}.${t}`;
    }
    if (!tld) {
        const pegado = Object.keys(PROVEEDORES).find((p) => PROVEEDORES[p].some((t) => distancia(dom, `${p}.${t}`) <= 1));

        return pegado ? `${local}@${pegado}.${PROVEEDORES[pegado][0]}` : null;
    }
    let mejor = null;
    let dm = 9;
    Object.keys(PROVEEDORES).forEach((p) => {
        const tope = p.length >= 6 ? 2 : p.length === 5 ? 1 : 0;
        const k = distancia(nombre, p);
        if (k < dm && k <= tope) { dm = k; mejor = p; }
    });

    return mejor ? `${local}@${mejor}.${arreglar(mejor)}` : null;
}

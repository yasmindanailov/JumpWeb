// **Copia las reseñas de TU ficha de Google Maps desde tu navegador** (`DECISIONES #771`) — la alternativa manual a
// `scripts/resenas-google.mjs`, para cuando prefieras hacerlo tú o Google no deje entrar al navegador automático.
//
// 1. Abre tu ficha en Google Maps (en el ordenador) y pulsa la pestaña «Reseñas». Ordénalas por «Más recientes».
// 2. Abre la consola del navegador (F12 → «Consola»), pega TODO este fichero y pulsa Intro.
// 3. Baja sola hasta cargar todas, despliega los «Más» y descarga `resenas.json`.
// 4. Déjalo en `storage/app/resenas/` del producto y ejecuta: php artisan reviews:import storage/app/resenas/resenas.json
//
// Copia lo mismo que la herramienta automática: id, autor, su línea, su foto, su perfil, estrellas, fecha, texto, fotos
// y tu respuesta. Nada sale de tu navegador salvo el fichero que descargas.
(async () => {
    const espera = (ms) => new Promise((r) => setTimeout(r, ms));
    const ids = () => new Set([...document.querySelectorAll('div[data-review-id]')].map((e) => e.getAttribute('data-review-id')));
    const total = (() => { const m = document.body.innerText.match(/([\d.]+)\s+reseñas/); return m ? Number(m[1].replace(/\./g, '')) : null; })();
    let vistas = 0;
    let quietas = 0;
    while (quietas < 8) {
        const una = document.querySelector('[data-review-id]');
        let caja = una;
        while (caja && !(caja.scrollHeight > caja.clientHeight + 10 && getComputedStyle(caja).overflowY !== 'visible')) caja = caja.parentElement;
        if (caja) caja.scrollTop = caja.scrollHeight;
        await espera(900);
        const ahora = ids().size;
        quietas = ahora === vistas ? quietas + 1 : 0;
        vistas = ahora;
        console.log(`reseñas cargadas: ${vistas}${total ? ' de ' + total : ''}`);
        if (total && vistas >= total) break;
    }
    document.querySelectorAll('button.w8nwRe, button[aria-label="Ver más"]').forEach((b) => b.click());
    await espera(800);

    const grande = (src, tam) => (src ? src.replace(/=[swh]\d+[^/]*$/, '=' + tam) : null);
    const fondo = (el) => { const m = (el.getAttribute('style') || '').match(/url\(["']?([^"')]+)["']?\)/); return m ? m[1] : null; };
    const unicas = new Map();
    for (const el of document.querySelectorAll('div.jftiEf[data-review-id], div[data-review-id][aria-label]')) {
        const id = el.getAttribute('data-review-id');
        if (!id || unicas.has(id)) continue;
        const estrellas = el.querySelector('span[role="img"][aria-label*="estrella"], span.kvMYJc');
        const cuerpo = el.querySelector('.MyEned .wiI7pd') || el.querySelector('.wiI7pd');
        const respuesta = el.querySelector('.CDe7pd .wiI7pd');
        const perfil = el.querySelector('button.WEBjve, button.al6Kxe');
        unicas.set(id, {
            id,
            author: el.querySelector('.d4r55')?.textContent.trim() ?? el.getAttribute('aria-label') ?? '',
            author_meta: el.querySelector('.RfnDt')?.textContent.trim() ?? null,
            author_url: perfil?.getAttribute('data-href') ?? null,
            avatar_url: grande(el.querySelector('img.NBa7we')?.getAttribute('src'), 's160-c'),
            rating: estrellas ? Number((estrellas.getAttribute('aria-label') || '').match(/(\d)/)?.[1] ?? 0) : null,
            when: el.querySelector('.rsqaWe')?.textContent.trim() ?? null,
            text: cuerpo?.textContent.trim() ?? '',
            translated: Boolean(el.querySelector('button[aria-label*="original"], .kyuRq')),
            photos: [...el.querySelectorAll('button.Tya61d')].map((b) => grande(fondo(b), 'w1200')).filter(Boolean),
            reply: respuesta?.textContent.trim() ?? null,
        });
    }
    const copia = { source: location.href, place_url: location.href.split('/data=')[0], total, copied_at: new Date().toISOString(), reviews: [...unicas.values()] };
    const enlace = Object.assign(document.createElement('a'), { href: URL.createObjectURL(new Blob([JSON.stringify(copia, null, 2)], { type: 'application/json' })), download: 'resenas.json' });
    enlace.click();
    console.log(`✓ ${copia.reviews.length} reseñas en resenas.json (con texto: ${copia.reviews.filter((r) => r.text).length})`);
})();

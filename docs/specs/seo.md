# [SPEC] El SEO de la web: salir el primero en lo que convierte, medido

> Estado: 🟦 en curso (investigado y medido el 30-09; S4 y S6 hechos y S3 empezado el 01-10; S1/S2, en manos del owner con
> los textos en la instancia) · Carril: plataforma (`#861`: el SEO y las
> imágenes de la web son de aquí; la imagen de la INVITACIÓN, del SPA) · Encargo del owner (30-09): «el SEO es
> IMPORTANTÍSIMO: quiero salir el primero en los temas principales cuando el usuario busque lo que más convierta; con rigor
> y empirismo, documéntate primero». La web de la que se habla es la NUEVA: sale con la v2.0.0 (`#670`), no antes.

## §0 · Antes de tocar

- **La regla**: se mide antes y después. `scripts/sonda-seo.mjs` da, por página, lo que Google lee (título, descripción,
  canónica, H1, `og:image`, `hreflang`, JSON-LD) y los Core Web Vitals de LABORATORIO en un móvil (4G lenta, CPU ×4). Google
  juzga con datos de CAMPO (CrUX, percentil 75): la sonda compara versiones, no da la nota.
- **Empieza por** §1 (lo que dice Google, con sus fuentes) → §2 (lo medido) → §4 (el plan por impacto).
- **Trampas**: (1) en LOCAL no hay compresión ni HTTP/2 (producción: LiteSpeed, HTTP/2 y 3, gzip y brotli): los tiempos
  locales exageran; se comparan entre sí. (2) El buscador de las herramientas del agente no es Google España: sus
  resultados orientan, no miden posiciones. (3) El ranking LOCAL (el mapa de Google) es sobre todo el Perfil de Empresa:
  la web ayuda, no lo decide sola (§1). (4) Nada de estrellas con reseñas propias: Google no las da a quien se reseña a sí
  mismo. (5) El idioma cambia por SESIÓN en la misma URL: Google solo ve el español.
- **Invariantes**: los 301 de la web vieja (`#843`) no se tocan; ninguna página pública con `noindex` por accidente.

## 1. Lo que dice Google (leído el 30-09)

| Tema | Lo que dice | Fuente |
|---|---|---|
| Ranking local | Tres factores: **relevancia**, **distancia** y **prominencia**; esta última «también se basa en cuántas webs enlazan a tu negocio y cuántas reseñas tienes». Perfil completo y verificado, horario, fotos, responder reseñas. No se paga | [Google, ranking local](https://support.google.com/business/answer/7091?hl=en) |
| Datos estructurados | `LocalBusiness` (el subtipo más específico: `AmusementPark`): obligatorios `name` y `address`; recomendados `geo` (5 decimales), `openingHoursSpecification`, `priceRange` (< 100 caracteres), `telephone`, `url` | [LocalBusiness](https://developers.google.com/search/docs/appearance/structured-data/local-business) |
| Reseñas propias | «Si la entidad reseñada controla las reseñas sobre sí misma», sus páginas **no pueden tener estrellas** | [Review snippet](https://developers.google.com/search/docs/appearance/structured-data/review-snippet) |
| Preguntas frecuentes | Desde agosto de 2023 el resultado enriquecido de FAQ solo sale en webs oficiales de gobierno y salud: marcarlo no se ve | [HowTo y FAQ](https://developers.google.com/search/blog/2023/08/howto-faq-changes) |
| Idiomas | Mejor una URL por idioma y decirlo con `hreflang` (cada versión se nombra a sí misma y a las demás; `x-default`) | [Versiones por idioma](https://developers.google.com/search/docs/specialty/international/localized-versions) |
| Títulos | Únicos, que describan la página; la marca al principio o al final con un separador; sin repetir palabras clave. Se cortan al ancho del móvil. Google puede reescribirlos con el H1 | [Title links](https://developers.google.com/search/docs/appearance/title-link) |
| Contenido | URLs con palabras, texto propio y ordenado, `alt` descriptivo, enlaces con texto que diga adónde van. No cuentan: la etiqueta `keywords`, el número de palabras, el orden de los encabezados | [Guía de inicio SEO](https://developers.google.com/search/docs/fundamentals/seo-starter-guide) |
| Core Web Vitals | Bueno: **LCP < 2,5 s**, **INP < 200 ms**, **CLS < 0,1**; forman parte de lo que el ranking premia | [Core Web Vitals](https://developers.google.com/search/docs/appearance/core-web-vitals) |
| Compartir por WhatsApp | 1200 × 630 (1,91:1), JPG; por debajo de 300 KB (por encima de 600 KB la descarta) | [guía WhatsApp](https://opengraphplus.com/consumers/whatsapp/images) (no oficial) |

## 2. Lo medido (30-09, en local, `sonda-seo.mjs`; y fuera)

**La competencia** (buscador de las herramientas, orientativo): en *trampolines* en Lorca, **Sould Park Jump** (CC Parque
Almenara, 4,9 en Maps) y, en la Región, Star Jump Park, Cero G y Murcia Jump; Urban Planet Lorca cerró. En *cumpleaños
infantiles Lorca* y *parque de bolas Lorca* salen Happyland, Pequeland, El Trastolillo, El Tipi, Las Bolas, PeriquitosPark
y Bola Loca, y **directorios** que los listan y a Play Jump no: elcircodechloe.es, parqueinfantilen.com, cumpleclub.com,
playbooking.club, planinfantil.es, parquesdebolas.es, lumpio.com, planenfamilia.com (y Tripadvisor). Producción (la web
vieja) sale con «Parque de saltos para toda la familia · **Murcia**» y «in the heart of Murcia»: el parque está en Lorca.

**La web nueva, página a página** (móvil 390×844, 4G lenta, CPU ×4, sin caché; LOCAL sin compresión):

| Página | LCP (s) | Qué es el LCP | CLS | Peso | Título | Descripción |
|---|---|---|---|---|---|---|
| Portada | 4,6 | póster del vídeo (45 KB) | 0,085 | 3,1 MB · 56 pet. | 47 | **203** |
| Cumpleaños | 4,7 | póster del vídeo | 0,025 | 1,5 MB · 54 | 47 | 156 |
| Kids | **8,6** | `kids_zone.webp` (188 KB, una sola medida) | **0,115** | 3,4 MB · 60 | **67** | 155 |
| Jump | **7,3** | `park_jump.webp` (145 KB) | 0,089 | 4,3 MB · 67 | **75** | 144 |
| Colegios | 4,6 | — | 0,059 | 1,5 MB · 55 | 48 | 169 |
| Visítanos | 3,9 | — | 0,027 | 1,2 MB · 44 | 54 | 161 |
| Normas | 4,2 | — | 0,013 | 1,3 MB · 45 | 53 | 160 |

- **El LCP de Kids y Jump** es una foto de la cabecera sin `fetchpriority`, sin precarga y sin tamaños para el móvil, que
  compite con los JavaScript que la página precarga antes (`modulepreload` del cajón y la isla).
- **Lo que Google lee**: un H1 por página, pero **ninguno lleva lo que se busca** («El cumpleaños que ellos piden, resuelto
  para ti», «Saltan hasta caer rendidos, y tú descansas»); el título sí. Ninguna imagen sin `alt` (las vacías son
  decorativas). Enlaces internos entre las siete páginas. JSON-LD `Organization` + `AmusementPark` (bien: sin reseñas
  propias) al que faltan `geo`, `priceRange` y lo propio de cada página. `og:image` = una foto `.webp` de medida libre (y
  `og-image.jpg` genérica en tres). **Sin `hreflang`**. `robots.txt` sin la línea `Sitemap:`.
- **La mudanza** (v2.0.0): las 11 URLs del sitemap de producción dan 200 o un 301 a su página nueva (`#843`). No se pierde
  nada por 404.
**Search Console de producción** (el owner, 30-09; web vieja; tipo Web; «últimos 3 meses», con datos desde el 1-09): ~3.050
clics y ~12.900 impresiones. El export no entra al repo (es de la instalación); se pide otro al medir de nuevo.

| Lo medido | Dato |
|---|---|
| Marca vs. resto | **42 búsquedas de MARCA** («play jump park lorca» 778 clics, pos. 1,06; «play jump park» 532; «playjump lorca» 157…): **1.929 clics**. **427 sin marca: 168 clics** y 2.319 impresiones |
| Por página | Portada **2.880 clics de ~3.050** (94 %). `/entradas` 82 (3.052 impr., CTR 2,7 %), `/precios` 56, `/normas` 21, `/atracciones` **8 de 1.793 impr.** (0,45 %), **`/cumpleanos` 1 impresión y 0 clics** |
| Cumpleaños (la prioridad del owner) | 12 búsquedas, **27 impresiones en tres meses** («cumpleaños lorca» 9, pos. 2,7; «locales para cumpleaños infantiles» 4): para Google, la web no es de cumpleaños |
| Trampolines y saltos | Bien con «Lorca»: «jump lorca» pos. 1,6, «jumping lorca» 1,7, «jumping park lorca» 1,5, «colchonetas lorca» 2,8; flojo sin él: «trampoline park» 4,2 (0 clics), «jump park» 6,4, «parque de trampolines» 27 |
| Kids | «parque de bolas lorca» 73 impr., 18 clics, pos. 2,7; «parque infantil lorca» pos. 1 |
| Colegios | Nada (una impresión) |
| Países | España **99,6 %** de los clics (Reino Unido 19 impr., Francia 14): el inglés y el francés no tienen demanda que medir |
| Dispositivos | Móvil **94 %** de los clics (pos. 2,4; ordenador, 10,9) |

**PageSpeed de producción, CAMPO** (el owner, 30-09; 28 días, 1→28-09): móvil **aprueba** los Core Web Vitals: LCP 1,6 s, INP
151 ms, CLS 0,06, TTFB 0,6 s (ordenador: 1,3 s · 140 ms · 0,04). Lo que hay que conseguir con la web nueva es **no empeorar**:
los tiempos de la tabla de arriba son de laboratorio en local y no se comparan con estos.

## 3. Lo que falta para medir de verdad (del owner)

- ✅ Search Console y PageSpeed de campo: recibidos el 30-09 (§2).
- 🟦 **Perfil de Empresa** (el owner, 01-10): 2.827 personas vieron el perfil (56 % Búsqueda móvil, **37 % Maps móvil**, 6 %
  Búsqueda en ordenador, 1 % Maps en ordenador: el 93 % en el móvil); interacciones: 7 en agosto y **2.695 en septiembre** (el
  perfil es NUEVO: le falta la prominencia de reseñas y enlaces). «Editar perfil», recibido: el estado, lo que NO cuadra con
  la web (código postal 30813/30800, el domingo 21:00/21:30, el Instagram) y lo recomendado con sus textos viven en la
  INSTANCIA, `instancias/playjump/docs/perfil-de-empresa.md` (son datos del cliente). `[PENDIENTE: owner]` los TÉRMINOS de
  búsqueda: Google los actualiza a principios de mes y tarda hasta 5 días; los de septiembre, desde el 6 de octubre.
- `[PENDIENTE: owner]` Si hay cuenta de Google Ads: el Planificador de palabras clave para Lorca y la Región (volúmenes).

## 4. El plan, por impacto (ordenado con los datos de §2)

La marca ya está en el 1: **lo que falta es que la web exista para quien aún no la conoce**, y sobre todo para quien busca
un CUMPLEAÑOS, que es la prioridad del owner y hoy no sale.

| # | Qué | Por qué (§1/§2) | De quién |
|---|---|---|---|
| S1 | **Cumpleaños, visible**: la página nueva ya habla de cumpleaños infantiles en Lorca (título, texto, 1.160 palabras); falta que Google lo sepa: el Perfil de Empresa con la categoría y el servicio de fiestas infantiles, los directorios de cumpleaños de §2 enlazando a `/cumpleanos`, y el H1 (S6) | 27 impresiones de cumpleaños en tres meses | Owner (el agente prepara las fichas y los textos) y aquí |
| S2 | 🟦 (01-10: recomendado con sus textos en `instancias/playjump/docs/perfil-de-empresa.md`; lo aplica el owner) **El Perfil de Empresa, completo** (categoría principal «Parque de trampolines», secundarias de fiestas infantiles y parque infantil; servicios con precio; fotos y vídeos; publicaciones; responder todas las reseñas) y **los directorios** de §2 con el mismo nombre, dirección y teléfono | El mapa: relevancia y prominencia («cuántas webs enlazan») | Owner (el agente prepara los textos) |
| S3 | 🟦 **No empeorar los Core Web Vitals** (hoy aprueban en campo). Diagnosticado el 01-10 con la línea de tiempo del navegador: la foto de la cabecera (el LCP) se pedía a los 0,5 s y llegaba a los 8,7 s porque **compartía la red con ~2 MB de fotos de atracciones de más abajo, sin diferir**. HECHO (instancia): `loading="lazy"` en las fotos de atracciones (`clip-tile`, `clip-list`) y en el logotipo del pie → **Kids, LCP 8,7 → 6,5 s y 3,4 → 1,5 MB al llegar; Jump, 7,5 → 6,3 s y 4,3 → 1,5 MB** (laboratorio local, dos pasadas por lado y en la misma franja horaria). `fetchpriority="high"` en la foto y la precarga del póster: sin efecto medible en local (HTTP/1.1, sin compresión), se quedan por ser la práctica recomendada y se miden en staging — **cuando estén los vídeos e imágenes nuevos** (el owner, 01-10: no antes). Cómo se diagnosticó: la línea de tiempo del navegador (candidatos a LCP, recursos con su inicio y fin, lo que bloquea el render), con las condiciones de `sonda-seo.mjs` (un guion puntual, sin versionar, en la carpeta de auditoría de la máquina de plataforma: si hace falta otra vez, se pasa a `scripts/`). **Queda**: el logotipo (PNG de 400×151 y 115 KB: debería pesar ~20 KB), la foto de la cabecera en tamaños por pantalla (1600 px y 184 KB para un móvil; mecanismo de producto), `cajon.css` bloqueando el primer pintado (39 KB comprimido) y el CLS que deja la cabecera al volver a medirse cuando llega la isla (0,07). ⚠️ Lo comprimido: CSS ~71 KB y JS ~94 KB (en local viajan sin comprimir: los tiempos locales exageran) | Kids y Jump daban el doble de LCP que el resto en el mismo laboratorio | Aquí |
| S4 | 🟦 **Lo que lee Google** (01-10): `robots.txt` lo escribe el producto (`RobotsController`) con `Sitemap:` y la URL entera (staging sigue cerrado: su fichero lo pone el despliegue y se sirve antes); el JSON-LD de negocio gana `geo` (de la inserción del mapa, 7 decimales, `MapsEmbed::coordinates`), `hasMap`, `priceRange` («Desde 6,40 €») y la dirección por campos (`VenueAddress::parts`: calle, código postal, localidad, provincia; `#650` intacto: la regla sigue en una clase); la descripción de la portada, de 203 a 153 caracteres. **Sin miga** (BreadcrumbList): Google no la enseña en el móvil desde enero de 2025 ([Google](https://developers.google.com/search/blog/2025/01/simplifying-breadcrumbs)) y el móvil es el 94 %. **Los títulos se quedan**: la palabra clave va delante y lo que se corta es la marca, que Google ya enseña aparte. Guardas: `SeoTest`, `StructuredDataTest`, `VenueAddressTest`, `MapsEmbedTest`, `InstancePagesTest`; arnés `mutar-seo.sh` | Recomendados por Google | Aquí |
| S5 | **La imagen al compartir de cada página**: 1200 × 630 JPG < 300 KB con la marca, dibujada en el servidor (`ENTORNOS.md` §6) | El móvil es el 94 %; WhatsApp, el canal de las familias | Aquí |
| S6 | ✅ `[DECIDIDO owner]` 01-10 (`#862`) · **Los titulares SE QUEDAN**: venden, Google dice que el H1 es «útil pero no crítico» ([SEJ](https://www.searchenginejournal.com/google-h1-headings-seo/328459/)) y el diseño ya probó y quitó un sobretítulo («Sin sobretítulo de producto», readme del zip). Lo que sí importa, a la vez para Google y para convertir («information scent», [NN/g](https://www.nngroup.com/articles/information-scent/)), es que **la primera pantalla diga QUÉ y DÓNDE**. Medido: portada, Cumpleaños y Colegios, sí; **Kids y Jump no dicen «Lorca»** (y «parque de bolas lorca» es la mejor búsqueda sin marca): «en Lorca» en su primera frase. La de Cumpleaños cambia igual con Z6e (las dos horas, 90 + 30) y tiene que seguir diciendo «cumpleaños» y «en Lorca». HECHO en la instancia (`lang/{es,en,fr}/paginas.php`): «La zona Kids de Play Jump Park, en Lorca, es solo para niños…» y «La zona Jump de Play Jump Park, en Lorca, es la de los mayores…»; `sonda-primera-pantalla` igual con y sin el cambio (control: los 9 fallos conocidos de la primera visita, regla a regla) | Relevancia y confirmación de quien llega de Google | Aquí (las frases, del owner) |
| S7 | **Una URL por idioma** (`/en/`, `/fr/`) con `hreflang` | España es el 99,6 %: **no ahora** | Aparcado por los datos |
| S8 | **El día de la v2.0.0**: los 301 están (las 11 del sitemap y `/atracciones`, 1.793 impr.); enviar el sitemap nuevo en Search Console y vigilar la cobertura | La mudanza | Aquí, con el owner |

Cada fila de «Aquí» se mide con `sonda-seo.mjs` antes y después, con su guarda, y se ve en local antes de commitear.

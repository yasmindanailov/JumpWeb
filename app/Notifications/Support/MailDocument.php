<?php

namespace App\Notifications\Support;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **EL DOCUMENTO de un correo** (la R1a, `specs/correos-rediseno.md` §4.1.1): la plantilla del diseño en el servidor —una
 * lista de BLOQUES, cada uno pintado dos veces (`correo/html/<tipo>` y `correo/texto/<tipo>`), con el aire entre bloques
 * calculado por el bloque SIGUIENTE—, compuesta a partir de los MISMOS datos que ya da cada correo por los verbos del molde
 * (`hero`, `line`, `notice`, `action`, `outro`). Así los 28 pasan a la plantilla nueva sin tocar ninguno.
 *
 * ▶ **Por qué no Markdown** (lo que había): el aire depende del vecino (16 px dentro de una zona, 28 entre zonas) y el
 * oscuro va por CLASE; un parser de Markdown no conoce al vecino, y el inliner de Laravel descarta lo que no puede pegar a
 * una etiqueta (`#503`: la `@media` desaparecía sin que fallara nada).
 *
 * ▶ **Los bloques de la R1a son los que usan los 28 hoy** —cabecera, resguardo de filas, texto, aviso, botón, línea y
 * pie— y uno de paso, `marcado`, para el HTML que un correo compone él mismo (el libro del pedido, la ficha de producto,
 * los enlaces de baja de los dos comerciales). La R1c suma el `codigo`, con sus tres consumidores (los correos de código).
 * QR, pasos, lista, sección… nacen con su primer consumidor (R2, C1): una pieza no se declara antes que su consumidor (`#503`).
 *
 * ⚠️ **Todo se ESCAPA**, y el único formato que se entiende es la negrita `**así**`, la que ya escribía
 * `CustomerAccountCreated`: antes cada línea pasaba por Markdown y un `_` o un `*` de un dato del cliente (un correo con
 * guiones bajos) podía volverse cursiva. Ahora no.
 */
final class MailDocument
{
    /** La zona de cada bloque: el aire entre dos bloques depende de si comparten zona (el `ZONA` del diseño). */
    public const ZONAS = [
        'cabecera' => 'cabecera',
        'resguardo' => 'hecho',
        'texto' => 'hecho',
        'codigo' => 'accion',
        'boton' => 'accion',
        'aviso' => 'detalle',
        'linea' => 'detalle',
        'marcado' => 'detalle',
        'pie' => 'pie',
    ];

    /** Los tonos del molde (`hero()`, `notice()`) en los del diseño. `neutro` es el de las devoluciones (`#503`). */
    private const TONOS = ['ok' => 'ok', 'warn' => 'aviso', 'err' => 'error', 'info' => 'info', 'neutro' => 'neutro'];

    /**
     * @param  list<array<string, mixed>>  $bloques
     */
    private function __construct(
        public readonly array $bloques,
        public readonly MailTheme $tema,
        public readonly MailPie $pie,
        public readonly string $asunto,
        public readonly ?string $adelanto,
        public readonly string $logoUrl,
        public readonly ?string $logoImagen,
        public readonly ?string $pixel,
        /** @var list<array{0: string, 1: string}> rótulo y URL ya etiquetada: la última línea del pie */
        public readonly array $legales,
    ) {}

    /**
     * El documento a partir de `MailMessage::data()` —lo que la vista recibe—.
     *
     * @param  array<string, mixed>  $data
     */
    public static function desde(array $data): self
    {
        $utm = is_string($data['utm'] ?? null) ? $data['utm'] : null;
        $marca = is_string($data['clickMark'] ?? null) ? $data['clickMark'] : null;
        $pixel = is_string($data['openMark'] ?? null) && $data['openMark'] !== '' ? $data['openMark'] : null;
        $logo = public_path('img/client-logo@4x.png');

        return new self(
            bloques: self::conAire(self::bloques($data)),
            tema: MailTheme::current(),
            pie: MailPie::current(),
            asunto: is_string($data['subject'] ?? null) && $data['subject'] !== '' ? $data['subject'] : Setting::businessName(),
            adelanto: is_string($data['preheader'] ?? null) && $data['preheader'] !== '' ? $data['preheader'] : null,
            logoUrl: EmailUtm::tag((string) config('app.url'), $utm, $marca),
            logoImagen: is_file($logo) ? asset('img/client-logo@4x.png').'?v='.(int) filemtime($logo) : null,
            pixel: $pixel !== null ? route('emails.open', ['send' => $pixel]) : null,
            legales: self::legales($utm, $marca),
        );
    }

    /**
     * Los cuatro enlaces a la web del pie de siempre —privacidad, condiciones, cookies y contacto—, que el brief no nombra
     * y que se QUEDAN, discretos, al final del pie (`[DECIDIDO owner]` 29-09). Con la UTM del correo y la marca del envío,
     * como el botón y el logotipo: cada uno es un enlace a esta casa. Los rótulos, los del pie de la web.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function legales(?string $utm, ?string $marca): array
    {
        $rotulos = (array) __('landing.footer.legal');
        $con = static fn (string $url): string => EmailUtm::tag($url, $utm, $marca);

        return [
            [(string) ($rotulos[1] ?? 'Privacidad'), $con(route('legal.privacidad'))],
            [(string) ($rotulos[2] ?? 'Condiciones'), $con(route('legal.condiciones'))],
            [(string) ($rotulos[3] ?? 'Cookies'), $con(route('legal.cookies'))],
            [(string) __('landing.footer.contact_link'), $con(route('contacto'))],
        ];
    }

    /**
     * Los bloques, en el orden en que el molde siempre pintó: cabecera (y su resguardo) · cuerpo · aviso · botón · cierre ·
     * firma · pie.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function bloques(array $data): array
    {
        $bloques = [];
        $hero = is_array($data['hero'] ?? null) ? $data['hero'] : null;

        $bloques[] = [
            'tipo' => 'cabecera',
            'titulo' => (string) ($hero['titulo'] ?? ($data['greeting'] ?? '')),
            'chapa' => isset($hero['chapa']) && $hero['chapa'] !== '' ? (string) $hero['chapa'] : null,
            'tono' => self::TONOS[$hero['tono'] ?? 'info'] ?? 'info',
        ];
        if ($hero !== null && is_array($hero['datos'] ?? null) && $hero['datos'] !== []) {
            $bloques[] = ['tipo' => 'resguardo', 'filas' => array_map('strval', $hero['datos'])];
        }

        array_push($bloques, ...self::lineas((array) ($data['introLines'] ?? []), 'texto'));

        if (is_array($data['notice'] ?? null)) {
            $bloques[] = [
                'tipo' => 'aviso',
                'titulo' => (string) ($data['notice']['titulo'] ?? ''),
                'texto' => (string) ($data['notice']['texto'] ?? ''),
                'tono' => self::TONOS[$data['notice']['tono'] ?? 'info'] ?? 'info',
            ];
        }

        // El CÓDIGO de un solo uso (la R1c): la acción de su correo, en el sitio del botón —ninguno lleva los dos—.
        if (is_array($data['code'] ?? null)) {
            $bloques[] = [
                'tipo' => 'codigo',
                'etiqueta' => (string) ($data['code']['etiqueta'] ?? ''),
                'codigo' => (string) ($data['code']['codigo'] ?? ''),
                'nota' => is_string($data['code']['nota'] ?? null) && $data['code']['nota'] !== '' ? $data['code']['nota'] : null,
            ];
        }

        if (is_string($data['actionText'] ?? null) && is_string($data['actionUrl'] ?? null)) {
            $bloques[] = ['tipo' => 'boton', 'texto' => $data['actionText'], 'url' => $data['actionUrl']];
        }

        array_push($bloques, ...self::lineas((array) ($data['outroLines'] ?? []), 'linea'));

        // La firma de fábrica («Un saludo, …») se va: el diseño no la tiene y el pie ya nombra al parque. La PROPIA de un
        // correo (hoy, solo `GuardianAuthorizationSigned`) sigue, como una línea.
        if (is_string($data['salutation'] ?? null) && trim($data['salutation']) !== '') {
            $bloques[] = ['tipo' => 'linea', 'lineas' => [$data['salutation']]];
        }

        $bloques[] = ['tipo' => 'pie'];

        return $bloques;
    }

    /**
     * Las líneas de texto, agrupadas: seguidas en un bloque de `$tipo`; un `Htmlable` (el libro, la ficha, un enlace de
     * baja) en su propio bloque de marcado, porque dentro de un párrafo una tabla no es HTML válido.
     *
     * @param  array<int, mixed>  $lineas
     * @return list<array<string, mixed>>
     */
    private static function lineas(array $lineas, string $tipo): array
    {
        $bloques = [];
        $seguidas = [];

        foreach ($lineas as $linea) {
            if ($linea instanceof Htmlable) {
                $html = trim($linea->toHtml());
                if ($html === '') {
                    continue;
                }
                if ($seguidas !== []) {
                    $bloques[] = ['tipo' => $tipo, 'lineas' => $seguidas];
                    $seguidas = [];
                }
                $bloques[] = ['tipo' => 'marcado', 'html' => $html];
            } elseif (trim((string) $linea) !== '') {
                $seguidas[] = (string) $linea;
            }
        }
        if ($seguidas !== []) {
            $bloques[] = ['tipo' => $tipo, 'lineas' => $seguidas];
        }

        return $bloques;
    }

    /**
     * El aire bajo cada bloque, por el SIGUIENTE (el `aire()` del diseño): 24 bajo la cabecera, 16 dentro de una zona, 28
     * entre zonas, y nada bajo el último. Quitar un bloque no deja hueco.
     *
     * @param  list<array<string, mixed>>  $bloques
     * @return list<array<string, mixed>>
     */
    public static function conAire(array $bloques): array
    {
        foreach ($bloques as $i => $b) {
            $siguiente = $bloques[$i + 1] ?? null;
            $bloques[$i]['aire'] = match (true) {
                $siguiente === null => 0,
                $b['tipo'] === 'cabecera' => 24,
                self::ZONAS[$b['tipo']] !== self::ZONAS[$siguiente['tipo']] => 28,
                default => 16,
            };
        }

        return $bloques;
    }

    /**
     * Una línea con su formato: escapada, y la negrita `**así**` en `<strong>`. Devuelve el HTML y el texto plano.
     *
     * @return array{h: string, t: string}
     */
    public function rico(string $linea): array
    {
        $fuerte = $this->tema->claro('fuerte');
        $h = (string) preg_replace(
            '/\*\*(.+?)\*\*/u',
            '<strong class="pjm-strong" style="font-weight:700;color:'.$fuerte.';">$1</strong>',
            e($linea),
        );

        return ['h' => $h, 't' => (string) preg_replace('/\*\*(.+?)\*\*/u', '$1', $linea)];
    }

    /**
     * El HTML que compone un correo por su cuenta, con el ROL de enlace en cada `<a>` que no traiga estilo: sin él, un
     * enlace de baja sale azul de navegador, y en oscuro, azul sobre tinta.
     */
    public function marcado(string $html): string
    {
        $t = $this->tema;
        $estilo = 'color:'.$t->claro('enlace').';font-weight:700;text-decoration:underline;';

        return (string) preg_replace('/<a\s(?![^>]*\bstyle=)/i', '<a class="pjm-link" style="'.$estilo.'" ', $html);
    }

    /** Los atributos de toda tabla de maquetación (el `TB` del diseño). */
    public const TABLA = 'role="presentation" cellpadding="0" cellspacing="0" border="0"';

    /** La tipografía en línea (el `ty()` del diseño): familia, cuerpo, interlínea, peso y color, con la regla de Outlook. */
    public function ty(string $familia, int|float $cuerpo, int $interlinea, int $peso, string $color, string $extra = ''): string
    {
        return 'font-family:'.$this->tema->fuente($familia).';font-size:'.$cuerpo.'px;line-height:'.$interlinea.'px;'
            .'font-weight:'.$peso.';color:'.$color.';mso-line-height-rule:exactly;'.$extra;
    }

    /**
     * Un icono de Lucide como imagen del color del rol `icono` (el `icono()` y el `enLinea()` del diseño; la R1b): decorativo,
     * con `alt` vacío porque el dato va en el texto. Sin máscara para ese nombre, NADA: la plantilla deja su hueco.
     */
    public function icono(string $nombre, int $px, bool $enLinea = false): string
    {
        $src = MailIcons::url($nombre, $this->tema->claro('icono'));
        if ($src === null) {
            return '';
        }
        $estilo = $enLinea
            ? "display:inline-block;width:{$px}px;height:{$px}px;border:0;outline:none;vertical-align:-3px;margin-right:7px;"
            : "display:block;width:{$px}px;height:{$px}px;border:0;outline:none;";

        return '<img src="'.e($src).'" width="'.$px.'" height="'.$px.'" alt="" style="'.$estilo.'">';
    }

    /** Un hueco de alto fijo (el `hueco()` del diseño). */
    public function hueco(int $alto): string
    {
        return '<table '.self::TABLA.' width="100%"><tr><td height="'.$alto.'" style="height:'.$alto.'px;font-size:0;line-height:0;">&nbsp;</td></tr></table>';
    }

    /**
     * Los dos colores de un tono en claro —fondo y letra—. `neutro` (las devoluciones, `#503`: «una devolución no es un
     * color») toma el sutil y el fuerte.
     *
     * @return array{0: string, 1: string}
     */
    public function tono(string $tono, bool $oscuro = false): array
    {
        $color = $oscuro ? $this->tema->oscuro(...) : $this->tema->claro(...);

        return $tono === 'neutro'
            ? [$color('sutil'), $color('fuerte')]
            : [$color($tono.'-fondo'), $color($tono.'-letra')];
    }

    /**
     * EL OSCURO, por CLASE (el `cssOscuro()` del diseño): `prefers-color-scheme` para Apple Mail e iOS, y los
     * `data-ogsc`/`data-ogsb` de Outlook.com. Gmail invierte a su manera y no se deja. `!important` porque el claro va en
     * línea en cada etiqueta y ganaría.
     *
     * ⚠️ La LETRA de los tonos tiene su clase (`pjm-tono-*-t`), y en el diseño no: su chapa pinta el texto del tono sin
     * clase, así que en el oscuro AUTOMÁTICO conservaba el color claro sobre el fondo oscuro del tono. Su visor no lo
     * enseña porque fuerza el oscuro pintando con la paleta oscura, no por la `@media`.
     */
    public function cssOscuro(): string
    {
        $o = $this->tema->oscuro(...);
        $reglas = [
            ['.pjm-bg', 'background-color', $o('fondo')],
            ['.pjm-strong', 'color', $o('fuerte')],
            ['.pjm-body', 'color', $o('cuerpo')],
            ['.pjm-muted', 'color', $o('apagado')],
            ['.pjm-link', 'color', $o('enlace')],
            ['.pjm-line', 'border-color', $o('filete')],
            ['.pjm-rule', 'background-color', $o('filete')],
            ['.pjm-subtle', 'background-color', $o('sutil')],
            ['.pjm-subtle', 'border-color', $o('filete')],
            ['.pjm-btn', 'background-color', $o('accion')],
            ['.pjm-btn-t', 'color', $o('accion-letra')],
        ];
        foreach (['ok', 'error', 'aviso', 'info', 'neutro'] as $tono) {
            [$fondo, $letra] = $this->tono($tono, oscuro: true);
            $reglas[] = [".pjm-tono-{$tono}", 'background-color', $fondo];
            $reglas[] = [".pjm-tono-{$tono}-t", 'color', $letra];
            $reglas[] = [".pjm-tono-{$tono}-p", 'background-color', $letra];
        }

        $d = static fn (string $sel, string $prop, string $valor): string => "{$sel}{{$prop}:{$valor}!important}";
        $media = implode('', array_map(static fn (array $r): string => $d(...$r), $reglas));
        $outlook = implode('', array_map(
            static fn (array $r): string => $d('['.($r[1] === 'color' ? 'data-ogsc' : 'data-ogsb').'] '.$r[0], $r[1], $r[2]),
            array_filter($reglas, static fn (array $r): bool => $r[1] !== 'border-color'),
        ));

        return "@media (prefers-color-scheme:dark){{$media}}\n{$outlook}";
    }

    /** La versión de texto del documento: cada bloque por su gemela, separados por una línea en blanco. */
    public function texto(): string
    {
        $partes = [];
        foreach ($this->bloques as $b) {
            $t = trim(view('correo.texto.'.$b['tipo'], ['b' => $b, 'correo' => $this])->render());
            if ($t !== '') {
                $partes[] = $t;
            }
        }

        return implode("\n\n", $partes)."\n";
    }

    /**
     * Un HTML en texto plano legible: una fila por línea, las celdas separadas, los enlaces con su dirección (salvo
     * `tel:` y `mailto:`, que ya se leen), y las entidades decodificadas. Hoy se hacía `strip_tags` sobre el cuerpo
     * entero y el libro salía con las celdas pegadas.
     */
    public static function textoDe(string $html): string
    {
        $s = (string) preg_replace_callback('#<a\s[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is', static function (array $m): string {
            $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $texto = trim(strip_tags($m[2]));

            return preg_match('#^https?://#i', $href) === 1 ? "{$texto} ({$href})" : $texto;
        }, $html);
        $s = (string) preg_replace('#<br\s*/?>#i', "\n", $s);
        $s = (string) preg_replace('#</(tr|p|div|h[1-6]|li|table)>#i', "\n", $s);
        $s = (string) preg_replace('#</t[dh]>#i', '  ', $s);
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lineas = array_map(static fn (string $l): string => trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $l)), explode("\n", $s));

        return implode("\n", array_values(array_filter($lineas, static fn (string $l): bool => $l !== '')));
    }
}

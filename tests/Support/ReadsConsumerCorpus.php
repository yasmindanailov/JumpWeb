<?php

namespace Tests\Support;

/**
 * **DÓNDE PUEDE ESTAR VIVA UNA CLASE DE CSS.**
 *
 * Lo necesitan las dos guardas de huérfanos —la del armazón y la del material de fachada— y hasta
 * ahora se lo escribía cada una. Es la misma respuesta que `#233` dio al selector de idioma:
 * *cuando algo está en dos sitios, la salida no es retirarlo de uno, es que haya UNA definición.*
 *
 * ❗ **La corrección que motivó separar esto en su sitio: el corpus NO cuenta los comentarios.**
 * `.nav__scan` no existía en ningún sitio salvo dentro de un `{{-- … --}}` de Blade que explicaba
 * que se había sustituido; con el corpus en crudo salía viva y sus ocho reglas se habrían migrado.
 * *Un `grep` que SÍ encuentra tampoco demuestra que la cosa exista donde crees.*
 *
 * ⚠️ **Las hojas del propio `public/css` quedan fuera del corpus**: son el sujeto de la medición,
 * no un consumidor. Sin esa línea toda clase declarada se demuestra viva a sí misma.
 */
trait ReadsConsumerCorpus
{
    /** Dónde puede estar VIVA una clase: marcado, JS fuente, SSR y bundle compilado. */
    private const CORPUS_DIRS = ['resources', 'storage/ssr', 'public/build', 'app'];

    private const CORPUS_EXTENSIONS = ['php', 'js', 'vue', 'ts', 'json', 'html', 'css'];

    private ?string $consumerCorpusCache = null;

    /** Todo el código donde una clase puede estar VIVA, sin comentarios. */
    protected function consumerCorpus(): string
    {
        if ($this->consumerCorpusCache !== null) {
            return $this->consumerCorpusCache;
        }

        $chunks = [];

        foreach (self::CORPUS_DIRS as $dir) {
            $base = base_path($dir);

            if (! is_dir($base)) {
                continue;
            }

            /** @var \SplFileInfo $file */
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $extension = strtolower($file->getExtension());

                if (! in_array($extension, self::CORPUS_EXTENSIONS, true)) {
                    continue;
                }

                // Las hojas del sitio no valen como consumidor: son lo que se está midiendo.
                if (str_starts_with($file->getPathname(), base_path('public/css'))) {
                    continue;
                }

                $chunks[] = $this->stripComments((string) file_get_contents($file->getPathname()), $extension);
            }
        }

        return $this->consumerCorpusCache = implode("\n", $chunks);
    }

    /**
     * Quita los comentarios de Blade, de bloque, de línea y de HTML.
     *
     * ❗ Sin esto, una clase citada en un comentario que explica que YA NO SE USA cuenta como viva.
     */
    protected function stripComments(string $source, string $extension): string
    {
        $source = (string) preg_replace('/\{\{--.*?--\}\}/s', ' ', $source);
        $source = (string) preg_replace('/<!--.*?-->/s', ' ', $source);

        if (in_array($extension, ['php', 'js', 'vue', 'ts', 'css'], true)) {
            $source = (string) preg_replace('#/\*.*?\*/#s', ' ', $source);
            $source = (string) preg_replace('#^\s*(//|\#)\s.*$#m', ' ', $source);
        }

        return $source;
    }
}

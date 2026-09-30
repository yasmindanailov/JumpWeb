<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\MailText;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * EL ALMACÉN DE LOS TEXTOS DE CORREO DEL PARQUE (R1·T de `specs/correos-rediseno.md` §4.2.1, `#802`): la única puerta para
 * escribirlos (con sus reglas y su rastro), la lectura que usa el cargador de textos (con caché) y el BORRADOR de la vista
 * previa, que se aplica a un solo pintado y no toca la base.
 *
 * ⚠️ La caché va por VERSIÓN: guardar o restaurar sube la versión y todo proceso lee la nueva en su siguiente carga. Y el
 * traductor guarda en memoria cada grupo que ya cargó: tras escribir, `olvidarCargados()` le obliga a releer en ESTA misma
 * petición (la pantalla enseña lo recién guardado).
 */
final class MailTexts
{
    private const VERSION = 'mail_texts.version';

    /** @var array<string, array<string, string>> idioma → clave → texto: el borrador de UNA vista previa, en memoria. */
    private static array $borrador = [];

    /**
     * Los textos del parque en un idioma, `clave → texto` (con sus `{variables}`), más el borrador si hay una vista previa en
     * curso. Sin tabla (una migración a medias, una orden de consola antes de migrar), nada: el correo sale de fábrica.
     *
     * @return array<string, string>
     */
    public function enIdioma(string $locale): array
    {
        $deLaBase = static fn (): array => MailText::query()->where('locale', $locale)->pluck('text', 'key')->all();
        try {
            $version = (int) Cache::get(self::VERSION, 0);
            /** @var array<string, string> $guardados */
            $guardados = Cache::rememberForever('mail_texts.'.$version.'.'.$locale, $deLaBase);
        } catch (Throwable) {
            // Sin caché, de la base: un fallo de la caché no puede devolver el correo a fábrica en silencio.
            try {
                /** @var array<string, string> $guardados */
                $guardados = $deLaBase();
            } catch (Throwable) {
                $guardados = [];
            }
        }

        return array_merge($guardados, self::$borrador[$locale] ?? []);
    }

    /**
     * Guarda el texto del parque de una clave en un idioma, si pasa las reglas contra su texto de FÁBRICA. Devuelve el
     * problema (y no guarda) o `null` (guardado, con rastro si cambió). **Igual que el de fábrica = vuelve al de fábrica**
     * (se borra la fila): una fila con el mismo texto CONGELARÍA el correo y el siguiente arreglo del producto no llegaría.
     *
     * @return array{motivo: string, tope?: int, variables?: list<string>}|null
     */
    public function guardar(string $clave, string $locale, string $texto, string $fabrica, ?User $por): ?array
    {
        $texto = trim(str_replace("\r\n", "\n", $texto));
        $problema = MailTextRules::problema($texto, $fabrica, $clave);
        if ($problema !== null) {
            return $problema;
        }
        if ($texto === trim(MailTextRules::aParque($fabrica))) {
            $this->restaurar($clave, $locale, $por);

            return null;
        }

        $fila = MailText::query()->where('key', $clave)->where('locale', $locale)->first();
        $antes = $fila?->text;
        if ($antes === $texto) {
            return null;
        }

        DB::transaction(static function () use ($fila, $clave, $locale, $texto, $antes, $por): void {
            $fila ??= new MailText(['key' => $clave, 'locale' => $locale]);
            $fila->fill(['text' => $texto, 'updated_by' => $por?->getKey()])->save();
            AuditLogger::log('emails.text_updated', $fila, [
                'key' => $clave, 'locale' => $locale, 'from' => $antes, 'to' => $texto,
            ]);
        });
        $this->invalidar();

        return null;
    }

    /** Vuelve al texto de fábrica (borra la fila). `false` si ya estaba de fábrica. */
    public function restaurar(string $clave, string $locale, ?User $por): bool
    {
        $fila = MailText::query()->where('key', $clave)->where('locale', $locale)->first();
        if ($fila === null) {
            return false;
        }

        DB::transaction(static function () use ($fila, $clave, $locale): void {
            AuditLogger::log('emails.text_restored', $fila, ['key' => $clave, 'locale' => $locale, 'from' => $fila->text]);
            $fila->delete();
        });
        $this->invalidar();

        return true;
    }

    /**
     * Pinta algo con un BORRADOR aplicado solo durante `$pintar` (la vista previa del panel): el cargador lo superpone como
     * si estuviera guardado, y al terminar —también si revienta— desaparece y el traductor olvida lo que cargó con él.
     *
     * @template T
     *
     * @param  array<string, string>  $borrador  clave → texto del parque
     * @param  Closure(): T  $pintar
     * @return T
     */
    public static function conBorrador(string $locale, array $borrador, Closure $pintar): mixed
    {
        self::$borrador[$locale] = $borrador;
        self::olvidarCargados();
        try {
            return $pintar();
        } finally {
            unset(self::$borrador[$locale]);
            self::olvidarCargados();
        }
    }

    /** Que el traductor relea sus grupos (los tenía en memoria desde la primera carga de esta petición). */
    public static function olvidarCargados(): void
    {
        app('translator')->setLoaded([]);
    }

    private function invalidar(): void
    {
        try {
            Cache::forever(self::VERSION, (int) Cache::get(self::VERSION, 0) + 1);
        } catch (Throwable) {
            // Sin caché, `enIdioma()` lee la base en cada carga: sigue siendo correcto.
        }
        self::olvidarCargados();
    }
}

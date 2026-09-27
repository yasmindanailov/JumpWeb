<?php

namespace App\Domain\Platform\Services\Surveys;

use App\Domain\Platform\Contracts\VisitFacts;
use App\Domain\Platform\Models\SurveyResponse;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * **EL SELLO de una respuesta anónima** (`docs/specs/encuestas.md` §4.7, T5; `[DECIDIDO owner]` `DECISIONES #754`):
 * el cliente CIFRADO (`Crypt::encryptString`, IV aleatorio: dos sellos del mismo cliente no se parecen), que solo
 * existe para contestar «¿volvió quien puntuó mal?» y se borra al saberlo o a los {@see DAYS} días.
 *
 * ⚠️⚠️ **Es la ÚNICA clase que lee o escribe la columna `seal`** (`SurveyAnonymityTest` lo vigila): el panel, el
 * cuadro y el CSV no la ven nunca. Sus lectores, y ninguno más:
 *  · {@see resolve()}: la pasada diaria (`surveys:resolve-returns`), que anota «volvió» y borra el sello;
 *  · {@see forget()}: `User::anonymize()`, que borra el de esa persona (art. 17) dentro de su transacción;
 *  · {@see sealedFor()}: el export de esa persona (art. 15): mientras hay sello, la respuesta es un dato suyo;
 *  · {@see answeredInPersonOn()}: el correo del día siguiente, para no repetírselo a quien ya contestó en la puerta
 *    (`#742`) sin guardar el desenlace en su participación.
 *
 * Se descifra en PHP recorriendo los sellos vivos, que son pocos (los de los últimos 90 días): no hay índice por
 * persona, y no debe haberlo — un índice por persona sería la unión que el diseño quita.
 */
final class SurveySeals
{
    /** Días que vive un sello: el plazo de «volvió» (`[PENDIENTE: asesoría]` el plazo y su texto en `/privacidad`). */
    public const DAYS = 90;

    private const CHUNK = 200;

    /** Sella una respuesta que aún no se ha guardado: la ÚNICA escritura de un sello. */
    public function stamp(SurveyResponse $response, int $userId): void
    {
        $response->forceFill(['seal' => Crypt::encryptString((string) $userId)]);
    }

    /**
     * **La pasada diaria.** Un sello se resuelve con la PRIMERA visita en `(día, día + 90]` —una visita acreditada o una
     * reserva cobrada, `VisitFacts`— contando solo días YA VIVIDOS (hasta ayer: la reserva de hoy aún puede no venir), y
     * caduca cuando el tramo entero ha pasado. En los dos casos el sello se borra en la MISMA escritura.
     *
     * ⚠️ Solo cuenta hasta AYER también por otra razón: el correo del día siguiente lee los sellos de ayer
     * ({@see answeredInPersonOn()}), y así ninguno de ellos puede resolverse antes de que salga.
     *
     * @return array{returned: int, expired: int, pending: int}
     */
    public function resolve(VisitFacts $visits, string $today): array
    {
        $lived = Carbon::parse($today)->subDay()->toDateString();
        $out = ['returned' => 0, 'expired' => 0, 'pending' => 0];

        DB::table('survey_responses')->whereNotNull('seal')->select(['id', 'answered_on', 'seal'])
            ->chunkById(self::CHUNK, function (Collection $rows) use ($visits, $lived, &$out): void {
                foreach ($rows as $row) {
                    $day = substr((string) $row->answered_on, 0, 10);
                    $end = Carbon::parse($day)->addDays(self::DAYS)->toDateString();
                    $until = min($lived, $end);
                    $userId = $until > $day ? $this->open((string) $row->seal) : null;
                    $first = $userId === null ? null : $visits->firstReturn($userId, $day, $until);

                    if ($first !== null) {
                        DB::table('survey_responses')->where('id', $row->id)->update([
                            'returned' => true,
                            'returned_after_days' => (int) Carbon::parse($day)->diffInDays(Carbon::parse($first)),
                            'seal' => null,
                        ]);
                        $out['returned']++;
                    } elseif ($lived >= $end) {
                        DB::table('survey_responses')->where('id', $row->id)->update(['returned' => false, 'seal' => null]);
                        $out['expired']++;
                    } else {
                        $out['pending']++;
                    }
                }
            });

        return $out;
    }

    /** **Art. 17**: borra los sellos de ESA persona y ninguno más. Devuelve cuántos. */
    public function forget(int $userId): int
    {
        $ids = $this->idsOf($userId);
        if ($ids !== []) {
            DB::table('survey_responses')->whereIn('id', $ids)->update(['seal' => null]);
        }

        return count($ids);
    }

    /**
     * **Art. 15**: las respuestas aún selladas de esa persona, para su export — sin quién preguntó, que es un dato del
     * empleado y no suyo.
     *
     * @return list<array{survey: ?string, channel: string, answered_on: string, answers: ?array<string, mixed>}>
     */
    public function sealedFor(int $userId): array
    {
        $ids = $this->idsOf($userId);
        if ($ids === []) {
            return [];
        }

        return SurveyResponse::query()->whereIn('id', $ids)->with('survey')->orderBy('answered_on')->get()
            ->map(static fn (SurveyResponse $response): array => [
                'survey' => $response->survey?->key,
                'channel' => $response->channel,
                'answered_on' => $response->answered_on->toDateString(),
                'answers' => $response->answers,
            ])->values()->all();
    }

    /**
     * Quién CONTESTÓ una encuesta en la puerta ese día (`#742`: a esos no se les repite por correo). Los que dijeron
     * «no preguntar» no tienen sello, así que no salen: a ellos sí les llega el correo.
     *
     * @return list<int>
     */
    public function answeredInPersonOn(string $day): array
    {
        $holders = [];
        DB::table('survey_responses')->whereNotNull('seal')
            ->where('channel', SurveyResponse::CHANNEL_INTERNAL)
            ->where('declined', false)
            ->where('answered_on', $day)
            ->select(['id', 'seal'])
            ->chunkById(self::CHUNK, function (Collection $rows) use (&$holders): void {
                foreach ($rows as $row) {
                    $userId = $this->open((string) $row->seal);
                    if ($userId !== null) {
                        $holders[$userId] = true;
                    }
                }
            });

        return array_keys($holders);
    }

    /** @return list<string> las claves de las respuestas selladas de esa persona */
    private function idsOf(int $userId): array
    {
        $ids = [];
        DB::table('survey_responses')->whereNotNull('seal')->select(['id', 'seal'])
            ->chunkById(self::CHUNK, function (Collection $rows) use ($userId, &$ids): void {
                foreach ($rows as $row) {
                    if ($this->open((string) $row->seal) === $userId) {
                        $ids[] = (string) $row->id;
                    }
                }
            });

        return $ids;
    }

    /**
     * El cliente de un sello, o `null` si no se puede leer (una clave de la aplicación rotada sin `APP_PREVIOUS_KEYS`):
     * ilegible para esta aplicación es ilegible para cualquiera con ella, y la pasada diaria lo borra al caducar.
     */
    private function open(string $seal): ?int
    {
        try {
            $plain = Crypt::decryptString($seal);
        } catch (DecryptException) {
            Log::warning('surveys.seal_unreadable');

            return null;
        }

        return ctype_digit($plain) ? (int) $plain : null;
    }
}

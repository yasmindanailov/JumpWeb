<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\PaidVisits;
use App\Domain\Identity\Models\CustomerVisit;
use App\Domain\Platform\Contracts\VisitFacts;
use App\Domain\Platform\Models\SurveyResponse;

/**
 * La implementación de {@see VisitFacts} (`docs/specs/encuestas.md` §4.7, T5): las visitas acreditadas son de aquí
 * (`customer_visits`) y los días cobrados se preguntan a Booking por su contrato ({@see PaidVisits}). Es el único
 * sitio donde las dos mitades de «¿vino?» se juntan. Solo lee.
 */
final class CustomerVisitFacts implements VisitFacts
{
    public function __construct(private readonly PaidVisits $paid) {}

    public function isFirstVisit(int $userId, string $day): bool
    {
        return ! CustomerVisit::query()->where('user_id', $userId)->where('visited_on', '<', $day)->exists()
            && ! $this->paid->anyBefore($userId, $day);
    }

    public function kindOn(int $userId, string $day): string
    {
        return match ($this->paid->kindOn($userId, $day)) {
            PaidVisits::KIND_PARTY => SurveyResponse::KIND_PARTY,
            PaidVisits::KIND_GROUP => SurveyResponse::KIND_GROUP,
            PaidVisits::KIND_ENTRY => SurveyResponse::KIND_ENTRY,
            default => SurveyResponse::KIND_OTHER,
        };
    }

    public function firstReturn(int $userId, string $after, string $until): ?string
    {
        $visit = CustomerVisit::query()->where('user_id', $userId)
            ->where('visited_on', '>', $after)
            ->where('visited_on', '<=', $until)
            ->min('visited_on');
        $days = array_filter([
            $visit === null ? null : substr((string) $visit, 0, 10),
            $this->paid->firstBetween($userId, $after, $until),
        ]);

        return $days === [] ? null : min($days);
    }

    public function firstVisitDays(array $userIds): array
    {
        $ids = array_values(array_unique($userIds));
        $first = [];
        // En tandas: una lista de clientes de un trimestre no cabe entera en un `IN` de SQLite.
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = CustomerVisit::query()->whereIn('user_id', $chunk)->groupBy('user_id')
                ->selectRaw('user_id, MIN(visited_on) AS first_day')->get();
            foreach ($rows as $row) {
                $first[(int) $row->getAttribute('user_id')] = substr((string) $row->getAttribute('first_day'), 0, 10);
            }
        }
        foreach ($this->paid->firstPaidDays($ids) as $userId => $day) {
            if (! isset($first[$userId]) || $day < $first[$userId]) {
                $first[$userId] = $day;
            }
        }

        return $first;
    }
}

<?php

namespace App\Services;

class CompetitorKeywordComparison
{
    /**
     * Compare each site's top on-page terms against every term found on the other site.
     *
     * @param  array{keywords: list<array{term: string, occurrences: int}>, term_occurrences: array<string, int>}  $competitorAudit
     * @param  array{keywords: list<array{term: string, occurrences: int}>, term_occurrences: array<string, int>}  $ownAudit
     * @return array{
     *     missing: list<array{term: string, competitor_occurrences: int}>,
     *     shared: list<array{term: string, competitor_occurrences: int, own_occurrences: int}>,
     *     own_only: list<array{term: string, own_occurrences: int}>
     * }
     */
    public function compare(array $competitorAudit, array $ownAudit): array
    {
        $missing = [];
        $shared = [];
        $ownOnly = [];

        foreach ($competitorAudit['keywords'] as $keyword) {
            $ownOccurrences = $ownAudit['term_occurrences'][$keyword['term']] ?? 0;

            if ($ownOccurrences === 0) {
                $missing[] = [
                    'term' => $keyword['term'],
                    'competitor_occurrences' => $keyword['occurrences'],
                ];

                continue;
            }

            $shared[] = [
                'term' => $keyword['term'],
                'competitor_occurrences' => $keyword['occurrences'],
                'own_occurrences' => $ownOccurrences,
            ];
        }

        foreach ($ownAudit['keywords'] as $keyword) {
            if (($competitorAudit['term_occurrences'][$keyword['term']] ?? 0) === 0) {
                $ownOnly[] = [
                    'term' => $keyword['term'],
                    'own_occurrences' => $keyword['occurrences'],
                ];
            }
        }

        return [
            'missing' => $missing,
            'shared' => $shared,
            'own_only' => $ownOnly,
        ];
    }
}

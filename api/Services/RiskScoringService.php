<?php

class RiskScoringService {

    public function calculate($breaches) {

        if (!is_array($breaches)) {
            return 0;
        }

        $score = count($breaches) * 10;

        foreach ($breaches as $breach) {
            // Handle both PascalCase (raw HIBP) and snake_case (normalized)
            $dataClasses = $breach['DataClasses'] ?? $breach['data_classes'] ?? [];
            if (in_array("Passwords", $dataClasses)) {
                $score += 20;
            }

            if (!empty($breach['IsSensitive']) || !empty($breach['is_sensitive'])) {
                $score += 30;
            }

            if (!empty($breach['IsStealerLog']) || !empty($breach['is_stealer_log'])) {
                $score += 25;
            }
        }

        return min($score, 100);
    }
}

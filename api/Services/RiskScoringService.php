<?php

class RiskScoringService {

    public function calculate($breaches) {

        if (!is_array($breaches)) {
            return 0;
        }

        $score = count($breaches) * 10;

        foreach ($breaches as $breach) {

            if (isset($breach['DataClasses']) && 
                in_array("Passwords", $breach['DataClasses'])) {
                $score += 20;
            }

            if (!empty($breach['IsSensitive'])) {
                $score += 30;
            }

            if (!empty($breach['IsStealerLog'])) {
                $score += 25;
            }
        }

        return min($score, 100);
    }
}
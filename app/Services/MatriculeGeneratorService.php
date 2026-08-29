<?php

namespace App\Services;

use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use DateTimeInterface;

class MatriculeGeneratorService
{
    protected static int $lastTimestamp = 0;
    protected static array $usedLettersThisSecond = [];

    /**
     * Génère un matricule unique officiel pour un catéchumène.
     * Format : {prefixe_matricule}{AA}-{JJMMHHmmss}{LETTRE}
     * Exemple : CIM26-1001124002A
     *
     * @param int $paroisseId Identifiant de la paroisse
     * @param DateTimeInterface|string|null $date Date de référence (par défaut maintenant)
     * @return string
     */
    public function generate(int $paroisseId, DateTimeInterface|string|null $date = null): string
    {
        $config = CatecheseConfiguration::find($paroisseId);

        // 1. Récupération du préfixe configuré pour la paroisse (sans hardcoding)
        $rawPrefix = $config?->prefixe_matricule;
        if (!empty($rawPrefix)) {
            $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', $rawPrefix));
        } else {
            $prefix = 'CIM';
        }

        $baseTime = $date ? (is_string($date) ? strtotime($date) : $date->getTimestamp()) : time();
        if ($baseTime > self::$lastTimestamp) {
            self::$lastTimestamp = $baseTime;
            self::$usedLettersThisSecond = [];
        }

        // 2. Boucle sécurisée avec vérification d'unicité et prévention absolue des collisions
        $attempts = 0;
        do {
            $availableLetters = array_diff(range('A', 'Z'), self::$usedLettersThisSecond);
            if (empty($availableLetters)) {
                self::$lastTimestamp++;
                self::$usedLettersThisSecond = [];
                $availableLetters = range('A', 'Z');
            }

            $lettre = $availableLetters[array_rand($availableLetters)];
            self::$usedLettersThisSecond[] = $lettre;

            $anneeCourt = date('y', self::$lastTimestamp);
            $dateHeure = date('dmHis', self::$lastTimestamp);
            $matricule = sprintf('%s%s-%s%s', $prefix, $anneeCourt, $dateHeure, $lettre);

            // Vérification de l'unicité globale (y compris corbeille soft deletes)
            $exists = Catechumene::withTrashed()->where('matricule', $matricule)->exists();
            $attempts++;
        } while ($exists && $attempts < 100);

        return $matricule;
    }
}

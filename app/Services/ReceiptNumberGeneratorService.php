<?php

namespace App\Services;

use App\Models\CatecheseConfiguration;
use App\Models\Paiement;
use DateTimeInterface;

class ReceiptNumberGeneratorService
{
    /**
     * Génère un numéro de reçu officiel unique et séquentiel par paroisse et par année.
     * Format : {prefixe_recu}{AA}-{HHmmss}-{SEQUENCE}
     * Exemple : REC26-124002-0001
     *
     * @param int $paroisseId Identifiant de la paroisse
     * @param DateTimeInterface|string|null $date Date du paiement
     * @return string
     */
    public function generate(int $paroisseId, DateTimeInterface|string|null $date = null): string
    {
        $config = CatecheseConfiguration::find($paroisseId);

        // 1. Récupération du préfixe configuré pour la paroisse (sans hardcoding)
        $rawPrefix = $config?->prefixe_recu;
        if (!empty($rawPrefix)) {
            $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', $rawPrefix));
        } else {
            $prefix = 'REC';
        }

        // 2. Année sur 2 chiffres et Heure HHmmss (ex: 124002)
        $timestamp = $date ? (is_string($date) ? strtotime($date) : $date->getTimestamp()) : time();
        $anneeCourt = date('y', $timestamp);
        $anneeComplete = date('Y', $timestamp);

        // Si la date passée n'inclut pas d'heure précise (ex: '2026-09-01'), on utilise l'heure/minute/seconde courante
        $hasSpecificTime = $date instanceof DateTimeInterface || ($date && is_string($date) && str_contains($date, ':'));
        $heureStr = $hasSpecificTime ? date('His', $timestamp) : date('His');

        // 3. Calcul atomique et transactionnel du numéro séquentiel (réinitialisé chaque année par paroisse)
        $nextSequence = $this->resolveNextSequence($paroisseId, $anneeComplete, $anneeCourt);

        $numeroRecu = sprintf('%s%s-%s-%04d', $prefix, $anneeCourt, $heureStr, $nextSequence);

        // 4. Double sécurité contre toute collision concurrente
        while (Paiement::withTrashed()->where('numero_recu', $numeroRecu)->exists()) {
            $nextSequence++;
            $numeroRecu = sprintf('%s%s-%s-%04d', $prefix, $anneeCourt, $heureStr, $nextSequence);
        }

        return $numeroRecu;
    }

    /**
     * Calcule la prochaine séquence pour la paroisse et l'année données.
     */
    protected function resolveNextSequence(int $paroisseId, string $yearFull, string $yearShort): int
    {
        // Récupérer les numéros de reçus de l'année pour la paroisse (y compris corbeille)
        $recus = Paiement::withTrashed()
            ->where('paroisse_configuration_id', $paroisseId)
            ->where(function ($q) use ($yearFull, $yearShort) {
                $q->whereYear('date_paiement', $yearFull)
                  ->orWhere('numero_recu', 'like', "%{$yearShort}-%")
                  ->orWhere('numero_recu', 'like', "%-{$yearFull}-%");
            })
            ->lockForUpdate()
            ->pluck('numero_recu');

        $maxSeq = 0;
        foreach ($recus as $r) {
            if (preg_match('/-(\d{4,})$/', $r, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxSeq) {
                    $maxSeq = $num;
                }
            }
        }

        return $maxSeq + 1;
    }
}

<?php

namespace App\Services;

use App\Models\Catechumene;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\RegleProgressionPastorale;
use App\Models\Section;
use Illuminate\Support\Facades\Log;

class ProgressionPastoraleService
{
    /**
     * Standard codes for sections in CATHEO
     */
    public const CODE_ENFANTS_PRI = 'SEC-ENFANTS-PRI';
    public const CODE_ENFANTS_COL = 'SEC-ENFANTS-COL';
    public const CODE_JEUNES = 'SEC-JEUNES';
    public const CODE_ADULTES = 'SEC-ADULTES';

    /**
     * Map variations of section codes to canonical codes
     */
    public static function normaliserCodeSection(?string $code): ?string
    {
        if (!$code) {
            return null;
        }

        $codeClean = strtoupper(trim($code));

        $map = [
            'SEC-ENF-PRI' => self::CODE_ENFANTS_PRI,
            'SEC-ENFANTS-PRI' => self::CODE_ENFANTS_PRI,
            'SEC-ENFANTS-PRIMAIRE' => self::CODE_ENFANTS_PRI,
            'SEC-ENF-COL' => self::CODE_ENFANTS_COL,
            'SEC-ENFANTS-COL' => self::CODE_ENFANTS_COL,
            'SEC-ENFANTS-COLLEGE' => self::CODE_ENFANTS_COL,
            'SEC-JEUNE' => self::CODE_JEUNES,
            'SEC-JEUNES' => self::CODE_JEUNES,
            'SEC-ADULTE' => self::CODE_ADULTES,
            'SEC-ADULTES' => self::CODE_ADULTES,
        ];

        return $map[$codeClean] ?? $codeClean;
    }

    /**
     * Normaliser la décision du bilan annuel.
     * Seuls 'ADMIS' et 'ADMISE' (insensible à la casse) sont considérés comme ADMIS.
     */
    public static function normaliserDecision(?string $decision): ?string
    {
        if (!$decision) {
            return null;
        }

        $decisionClean = mb_strtoupper(trim($decision), 'UTF-8');

        if ($decisionClean === 'ADMIS' || $decisionClean === 'ADMISE') {
            return 'ADMIS';
        }

        return $decisionClean;
    }

    /**
     * Vérifie si une décision correspond à une admission.
     */
    public static function estAdmis(?string $decision): bool
    {
        return self::normaliserDecision($decision) === 'ADMIS';
    }

    /**
     * Calculer la progression pastorale pour un catéchumène donné et une paroisse cible.
     */
    public function calculerProgression(Catechumene $catechumene, ?int $paroisseId = null): array
    {
        $paroisseId = $paroisseId ?? $catechumene->paroisse_configuration_id;

        // 1. Récupérer la dernière inscription annuelle pertinente
        $derniereInscription = InscriptionAnnuelle::with(['section', 'niveau', 'anneeCatechese', 'decisionFinAnnee'])
            ->where('catechumene_id', $catechumene->id)
            ->where('paroisse_configuration_id', $paroisseId)
            ->orderByDesc('id')
            ->first();

        if (!$derniereInscription || !$derniereInscription->section || !$derniereInscription->niveau) {
            return [
                'eligible' => false,
                'est_fin_parcours' => false,
                'est_admis' => false,
                'decision_bilan' => null,
                'decision_normalisee' => null,
                'parcours_actuel' => null,
                'parcours_suivant' => null,
                'message' => 'Aucune inscription précédente valide trouvée pour ce catéchumène.',
            ];
        }

        $sectionActuelle = $derniereInscription->section;
        $niveauActuel = $derniereInscription->niveau;
        $decisionModel = $derniereInscription->decisionFinAnnee;
        $decisionTexte = $decisionModel?->decision;

        $codeSectionActuelleNorm = self::normaliserCodeSection($sectionActuelle->code);
        $nomNiveauActuel = trim($niveauActuel->nom);

        $estAdmis = self::estAdmis($decisionTexte);

        $parcoursActuelInfo = [
            'section_id' => $sectionActuelle->id,
            'section_uuid' => $sectionActuelle->uuid,
            'section_nom' => $sectionActuelle->nom,
            'section_code' => $sectionActuelle->code,
            'section_code_normalise' => $codeSectionActuelleNorm,
            'niveau_id' => $niveauActuel->id,
            'niveau_uuid' => $niveauActuel->uuid,
            'niveau_nom' => $niveauActuel->nom,
            'annee_pastorale' => $derniereInscription->anneeCatechese?->libelle ?? $derniereInscription->anneePastorale?->libelle,
        ];

        // CAS 1 : DÉCISION = ADMIS / ADMISE
        if ($estAdmis) {
            // Rechercher une règle de progression
            $regle = $this->trouverRegleProgression(
                $codeSectionActuelleNorm,
                $nomNiveauActuel,
                'ADMIS',
                $paroisseId
            );

            if ($regle) {
                if ($regle->est_fin_parcours) {
                    return [
                        'eligible' => true,
                        'est_fin_parcours' => true,
                        'est_admis' => true,
                        'decision_bilan' => $decisionTexte,
                        'decision_normalisee' => 'ADMIS',
                        'parcours_actuel' => $parcoursActuelInfo,
                        'parcours_suivant' => null,
                        'message' => 'Félicitations, vous avez achevé le parcours catéchétique de cette section. Veuillez contacter le secrétariat paroissial.',
                    ];
                }

                // Trouver la section et le niveau de destination dans la paroisse
                $codeDestNorm = self::normaliserCodeSection($regle->code_section_destination);
                $nomNiveauDest = trim($regle->niveau_destination);

                $sectionDest = $this->trouverSectionParCode($codeDestNorm, $paroisseId);
                $niveauDest = null;

                if ($sectionDest) {
                    $niveauDest = Niveau::where('paroisse_configuration_id', $paroisseId)
                        ->where('section_id', $sectionDest->id)
                        ->where('nom', $nomNiveauDest)
                        ->first();

                    if (!$niveauDest) {
                        $niveauDest = Niveau::where('paroisse_configuration_id', $paroisseId)
                            ->where('section_id', $sectionDest->id)
                            ->whereRaw('LOWER(TRIM(nom)) = ?', [mb_strtolower($nomNiveauDest, 'UTF-8')])
                            ->first();
                    }
                }

                if ($sectionDest && $niveauDest) {
                    return [
                        'eligible' => true,
                        'est_fin_parcours' => false,
                        'est_admis' => true,
                        'decision_bilan' => $decisionTexte,
                        'decision_normalisee' => 'ADMIS',
                        'parcours_actuel' => $parcoursActuelInfo,
                        'parcours_suivant' => [
                            'section_id' => $sectionDest->id,
                            'section_uuid' => $sectionDest->uuid,
                            'section_nom' => $sectionDest->nom,
                            'section_code' => $sectionDest->code,
                            'niveau_id' => $niveauDest->id,
                            'niveau_uuid' => $niveauDest->uuid,
                            'niveau_nom' => $niveauDest->nom,
                        ],
                        'message' => 'Progression automatique appliquée avec succès.',
                    ];
                }
            }

            // Si aucune règle trouvée mais au niveau maximum connu d'une section -> Fin de parcours
            if ($this->estNiveauTerminal($codeSectionActuelleNorm, $nomNiveauActuel)) {
                return [
                    'eligible' => true,
                    'est_fin_parcours' => true,
                    'est_admis' => true,
                    'decision_bilan' => $decisionTexte,
                    'decision_normalisee' => 'ADMIS',
                    'parcours_actuel' => $parcoursActuelInfo,
                    'parcours_suivant' => null,
                    'message' => 'Félicitations, vous avez achevé le parcours catéchétique de cette section. Veuillez contacter le secrétariat paroissial.',
                ];
            }

            // Si pas de règle et pas fin de parcours connue : aucune progression inventée, maintien
            return [
                'eligible' => true,
                'est_fin_parcours' => false,
                'est_admis' => true,
                'decision_bilan' => $decisionTexte,
                'decision_normalisee' => 'ADMIS',
                'parcours_actuel' => $parcoursActuelInfo,
                'parcours_suivant' => [
                    'section_id' => $sectionActuelle->id,
                    'section_uuid' => $sectionActuelle->uuid,
                    'section_nom' => $sectionActuelle->nom,
                    'section_code' => $sectionActuelle->code,
                    'niveau_id' => $niveauActuel->id,
                    'niveau_uuid' => $niveauActuel->uuid,
                    'niveau_nom' => $niveauActuel->nom,
                ],
                'message' => 'Aucune règle de progression configurée : maintien au niveau actuel.',
            ];
        }

        // CAS 2 : TOUTE AUTRE DÉCISION (NON ADMIS, À REPRENDRE, AJOURNÉ, OU ABSENTE)
        // → Conserver section + niveau actuels
        return [
            'eligible' => true,
            'est_fin_parcours' => false,
            'est_admis' => false,
            'decision_bilan' => $decisionTexte,
            'decision_normalisee' => $decisionTexte ? mb_strtoupper(trim($decisionTexte), 'UTF-8') : 'NON DÉFINIE',
            'parcours_actuel' => $parcoursActuelInfo,
            'parcours_suivant' => [
                'section_id' => $sectionActuelle->id,
                'section_uuid' => $sectionActuelle->uuid,
                'section_nom' => $sectionActuelle->nom,
                'section_code' => $sectionActuelle->code,
                'niveau_id' => $niveauActuel->id,
                'niveau_uuid' => $niveauActuel->uuid,
                'niveau_nom' => $niveauActuel->nom,
            ],
            'message' => 'Maintien de la section et du niveau actuels selon la décision du bilan.',
        ];
    }

    /**
     * Trouver la règle de progression pastorale (priorité à la paroisse, sinon globale).
     */
    protected function trouverRegleProgression(string $codeSection, string $nomNiveau, string $decision, int $paroisseId): ?RegleProgressionPastorale
    {
        // 1. Règle spécifique à la paroisse
        $regle = RegleProgressionPastorale::where('paroisse_configuration_id', $paroisseId)
            ->where('actif', true)
            ->where('code_section_source', $codeSection)
            ->where('niveau_source', $nomNiveau)
            ->where('decision', $decision)
            ->orderByDesc('ordre_priorite')
            ->first();

        if ($regle) {
            return $regle;
        }

        // 2. Règle globale par défaut
        return RegleProgressionPastorale::whereNull('paroisse_configuration_id')
            ->where('actif', true)
            ->where('code_section_source', $codeSection)
            ->where('niveau_source', $nomNiveau)
            ->where('decision', $decision)
            ->orderByDesc('ordre_priorite')
            ->first();
    }

    /**
     * Trouver une section par son code canonique dans la paroisse spécifiée.
     */
    public function trouverSectionParCode(string $codeNormalise, int $paroisseId): ?Section
    {
        $sections = Section::where('paroisse_configuration_id', $paroisseId)->get();

        foreach ($sections as $section) {
            if (self::normaliserCodeSection($section->code) === $codeNormalise) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Vérifier si un niveau est un niveau terminal connu sans suite automatique
     */
    protected function estNiveauTerminal(string $codeSection, string $nomNiveau): bool
    {
        $terminaux = [
            self::CODE_ENFANTS_COL => '5ème Année',
            self::CODE_JEUNES => '5ème Année',
            self::CODE_ADULTES => '4ème Année',
        ];

        return isset($terminaux[$codeSection]) && $terminaux[$codeSection] === $nomNiveau;
    }
}

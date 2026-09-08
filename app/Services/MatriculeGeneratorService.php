<?php

namespace App\Services;

use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Section;
use DateTimeInterface;
use DomainException;
use RuntimeException;

class MatriculeGeneratorService
{
    /**
     * Jeu de caractères alphanumériques autorisés (32 caractères).
     * Caractères ambigus (0, O, 1, I) exclus pour garantir une lisibilité optimale.
     */
    public const CHARSET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * Nombre maximum de tentatives pour trouver une combinaison unique.
     */
    protected const MAX_ATTEMPTS = 100;

    /**
     * Génère un matricule unique officiel pour un catéchumène selon le nouveau format.
     * Format : {prefixe_matricule}{AA}-{SECTION}{CODE_ALEATOIRE_4}
     * Exemples : SM26-J8HDX, SM26-A8HDX, SM26-E8HDX
     *
     * @param int $paroisseId Identifiant de la configuration de paroisse
     * @param Section|string|int|null $section Section, code de section, ID de section ou null
     * @param DateTimeInterface|string|int|null $yearOrDate Année ou date de référence
     * @param bool $strictSection Si true, lève une exception si aucune section n'est identifiable
     * @return string
     *
     * @throws DomainException Si le préfixe paroisse n'est pas configuré ou si la section est requise et manquante
     * @throws RuntimeException Si aucun matricule unique n'est trouvé après MAX_ATTEMPTS
     */
    public function generate(
        int $paroisseId,
        Section|string|int|null $section = null,
        DateTimeInterface|string|int|null $yearOrDate = null,
        bool $strictSection = false
    ): string {
        // 1. Récupération et validation du préfixe de paroisse
        $prefix = $this->resolvePrefix($paroisseId);

        // 2. Détermination de l'année (2 derniers chiffres)
        $anneeCourt = $this->resolveYear($yearOrDate, $paroisseId);

        // 3. Détermination du caractère de section (J, A ou E)
        $sectionLetter = $this->resolveSectionLetter($section, $strictSection);

        // 4. Génération avec vérification d'unicité absolue (soft-deletes inclus)
        $attempts = 0;
        do {
            $codeAleatoire = $this->generateRandomCode(4);
            $matricule = sprintf('%s%s-%s%s', $prefix, $anneeCourt, $sectionLetter, $codeAleatoire);

            $exists = Catechumene::withTrashed()->where('matricule', $matricule)->exists();
            $attempts++;

            if (!$exists) {
                return $matricule;
            }
        } while ($attempts < self::MAX_ATTEMPTS);

        throw new RuntimeException("Impossible de générer un matricule unique après {$attempts} tentatives pour la paroisse {$paroisseId}.");
    }

    /**
     * Résout le préfixe de la paroisse depuis sa configuration.
     *
     * @param int $paroisseId
     * @return string
     * @throws DomainException Si non configuré
     */
    public function resolvePrefix(int $paroisseId): string
    {
        $config = CatecheseConfiguration::find($paroisseId);
        $rawPrefix = $config?->prefixe_matricule;

        if (empty($rawPrefix)) {
            throw new DomainException("Le préfixe matricule de la paroisse n'est pas configuré.");
        }

        $cleanPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $rawPrefix));
        if (empty($cleanPrefix)) {
            throw new DomainException("Le préfixe matricule de la paroisse n'est pas configuré.");
        }

        return $cleanPrefix;
    }

    /**
     * Résout la lettre de section (J, A ou E) à partir de sections.code.
     * Règle obligatoire :
     * - SEC-JEUNE (ou SEC-JEUNES)  -> J
     * - SEC-ADULTE (ou SEC-ADULTES) -> A
     * - Toute autre section         -> E
     *
     * @param Section|string|int|null $section
     * @param bool $strict
     * @return string 'J', 'A' ou 'E'
     *
     * @throws DomainException Si la section est requise (strict) mais non identifiable
     */
    public function resolveSectionLetter(Section|string|int|null $section, bool $strict = false): string
    {
        $code = null;

        if ($section instanceof Section) {
            $code = $section->code;
        } elseif (is_string($section)) {
            $code = $section;
        } elseif (is_numeric($section)) {
            $code = Section::find((int) $section)?->code;
        }

        if (empty($code)) {
            if ($strict) {
                throw new DomainException("Section introuvable pour la génération du matricule.");
            }
            // Par défaut, si aucune section n'est explicitée, on applique la règle générique 'E'
            return 'E';
        }

        $normalized = strtoupper(trim($code));

        if ($normalized === 'SEC-JEUNE' || $normalized === 'SEC-JEUNES') {
            return 'J';
        }

        if ($normalized === 'SEC-ADULTE' || $normalized === 'SEC-ADULTES') {
            return 'A';
        }

        // Pour toutes les autres sections (SEC-ENF-PRI, SEC-ENF-COL, SEC-ENFANCE, etc.)
        return 'E';
    }

    /**
     * Résout l'année sous la forme de 2 chiffres (ex: 2026 -> '26').
     *
     * @param DateTimeInterface|string|int|null $yearOrDate
     * @param int|null $paroisseId
     * @return string
     */
    public function resolveYear(DateTimeInterface|string|int|null $yearOrDate = null, ?int $paroisseId = null): string
    {
        if ($yearOrDate instanceof DateTimeInterface) {
            return $yearOrDate->format('y');
        }

        if (is_int($yearOrDate)) {
            $val = (string) $yearOrDate;
            return strlen($val) >= 4 ? substr($val, -2) : sprintf('%02d', $yearOrDate);
        }

        if (is_string($yearOrDate) && trim($yearOrDate) !== '') {
            $str = trim($yearOrDate);

            // Année sous forme 2 chiffres exacte (ex: '26')
            if (preg_match('/^[0-9]{2}$/', $str)) {
                return $str;
            }

            // Année scolaire / pastorale (ex: '2026-2027')
            if (preg_match('/^([0-9]{4})-/', $str, $matches)) {
                return substr($matches[1], -2);
            }

            // Année 4 chiffres (ex: '2026')
            if (preg_match('/^([0-9]{4})$/', $str, $matches)) {
                return substr($matches[1], -2);
            }

            // Date complète (ex: '2026-09-01')
            $time = strtotime($str);
            if ($time !== false) {
                return date('y', $time);
            }
        }

        // Repli sur l'année pastorale active de la paroisse
        if ($paroisseId) {
            $anneeCourante = AnneeCatechese::getAnneeCourante($paroisseId);
            if ($anneeCourante) {
                if ($anneeCourante->date_debut) {
                    return $anneeCourante->date_debut->format('y');
                }
                if (preg_match('/^([0-9]{4})/', (string) $anneeCourante->libelle, $m)) {
                    return substr($m[1], -2);
                }
            }
        }

        // Repli ultime : année calendaire en cours
        return date('y');
    }

    /**
     * Génère un code alphanumérique aléatoire de longueur définie en majuscules.
     *
     * @param int $length
     * @return string
     */
    public function generateRandomCode(int $length = 4): string
    {
        $charset = self::CHARSET;
        $maxIndex = strlen($charset) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $charset[random_int(0, $maxIndex)];
        }

        return $code;
    }

    /**
     * Détermine la section associée à un catéchumène à partir de ses relations Eloquent.
     * Cascade :
     * 1. InscriptionAnnuelle la plus récente -> section
     * 2. InscriptionAnnuelle -> niveau -> section
     * 3. InscriptionAnnuelle -> classe -> niveau -> section
     *
     * @param Catechumene $catechumene
     * @return Section|null
     */
    public function resolveSectionFromCatechumene(Catechumene $catechumene): ?Section
    {
        $inscriptions = $catechumene->relationLoaded('inscriptionsAnnuelles')
            ? $catechumene->inscriptionsAnnuelles
            : $catechumene->inscriptionsAnnuelles()
                ->with(['section', 'niveau.section', 'classe.niveau.section'])
                ->get();

        $derniereInscription = $inscriptions->sortByDesc('created_at')->first();

        if (!$derniereInscription) {
            return null;
        }

        if ($derniereInscription->section) {
            return $derniereInscription->section;
        }

        if ($derniereInscription->niveau?->section) {
            return $derniereInscription->niveau->section;
        }

        if ($derniereInscription->classe?->niveau?->section) {
            return $derniereInscription->classe->niveau->section;
        }

        return null;
    }
}

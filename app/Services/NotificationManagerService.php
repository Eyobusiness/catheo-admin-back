<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\InscriptionAnnuelle;
use App\Models\OperationPaiement;
use App\Models\Preinscription;
use App\Models\Seance;
use App\Models\Presence;
use App\Models\Evaluation;
use App\Models\AnneeCatechese;
use Illuminate\Support\Facades\Log;

class NotificationManagerService
{
    /**
     * Créer une notification système ou enregistrer une activité.
     */
    public static function createNotification(array $data): ?SystemNotification
    {
        try {
            return SystemNotification::create([
                'paroisse_configuration_id' => $data['paroisse_configuration_id'] ?? auth()->user()?->paroisse_configuration_id ?? 1,
                'user_id'                   => $data['user_id'] ?? null,
                'role_destinataire'         => $data['role_destinataire'] ?? 'ALL',
                'type'                      => $data['type'] ?? 'activite', // alerte, activite, rappel, info
                'action'                    => $data['action'] ?? 'CREATE',
                'titre'                     => $data['titre'],
                'message'                   => $data['message'],
                'source_type'               => $data['source_type'] ?? null,
                'source_id'                 => $data['source_id'] ?? null,
                'route_url'                 => $data['route_url'] ?? null,
                'icon'                      => $data['icon'] ?? 'bell',
                'couleur'                   => $data['couleur'] ?? 'primary',
                'donnees_additionnelles'    => $data['donnees_additionnelles'] ?? null,
                'is_read'                   => false,
                'created_by'                => $data['created_by'] ?? auth()->id() ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Impossible de créer la notification système : ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculer en temps réel le résumé des alertes et rappels pour le Dashboard.
     */
    public static function getAlertsSummary(int $paroisseId): array
    {
        // 1. Année catéchèse active
        $anneeActive = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'ouverte')
            ->first() ?? AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->latest('id')->first();

        $anneeId = $anneeActive?->id;

        // 2. Catéchumènes non affectés à une classe pour l'année active
        $inscriptionsSansClasseQuery = InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->whereNull('classe_id');
        if ($anneeId) {
            $inscriptionsSansClasseQuery->where('annee_catechese_id', $anneeId);
        }
        $nonAffectesCount = $inscriptionsSansClasseQuery->count();

        // 3. Paiements en attente / Incomplets
        $paiementsEnAttenteQuery = OperationPaiement::where('paroisse_configuration_id', $paroisseId)
            ->whereIn('statut', ['en_attente', 'partiel']);
        if ($anneeId) {
            $paiementsEnAttenteQuery->where('annee_catechese_id', $anneeId);
        }
        $paiementsEnAttenteCount = $paiementsEnAttenteQuery->count();
        $montantTotalRestant = (float) $paiementsEnAttenteQuery->selectRaw('SUM(montant - montant_paye) as total_du')->value('total_du') ?? 0;

        // 4. Préinscriptions en attente de validation
        $preinscriptionsEnAttenteCount = Preinscription::where('paroisse_configuration_id', $paroisseId)
            ->whereIn('statut', ['en_attente', 'soumis', 'deposee'])
            ->count();

        // 5. Séances récentes sans feuille de présence (Appels non faits)
        $seancesSansPresenceQuery = Seance::where('paroisse_configuration_id', $paroisseId)
            ->where('date_seance', '<=', now()->toDateString())
            ->where('date_seance', '>=', now()->subDays(14)->toDateString())
            ->whereDoesntHave('presences');
        $appelsNonFaitsCount = $seancesSansPresenceQuery->count();

        // 6. Évaluations sans notes
        $evaluationsSansNotesQuery = Evaluation::where('paroisse_configuration_id', $paroisseId)
            ->where('date_evaluation', '<=', now()->toDateString())
            ->whereDoesntHave('notes');
        $evaluationsSansNotesCount = $evaluationsSansNotesQuery->count();

        // Créer une liste d'items d'alertes formattés pour le frontend
        $alertsList = [];

        if ($nonAffectesCount > 0) {
            $alertsList[] = [
                'id'          => 'alert_non_affectes',
                'type'        => 'alerte',
                'titre'       => 'Catéchumènes non affectés',
                'message'     => "{$nonAffectesCount} catéchumène(s) inscrit(s) n'ont pas encore été affecté(s) à une classe.",
                'count'       => $nonAffectesCount,
                'route_url'   => '/dashboard/catechumenes',
                'icon'        => 'user-x',
                'couleur'     => 'warning',
                'action_label'=> 'Affecter aux classes',
            ];
        }

        if ($paiementsEnAttenteCount > 0) {
            $formattedMontant = number_format($montantTotalRestant, 0, ',', ' ') . ' FCFA';
            $alertsList[] = [
                'id'          => 'alert_paiements',
                'type'        => 'rappel',
                'titre'       => 'Paiements en attente',
                'message'     => "{$paiementsEnAttenteCount} opération(s) en attente de solde (Reste : {$formattedMontant}).",
                'count'       => $paiementsEnAttenteCount,
                'montant'     => $montantTotalRestant,
                'route_url'   => '/dashboard/finances/paiements',
                'icon'        => 'credit-card',
                'couleur'     => 'danger',
                'action_label'=> 'Gérer les paiements',
            ];
        }

        if ($preinscriptionsEnAttenteCount > 0) {
            $alertsList[] = [
                'id'          => 'alert_preinscriptions',
                'type'        => 'alerte',
                'titre'       => 'Nouvelles préinscriptions',
                'message'     => "{$preinscriptionsEnAttenteCount} dossier(s) de préinscription en attente de validation.",
                'count'       => $preinscriptionsEnAttenteCount,
                'route_url'   => '/dashboard/preinscriptions',
                'icon'        => 'clipboard',
                'couleur'     => 'info',
                'action_label'=> 'Valider les dossiers',
            ];
        }

        if ($appelsNonFaitsCount > 0) {
            $alertsList[] = [
                'id'          => 'alert_appels',
                'type'        => 'rappel',
                'titre'       => 'Appels non effectués',
                'message'     => "{$appelsNonFaitsCount} séance(s) passée(s) n'ont pas encore de feuille de présence enregistrée.",
                'count'       => $appelsNonFaitsCount,
                'route_url'   => '/dashboard/presences',
                'icon'        => 'check-square',
                'couleur'     => 'warning',
                'action_label'=> 'Enregistrer présences',
            ];
        }

        if ($evaluationsSansNotesCount > 0) {
            $alertsList[] = [
                'id'          => 'alert_notes',
                'type'        => 'rappel',
                'titre'       => 'Notes d\'évaluation manquantes',
                'message'     => "{$evaluationsSansNotesCount} évaluation(s) passée(s) sont sans notes enregistrées.",
                'count'       => $evaluationsSansNotesCount,
                'route_url'   => '/dashboard/evaluations',
                'icon'        => 'book-open',
                'couleur'     => 'warning',
                'action_label'=> 'Saisir les notes',
            ];
        }

        return [
            'total_alertes'                 => count($alertsList),
            'catechumenes_non_affectes'    => $nonAffectesCount,
            'paiements_en_attente'         => $paiementsEnAttenteCount,
            'montant_du_restant'           => $montantTotalRestant,
            'preinscriptions_en_attente'   => $preinscriptionsEnAttenteCount,
            'appels_non_faits'             => $appelsNonFaitsCount,
            'evaluations_sans_notes'       => $evaluationsSansNotesCount,
            'alerts_list'                  => $alertsList,
        ];
    }
}

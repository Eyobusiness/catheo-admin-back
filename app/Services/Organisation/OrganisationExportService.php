<?php

namespace App\Services\Organisation;

use App\Models\Activite;
use App\Models\CampagnePelerinage;
use App\Models\InscriptionPelerinage;
use App\Models\Membre;
use App\Models\OperationOrganisation;
use App\Models\Organisation;
use App\Models\PaiementPelerinage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganisationExportService
{
    /**
     * Génère un flux CSV compatible Excel (UTF-8 BOM + séparateur point-virgule).
     */
    protected function streamCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM pour ouverture propre directe dans Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    /**
     * Export CSV de la liste des membres.
     */
    public function exportMembres(Organisation $organisation, array $filters = []): StreamedResponse
    {
        $query = Membre::where('organisation_id', $organisation->id)->latest('id');

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }
        if (!empty($filters['sexe'])) {
            $query->where('sexe', strtoupper($filters['sexe']));
        }
        if (!empty($filters['fonction'])) {
            $query->where('fonction', $filters['fonction']);
        }

        $headers = [
            'ID', 'Nom', 'Prénoms', 'Sexe', 'Date Naissance', 'Téléphone', 'Email',
            'Quartier', 'Adresse', 'Fonction', 'Date Entrée', 'Statut',
        ];

        $rows = (function () use ($query) {
            foreach ($query->cursor() as $membre) {
                yield [
                    $membre->id,
                    $membre->nom,
                    $membre->prenoms,
                    $membre->sexe,
                    $membre->date_naissance?->format('d/m/Y') ?? '',
                    $membre->telephone ?? '',
                    $membre->email ?? '',
                    $membre->quartier ?? '',
                    $membre->adresse ?? '',
                    $membre->fonction ?? '',
                    $membre->date_entree?->format('d/m/Y') ?? '',
                    $membre->statut,
                ];
            }
        })();

        $filename = sprintf('membres_%s_%s.csv', strtolower($organisation->code), date('Ymd_His'));

        return $this->streamCsv($filename, $headers, $rows);
    }

    /**
     * Export CSV de la liste des activités.
     */
    public function exportActivites(Organisation $organisation, array $filters = []): StreamedResponse
    {
        $query = Activite::with('responsable')->where('organisation_id', $organisation->id)->latest('date_debut');

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }
        if (!empty($filters['type_activite'])) {
            $query->where('type_activite', $filters['type_activite']);
        }

        $headers = [
            'Code', 'Titre', 'Type', 'Date Début', 'Date Fin', 'Lieu',
            'Responsable', 'Statut', 'Taux Exécution (%)',
        ];

        $rows = (function () use ($query) {
            foreach ($query->cursor() as $act) {
                yield [
                    $act->code,
                    $act->titre,
                    $act->type_activite ?? '',
                    $act->date_debut?->format('d/m/Y H:i') ?? '',
                    $act->date_fin?->format('d/m/Y H:i') ?? '',
                    $act->lieu ?? '',
                    $act->responsable ? trim("{$act->responsable->nom} {$act->responsable->prenoms}") : '',
                    $act->statut,
                    $act->taux_execution,
                ];
            }
        })();

        $filename = sprintf('activites_%s_%s.csv', strtolower($organisation->code), date('Ymd_His'));

        return $this->streamCsv($filename, $headers, $rows);
    }

    /**
     * Export CSV des participants à un pèlerinage (adapté aux profils logistiques).
     */
    public function exportParticipantsPelerinage(CampagnePelerinage $campagne, array $filters = [], string $typeExport = 'general'): StreamedResponse
    {
        $query = InscriptionPelerinage::with('tarif')
            ->where('campagne_pelerinage_id', $campagne->id)
            ->latest('id');

        if (!empty($filters['statut_inscription'])) {
            $query->where('statut_inscription', $filters['statut_inscription']);
        }
        if (!empty($filters['statut_participation'])) {
            $query->where('statut_participation', $filters['statut_participation']);
        }

        switch (strtolower(trim($typeExport))) {
            case 'transport':
                $headers = [
                    'Référence', 'Nom', 'Prénoms', 'Sexe', 'Téléphone', 'Adresse',
                    'Contact Urgence Nom', 'Contact Urgence Tél', 'Lieu Départ', 'Destination', 'Date Départ',
                ];
                $rows = (function () use ($query, $campagne) {
                    foreach ($query->cursor() as $ins) {
                        yield [
                            $ins->reference,
                            $ins->nom,
                            $ins->prenoms,
                            $ins->sexe,
                            $ins->telephone ?? '',
                            $ins->adresse ?? '',
                            $ins->contact_urgence_nom ?? '',
                            $ins->contact_urgence_telephone ?? '',
                            $campagne->lieu_depart,
                            $campagne->destination,
                            $campagne->date_depart?->format('d/m/Y') ?? '',
                        ];
                    }
                })();
                break;

            case 'embarquement':
                $headers = [
                    'Référence', 'Nom', 'Prénoms', 'Sexe', 'Taille', 'Téléphone',
                    'Statut Inscription', 'Statut Participation', 'Badge Imprimé', 'Kit Remis', 'Date Remise Kit',
                ];
                $rows = (function () use ($query) {
                    foreach ($query->cursor() as $ins) {
                        yield [
                            $ins->reference,
                            $ins->nom,
                            $ins->prenoms,
                            $ins->sexe,
                            $ins->taille ?? '',
                            $ins->telephone ?? '',
                            $ins->statut_inscription,
                            $ins->statut_participation,
                            $ins->badge_imprime ? 'OUI' : 'NON',
                            $ins->kit_remis ? 'OUI' : 'NON',
                            $ins->date_remise_kit?->format('d/m/Y H:i') ?? '',
                        ];
                    }
                })();
                break;

            case 'hebergement':
                $headers = [
                    'Référence', 'Nom', 'Prénoms', 'Sexe', 'Téléphone', 'Adresse',
                    'Contact Urgence Nom', 'Contact Urgence Tél', 'Destination', 'Date Début', 'Date Fin',
                ];
                $rows = (function () use ($query, $campagne) {
                    foreach ($query->cursor() as $ins) {
                        yield [
                            $ins->reference,
                            $ins->nom,
                            $ins->prenoms,
                            $ins->sexe,
                            $ins->telephone ?? '',
                            $ins->adresse ?? '',
                            $ins->contact_urgence_nom ?? '',
                            $ins->contact_urgence_telephone ?? '',
                            $campagne->destination,
                            $campagne->date_depart?->format('d/m/Y') ?? '',
                            $campagne->date_fin?->format('d/m/Y') ?? '',
                        ];
                    }
                })();
                break;

            case 'general':
            default:
                $headers = [
                    'Référence', 'Nom', 'Prénoms', 'Sexe', 'Taille', 'Date Naissance', 'Téléphone', 'Email',
                    'Type Participant', 'Tarif', 'Montant Total', 'Montant Payé', 'Reste à Payer',
                    'Statut Inscription', 'Statut Participation', 'Badge Imprimé', 'Kit Remis',
                ];
                $rows = (function () use ($query) {
                    foreach ($query->cursor() as $ins) {
                        yield [
                            $ins->reference,
                            $ins->nom,
                            $ins->prenoms,
                            $ins->sexe,
                            $ins->taille ?? '',
                            $ins->date_naissance?->format('d/m/Y') ?? '',
                            $ins->telephone ?? '',
                            $ins->email ?? '',
                            $ins->type_participant,
                            $ins->tarif?->libelle ?? '',
                            $ins->montant,
                            $ins->montant_paye,
                            $ins->reste_a_payer,
                            $ins->statut_inscription,
                            $ins->statut_participation,
                            $ins->badge_imprime ? 'OUI' : 'NON',
                            $ins->kit_remis ? 'OUI' : 'NON',
                        ];
                    }
                })();
                break;
        }

        $filename = sprintf('pelerins_%s_%s_%s.csv', strtolower($campagne->code), $typeExport, date('Ymd_His'));

        return $this->streamCsv($filename, $headers, $rows);
    }

    /**
     * Export CSV des paiements d'une campagne de pèlerinage.
     */
    public function exportPaiementsPelerinage(CampagnePelerinage $campagne, array $filters = []): StreamedResponse
    {
        $query = PaiementPelerinage::with(['inscription', 'caissier'])
            ->whereHas('inscription', function ($q) use ($campagne) {
                $q->where('campagne_pelerinage_id', $campagne->id);
            })
            ->latest('date_paiement');

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }
        if (!empty($filters['mode_paiement'])) {
            $query->where('mode_paiement', $filters['mode_paiement']);
        }

        $headers = [
            'Référence Paiement', 'Date Paiement', 'Participant', 'Réf Inscription',
            'Montant', 'Devise', 'Mode Paiement', 'Statut', 'Réf Transaction', 'Caissier',
        ];

        $rows = (function () use ($query) {
            foreach ($query->cursor() as $p) {
                yield [
                    $p->reference,
                    $p->date_paiement?->format('d/m/Y H:i') ?? '',
                    $p->inscription ? trim("{$p->inscription->nom} {$p->inscription->prenoms}") : '',
                    $p->inscription?->reference ?? '',
                    $p->montant,
                    $p->devise,
                    $p->mode_paiement,
                    $p->statut,
                    $p->reference_transaction ?? '',
                    $p->caissier?->name ?? '',
                ];
            }
        })();

        $filename = sprintf('paiements_pelerinage_%s_%s.csv', strtolower($campagne->code), date('Ymd_His'));

        return $this->streamCsv($filename, $headers, $rows);
    }

    /**
     * Export CSV des opérations de caisse de l'organisation.
     */
    public function exportOperations(Organisation $organisation, array $filters = []): StreamedResponse
    {
        $query = OperationOrganisation::with('operateur')
            ->where('organisation_id', $organisation->id)
            ->latest('date_operation');

        if (!empty($filters['type_operation'])) {
            $query->where('type_operation', $filters['type_operation']);
        }
        if (!empty($filters['date_debut'])) {
            $query->whereDate('date_operation', '>=', $filters['date_debut']);
        }
        if (!empty($filters['date_fin'])) {
            $query->whereDate('date_operation', '<=', $filters['date_fin']);
        }

        $headers = [
            'Référence', 'Date', 'Type Opération', 'Libellé', 'Montant', 'Devise', 'Mode Règlement', 'Statut', 'Opérateur',
        ];

        $rows = (function () use ($query) {
            foreach ($query->cursor() as $op) {
                yield [
                    $op->reference,
                    $op->date_operation?->format('d/m/Y H:i') ?? '',
                    strtoupper($op->type_operation),
                    $op->libelle,
                    $op->montant,
                    $op->devise,
                    $op->mode_reglement ?? '',
                    $op->statut,
                    $op->operateur?->name ?? '',
                ];
            }
        })();

        $filename = sprintf('operations_%s_%s.csv', strtolower($organisation->code), date('Ymd_His'));

        return $this->streamCsv($filename, $headers, $rows);
    }
}

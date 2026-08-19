<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExportRequest;
use App\Models\AnneeCatechese;
use App\Models\BulletinTrimestriel;
use App\Models\CaisseParoissiale;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\Niveau;
use App\Models\Paiement;
use App\Models\Presence;
use App\Models\Seance;
use App\Models\Section;
use App\Services\ExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    protected ExportService $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Exportation de la liste des catéchumènes filtrée par section, niveau, classe.
     */
    public function catechumenes(ExportRequest $request): StreamedResponse
    {
        $paroisse = $request->user()->paroisse;
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $query = Catechumene::with(['ceb', 'inscriptionsAnnuelles.niveau', 'inscriptionsAnnuelles.classe', 'inscriptionsAnnuelles.anneeCatechese'])
            ->where('paroisse_configuration_id', $paroisseId);

        if (!empty($validated['statut'])) {
            $query->where('statut', $validated['statut']);
        }

        if (!empty($validated['section_id'])) {
            $sectionId = Section::where('uuid', $validated['section_id'])->value('id');
            if ($sectionId) {
                $query->whereHas('inscriptionsAnnuelles.niveau', function ($q) use ($sectionId) {
                    $q->where('section_id', $sectionId);
                });
            }
        }

        if (!empty($validated['niveau_id'])) {
            $niveauId = Niveau::where('uuid', $validated['niveau_id'])->value('id');
            if ($niveauId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($niveauId) {
                    $q->where('niveau_id', $niveauId);
                });
            }
        }

        if (!empty($validated['classe_id'])) {
            $classeId = Classe::where('uuid', $validated['classe_id'])->value('id');
            if ($classeId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($classeId) {
                    $q->where('classe_id', $classeId);
                });
            }
        }

        $catechumenes = $query->orderBy('nom')->get();

        $headers = ['Matricule', 'Nom', 'Prénoms', 'Sexe', 'Date Naissance', 'Téléphone', 'CEB', 'Statut'];
        $rows = [];

        foreach ($catechumenes as $cat) {
            $rows[] = [
                $cat->code_catechumene,
                $cat->nom,
                $cat->prenoms,
                $cat->sexe,
                $cat->date_naissance ? date('d/m/Y', strtotime($cat->date_naissance)) : '',
                $cat->telephone ?? $cat->telephone_pere ?? $cat->telephone_tuteur ?? '',
                $cat->ceb?->libelle ?? 'N/A',
                strtoupper($cat->statut),
            ];
        }

        $titre = "Liste_des_Catechumenes";

        if ($validated['format'] === 'pdf') {
            return $this->exportService->exportPdfHtml("Liste des Catéchumènes", $headers, $rows, $paroisse?->nom ?? 'Catheo');
        }

        return $this->exportService->exportCsv("{$titre}.csv", $headers, $rows);
    }

    /**
     * Exportation de la feuille de présence d'une classe ou d'une séance.
     */
    public function presences(ExportRequest $request): StreamedResponse
    {
        $paroisse = $request->user()->paroisse;
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $query = Presence::with(['catechumene', 'seance.classe'])
            ->where('paroisse_configuration_id', $paroisseId);

        if (!empty($validated['seance_id'])) {
            $seanceId = Seance::where('uuid', $validated['seance_id'])->value('id');
            if ($seanceId) {
                $query->where('seance_id', $seanceId);
            }
        }

        $presences = $query->get();

        $headers = ['Matricule', 'Catéchumène', 'Classe', 'Séance', 'Date', 'Statut Présence', 'Remarque'];
        $rows = [];

        foreach ($presences as $p) {
            $rows[] = [
                $p->catechumene?->code_catechumene ?? '',
                ($p->catechumene?->nom . ' ' . $p->catechumene?->prenoms),
                $p->seance?->classe?->libelle ?? 'N/A',
                $p->seance?->titre ?? 'Séance',
                $p->seance?->date_seance ? date('d/m/Y', strtotime($p->seance->date_seance)) : '',
                strtoupper($p->statut_presence),
                $p->remarque ?? '',
            ];
        }

        if ($validated['format'] === 'pdf') {
            return $this->exportService->exportPdfHtml("Feuille de Présence", $headers, $rows, $paroisse?->nom ?? 'Catheo');
        }

        return $this->exportService->exportCsv("Feuille_de_Presence.csv", $headers, $rows);
    }

    /**
     * Exportation du livre journal de caisse ou bilan financier.
     */
    public function finances(ExportRequest $request): StreamedResponse
    {
        $paroisse = $request->user()->paroisse;
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $query = CaisseParoissiale::where('paroisse_configuration_id', $paroisseId);

        if (!empty($validated['date_debut']) && !empty($validated['date_fin'])) {
            $query->whereBetween('date_mouvement', [$validated['date_debut'], $validated['date_fin']]);
        }

        $mouvements = $query->latest('date_mouvement')->get();

        $headers = ['Date', 'Réf. Document', 'Type Mouvement', 'Catégorie', 'Libellé', 'Montant (FCFA)'];
        $rows = [];

        foreach ($mouvements as $m) {
            $rows[] = [
                date('d/m/Y', strtotime($m->date_mouvement)),
                $m->reference_document ?? '-',
                strtoupper($m->type_mouvement),
                strtoupper($m->categorie),
                $m->libelle,
                number_format($m->montant, 0, ',', ' '),
            ];
        }

        if ($validated['format'] === 'pdf') {
            return $this->exportService->exportPdfHtml("Livre Journal de Caisse", $headers, $rows, $paroisse?->nom ?? 'Catheo');
        }

        return $this->exportService->exportCsv("Journal_Caisse.csv", $headers, $rows);
    }
}

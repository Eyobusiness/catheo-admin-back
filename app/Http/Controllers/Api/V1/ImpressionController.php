<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CatecheseConfigurationResource;
use App\Models\AnneeCatechese;
use App\Models\BulletinTrimestriel;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\DecisionFinAnnee;
use App\Models\Evaluation;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\OperationPaiement;
use App\Models\Presence;
use App\Models\Seance;
use App\Models\Section;
use App\Services\ParoisseHeaderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpressionController extends Controller
{
    private function resolveParoisse(Request $request): CatecheseConfiguration
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? CatecheseConfiguration::value('id');
        return CatecheseConfiguration::find($paroisseId) ?? CatecheseConfiguration::firstOrFail();
    }

    /**
     * Obtenir les métadonnées officielles de l'entête d'impression de la paroisse.
     */
    public function entete(Request $request): JsonResponse
    {
        $paroisse = $this->resolveParoisse($request);
        $annee = AnneeCatechese::resolveAnnee($request, $paroisse->id);

        return response()->json([
            'status' => 'success',
            'data'   => new CatecheseConfigurationResource($paroisse),
            'entete' => $this->getEntetePayload($paroisse, $annee),
        ]);
    }

    /**
     * Générer la "Fiche de Notes".
     */
    public function ficheNotes(Request $request): JsonResponse
    {
        $paroisse = $this->resolveParoisse($request);
        $validated = $this->validateFilters($request);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->orWhere('id', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::resolveAnnee($request, $paroisse->id);

        $classe = !empty($validated['classe_id'])
            ? Classe::with(['niveau.section', 'affectations.animateur'])->where('uuid', $validated['classe_id'])->orWhere('id', $validated['classe_id'])->first()
            : null;

        $inscriptions = $this->getInscriptionsQuery($paroisse->id, $annee?->id, $classe?->id, $validated)->get();
        $animateurs = $classe ? $classe->affectations->map(fn($a) => trim($a->animateur->nom . ' ' . ($a->animateur->prenoms ?? $a->animateur->prenom ?? '')))->values()->toArray() : [];

        // Récupérer les évaluations de la classe pour pré-remplir les notes si existantes
        $evaluations = $classe ? Evaluation::where('classe_id', $classe->id)->when($annee, fn($q) => $q->where('annee_catechese_id', $annee->id))->get() : collect();
        $evalIds = $evaluations->pluck('id');
        $notesGrouped = Note::whereIn('evaluation_id', $evalIds)->get()->groupBy('catechumene_id');

        $rows = $inscriptions->map(function ($ins, $index) use ($notesGrouped) {
            $cat = $ins->catechumene;
            $catNotes = $notesGrouped->get($cat->id, collect());
            $moyenne = $catNotes->isNotEmpty() ? round($catNotes->avg('valeur_note'), 2) : null;
            $prenom = $cat->prenoms ?? ($cat->prenom ?? '');

            return [
                'numero'           => sprintf('%02d', $index + 1),
                'matricule'        => $cat->matricule,
                'code_catechumene' => $cat->matricule,
                'nom'              => mb_strtoupper($cat->nom),
                'prenom'           => $prenom,
                'prenoms'          => $prenom,
                'nom_complet'      => trim(mb_strtoupper($cat->nom) . ' ' . $prenom),
                'nomPrenoms'       => trim(mb_strtoupper($cat->nom) . ' ' . $prenom),
                'sexe'             => $cat->sexe,
                'date_naissance'   => $cat->date_naissance ? (is_string($cat->date_naissance) ? substr($cat->date_naissance, 0, 10) : $cat->date_naissance->toDateString()) : null,
                'note_1'           => $catNotes->get(0)?->valeur_note ?? '',
                'note_2'           => $catNotes->get(1)?->valeur_note ?? '',
                'note_3'           => $catNotes->get(2)?->valeur_note ?? '',
                'moyenne'          => $moyenne ?? '',
                'decision'         => $moyenne !== null ? ($moyenne >= 10 ? 'Admis' : 'Non admis') : '',
            ];
        });

        return response()->json([
            'status'   => 'success',
            'entete'   => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre'            => 'FICHE DE NOTES',
                'classe_nom'       => $classe?->nom ?? 'Toutes les classes',
                'section_nom'      => $classe?->niveau?->section?->nom ?? 'Toutes les sections',
                'niveau_nom'       => $classe?->niveau?->nom ?? 'Tous les niveaux',
                'annee_pastorale'  => $annee?->libelle ?? date('Y') . '-' . (date('Y') + 1),
                'animateurs'       => $animateurs,
                'total_eleves'     => $rows->count(),
            ],
            'colonnes' => ['N°', 'MATRICULE', 'NOMS ET PRÉNOMS', 'SEXE', 'ÉVAL 1', 'ÉVAL 2', 'ÉVAL 3', 'MOYENNE', 'OBSERVATION'],
            'lignes'   => $rows,
        ]);
    }

    /**
     * Générer la "Fiche de Présences".
     */
    public function fichePresences(Request $request): JsonResponse
    {
        $response = $this->listePresence($request);
        $data = $response->getData(true);
        if ($data['status'] === 'success') {
            $data['document']['titre'] = 'FICHE DE PRÉSENCES';
        }
        return response()->json($data);
    }

    /**
     * Générer le "Registre & Liste Officielle des Catéchumènes".
     */
    public function listeCatechumenes(Request $request): JsonResponse
    {
        $paroisse = $this->resolveParoisse($request);
        $validated = $this->validateFilters($request);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->orWhere('id', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::resolveAnnee($request, $paroisse->id);

        $classe = !empty($validated['classe_id'])
            ? Classe::with(['niveau.section', 'affectations.animateur'])->where('uuid', $validated['classe_id'])->orWhere('id', $validated['classe_id'])->first()
            : null;

        $inscriptions = $this->getInscriptionsQuery($paroisse->id, $annee?->id, $classe?->id, $validated)
            ->with(['catechumene.ceb', 'catechumene.parrainsMarraines'])
            ->get();

        $garcons = 0;
        $filles = 0;
        $baptises = 0;

        $rows = $inscriptions->map(function ($ins, $index) use (&$garcons, &$filles, &$baptises) {
            $cat = $ins->catechumene;
            $prenom = $cat->prenoms ?? ($cat->prenom ?? '');
            if ($cat->sexe === 'M') $garcons++;
            if ($cat->sexe === 'F') $filles++;
            if ($cat->est_baptise) $baptises++;

            return [
                'numero'                  => sprintf('%02d', $index + 1),
                'matricule'               => $cat->matricule,
                'code_catechumene'        => $cat->matricule,
                'nom'                     => mb_strtoupper($cat->nom),
                'prenom'                  => $prenom,
                'prenoms'                 => $prenom,
                'nom_complet'             => trim(mb_strtoupper($cat->nom) . ' ' . $prenom),
                'nomPrenoms'              => trim(mb_strtoupper($cat->nom) . ' ' . $prenom),
                'sexe'                    => $cat->sexe,
                'date_naissance'          => $cat->date_naissance ? (is_string($cat->date_naissance) ? substr($cat->date_naissance, 0, 10) : $cat->date_naissance->toDateString()) : null,
                'lieu_naissance'          => $cat->lieu_naissance ?? '',
                'telephone'               => $cat->telephone ?? '',
                'telephone_parent'        => $cat->telephone_tuteur ?? ($cat->telephone_pere ?? ($cat->telephone_mere ?? $cat->telephone)),
                'nom_pere'                => $cat->nom_pere ?? '',
                'nom_mere'                => $cat->nom_mere ?? '',
                'nom_tuteur'              => $cat->nom_tuteur ?? '',
                'domicile'                => $cat->domicile ?? ($cat->adresse ?? ''),
                'est_baptise'             => (bool) $cat->est_baptise,
                'statut_bapteme'          => $cat->est_baptise ? 'Oui' : 'Non',
                'date_bapteme'            => $cat->date_bapteme ? (is_string($cat->date_bapteme) ? substr($cat->date_bapteme, 0, 10) : $cat->date_bapteme->toDateString()) : '',
                'paroisse_bapteme'        => $cat->paroisse_bapteme ?? '',
                'classe_nom'              => $ins->classe?->nom ?? 'Non assigné',
                'niveau_nom'              => $ins->niveau?->nom ?? '',
                'section_nom'             => $ins->section?->nom ?? ($ins->niveau?->section?->nom ?? ''),
                'ceb_nom'                 => $cat->ceb?->nom ?? '',
                'frais_inscription_payes' => (bool) $ins->frais_inscription_payes,
                'statut_paiement'         => $ins->frais_inscription_payes ? 'Payé' : 'Non payé',
            ];
        });

        return response()->json([
            'status'   => 'success',
            'entete'   => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre'                => 'REGISTRE & LISTE OFFICIELLE DES CATÉCHUMÈNES',
                'classe_nom'           => $classe?->nom ?? 'Toutes les classes',
                'section_nom'          => $classe?->niveau?->section?->nom ?? 'Toutes les sections',
                'niveau_nom'           => $classe?->niveau?->nom ?? 'Tous les niveaux',
                'annee_pastorale'      => $annee?->libelle ?? date('Y') . '-' . (date('Y') + 1),
                'total_eleves'         => $rows->count(),
                'effectif_garcons'     => $garcons,
                'effectif_filles'      => $filles,
                'total_baptises'       => $baptises,
                'total_non_baptises'   => $rows->count() - $baptises,
            ],
            'colonnes' => ['N°', 'MATRICULE', 'NOMS ET PRÉNOMS', 'SEXE', 'DATE NAISS.', 'CONTACTS', 'BAPTÊME', 'CLASSE', 'FRAIS'],
            'lignes'   => $rows,
        ]);
    }

    /**
     * Générer le "Suivi Sacramental".
     */
    public function suiviSacramental(Request $request): JsonResponse
    {
        $paroisse = $this->resolveParoisse($request);
        $validated = $this->validateFilters($request);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->orWhere('id', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::resolveAnnee($request, $paroisse->id);

        $classe = !empty($validated['classe_id'])
            ? Classe::with(['niveau.section'])->where('uuid', $validated['classe_id'])->orWhere('id', $validated['classe_id'])->first()
            : null;

        $sacramentLibelle = mb_strtoupper($validated['sacrament'] ?? $request->input('sacrament', 'PREMIÈRE COMMUNION'));

        $inscriptions = $this->getInscriptionsQuery($paroisse->id, $annee?->id, $classe?->id, $validated)
            ->with(['catechumene.parrainsMarraines'])
            ->get();

        $rows = $inscriptions->map(function ($ins, $index) {
            $cat = $ins->catechumene;
            $prenom = $cat->prenoms ?? ($cat->prenom ?? '');
            $parrain = $cat->parrainsMarraines->firstWhere('type', 'parrain') ?? $cat->parrainsMarraines->first();

            return [
                'numero'           => sprintf('%02d', $index + 1),
                'matricule'        => $cat->matricule,
                'code_catechumene' => $cat->matricule,
                'nom'              => mb_strtoupper($cat->nom),
                'prenom'           => $prenom,
                'prenoms'          => $prenom,
                'nom_complet'      => trim(mb_strtoupper($cat->nom) . ' ' . $prenom),
                'nomPrenoms'       => trim(mb_strtoupper($cat->nom) . ' ' . $prenom),
                'sexe'             => $cat->sexe,
                'date_naissance'   => $cat->date_naissance ? (is_string($cat->date_naissance) ? substr($cat->date_naissance, 0, 10) : $cat->date_naissance->toDateString()) : null,
                'contacts'         => $cat->telephone ?? ($cat->telephone_pere ?? ($cat->telephone_mere ?? $cat->telephone_tuteur ?? '')),
                'parrain_marraine' => $parrain?->nom_prenoms ?? 'Non assigné',
                'dossiers'         => [
                    'fiche_identite' => '✓',
                    'photos'         => '✓',
                    'carnet_bapteme' => $cat->est_baptise ? '✓' : 'En attente',
                    'carnet_parrain' => $parrain ? '✓' : 'En attente',
                ],
                'casuel'           => [
                    'payee'          => $ins->frais_inscription_payes ? 'Payé' : 'En attente',
                ],
                'retraite'         => [
                    'presence'       => '',
                    'bougie'         => '',
                    'photos'         => '',
                    'retrait_photos' => '',
                    'retrait_carnet' => '',
                ],
            ];
        });

        return response()->json([
            'status'   => 'success',
            'entete'   => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre'            => "FICHE DE SUIVI DES CANDIDATS À LA {$sacramentLibelle} — " . ($annee?->libelle ?? date('Y')),
                'sacrament'        => $sacramentLibelle,
                'classe_nom'       => $classe?->nom ?? 'Toutes les classes',
                'section_nom'      => $classe?->niveau?->section?->nom ?? 'Toutes les sections',
                'niveau_nom'       => $classe?->niveau?->nom ?? 'Tous les niveaux',
                'annee_pastorale'  => $annee?->libelle ?? date('Y') . '-' . (date('Y') + 1),
                'total_candidats'  => $rows->count(),
            ],
            'colonnes' => [
                'numero'      => 'N°',
                'nom_complet' => 'NOMS ET PRÉNOMS',
                'contacts'    => 'CONTACTS',
                'dossiers'    => ['Fiche d\'ident.', 'Photos', 'Carnet baptême', 'Carnet parrain'],
                'casuel'      => ['Casuel'],
                'retraite'    => ['Présence', 'Bougie', 'Photos', 'Retrait photos', 'Retrait carnet'],
            ],
            'lignes'   => $rows,
        ]);
    }

    /**
     * Générer la "Liste de Présence".
     */
    public function listePresence(Request $request): JsonResponse
    {
        $paroisse = $this->resolveParoisse($request);
        $validated = $this->validateFilters($request);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->orWhere('id', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::resolveAnnee($request, $paroisse->id);

        $classe = !empty($validated['classe_id'])
            ? Classe::with(['niveau.section', 'affectations.animateur'])->where('uuid', $validated['classe_id'])->orWhere('id', $validated['classe_id'])->first()
            : null;

        $inscriptions = $this->getInscriptionsQuery($paroisse->id, $annee?->id, $classe?->id, $validated)->get();

        $dateDebut = !empty($validated['debut_cours']) ? Carbon::parse($validated['debut_cours']) : Carbon::now();
        $nbSeances = (int) ($validated['nombre_seances'] ?? $request->input('nombre_seances', 12));
        $datesSeances = [];

        for ($i = 0; $i < $nbSeances; $i++) {
            $datesSeances[] = $dateDebut->copy()->addWeeks($i)->format('d/m');
        }

        $animateurs = $classe ? $classe->affectations->map(fn($a) => trim($a->animateur->nom . ' ' . ($a->animateur->prenoms ?? $a->animateur->prenom ?? '')))->values()->toArray() : [];

        // Récupérer les séances et présences réelles enregistrées dans la base si disponibles
        $seanceIds = $classe ? Seance::where('classe_id', $classe->id)->pluck('id') : collect();
        $presencesGrouped = Presence::whereIn('seance_id', $seanceIds)->get()->groupBy('catechumene_id');

        $rows = $inscriptions->map(function ($ins, $index) use ($datesSeances, $presencesGrouped) {
            $cat = $ins->catechumene;
            $prenom = $cat->prenoms ?? ($cat->prenom ?? '');
            $catPresences = $presencesGrouped->get($cat->id, collect());

            $seancesMap = [];
            foreach ($datesSeances as $d) {
                $seancesMap[$d] = '';
            }

            return [
                'numero'           => sprintf('%02d', $index + 1),
                'matricule'        => $cat->matricule,
                'code_catechumene' => $cat->matricule,
                'nom'              => mb_strtoupper($cat->nom),
                'prenom'           => $prenom,
                'prenoms'          => $prenom,
                'nom_complet'      => trim(mb_strtoupper($cat->nom) . ' ' . $prenom),
                'nomPrenoms'       => trim(mb_strtoupper($cat->nom) . ' ' . $prenom),
                'sexe'             => $cat->sexe,
                'telephone'        => $cat->telephone ?? ($cat->telephone_pere ?? ''),
                'classe_scolaire'  => $cat->classe_scolaire ?? '',
                'seances'          => $seancesMap,
                'total_presences'  => $catPresences->where('statut', 'present')->count(),
                'total_absences'   => $catPresences->where('statut', 'absent')->count(),
            ];
        });

        return response()->json([
            'status'   => 'success',
            'entete'   => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre'            => 'LISTE DE PRÉSENCE',
                'classe_nom'       => $classe?->nom ?? 'Toutes les classes',
                'jour'             => $validated['jour'] ?? ($request->input('jour') ?? ($classe?->jour_rencontre ?? 'Samedi')),
                'section_nom'      => $classe?->niveau?->section?->nom ?? 'Toutes les sections',
                'niveau_nom'       => $classe?->niveau?->nom ?? 'Tous les niveaux',
                'annee_pastorale'  => $annee?->libelle ?? date('Y') . '-' . (date('Y') + 1),
                'animateurs'       => $animateurs,
                'dates_seances'    => $datesSeances,
                'total_eleves'     => $rows->count(),
            ],
            'lignes'   => $rows,
        ]);
    }

    /**
     * Générer la "Fiche de Bilan Annuel".
     */
    public function ficheBilanAnnuel(Request $request): JsonResponse
    {
        $paroisse = $this->resolveParoisse($request);
        $validated = $this->validateFilters($request);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->orWhere('id', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::resolveAnnee($request, $paroisse->id);

        $classe = !empty($validated['classe_id'])
            ? Classe::with(['niveau.section', 'affectations.animateur'])->where('uuid', $validated['classe_id'])->orWhere('id', $validated['classe_id'])->first()
            : null;

        $inscriptions = $this->getInscriptionsQuery($paroisse->id, $annee?->id, $classe?->id, $validated)->get();
        $animateurs = $classe ? $classe->affectations->map(fn($a) => trim($a->animateur->nom . ' ' . ($a->animateur->prenoms ?? $a->animateur->prenom ?? '')))->values()->toArray() : [];

        // Charger les décisions de fin d'année et bulletins si existants
        $insIds = $inscriptions->pluck('id');
        $decisions = DecisionFinAnnee::whereIn('inscription_annuelle_id', $insIds)
            ->get()
            ->keyBy('inscription_annuelle_id');

        $rows = $inscriptions->map(function ($ins, $index) use ($decisions) {
            $cat = $ins->catechumene;
            $prenom = $cat->prenoms ?? ($cat->prenom ?? '');
            $decision = $decisions->get($ins->id);

            return [
                'numero'           => sprintf('%02d', $index + 1),
                'matricule'        => $cat->matricule,
                'code_catechumene' => $cat->matricule,
                'nom'              => mb_strtoupper($cat->nom),
                'prenom'           => $prenom,
                'prenoms'          => $prenom,
                'nom_complet'      => trim(mb_strtoupper($cat->nom) . ' ' . $prenom),
                'nomPrenoms'       => trim(mb_strtoupper($cat->nom) . ' ' . $prenom),
                'cours'            => $decision?->moyenne_annuelle ? round($decision->moyenne_annuelle, 2) : '',
                'messe'            => $decision?->note_assiduite ?? '',
                'ceb'              => '',
                'mouvt'            => '',
                'moyenne'          => $decision?->moyenne_annuelle ? round($decision->moyenne_annuelle, 2) : '',
                'decision'         => $decision?->decision ?? '',
                'observation'      => $decision?->observations ?? '',
            ];
        });

        return response()->json([
            'status'   => 'success',
            'entete'   => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre'            => 'FICHE DE BILAN ANNUEL',
                'classe_nom'       => $classe?->nom ?? 'Toutes les classes',
                'section_nom'      => $classe?->niveau?->section?->nom ?? 'Toutes les sections',
                'niveau_nom'       => $classe?->niveau?->nom ?? 'Tous les niveaux',
                'annee_pastorale'  => $annee?->libelle ?? date('Y') . '-' . (date('Y') + 1),
                'animateurs'       => $animateurs,
                'total_eleves'     => $rows->count(),
            ],
            'colonnes' => ['N°', 'MATRICULE', 'NOMS ET PRÉNOMS', 'COURS', 'MESSE', 'CEB', 'MOUVT', 'MOYENNE', 'DÉCISION'],
            'lignes'   => $rows,
        ]);
    }

    /**
     * Générer la "Fiche de Renseignements Sacrement de Baptême".
     */
    public function ficheRenseignementBapteme(Request $request): JsonResponse
    {
        $paroisse = $this->resolveParoisse($request);
        $validated = $this->validateFilters($request);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->orWhere('id', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::resolveAnnee($request, $paroisse->id);

        $catechumenes = $this->getCatechumenesQuery($paroisse->id, $validated)->get();

        $fiches = $catechumenes->map(function ($c) use ($annee) {
            $prenom = $c->prenoms ?? ($c->prenom ?? '');
            $parrain = $c->parrainsMarraines->firstWhere('type', 'parrain');
            $marraine = $c->parrainsMarraines->firstWhere('type', 'marraine');

            return [
                'annee_pastorale'   => $annee?->libelle ?? date('Y') . '-' . (date('Y') + 1),
                'matricule'         => $c->matricule,
                'nom'               => mb_strtoupper($c->nom),
                'prenom'            => $prenom,
                'prenoms'           => $prenom,
                'nom_et_prenoms'    => trim(mb_strtoupper($c->nom) . ' ' . $prenom),
                'nom_complet'       => trim(mb_strtoupper($c->nom) . ' ' . $prenom),
                'sexe'              => $c->sexe,
                'date_naissance'    => $c->date_naissance ? (is_string($c->date_naissance) ? substr($c->date_naissance, 0, 10) : $c->date_naissance->toDateString()) : null,
                'lieu_naissance'    => $c->lieu_naissance ?? '',
                'profession'        => $c->profession ?? '',
                'contact'           => $c->telephone ?? ($c->telephone_pere ?? ''),
                'telephone'         => $c->telephone ?? '',
                'domicilie_a'       => $c->domicile ?? ($c->adresse ?? ''),
                'nom_pere'          => $c->nom_pere ?? '',
                'origine_pere'      => $c->origine_pere ?? '',
                'nom_mere'          => $c->nom_mere ?? '',
                'origine_mere'      => $c->origine_mere ?? '',
                'parrain'           => [
                    'nom_prenoms'          => $parrain?->nom_prenoms ?? '',
                    'domicilie_a'          => $parrain?->domicile ?? '',
                    'contact'              => $parrain?->telephone ?? '',
                    'represente_par'       => $parrain?->representant_nom ?? '',
                    'representant_contact' => $parrain?->representant_contact ?? '',
                ],
                'marraine'          => [
                    'nom_prenoms'          => $marraine?->nom_prenoms ?? '',
                    'domicilie_a'          => $marraine?->domicile ?? '',
                    'contact'              => $marraine?->telephone ?? '',
                    'represente_par'       => $marraine?->representant_nom ?? '',
                    'representant_contact' => $marraine?->representant_contact ?? '',
                ],
                'dossier_a_fournir' => [
                    '1. Photocopie du carnet de baptême à jour du parrain ou de la marraine',
                    '2. Photocopie de l\'extrait d\'acte de naissance',
                    '3. Une (1) photo d\'identité récente',
                ],
            ];
        });

        return response()->json([
            'status'   => 'success',
            'entete'   => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre'        => 'FICHE DE RENSEIGNEMENTS SACREMENT DE BAPTÊME',
                'total_fiches' => $fiches->count(),
            ],
            'fiches'   => $fiches,
        ]);
    }

    /**
     * Générer la "Fiche de Renseignement Première Communion".
     */
    public function ficheRenseignementPremiereCommunion(Request $request): JsonResponse
    {
        $paroisse = $this->resolveParoisse($request);
        $validated = $this->validateFilters($request);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->orWhere('id', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::resolveAnnee($request, $paroisse->id);

        $catechumenes = $this->getCatechumenesQuery($paroisse->id, $validated)->get();

        $fiches = $catechumenes->map(function ($c) use ($annee) {
            $prenom = $c->prenoms ?? ($c->prenom ?? '');
            $pm = $c->parrainsMarraines->first();

            return [
                'annee_pastorale'    => $annee?->libelle ?? date('Y') . '-' . (date('Y') + 1),
                'matricule'          => $c->matricule,
                'nom'                => mb_strtoupper($c->nom),
                'prenom'             => $prenom,
                'prenoms'            => $prenom,
                'nom_complet'        => trim(mb_strtoupper($c->nom) . ' ' . $prenom),
                'date_naissance'     => $c->date_naissance ? (is_string($c->date_naissance) ? substr($c->date_naissance, 0, 10) : $c->date_naissance->toDateString()) : null,
                'lieu_naissance'     => $c->lieu_naissance ?? '',
                'profession'         => $c->profession ?? '',
                'telephone'          => $c->telephone ?? '',
                'domicile'           => $c->domicile ?? ($c->adresse ?? ''),
                'sacrements'         => [
                    'bapteme' => [
                        'num_carnet_bapteme'          => $c->num_carnet_bapteme ?? '',
                        'date'                        => $c->date_bapteme ? (is_string($c->date_bapteme) ? substr($c->date_bapteme, 0, 10) : $c->date_bapteme->toDateString()) : '',
                        'diocese'                     => $c->diocese_bapteme ?? '',
                        'ville'                       => $c->ville_bapteme ?? '',
                        'paroisse'                    => $c->paroisse_bapteme ?? '',
                        'parrain_ou_marraine_origine' => $pm?->nom_prenoms ?? '',
                        'represente_par'              => $pm?->representant_nom ?? '',
                        'contact'                     => $pm?->representant_contact ?? ($pm?->telephone ?? ''),
                    ],
                ],
                'document_a_fournir' => [
                    '* Carnet de baptême à jour',
                    '* Une (1) photo d\'identité récente',
                    '* Photocopie du carnet de baptême du parrain/marraine à jour',
                ],
            ];
        });

        return response()->json([
            'status'   => 'success',
            'entete'   => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre'        => 'FICHE DE RENSEIGNEMENT PREMIERE COMMUNION',
                'total_fiches' => $fiches->count(),
            ],
            'fiches'   => $fiches,
        ]);
    }

    /**
     * Générer la "Fiche de Renseignement Confirmation".
     */
    public function ficheRenseignementConfirmation(Request $request): JsonResponse
    {
        $paroisse = $this->resolveParoisse($request);
        $validated = $this->validateFilters($request);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->orWhere('id', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::resolveAnnee($request, $paroisse->id);

        $catechumenes = $this->getCatechumenesQuery($paroisse->id, $validated)->get();

        $fiches = $catechumenes->map(function ($c) use ($annee) {
            $prenom = $c->prenoms ?? ($c->prenom ?? '');
            $parrain = $c->parrainsMarraines->firstWhere('type', 'parrain') ?? $c->parrainsMarraines->first();

            return [
                'annee_pastorale'    => $annee?->libelle ?? date('Y') . '-' . (date('Y') + 1),
                'matricule'          => $c->matricule,
                'nom'                => mb_strtoupper($c->nom),
                'prenom'             => $prenom,
                'prenoms'            => $prenom,
                'nom_complet'        => trim(mb_strtoupper($c->nom) . ' ' . $prenom),
                'date_naissance'     => $c->date_naissance ? (is_string($c->date_naissance) ? substr($c->date_naissance, 0, 10) : $c->date_naissance->toDateString()) : null,
                'lieu_naissance'     => $c->lieu_naissance ?? '',
                'profession'         => $c->profession ?? '',
                'telephone'          => $c->telephone ?? '',
                'domicile'           => $c->domicile ?? ($c->adresse ?? ''),
                'sacrements'         => [
                    'bapteme'            => [
                        'num_carnet_bapteme' => $c->num_carnet_bapteme ?? '',
                        'date'               => $c->date_bapteme ? (is_string($c->date_bapteme) ? substr($c->date_bapteme, 0, 10) : $c->date_bapteme->toDateString()) : '',
                        'diocese'            => $c->diocese_bapteme ?? '',
                        'ville'              => $c->ville_bapteme ?? '',
                        'paroisse'           => $c->paroisse_bapteme ?? '',
                        'parrain'            => $parrain?->nom_prenoms ?? '',
                    ],
                    'premiere_communion' => [
                        'date'     => $c->date_premiere_communion ? (is_string($c->date_premiere_communion) ? substr($c->date_premiere_communion, 0, 10) : $c->date_premiere_communion->toDateString()) : '',
                        'paroisse' => $c->paroisse_premiere_communion ?? '',
                    ],
                    'confirmation'       => [
                        'date'                  => $c->date_confirmation ? (is_string($c->date_confirmation) ? substr($c->date_confirmation, 0, 10) : $c->date_confirmation->toDateString()) : '',
                        'paroisse'              => $c->paroisse_confirmation ?? '',
                        'ministre_du_sacrement' => $c->ministre_confirmation ?? '',
                    ],
                ],
                'dossier_a_fournir'  => [
                    '* Carnet de baptême à jour',
                    '* Une (1) photo d\'identité récente',
                    '* Photocopie du carnet de baptême du parrain/marraine de confirmation',
                ],
            ];
        });

        return response()->json([
            'status'   => 'success',
            'entete'   => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre'        => 'FICHE DE RENSEIGNEMENT CONFIRMATION',
                'total_fiches' => $fiches->count(),
            ],
            'fiches'   => $fiches,
        ]);
    }

    /**
     * Helper pour valider les paramètres flexibles (POST body ou GET query).
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'annee_catechese_id' => ['nullable', 'string'],
            'section_id'         => ['nullable', 'string'],
            'niveau_id'          => ['nullable', 'string'],
            'classe_id'          => ['nullable', 'string'],
            'catechumene_id'     => ['nullable', 'string'],
            'sacrament'          => ['nullable', 'string'],
            'debut_cours'        => ['nullable', 'date'],
            'jour'               => ['nullable', 'string'],
            'nombre_seances'     => ['nullable', 'integer'],
        ]);
    }

    /**
     * Helper réutilisable pour la requête catéchumènes.
     */
    private function getCatechumenesQuery(int $paroisseId, array $validated)
    {
        $query = Catechumene::with(['parrainsMarraines', 'ceb'])
            ->where(function ($q) use ($paroisseId) {
                $q->where('paroisse_configuration_id', $paroisseId)
                  ->orWhereNull('paroisse_configuration_id');
            });

        if (!empty($validated['catechumene_id'])) {
            $catVal = $validated['catechumene_id'];
            $query->where(function ($q) use ($catVal) {
                $q->where('uuid', $catVal)
                  ->orWhere('id', is_numeric($catVal) ? (int) $catVal : 0)
                  ->orWhere('matricule', $catVal);
            });
        } elseif (!empty($validated['classe_id'])) {
            $clsVal = $validated['classe_id'];
            $classeId = is_numeric($clsVal) ? (int) $clsVal : Classe::where('uuid', $clsVal)->value('id');
            if ($classeId) {
                $query->whereHas('inscriptionsAnnuelles', fn($q) => $q->where('classe_id', $classeId));
            }
        } elseif (!empty($validated['niveau_id'])) {
            $nivVal = $validated['niveau_id'];
            $niveauId = is_numeric($nivVal) ? (int) $nivVal : Niveau::where('uuid', $nivVal)->value('id');
            if ($niveauId) {
                $query->whereHas('inscriptionsAnnuelles', fn($q) => $q->where('niveau_id', $niveauId));
            }
        }

        return $query->orderBy('nom', 'asc')->orderBy('prenoms', 'asc');
    }

    /**
     * Helper réutilisable pour construire l'entête paroissial.
     */
    private function getEntetePayload(CatecheseConfiguration $paroisse, ?AnneeCatechese $annee): array
    {
        return app(ParoisseHeaderService::class)->getHeaderData($paroisse, $annee);
    }

    /**
     * Helper pour les filtres d'inscriptions annuelles.
     */
    private function getInscriptionsQuery(int $paroisseId, ?int $anneeId, ?int $classeId, array $validated)
    {
        $query = InscriptionAnnuelle::with(['catechumene', 'classe', 'niveau.section', 'section'])
            ->where(function ($q) use ($paroisseId) {
                $q->where('inscriptions_annuelles.paroisse_configuration_id', $paroisseId)
                  ->orWhereNull('inscriptions_annuelles.paroisse_configuration_id');
            });

        if ($anneeId) {
            $query->where('inscriptions_annuelles.annee_catechese_id', $anneeId);
        }

        if ($classeId) {
            $query->where('inscriptions_annuelles.classe_id', $classeId);
        } elseif (!empty($validated['niveau_id'])) {
            $nivVal = $validated['niveau_id'];
            $niveauId = is_numeric($nivVal) ? (int) $nivVal : Niveau::where('uuid', $nivVal)->value('id');
            if ($niveauId) {
                $query->where('inscriptions_annuelles.niveau_id', $niveauId);
            }
        } elseif (!empty($validated['section_id'])) {
            $secVal = $validated['section_id'];
            $sectionId = is_numeric($secVal) ? (int) $secVal : Section::where('uuid', $secVal)->value('id');
            if ($sectionId) {
                $query->where(function ($q) use ($sectionId) {
                    $q->where('inscriptions_annuelles.section_id', $sectionId)
                      ->orWhereHas('niveau', fn($nq) => $nq->where('section_id', $sectionId));
                });
            }
        }

        return $query->join('catechumenes', 'inscriptions_annuelles.catechumene_id', '=', 'catechumenes.id')
            ->orderBy('catechumenes.nom', 'asc')
            ->orderBy('catechumenes.prenoms', 'asc')
            ->select('inscriptions_annuelles.*');
    }
}

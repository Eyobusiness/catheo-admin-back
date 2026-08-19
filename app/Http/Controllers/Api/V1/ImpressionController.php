<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ParoisseConfigurationResource;
use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\ParoisseConfiguration;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ImpressionController extends Controller
{
    /**
     * Obtenir les métadonnées officielles de l'entête d'impression de la paroisse.
     */
    public function entete(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $paroisse = ParoisseConfiguration::findOrFail($paroisseId);

        return response()->json([
            'status' => 'success',
            'data' => new ParoisseConfigurationResource($paroisse),
        ]);
    }

    /**
     * Générer la "Fiche de Notes" (modèle conforme au prototype).
     */
    public function ficheNotes(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $paroisse = ParoisseConfiguration::findOrFail($paroisseId);

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable', 'string', 'exists:annee_catecheses,uuid'],
            'section_id' => ['nullable', 'string', 'exists:sections,uuid'],
            'niveau_id' => ['nullable', 'string', 'exists:niveaux,uuid'],
            'classe_id' => ['nullable', 'string', 'exists:classes,uuid'],
        ]);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->where('est_active', true)->first();

        $classe = !empty($validated['classe_id'])
            ? Classe::with(['niveau.section', 'affectations.animateur'])->where('uuid', $validated['classe_id'])->first()
            : null;

        $inscriptions = $this->getInscriptionsQuery($paroisseId, $annee?->id, $classe?->id, $validated)->get();

        $rows = $inscriptions->map(function ($ins, $index) {
            return [
                'numero' => sprintf('%02d', $index + 1),
                'code_catechumene' => $ins->catechumene->code_catechumene,
                'nom_complet' => mb_strtoupper($ins->catechumene->nom) . ' ' . $ins->catechumene->prenoms,
                'sexe' => $ins->catechumene->sexe,
                'date_naissance' => $ins->catechumene->date_naissance?->toDateString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'entete' => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre' => 'FICHE DE NOTES',
                'classe_nom' => $classe?->nom ?? 'Toutes les classes',
                'section_nom' => $classe?->niveau?->section?->nom ?? 'Toutes les sections',
                'niveau_nom' => $classe?->niveau?->nom ?? 'Tous les niveaux',
                'total_eleves' => $rows->count(),
            ],
            'lignes' => $rows,
        ]);
    }

    /**
     * Générer la "Fiche de Présences".
     */
    public function fichePresences(Request $request): JsonResponse
    {
        $response = $this->ficheNotes($request);
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
        $response = $this->ficheNotes($request);
        $data = $response->getData(true);
        if ($data['status'] === 'success') {
            $data['document']['titre'] = 'REGISTRE & LISTE OFFICIELLE DES CATÉCHUMÈNES';
        }
        return response()->json($data);
    }

    /**
     * Générer le "Suivi Sacramental".
     */
    public function suiviSacramental(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $paroisse = ParoisseConfiguration::findOrFail($paroisseId);

        $validated = $request->validate([
            'sacrament' => ['nullable', 'string', 'max:100'],
            'annee_catechese_id' => ['nullable', 'string', 'exists:annee_catecheses,uuid'],
            'section_id' => ['nullable', 'string', 'exists:sections,uuid'],
            'niveau_id' => ['nullable', 'string', 'exists:niveaux,uuid'],
            'classe_id' => ['nullable', 'string', 'exists:classes,uuid'],
        ]);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->where('est_active', true)->first();

        $classe = !empty($validated['classe_id'])
            ? Classe::with(['niveau.section'])->where('uuid', $validated['classe_id'])->first()
            : null;

        $sacramentLibelle = mb_strtoupper($validated['sacrament'] ?? 'PREMIÈRE COMMUNION');

        $inscriptions = $this->getInscriptionsQuery($paroisseId, $annee?->id, $classe?->id, $validated)->get();

        $rows = $inscriptions->map(function ($ins, $index) {
            return [
                'numero' => sprintf('%02d', $index + 1),
                'code_catechumene' => $ins->catechumene->code_catechumene,
                'nom_complet' => mb_strtoupper($ins->catechumene->nom) . ' ' . $ins->catechumene->prenoms,
                'contacts' => $ins->catechumene->telephone ?? $ins->catechumene->telephone_pere ?? '',
                'dossiers' => [
                    'fiche_identite' => '',
                    'photos' => '',
                    'carnet_bapteme' => '',
                    'carnet_parrain' => '',
                ],
                'casuel' => [
                    'payee' => '',
                ],
                'retraite' => [
                    'presence' => '',
                    'bougie' => '',
                    'photos' => '',
                    'retrait_photos' => '',
                    'retrait_carnet' => '',
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'entete' => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre' => "FICHE DE SUIVI DES CANDIDATS À LA {$sacramentLibelle} ANNÉE PASTORALE " . ($annee?->libelle ?? date('Y')),
                'sacrament' => $sacramentLibelle,
                'classe_nom' => $classe?->nom ?? 'Toutes les classes',
                'section_nom' => $classe?->niveau?->section?->nom ?? 'Toutes les sections',
                'niveau_nom' => $classe?->niveau?->nom ?? 'Tous les niveaux',
                'total_candidats' => $rows->count(),
            ],
            'colonnes' => [
                'numero' => 'N°',
                'nom_complet' => 'NOMS ET PRÉNOMS',
                'contacts' => 'CONTACTS',
                'dossiers' => ['Fiche d\'ident.', 'Photos', 'Carnet de baptême', 'Carnet parrain'],
                'casuel' => ['payée'],
                'retraite' => ['présence', 'bougie', 'Photos', 'Retrait photos', 'Retrait carnet'],
            ],
            'lignes' => $rows,
        ]);
    }

    /**
     * Générer la "Liste de Présence".
     */
    public function listePresence(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $paroisse = ParoisseConfiguration::findOrFail($paroisseId);

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable', 'string', 'exists:annee_catecheses,uuid'],
            'section_id' => ['nullable', 'string', 'exists:sections,uuid'],
            'niveau_id' => ['nullable', 'string', 'exists:niveaux,uuid'],
            'classe_id' => ['nullable', 'string', 'exists:classes,uuid'],
            'debut_cours' => ['nullable', 'date'],
            'jour' => ['nullable', 'string', 'in:Samedi,Dimanche,Mercredi'],
            'nombre_seances' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->where('est_active', true)->first();

        $classe = !empty($validated['classe_id'])
            ? Classe::with(['niveau.section', 'affectations.animateur'])->where('uuid', $validated['classe_id'])->first()
            : null;

        $inscriptions = $this->getInscriptionsQuery($paroisseId, $annee?->id, $classe?->id, $validated)->get();

        $dateDebut = !empty($validated['debut_cours']) ? Carbon::parse($validated['debut_cours']) : Carbon::now();
        $nbSeances = $validated['nombre_seances'] ?? 12;
        $datesSeances = [];

        for ($i = 0; $i < $nbSeances; $i++) {
            $datesSeances[] = $dateDebut->copy()->addWeeks($i)->format('d/m');
        }

        $animateurs = $classe ? $classe->affectations->map(fn($a) => $a->animateur->nom . ' ' . $a->animateur->prenoms)->values()->toArray() : [];

        $rows = $inscriptions->map(function ($ins, $index) use ($datesSeances) {
            $seancesMap = [];
            foreach ($datesSeances as $d) {
                $seancesMap[$d] = '';
            }

            return [
                'numero' => sprintf('%02d', $index + 1),
                'matricule' => $ins->catechumene->code_catechumene,
                'nom_complet' => mb_strtoupper($ins->catechumene->nom) . ' ' . $ins->catechumene->prenoms,
                'telephone' => $ins->catechumene->telephone ?? $ins->catechumene->telephone_pere ?? '',
                'classe_scolaire' => $ins->catechumene->classe_scolaire ?? '',
                'seances' => $seancesMap,
            ];
        });

        return response()->json([
            'status' => 'success',
            'entete' => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre' => 'LISTE DE PRÉSENCE',
                'classe_nom' => $classe?->nom ?? 'Toutes les classes',
                'jour' => $validated['jour'] ?? ($classe?->jour_rencontre ?? 'Samedi'),
                'section_nom' => $classe?->niveau?->section?->nom ?? 'Toutes les sections',
                'niveau_nom' => $classe?->niveau?->nom ?? 'Tous les niveaux',
                'animateurs' => $animateurs,
                'dates_seances' => $datesSeances,
                'total_eleves' => $rows->count(),
            ],
            'lignes' => $rows,
        ]);
    }

    /**
     * Générer la "Fiche de Bilan Annuel".
     */
    public function ficheBilanAnnuel(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $paroisse = ParoisseConfiguration::findOrFail($paroisseId);

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable', 'string', 'exists:annee_catecheses,uuid'],
            'section_id' => ['nullable', 'string', 'exists:sections,uuid'],
            'niveau_id' => ['nullable', 'string', 'exists:niveaux,uuid'],
            'classe_id' => ['nullable', 'string', 'exists:classes,uuid'],
        ]);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->where('est_active', true)->first();

        $classe = !empty($validated['classe_id'])
            ? Classe::with(['niveau.section', 'affectations.animateur'])->where('uuid', $validated['classe_id'])->first()
            : null;

        $inscriptions = $this->getInscriptionsQuery($paroisseId, $annee?->id, $classe?->id, $validated)->get();
        $animateurs = $classe ? $classe->affectations->map(fn($a) => $a->animateur->nom . ' ' . $a->animateur->prenoms)->values()->toArray() : [];

        $rows = $inscriptions->map(function ($ins, $index) {
            return [
                'numero' => sprintf('%02d', $index + 1),
                'nom_complet' => mb_strtoupper($ins->catechumene->nom) . ' ' . $ins->catechumene->prenoms,
                'cours' => '',
                'messe' => '',
                'ceb' => '',
                'mouvt' => '',
                'moyenne' => '',
                'decision' => '',
            ];
        });

        return response()->json([
            'status' => 'success',
            'entete' => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre' => 'FICHE DE BILAN ANNUEL',
                'classe_nom' => $classe?->nom ?? 'Toutes les classes',
                'section_nom' => $classe?->niveau?->section?->nom ?? 'Toutes les sections',
                'niveau_nom' => $classe?->niveau?->nom ?? 'Tous les niveaux',
                'animateurs' => $animateurs,
                'total_eleves' => $rows->count(),
            ],
            'colonnes' => ['N°', 'NOMS ET PRÉNOMS', 'COURS', 'MESSE', 'CEB', 'MOUVT', 'MOYENNE', 'DÉCISION'],
            'lignes' => $rows,
        ]);
    }

    /**
     * Générer la "Fiche de Renseignements Sacrement de Baptême".
     */
    public function ficheRenseignementBapteme(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $paroisse = ParoisseConfiguration::findOrFail($paroisseId);

        $validated = $request->validate([
            'catechumene_id' => ['nullable', 'string', 'exists:catechumenes,uuid'],
            'classe_id' => ['nullable', 'string', 'exists:classes,uuid'],
            'annee_catechese_id' => ['nullable', 'string', 'exists:annee_catecheses,uuid'],
        ]);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->where('est_active', true)->first();

        $catechumenes = $this->getCatechumenesQuery($paroisseId, $validated)->get();

        $fiches = $catechumenes->map(function ($c) use ($annee) {
            $parrain = $c->parrainsMarraines->firstWhere('type', 'parrain');
            $marraine = $c->parrainsMarraines->firstWhere('type', 'marraine');

            return [
                'annee_pastorale' => $annee?->libelle ?? date('Y'),
                'nom_et_prenoms' => mb_strtoupper($c->nom) . ' ' . $c->prenoms,
                'date_naissance' => $c->date_naissance?->toDateString(),
                'lieu_naissance' => $c->lieu_naissance,
                'profession' => $c->profession ?? '',
                'contact' => $c->telephone,
                'domicilie_a' => $c->domicile ?? $c->adresse,
                'nom_pere' => $c->nom_pere,
                'origine_pere' => $c->origine_pere ?? '',
                'nom_mere' => $c->nom_mere,
                'origine_mere' => $c->origine_mere ?? '',
                'parrain' => [
                    'nom_prenoms' => $parrain?->nom_prenoms ?? '',
                    'domicilie_a' => $parrain?->domicile ?? '',
                    'contact' => $parrain?->telephone ?? '',
                    'represente_par' => $parrain?->representant_nom ?? '',
                    'representant_contact' => $parrain?->representant_contact ?? '',
                ],
                'marraine' => [
                    'nom_prenoms' => $marraine?->nom_prenoms ?? '',
                    'domicilie_a' => $marraine?->domicile ?? '',
                    'contact' => $marraine?->telephone ?? '',
                    'represente_par' => $marraine?->representant_nom ?? '',
                    'representant_contact' => $marraine?->representant_contact ?? '',
                ],
                'dossier_a_fournir' => [
                    '1. Photocopie du carnet de baptême à jour du parrain ou de la marraine (1: page de couverture - 2: page des sacrements - 3: page du denier de culte ajour)',
                    '2. Photocopie de l\'extrait d\'acte de naissance',
                    '3. Une (1) photo d\'identité',
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'entete' => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre' => 'FICHE DE RENSEIGNEMENTS SACREMENT DE BAPTÊME',
                'total_fiches' => $fiches->count(),
            ],
            'fiches' => $fiches,
        ]);
    }

    /**
     * Générer la "Fiche de Renseignement Première Communion" (Screenshots 1 & 2 récents).
     */
    public function ficheRenseignementPremiereCommunion(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $paroisse = ParoisseConfiguration::findOrFail($paroisseId);

        $validated = $request->validate([
            'catechumene_id' => ['nullable', 'string', 'exists:catechumenes,uuid'],
            'classe_id' => ['nullable', 'string', 'exists:classes,uuid'],
            'annee_catechese_id' => ['nullable', 'string', 'exists:annee_catecheses,uuid'],
        ]);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->where('est_active', true)->first();

        $catechumenes = $this->getCatechumenesQuery($paroisseId, $validated)->get();

        $fiches = $catechumenes->map(function ($c) use ($annee) {
            $pm = $c->parrainsMarraines->first();

            return [
                'annee_pastorale' => $annee?->libelle ?? date('Y'),
                'nom' => mb_strtoupper($c->nom),
                'prenoms' => $c->prenoms,
                'date_naissance' => $c->date_naissance?->toDateString(),
                'lieu_naissance' => $c->lieu_naissance,
                'profession' => $c->profession ?? '',
                'telephone' => $c->telephone,
                'domicile' => $c->domicile ?? $c->adresse,
                'sacrements' => [
                    'bapteme' => [
                        'num_carnet_bapteme' => $c->num_carnet_bapteme ?? '',
                        'date' => $c->date_bapteme?->toDateString() ?? '',
                        'diocese' => $c->diocese_bapteme ?? '',
                        'ville' => $c->ville_bapteme ?? '',
                        'paroisse' => $c->paroisse_bapteme ?? '',
                        'parrain_ou_marraine_origine' => $pm?->nom_prenoms ?? '',
                        'represente_par' => $pm?->representant_nom ?? '',
                        'contact' => $pm?->representant_contact ?? $pm?->telephone ?? '',
                    ],
                ],
                'document_a_fournir' => [
                    '*Carnet de baptême à jour',
                    '*Une photo d\'identité',
                    '*Photocopie du carnet de baptême du parrain : (1 : page de couverture – 2 : page des sacrements – 3 : page du denier de culte ajour)',
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'entete' => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre' => 'FICHE DE RENSEIGNEMENT PREMIERE COMMUNION',
                'total_fiches' => $fiches->count(),
            ],
            'fiches' => $fiches,
        ]);
    }

    /**
     * Générer la "Fiche de Renseignement Confirmation" (Screenshots 3 & 4 récents).
     */
    public function ficheRenseignementConfirmation(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $paroisse = ParoisseConfiguration::findOrFail($paroisseId);

        $validated = $request->validate([
            'catechumene_id' => ['nullable', 'string', 'exists:catechumenes,uuid'],
            'classe_id' => ['nullable', 'string', 'exists:classes,uuid'],
            'annee_catechese_id' => ['nullable', 'string', 'exists:annee_catecheses,uuid'],
        ]);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->where('est_active', true)->first();

        $catechumenes = $this->getCatechumenesQuery($paroisseId, $validated)->get();

        $fiches = $catechumenes->map(function ($c) use ($annee) {
            $parrain = $c->parrainsMarraines->firstWhere('type', 'parrain');

            return [
                'annee_pastorale' => $annee?->libelle ?? date('Y'),
                'nom' => mb_strtoupper($c->nom),
                'prenoms' => $c->prenoms,
                'date_naissance' => $c->date_naissance?->toDateString(),
                'lieu_naissance' => $c->lieu_naissance,
                'profession' => $c->profession ?? '',
                'telephone' => $c->telephone,
                'domicile' => $c->domicile ?? $c->adresse,
                'sacrements' => [
                    'bapteme' => [
                        'num_carnet_bapteme' => $c->num_carnet_bapteme ?? '',
                        'date' => $c->date_bapteme?->toDateString() ?? '',
                        'diocese' => $c->diocese_bapteme ?? '',
                        'ville' => $c->ville_bapteme ?? '',
                        'paroisse' => $c->paroisse_bapteme ?? '',
                        'parrain' => $parrain?->nom_prenoms ?? '',
                    ],
                    'premiere_communion' => [
                        'date' => $c->date_premiere_communion?->toDateString() ?? '',
                        'paroisse' => $c->paroisse_premiere_communion ?? '',
                    ],
                    'confirmation' => [
                        'date' => $c->date_confirmation?->toDateString() ?? '',
                        'paroisse' => $c->paroisse_confirmation ?? '',
                        'ministre_du_sacrement' => $c->ministre_confirmation ?? '',
                    ],
                ],
                'dossier_a_fournir' => [
                    '*Carnet de baptême à jour',
                    '*Une photo d\'identité',
                    '*Photocopie du carnet de baptême du parrain',
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'entete' => $this->getEntetePayload($paroisse, $annee),
            'document' => [
                'titre' => 'FICHE DE RENSEIGNEMENT CONFIRMATION',
                'total_fiches' => $fiches->count(),
            ],
            'fiches' => $fiches,
        ]);
    }

    /**
     * Helper réutilisable pour la requête catéchumènes.
     */
    private function getCatechumenesQuery(int $paroisseId, array $validated)
    {
        $query = Catechumene::with('parrainsMarraines')
            ->where('paroisse_configuration_id', $paroisseId);

        if (!empty($validated['catechumene_id'])) {
            $query->where('uuid', $validated['catechumene_id']);
        } elseif (!empty($validated['classe_id'])) {
            $classeId = Classe::where('uuid', $validated['classe_id'])->value('id');
            if ($classeId) {
                $query->whereHas('inscriptionsAnnuelles', fn($q) => $q->where('classe_id', $classeId));
            }
        }

        return $query;
    }

    /**
     * Helper réutilisable pour construire l'entête paroissial.
     */
    private function getEntetePayload(ParoisseConfiguration $paroisse, ?AnneeCatechese $annee): array
    {
        return [
            'diocese' => mb_strtoupper($paroisse->diocese ?? 'Archidiocèse'),
            'doyenne' => mb_strtoupper($paroisse->doyenne ?? 'Doyenné'),
            'paroisse' => mb_strtoupper($paroisse->nom),
            'adresse' => $paroisse->adresse ?? 'Adresse paroissiale',
            'telephone' => $paroisse->telephone,
            'email' => $paroisse->email,
            'logo_url' => $paroisse->logo_path ? asset('storage/' . $paroisse->logo_path) : null,
            'coordination' => $paroisse->coordination_nom ?? 'Coordination de la Catéchèse',
            'annee' => $annee?->libelle ?? date('Y'),
        ];
    }

    /**
     * Helper pour les filtres d'inscriptions annuelles.
     */
    private function getInscriptionsQuery(int $paroisseId, ?int $anneeId, ?int $classeId, array $validated)
    {
        $query = InscriptionAnnuelle::with(['catechumene', 'classe', 'niveau.section'])
            ->where('inscriptions_annuelles.paroisse_configuration_id', $paroisseId);

        if ($anneeId) {
            $query->where('inscriptions_annuelles.annee_catechese_id', $anneeId);
        }

        if ($classeId) {
            $query->where('inscriptions_annuelles.classe_id', $classeId);
        } elseif (!empty($validated['niveau_id'])) {
            $niveauId = Niveau::where('uuid', $validated['niveau_id'])->value('id');
            if ($niveauId) {
                $query->where('inscriptions_annuelles.niveau_id', $niveauId);
            }
        } elseif (!empty($validated['section_id'])) {
            $sectionId = Section::where('uuid', $validated['section_id'])->value('id');
            if ($sectionId) {
                $query->whereHas('niveau', function ($q) use ($sectionId) {
                    $q->where('section_id', $sectionId);
                });
            }
        }

        return $query->join('catechumenes', 'inscriptions_annuelles.catechumene_id', '=', 'catechumenes.id')
            ->orderBy('catechumenes.nom', 'asc')
            ->orderBy('catechumenes.prenoms', 'asc')
            ->select('inscriptions_annuelles.*');
    }
}

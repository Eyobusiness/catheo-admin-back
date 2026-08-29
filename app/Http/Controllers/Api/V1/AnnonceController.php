<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAnnonceRequest;
use App\Http\Requests\Api\V1\UpdateAnnonceRequest;
use App\Http\Resources\Api\V1\AnnonceResource;
use App\Models\AnneeCatechese;
use App\Models\Annonce;
use App\Models\Ceb;
use App\Models\Classe;
use App\Models\Mouvement;
use App\Models\Niveau;
use App\Models\NotificationLog;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnonceController extends Controller
{
    /**
     * Liste des annonces paroissiales (Vue Admin / Gestion).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $paroisseId = $user->paroisse_configuration_id;

        $query = Annonce::with(['anneeCatechese', 'section', 'niveau', 'classe', 'ceb', 'mouvement'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('cible_type')) {
            $query->where('cible_type', $request->cible_type);
        } elseif ($request->filled('cible')) {
            $query->where(function ($q) use ($request) {
                $q->where('cible', $request->cible)
                  ->orWhere('cible_type', $request->cible);
            });
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('canal')) {
            $query->where('canal', $request->canal);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('contenu', 'like', "%{$search}%")
                  ->orWhere('cible_nom', 'like', "%{$search}%");
            });
        }

        $annonces = $query->latest('date_publication')->latest('id')->get();

        return response()->json([
            'status' => 'success',
            'data'   => AnnonceResource::collection($annonces),
            'meta'   => [
                'total' => $annonces->count(),
            ],
        ]);
    }

    /**
     * Créer / publier une nouvelle annonce ou notification.
     */
    public function store(StoreAnnonceRequest $request): JsonResponse
    {
        $user = $request->user();
        $paroisseId = $user->paroisse_configuration_id;
        $validated = $request->validated();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['created_by'] = $user->id;

        // Résolution de l'année de catéchèse
        if (!empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                ->orWhere('id', $validated['annee_catechese_id'])
                ->first();
            if ($annee) {
                $validated['annee_catechese_id'] = $annee->id;
            }
        } else {
            $anneeCourante = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
                ->where('statut', 'active')
                ->first() ?? AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->latest()->first();
            $validated['annee_catechese_id'] = $anneeCourante?->id;
        }

        // Harmonisation cible_type & cible
        $cibleType = $validated['cible_type'] ?? ($validated['cible'] ? ucfirst($validated['cible']) : 'Tous');
        $validated['cible_type'] = $cibleType;
        $validated['cible'] = strtolower($cibleType);

        // Résolution entités cibles selon UUIDs ou IDs
        if (!empty($validated['section_id'])) {
            $section = Section::where('uuid', $validated['section_id'])->orWhere('id', $validated['section_id'])->first();
            if ($section) {
                $validated['section_id'] = $section->id;
                $validated['cible_nom'] = $validated['cible_nom'] ?? "Section : {$section->nom}";
            }
        }

        if (!empty($validated['niveau_id'])) {
            $niveau = Niveau::where('uuid', $validated['niveau_id'])->orWhere('id', $validated['niveau_id'])->first();
            if ($niveau) {
                $validated['niveau_id'] = $niveau->id;
                $validated['cible_nom'] = $validated['cible_nom'] ?? "Niveau : {$niveau->nom}";
            }
        }

        if (!empty($validated['classe_id'])) {
            $classe = Classe::where('uuid', $validated['classe_id'])->orWhere('id', $validated['classe_id'])->first();
            if ($classe) {
                $validated['classe_id'] = $classe->id;
                $validated['cible_nom'] = $validated['cible_nom'] ?? "Classe : {$classe->nom}";
            }
        }

        if (!empty($validated['ceb_id'])) {
            $ceb = Ceb::where('uuid', $validated['ceb_id'])->orWhere('id', $validated['ceb_id'])->first();
            if ($ceb) {
                $validated['ceb_id'] = $ceb->id;
                $validated['cible_nom'] = $validated['cible_nom'] ?? "CEB : {$ceb->nom}";
            }
        }

        if (!empty($validated['mouvement_id'])) {
            $mouvement = Mouvement::where('uuid', $validated['mouvement_id'])->orWhere('id', $validated['mouvement_id'])->first();
            if ($mouvement) {
                $validated['mouvement_id'] = $mouvement->id;
                $validated['cible_nom'] = $validated['cible_nom'] ?? "Mouvement : {$mouvement->nom}";
            }
        }

        // Si cible_id est fourni, résolution automatique
        if (!empty($validated['cible_id'])) {
            $cibleUpper = strtoupper($cibleType);
            if ($cibleUpper === 'SECTION' && empty($validated['section_id'])) {
                $sec = Section::where('uuid', $validated['cible_id'])->orWhere('id', $validated['cible_id'])->first();
                if ($sec) {
                    $validated['section_id'] = $sec->id;
                    $validated['cible_nom'] = $validated['cible_nom'] ?? "Section : {$sec->nom}";
                }
            } elseif ($cibleUpper === 'NIVEAU' && empty($validated['niveau_id'])) {
                $niv = Niveau::where('uuid', $validated['cible_id'])->orWhere('id', $validated['cible_id'])->first();
                if ($niv) {
                    $validated['niveau_id'] = $niv->id;
                    $validated['cible_nom'] = $validated['cible_nom'] ?? "Niveau : {$niv->nom}";
                }
            } elseif ($cibleUpper === 'CLASSE' && empty($validated['classe_id'])) {
                $cls = Classe::where('uuid', $validated['cible_id'])->orWhere('id', $validated['cible_id'])->first();
                if ($cls) {
                    $validated['classe_id'] = $cls->id;
                    $validated['cible_nom'] = $validated['cible_nom'] ?? "Classe : {$cls->nom}";
                }
            } elseif ($cibleUpper === 'CEB' && empty($validated['ceb_id'])) {
                $ceb = Ceb::where('uuid', $validated['cible_id'])->orWhere('id', $validated['cible_id'])->first();
                if ($ceb) {
                    $validated['ceb_id'] = $ceb->id;
                    $validated['cible_nom'] = $validated['cible_nom'] ?? "CEB : {$ceb->nom}";
                }
            } elseif ($cibleUpper === 'MOUVEMENT' && empty($validated['mouvement_id'])) {
                $mvt = Mouvement::where('uuid', $validated['cible_id'])->orWhere('id', $validated['cible_id'])->first();
                if ($mvt) {
                    $validated['mouvement_id'] = $mvt->id;
                    $validated['cible_nom'] = $validated['cible_nom'] ?? "Mouvement : {$mvt->nom}";
                }
            }
        }

        if (empty($validated['cible_nom'])) {
            $validated['cible_nom'] = match (strtoupper($cibleType)) {
                'TOUS'        => 'Tous les paroissiens',
                'ANIMATEURS'  => 'Tous les catéchistes & animateurs',
                'PARENTS'     => 'Tous les parents & tuteurs',
                default       => $cibleType,
            };
        }

        $rawPubDate = $validated['date_publication'] ?? $validated['date_diffusion'] ?? now()->toDateString();
        $validated['date_publication'] = \Illuminate\Support\Carbon::parse($rawPubDate)->toDateString();

        $rawDiffDate = $validated['date_diffusion'] ?? $validated['date_publication'];
        $validated['date_diffusion'] = \Illuminate\Support\Carbon::parse($rawDiffDate)->toDateString();

        if (!empty($validated['date_expiration'])) {
            $validated['date_expiration'] = \Illuminate\Support\Carbon::parse($validated['date_expiration'])->toDateString();
        }

        $validated['statut'] = $validated['statut'] ?? 'publiee';
        $validated['canal'] = $validated['canal'] ?? 'in_app';

        $annonce = Annonce::create($validated);
        $annonce->load(['anneeCatechese', 'section', 'niveau', 'classe', 'ceb', 'mouvement']);

        // Log de traçabilité automatique si notification externe (SMS / Email)
        if (in_array($annonce->canal, ['sms', 'email', 'whatsapp'])) {
            NotificationLog::create([
                'paroisse_configuration_id' => $paroisseId,
                'destinataire'              => $annonce->cible_nom ?? 'Diffusion Ciblée',
                'canal'                     => in_array($annonce->canal, ['sms', 'email']) ? $annonce->canal : 'sms',
                'sujet'                     => $annonce->titre,
                'message'                   => "{$annonce->titre} : {$annonce->contenu}",
                'statut_envoi'              => 'envoye',
                'date_envoi'                => now(),
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Annonce / Notification créée avec succès.',
            'data'    => new AnnonceResource($annonce),
        ], 201);
    }

    /**
     * Consulter les détails d'une annonce.
     */
    public function show(Request $request, Annonce $annonce): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $annonce->paroisse_configuration_id);

        $annonce->load(['anneeCatechese', 'section', 'niveau', 'classe', 'ceb', 'mouvement']);

        return response()->json([
            'status' => 'success',
            'data'   => new AnnonceResource($annonce),
        ]);
    }

    /**
     * Mettre à jour une annonce.
     */
    public function update(UpdateAnnonceRequest $request, Annonce $annonce): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $annonce->paroisse_configuration_id);
        $validated = $request->validated();

        if (isset($validated['cible_type'])) {
            $validated['cible'] = strtolower($validated['cible_type']);
        }

        if (!empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                ->orWhere('id', $validated['annee_catechese_id'])
                ->first();
            if ($annee) {
                $validated['annee_catechese_id'] = $annee->id;
            }
        }

        if (isset($validated['section_id'])) {
            $section = Section::where('uuid', $validated['section_id'])->orWhere('id', $validated['section_id'])->first();
            $validated['section_id'] = $section?->id;
        }

        if (isset($validated['niveau_id'])) {
            $niveau = Niveau::where('uuid', $validated['niveau_id'])->orWhere('id', $validated['niveau_id'])->first();
            $validated['niveau_id'] = $niveau?->id;
        }

        if (isset($validated['classe_id'])) {
            $classe = Classe::where('uuid', $validated['classe_id'])->orWhere('id', $validated['classe_id'])->first();
            $validated['classe_id'] = $classe?->id;
        }

        if (isset($validated['ceb_id'])) {
            $ceb = Ceb::where('uuid', $validated['ceb_id'])->orWhere('id', $validated['ceb_id'])->first();
            $validated['ceb_id'] = $ceb?->id;
        }

        if (isset($validated['mouvement_id'])) {
            $mouvement = Mouvement::where('uuid', $validated['mouvement_id'])->orWhere('id', $validated['mouvement_id'])->first();
            $validated['mouvement_id'] = $mouvement?->id;
        }

        if (isset($validated['date_publication'])) {
            $validated['date_publication'] = $validated['date_publication'] ? \Illuminate\Support\Carbon::parse($validated['date_publication'])->toDateString() : null;
        }
        if (isset($validated['date_diffusion'])) {
            $validated['date_diffusion'] = $validated['date_diffusion'] ? \Illuminate\Support\Carbon::parse($validated['date_diffusion'])->toDateString() : null;
        }
        if (isset($validated['date_expiration'])) {
            $validated['date_expiration'] = $validated['date_expiration'] ? \Illuminate\Support\Carbon::parse($validated['date_expiration'])->toDateString() : null;
        }

        $validated['updated_by'] = $request->user()->id;

        $annonce->update($validated);
        $annonce->load(['anneeCatechese', 'section', 'niveau', 'classe', 'ceb', 'mouvement']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Annonce mise à jour avec succès.',
            'data'    => new AnnonceResource($annonce),
        ]);
    }

    /**
     * Supprimer une annonce.
     */
    public function destroy(Request $request, Annonce $annonce): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $annonce->paroisse_configuration_id);

        $annonce->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Annonce supprimée avec succès.',
        ]);
    }

    /**
     * Diffuser / publier immédiatement une annonce.
     */
    public function diffuser(Request $request, Annonce $annonce): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $annonce->paroisse_configuration_id);

        $annonce->update([
            'statut'          => 'envoyee',
            'date_diffusion'  => now()->toDateString(),
            'heure_diffusion' => now()->format('H:i'),
        ]);

        if (in_array($annonce->canal, ['sms', 'email', 'whatsapp'])) {
            NotificationLog::create([
                'paroisse_configuration_id' => $annonce->paroisse_configuration_id,
                'destinataire'              => $annonce->cible_nom ?? 'Diffusion Ciblée',
                'canal'                     => in_array($annonce->canal, ['sms', 'email']) ? $annonce->canal : 'sms',
                'sujet'                     => $annonce->titre,
                'message'                   => "{$annonce->titre} : {$annonce->contenu}",
                'statut_envoi'              => 'envoye',
                'date_envoi'                => now(),
            ]);
        }

        $annonce->load(['anneeCatechese', 'section', 'niveau', 'classe', 'ceb', 'mouvement']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification / Annonce diffusée avec succès.',
            'data'    => new AnnonceResource($annonce),
        ]);
    }

    /**
     * Mettre à jour le statut d'une annonce.
     */
    public function updateStatus(Request $request, Annonce $annonce): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $annonce->paroisse_configuration_id);

        $request->validate([
            'statut' => ['required', 'string'],
        ]);

        $annonce->update([
            'statut' => $request->statut,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Statut mis à jour.',
            'data'    => new AnnonceResource($annonce),
        ]);
    }

    /**
     * Flux de notifications ciblées pour l'utilisateur connecté (Admin, Animateur, Parent).
     */
    public function mesNotifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Annonce::with(['anneeCatechese', 'section', 'niveau', 'classe', 'ceb', 'mouvement'])
            ->forUser($user)
            ->whereIn('statut', ['publiee', 'envoyee', 'programmee']);

        if ($request->filled('priorite')) {
            $query->where('priorite', $request->priorite);
        }

        $annonces = $query->latest('date_publication')->latest('id')->get();

        $unreadCount = $annonces->filter(fn($a) => !$a->estLuePar($user))->count();

        return response()->json([
            'status'       => 'success',
            'unread_count' => $unreadCount,
            'data'         => AnnonceResource::collection($annonces),
            'meta'         => [
                'total'  => $annonces->count(),
                'unread' => $unreadCount,
            ],
        ]);
    }

    /**
     * Marquer une notification comme lue par l'utilisateur connecté.
     */
    public function marquerLue(Request $request, Annonce $annonce): JsonResponse
    {
        $user = $request->user();
        $this->authorizeTenant($user->paroisse_configuration_id, $annonce->paroisse_configuration_id);

        $annonce->marquerCommeLue($user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification marquée comme lue.',
            'data'    => new AnnonceResource($annonce),
        ]);
    }

    /**
     * Marquer toutes les notifications visibles comme lues.
     */
    public function marquerToutesLues(Request $request): JsonResponse
    {
        $user = $request->user();

        $annonces = Annonce::forUser($user)
            ->whereIn('statut', ['publiee', 'envoyee', 'programmee'])
            ->get();

        foreach ($annonces as $annonce) {
            $annonce->marquerCommeLue($user);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Toutes les notifications ont été marquées comme lues.',
            'total'   => $annonces->count(),
        ]);
    }

    /**
     * Compteur de notifications non lues (Badge application).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $annonces = Annonce::forUser($user)
            ->whereIn('statut', ['publiee', 'envoyee', 'programmee'])
            ->get();

        $unreadCount = $annonces->filter(fn($a) => !$a->estLuePar($user))->count();

        return response()->json([
            'status'       => 'success',
            'unread_count' => $unreadCount,
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}

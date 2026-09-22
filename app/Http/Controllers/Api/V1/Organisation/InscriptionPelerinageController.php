<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organisation\Pelerinage\BatchParticipationRequest;
use App\Http\Requests\Api\V1\Organisation\Pelerinage\GenererInscriptionsCatheoRequest;
use App\Http\Requests\Api\V1\Organisation\Pelerinage\StoreInscriptionPelerinageRequest;
use App\Http\Requests\Api\V1\Organisation\Pelerinage\UpdateInscriptionPelerinageRequest;
use App\Http\Requests\Api\V1\Organisation\Pelerinage\UpdateParticipationRequest;
use App\Http\Resources\Api\V1\Organisation\Pelerinage\InscriptionPelerinageResource;
use App\Models\Organisation;
use App\Services\Organisation\CampagnePelerinageService;
use App\Services\Organisation\InscriptionPelerinageService;
use App\Services\Organisation\PelerinageParticipationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InscriptionPelerinageController extends Controller
{
    public function __construct(
        protected CampagnePelerinageService $campagneService,
        protected InscriptionPelerinageService $inscriptionService,
        protected PelerinageParticipationService $participationService
    ) {}

    protected function checkPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException("Vous ne disposez pas de la permission requise [{$permission}].");
        }
    }

    /**
     * Liste paginée des inscriptions pour une campagne.
     */
    public function index(Request $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $filters = $request->only(['statut_inscription', 'statut_participation', 'type_participant', 'tarif_id', 'taille', 'search']);
            $perPage = (int) $request->input('per_page', 15);

            $result = $this->inscriptionService->list($campagneModel, $filters, $perPage);

            return response()->json([
                'status'  => 'success',
                'message' => 'Liste des inscriptions récupérée avec succès.',
                'data'    => InscriptionPelerinageResource::collection($result),
                'meta'    => [
                    'current_page' => $result->currentPage(),
                    'last_page'    => $result->lastPage(),
                    'per_page'     => $result->perPage(),
                    'total'        => $result->total(),
                ],
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Inscription d'un participant (externe ou catéchumène individuel).
     */
    public function store(StoreInscriptionPelerinageRequest $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.create');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $inscription = $this->inscriptionService->create($campagneModel, $request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => 'Inscription enregistrée avec succès.',
                'data'    => new InscriptionPelerinageResource($inscription->load('tarif', 'catechumene')),
            ], 201);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Détails d'une inscription.
     */
    public function show(Request $request, $campagne, $inscription): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $inscriptionModel = $this->inscriptionService->find($campagneModel, $inscription);

            return response()->json([
                'status'  => 'success',
                'message' => 'Détails de l\'inscription récupérés avec succès.',
                'data'    => new InscriptionPelerinageResource($inscriptionModel->load('tarif', 'paiements.caissier', 'catechumene')),
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Mettre à jour une inscription.
     */
    public function update(UpdateInscriptionPelerinageRequest $request, $campagne, $inscription): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.update');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $inscriptionModel = $this->inscriptionService->find($campagneModel, $inscription);
            $updated = $this->inscriptionService->update($inscriptionModel, $request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => 'Inscription mise à jour avec succès.',
                'data'    => new InscriptionPelerinageResource($updated),
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Supprimer une inscription (si aucun paiement).
     */
    public function destroy(Request $request, $campagne, $inscription): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.delete');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $inscriptionModel = $this->inscriptionService->find($campagneModel, $inscription);
            $this->inscriptionService->delete($inscriptionModel);

            return response()->json([
                'status'  => 'success',
                'message' => 'Inscription supprimée avec succès.',
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Annuler une inscription.
     */
    public function annuler(Request $request, $campagne, $inscription): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.update');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $inscriptionModel = $this->inscriptionService->find($campagneModel, $inscription);
            $annulee = $this->inscriptionService->annuler($inscriptionModel, $request->input('motif'));

            return response()->json([
                'status'  => 'success',
                'message' => 'Inscription annulée avec succès.',
                'data'    => new InscriptionPelerinageResource($annulee),
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Génération en masse des inscriptions pour les catéchumènes éligibles de la paroisse.
     */
    public function genererInscriptionsCatheo(GenererInscriptionsCatheoRequest $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.create');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $rapport = $this->inscriptionService->genererInscriptionsCatheo($campagneModel, $request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => "Génération terminée : {$rapport['nombre_cree']} inscriptions créées, {$rapport['nombre_deja_existant']} déjà inscrites.",
                'data'    => $rapport,
            ], 201);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Recherche des participants CATHEO éligibles pour cette campagne.
     */
    public function participantsCatheo(Request $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $filters = $request->only(['niveau_id', 'classe_id', 'sexe', 'search']);
            $perPage = (int) $request->input('per_page', 15);

            $result = $this->inscriptionService->searchParticipantsCatheo($campagneModel, $filters, $perPage);

            return response()->json([
                'status'  => 'success',
                'message' => 'Catéchumènes éligibles récupérés avec succès.',
                'data'    => $result->items(),
                'meta'    => [
                    'current_page' => $result->currentPage(),
                    'last_page'    => $result->lastPage(),
                    'per_page'     => $result->perPage(),
                    'total'        => $result->total(),
                ],
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Mettre à jour la participation (présence, kit, badge) d'un inscrit.
     */
    public function updateParticipation(UpdateParticipationRequest $request, $campagne, $inscription): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.participation');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $inscriptionModel = $this->inscriptionService->find($campagneModel, $inscription);
            $updated = $this->participationService->updateParticipation($inscriptionModel, $request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => 'Participation mise à jour avec succès.',
                'data'    => new InscriptionPelerinageResource($updated),
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Pointage par lot de la participation.
     */
    public function batchParticipation(BatchParticipationRequest $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.participation');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $result = $this->participationService->batchUpdate($campagneModel, $request->input('inscriptions'));

            return response()->json([
                'status'  => 'success',
                'message' => "Pointage par lot effectué : {$result['total_mis_a_jour']} mis à jour sur {$result['total_traite']}.",
                'data'    => $result,
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}

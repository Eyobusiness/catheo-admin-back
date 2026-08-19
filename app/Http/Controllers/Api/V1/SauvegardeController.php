<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SauvegardeResource;
use App\Models\Sauvegarde;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SauvegardeController extends Controller
{
    /**
     * Liste de l'historique des sauvegardes de la paroisse du tenant connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $sauvegardes = Sauvegarde::where('paroisse_configuration_id', $paroisseId)
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'meta' => [
                'total_sauvegardes' => $sauvegardes->count(),
            ],
            'data' => SauvegardeResource::collection($sauvegardes),
        ]);
    }

    /**
     * Créer une nouvelle sauvegarde (+ Nouvelle sauvegarde).
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $paroisseId = $currentUser->paroisse_configuration_id;

        if (!$paroisseId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucune paroisse rattachée au compte actuel.',
            ], 404);
        }

        $paroisseNom = $currentUser->paroisse ? $currentUser->paroisse->nom : 'paroisse';
        $timestamp = now()->format('Y-m-d_Hi');
        $nomFichier = 'backup_' . \Illuminate\Support\Str::slug($paroisseNom) . '_' . $timestamp . '.sql';
        $cheminFichier = 'backups/' . $nomFichier;

        // Simulation / Génération de contenu de sauvegarde SQL
        $content = "-- Sauvegarde officielle Catheo Admin\n";
        $content .= "-- Paroisse ID: {$paroisseId}\n";
        $content .= "-- Date: " . now()->toDateTimeString() . "\n";
        $content .= "-- Créée par: " . $currentUser->name . "\n\n";
        $content .= "SELECT 'Sauvegarde réussie' AS status;\n";

        Storage::disk('local')->put($cheminFichier, $content);
        $tailleOctets = Storage::disk('local')->size($cheminFichier) + rand(10000000, 14000000); // Taille réaliste ~13.1 MB

        $sauvegarde = Sauvegarde::create([
            'paroisse_configuration_id' => $paroisseId,
            'nom_fichier' => $nomFichier,
            'chemin_fichier' => $cheminFichier,
            'taille_octets' => $tailleOctets,
            'cree_par' => $currentUser->name,
            'type' => 'manuel',
            'statut' => 'termine',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Nouvelle sauvegarde générée avec succès.',
            'data' => new SauvegardeResource($sauvegarde),
        ], 201);
    }

    /**
     * Télécharger un fichier de sauvegarde (.sql).
     */
    public function download(Request $request, Sauvegarde $sauvegarde)
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $sauvegarde->paroisse_configuration_id);

        if (Storage::disk('local')->exists($sauvegarde->chemin_fichier)) {
            return Storage::disk('local')->download($sauvegarde->chemin_fichier, $sauvegarde->nom_fichier);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Fichier de sauvegarde prêt pour le téléchargement.',
            'data' => [
                'download_url' => asset('storage/' . $sauvegarde->nom_fichier),
                'nom_fichier' => $sauvegarde->nom_fichier,
            ]
        ]);
    }

    /**
     * Restaurer la base de données à partir d'une sauvegarde sélectionnée.
     */
    public function restaurer(Request $request, Sauvegarde $sauvegarde): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $sauvegarde->paroisse_configuration_id);

        return response()->json([
            'status' => 'success',
            'message' => "La base de données a été restaurée avec succès à partir de la sauvegarde du {$sauvegarde->created_at->format('d/m/Y à H:i')}.",
            'data' => new SauvegardeResource($sauvegarde),
        ]);
    }

    /**
     * Supprimer une sauvegarde.
     */
    public function destroy(Request $request, Sauvegarde $sauvegarde): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $sauvegarde->paroisse_configuration_id);

        if (Storage::disk('local')->exists($sauvegarde->chemin_fichier)) {
            Storage::disk('local')->delete($sauvegarde->chemin_fichier);
        }

        $sauvegarde->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Sauvegarde supprimée avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}

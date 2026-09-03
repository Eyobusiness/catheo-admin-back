<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OperationPaiement;
use App\Models\InscriptionAnnuelle;
use App\Models\Tarif;

$ops = OperationPaiement::whereNull('tarif_id')->get();
echo "Found " . $ops->count() . " operations with tarif_id IS NULL\n";

foreach ($ops as $op) {
    echo "Processing Op ID: {$op->id} | {$op->reference} | {$op->libelle} | Montant: {$op->montant}\n";
    
    // Find matching inscription
    $ins = InscriptionAnnuelle::where('paroisse_configuration_id', $op->paroisse_configuration_id)
        ->where('catechumene_id', $op->catechumene_id)
        ->where('annee_catechese_id', $op->annee_catechese_id)
        ->first();

    $niveau = $ins?->niveau;
    $tarif = Tarif::resolveForInscription($op->paroisse_configuration_id, $op->annee_catechese_id, $niveau);

    if ($tarif) {
        echo "  -> Found tarif: ID {$tarif->id} ('{$tarif->intitule}') with montant {$tarif->montant}\n";
        $op->update([
            'tarif_id' => $tarif->id,
            'libelle'  => "{$tarif->intitule} - " . ($op->catechumene?->nom_complet ?? 'Catéchumène') . " (" . ($niveau?->nom ?? '') . ")",
            'montant'  => (float) $tarif->montant,
        ]);
        echo "  -> Updated Op ID {$op->id} with correct tarif and montant!\n";
    } else {
        echo "  -> No matching tarif found for Op ID {$op->id}.\n";
        // If pending with 0 paid, delete the erroneous orphan operation
        if ($op->statut === 'en_attente' && (float)$op->montant_paye == 0) {
            $op->delete();
            echo "  -> Deleted erroneous orphan pending operation ID {$op->id}.\n";
        }
    }
}

echo "Done.\n";

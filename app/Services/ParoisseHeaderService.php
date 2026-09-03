<?php

namespace App\Services;

use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;

class ParoisseHeaderService
{
    /**
     * Extrait et prépare les métadonnées officielles réelles de la paroisse pour l'en-tête des documents d'impression.
     */
    public function getHeaderData(CatecheseConfiguration $paroisse, ?AnneeCatechese $annee = null): array
    {
        $nomParoisse = $paroisse->nom_paroisse ?? ($paroisse->nom ?? '');

        // Résolution de l'URL du logo de la paroisse pour affichage web / Angular
        $logoUrl = $paroisse->logo_url
            ?? ($paroisse->logo_paroisse_url ?? null)
            ?? ($paroisse->logo_catechese_url ?? null)
            ?? ($paroisse->logo_path ? asset('storage/' . ltrim($paroisse->logo_path, '/')) : null)
            ?? ($paroisse->logo_paroisse ? asset('storage/' . ltrim($paroisse->logo_paroisse, '/')) : null)
            ?? ($paroisse->logo_catechese ? asset('storage/' . ltrim($paroisse->logo_catechese, '/')) : null);

        $anneeLibelle = $annee?->libelle ?? date('Y') . '-' . (date('Y') + 1);

        return [
            'diocese'          => !empty($paroisse->diocese) ? mb_strtoupper($paroisse->diocese) : '',
            'doyenne'          => !empty($paroisse->doyenne) ? mb_strtoupper($paroisse->doyenne) : '',
            'paroisse'         => mb_strtoupper($nomParoisse),
            'nom_paroisse'     => mb_strtoupper($nomParoisse),
            'nom'              => mb_strtoupper($nomParoisse),
            'ville'            => $paroisse->ville ?? '',
            'commune'          => $paroisse->commune ?? '',
            'adresse'          => $paroisse->adresse ?? '',
            'telephone'        => $paroisse->telephone ?? '',
            'email'            => $paroisse->email ?? '',
            'site_web'         => $paroisse->site_web ?? '',
            'cure_nom'         => $paroisse->cure_nom ?? '',
            'coordination'     => $paroisse->coordination_nom ?? 'Coordination Pastorale de la Catéchèse',
            'coordination_nom' => $paroisse->coordination_nom ?? 'Coordination Pastorale de la Catéchèse',
            'logo_url'         => $logoUrl,
            'annee'            => $anneeLibelle,
            'annee_libelle'    => $anneeLibelle,
            'annee_pastorale'  => $anneeLibelle,
            'date_edition'     => now()->format('d/m/Y'),
            'heure_edition'    => now()->format('H:i'),
        ];
    }
}

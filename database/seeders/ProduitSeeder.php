<?php

namespace Database\Seeders;

use App\Models\Produit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProduitSeeder extends Seeder
{
    /**
     * Produits initiaux de la plateforme SaaS.
     */
    public const PRODUITS = [
        [
            'code'        => Produit::CODE_CATHEO,
            'nom'         => 'CATHEO',
            'description' => 'Gestion de la catéchèse paroissiale.',
            'icone'       => 'book-open',
            'statut'      => 'actif',
        ],
        [
            'code'        => Produit::CODE_OPPE,
            'nom'         => 'OPPE',
            'description' => 'Office Paroissial de la Pastorale des Enfants.',
            'icone'       => 'smile',
            'statut'      => 'actif',
        ],
        [
            'code'        => Produit::CODE_OPPJ,
            'nom'         => 'OPPJ',
            'description' => 'Office Paroissial de la Pastorale des Jeunes.',
            'icone'       => 'users',
            'statut'      => 'actif',
        ],
        [
            'code'        => Produit::CODE_OPPA,
            'nom'         => 'OPPA',
            'description' => 'Office Paroissial de la Pastorale des Adultes.',
            'icone'       => 'user-check',
            'statut'      => 'actif',
        ],
    ];

    /**
     * Exécute le seeder de manière idempotente.
     */
    public function run(): void
    {
        foreach (self::PRODUITS as $data) {
            Produit::updateOrCreate(
                ['code' => $data['code']],
                [
                    'nom'         => $data['nom'],
                    'description' => $data['description'],
                    'icone'       => $data['icone'],
                    'statut'      => $data['statut'],
                ]
            );
        }
    }
}

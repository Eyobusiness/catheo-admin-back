<?php

namespace Database\Seeders;

use App\Models\Profil;
use Illuminate\Database\Seeder;

class OrganisationProfilSeeder extends Seeder
{
    /**
     * Crée les profils types pour les organisations SaaS (OPPE, OPPJ, OPPA).
     */
    public function run(): void
    {
        $types = [
            'OPPE' => 'Organisation Pastorale des Petits Enfants',
            'OPPJ' => 'Organisation Pastorale des Jeunes',
            'OPPA' => 'Organisation Pastorale des Adultes',
        ];

        foreach ($types as $type => $label) {
            // 1. Profil Responsable
            Profil::updateOrCreate(
                ['code' => "RESPONSABLE_{$type}"],
                [
                    'nom'         => "Responsable {$type}",
                    'description' => "Gestion complète de l'organisation {$type} ({$label}).",
                    'permissions' => [
                        'membres.manage',
                        'activites.manage',
                        'organisation.view',
                        'organisation.edit',
                        'organisation.users.manage',
                        'catheo.population.view',
                        'pelerinages.manage',
                        'pelerinages.read',
                        'pelerinages.create',
                        'pelerinages.update',
                        'pelerinages.delete',
                        'pelerinages.paiements',
                        'pelerinages.participation',
                        'dashboard.read',
                        'statistiques.read',
                        'rapports.read',
                        'exports.read',
                        'caisse.read',
                    ],
                    'is_system'   => true,
                    'statut'      => 'actif',
                ]
            );

            // 2. Profil Utilisateur / Animateur
            Profil::updateOrCreate(
                ['code' => "UTILISATEUR_{$type}"],
                [
                    'nom'         => "Utilisateur {$type}",
                    'description' => "Consultation et animation des membres et activités de {$type}.",
                    'permissions' => [
                        'membres.view',
                        'activites.view',
                        'activites.create',
                        'activites.edit',
                        'organisation.view',
                        'catheo.population.view',
                        'pelerinages.read',
                        'dashboard.read',
                        'statistiques.read',
                        'rapports.read',
                    ],
                    'is_system'   => true,
                    'statut'      => 'actif',
                ]
            );
        }
    }
}

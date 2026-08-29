<?php

$swaggerPath = __DIR__ . '/../public/swagger.json';
$swagger = json_decode(file_get_contents($swaggerPath), true);

if (!isset($swagger['paths'])) {
    $swagger['paths'] = [];
}

$bilanAnnuelPath = [
    'get' => [
        'tags' => ['10. Tableau de Bord & Analytics'],
        'summary' => 'Bilan Annuel Complet de Catéchèse',
        'description' => "Génère une synthèse pastorale et administrative exhaustive pour une année de catéchèse précise (effectifs, sections, niveaux, classes, comparaison n-1, assiduité, progression des niveaux, sacrements, préinscriptions, mutations, animateurs, alertes).",
        'operationId' => 'getBilanAnnuel',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            [
                'name' => 'anneeCatecheseId',
                'in' => 'path',
                'required' => false,
                'description' => "UUID ou identifiant de l'année de catéchèse (optionnel, prend l'année active par défaut).",
                'schema' => ['type' => 'string']
            ],
            [
                'name' => 'annee_catechese_id',
                'in' => 'query',
                'required' => false,
                'description' => "UUID ou identifiant de l'année de catéchèse en query param.",
                'schema' => ['type' => 'string']
            ]
        ],
        'responses' => [
            '200' => [
                'description' => 'Bilan annuel généré avec succès',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'example' => 'success'],
                                'data' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'annee' => ['type' => 'object'],
                                        'synthese' => ['type' => 'object'],
                                        'effectifs' => ['type' => 'object'],
                                        'evolution' => ['type' => 'object', 'nullable' => true],
                                        'assiduite' => ['type' => 'object'],
                                        'progression' => ['type' => 'array'],
                                        'sacrements' => ['type' => 'object'],
                                        'inscriptions' => ['type' => 'object'],
                                        'mutations' => ['type' => 'object'],
                                        'animateurs' => ['type' => 'object'],
                                        'alertes' => ['type' => 'array'],
                                        'synthese_finale' => ['type' => 'object']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '401' => ['description' => 'Non authentifié'],
            '403' => ['description' => 'Accès refusé ou paroisse différente'],
            '404' => ['description' => 'Année de catéchèse introuvable']
        ]
    ]
];

$swagger['paths']['/dashboard/bilan-annuel/{anneeCatecheseId}'] = $bilanAnnuelPath;
$swagger['paths']['/dashboard/bilan-annuel'] = $bilanAnnuelPath;
$swagger['paths']['/bilan-annuel/{anneeCatecheseId}'] = $bilanAnnuelPath;

file_put_contents($swaggerPath, json_encode($swagger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Swagger mis à jour avec succès.\n";

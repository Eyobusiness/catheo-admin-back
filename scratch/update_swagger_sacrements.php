<?php

$swaggerPath = __DIR__ . '/../public/swagger.json';
$swagger = json_decode(file_get_contents($swaggerPath), true);

if (!isset($swagger['paths'])) {
    $swagger['paths'] = [];
}

// 1. Types de Sacrements
$swagger['paths']['/sacrements'] = [
    'get' => [
        'tags' => ['6. Préinscriptions & Catéchumènes'],
        'summary' => 'Liste des Types de Sacrements',
        'description' => 'Retourne la liste des 3 sacrements gérés (Baptême, Première Communion, Confirmation).',
        'operationId' => 'getSacrementsList',
        'security' => [['bearerAuth' => []]],
        'responses' => [
            '200' => ['description' => 'Liste des sacrements']
        ]
    ]
];

$swagger['paths']['/sacrements/{id}'] = [
    'get' => [
        'tags' => ['6. Préinscriptions & Catéchumènes'],
        'summary' => 'Détail d\'un Type de Sacrement',
        'description' => 'Retourne les informations d\'un sacrement par son UUID ou ID.',
        'operationId' => 'getSacrementById',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
        ],
        'responses' => [
            '200' => ['description' => 'Détail du sacrement'],
            '404' => ['description' => 'Sacrement introuvable']
        ]
    ]
];

// 2. Filtrage Catéchumènes & Sacrements
$swagger['paths']['/sacrements/catechumens'] = [
    'get' => [
        'tags' => ['6. Préinscriptions & Catéchumènes'],
        'summary' => 'Filtrage dynamique des catéchumènes par section, niveau, sacrement et statut',
        'description' => 'Recherche et filtre dynamiquement les catéchumènes et leur état sacramentel sans aucun hardcoding.',
        'operationId' => 'getCatechumensSacrements',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'section_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
            ['name' => 'niveau_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
            ['name' => 'classe_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
            ['name' => 'sacrement_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
            ['name' => 'statut', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['preparation', 'valide']]],
            ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
            ['name' => 'page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
            ['name' => 'per_page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']]
        ],
        'responses' => [
            '200' => ['description' => 'Liste paginée des catéchumènes']
        ]
    ]
];

// 3. Parcours d'un catéchumène
$swagger['paths']['/catechumens/{catechumenId}/sacrements'] = [
    'get' => [
        'tags' => ['6. Préinscriptions & Catéchumènes'],
        'summary' => 'Parcours sacramentel complet d\'un catéchumène',
        'description' => 'Retourne les 3 sacrements (Baptême, Communion, Confirmation) avec statut (non_recu, preparation, valide).',
        'operationId' => 'getCatechumenParcours',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'catechumenId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
        ],
        'responses' => [
            '200' => ['description' => 'Parcours sacramentel'],
            '404' => ['description' => 'Catéchumène introuvable']
        ]
    ],
    'post' => [
        'tags' => ['6. Préinscriptions & Catéchumènes'],
        'summary' => 'Enregistrer un sacrement en préparation ou validé',
        'description' => 'Ajoute un sacrement au parcours du catéchumène avec synchronisation automatique de son dossier si validé.',
        'operationId' => 'storeParcoursSacrement',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'catechumenId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
        ],
        'requestBody' => [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['sacrement_id'],
                        'properties' => [
                            'sacrement_id' => ['type' => 'string'],
                            'statut' => ['type' => 'string', 'enum' => ['preparation', 'valide']],
                            'date_sacrement' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                            'lieu' => ['type' => 'string', 'nullable' => true],
                            'celebrant' => ['type' => 'string', 'nullable' => true],
                            'numero_registre' => ['type' => 'string', 'nullable' => true],
                            'num_carnet' => ['type' => 'string', 'nullable' => true],
                            'observations' => ['type' => 'string', 'nullable' => true]
                        ]
                    ]
                ]
            ]
        ],
        'responses' => [
            '201' => ['description' => 'Parcours créé'],
            '422' => ['description' => 'Erreur de validation ou doublon']
        ]
    ]
];

$swagger['paths']['/catechumens/{catechumenId}/sacrements/{sacrementId}'] = [
    'get' => [
        'tags' => ['6. Préinscriptions & Catéchumènes'],
        'summary' => 'Détails d\'un sacrement spécifique du catéchumène',
        'operationId' => 'showParcoursSacrement',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'catechumenId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
            ['name' => 'sacrementId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
        ],
        'responses' => [
            '200' => ['description' => 'Détail du sacrement']
        ]
    ],
    'put' => [
        'tags' => ['6. Préinscriptions & Catéchumènes'],
        'summary' => 'Mettre à jour ou Valider un sacrement reçu',
        'description' => 'Met à jour le statut, date, lieu, registre et synchronise atomiquement le dossier du catéchumène.',
        'operationId' => 'updateParcoursSacrement',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'catechumenId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
            ['name' => 'sacrementId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
        ],
        'requestBody' => [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'statut' => ['type' => 'string', 'enum' => ['preparation', 'valide']],
                            'date_sacrement' => ['type' => 'string', 'format' => 'date'],
                            'lieu' => ['type' => 'string'],
                            'celebrant' => ['type' => 'string'],
                            'numero_registre' => ['type' => 'string'],
                            'num_carnet' => ['type' => 'string'],
                            'observations' => ['type' => 'string']
                        ]
                    ]
                ]
            ]
        ],
        'responses' => [
            '200' => ['description' => 'Sacrement mis à jour et validé'],
            '404' => ['description' => 'Introuvable']
        ]
    ],
    'delete' => [
        'tags' => ['6. Préinscriptions & Catéchumènes'],
        'summary' => 'Supprimer un sacrement du parcours (Soft Delete)',
        'operationId' => 'deleteParcoursSacrement',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'catechumenId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
            ['name' => 'sacrementId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
        ],
        'responses' => [
            '200' => ['description' => 'Sacrement supprimé']
        ]
    ]
];

file_put_contents($swaggerPath, json_encode($swagger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Swagger sacrements mis à jour avec succès.\n";

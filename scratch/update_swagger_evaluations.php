<?php

$swaggerPath = __DIR__ . '/../public/swagger.json';
$swagger = json_decode(file_get_contents($swaggerPath), true);

if (!isset($swagger['paths'])) {
    $swagger['paths'] = [];
}

// 1. GET /evaluations
$swagger['paths']['/evaluations'] = [
    'get' => [
        'tags' => ['7. Évaluations & Bulletins'],
        'summary' => 'Liste des évaluations',
        'description' => 'Retourne la liste des évaluations selon les filtres pastoraux (session/section, niveau, classe, année, type, statut, recherche). Les enseignants (animateurs) sont automatiquement restreints à leurs classes assignées.',
        'operationId' => 'getEvaluationsList',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'section_id', 'in' => 'query', 'required' => false, 'description' => 'UUID ou ID de la session/section (Enfants, Jeunes, Adultes)', 'schema' => ['type' => 'string']],
            ['name' => 'session_id', 'in' => 'query', 'required' => false, 'description' => 'Alias pour section_id', 'schema' => ['type' => 'string']],
            ['name' => 'niveau_id', 'in' => 'query', 'required' => false, 'description' => 'UUID ou ID du niveau', 'schema' => ['type' => 'string']],
            ['name' => 'classe_id', 'in' => 'query', 'required' => false, 'description' => 'UUID ou ID de la classe', 'schema' => ['type' => 'string']],
            ['name' => 'annee_catechese_id', 'in' => 'query', 'required' => false, 'description' => 'UUID ou ID de l\'année de catéchèse', 'schema' => ['type' => 'string']],
            ['name' => 'type_eval', 'in' => 'query', 'required' => false, 'description' => 'Type d\'évaluation (interrogation, devoir, composition, examen, oral)', 'schema' => ['type' => 'string']],
            ['name' => 'statut', 'in' => 'query', 'required' => false, 'description' => 'Statut (actif, inactif)', 'schema' => ['type' => 'string']],
            ['name' => 'search', 'in' => 'query', 'required' => false, 'description' => 'Recherche par titre ou description', 'schema' => ['type' => 'string']],
        ],
        'responses' => [
            '200' => ['description' => 'Liste des évaluations filtrées'],
            '401' => ['description' => 'Non authentifié'],
            '403' => ['description' => 'Accès refusé'],
        ]
    ],
    'post' => [
        'tags' => ['7. Évaluations & Bulletins'],
        'summary' => 'Créer une évaluation',
        'description' => 'Crée une nouvelle évaluation dans une classe. L\'enseignant ne peut créer que dans une classe à laquelle il est affecté.',
        'operationId' => 'createEvaluation',
        'security' => [['bearerAuth' => []]],
        'requestBody' => [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['titre', 'date_evaluation'],
                        'properties' => [
                            'titre' => ['type' => 'string', 'example' => 'Interrogation 1'],
                            'coefficient' => ['type' => 'number', 'example' => 1.0],
                            'note_max' => ['type' => 'number', 'example' => 20.0],
                            'date_evaluation' => ['type' => 'string', 'format' => 'date', 'example' => '2026-09-04'],
                            'type_eval' => ['type' => 'string', 'example' => 'interrogation'],
                            'classe_id' => ['type' => 'string', 'example' => 'uuid-classe'],
                            'annee_catechese_id' => ['type' => 'string', 'example' => 'uuid-annee'],
                            'module_trimestriel_id' => ['type' => 'string', 'nullable' => true],
                            'description' => ['type' => 'string', 'nullable' => true],
                            'statut' => ['type' => 'string', 'enum' => ['actif', 'inactif'], 'default' => 'actif'],
                        ]
                    ]
                ]
            ]
        ],
        'responses' => [
            '201' => ['description' => 'Évaluation créée avec succès'],
            '403' => ['description' => 'Accès refusé pour cette classe'],
            '422' => ['description' => 'Erreur de validation'],
        ]
    ]
];

// 2. GET, PUT, DELETE /evaluations/{id}
$swagger['paths']['/evaluations/{id}'] = [
    'get' => [
        'tags' => ['7. Évaluations & Bulletins'],
        'summary' => 'Détails d\'une évaluation',
        'description' => 'Retourne les informations de l\'évaluation, barème, coefficient, session, niveau, classe et statistiques.',
        'operationId' => 'getEvaluationById',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
        ],
        'responses' => [
            '200' => ['description' => 'Détails de l\'évaluation'],
            '403' => ['description' => 'Accès refusé'],
            '404' => ['description' => 'Évaluation introuvable'],
        ]
    ],
    'put' => [
        'tags' => ['7. Évaluations & Bulletins'],
        'summary' => 'Modifier une évaluation',
        'operationId' => 'updateEvaluation',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
        ],
        'requestBody' => [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'titre' => ['type' => 'string'],
                            'coefficient' => ['type' => 'number'],
                            'note_max' => ['type' => 'number'],
                            'date_evaluation' => ['type' => 'string', 'format' => 'date'],
                            'type_eval' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'statut' => ['type' => 'string'],
                        ]
                    ]
                ]
            ]
        ],
        'responses' => [
            '200' => ['description' => 'Évaluation modifiée'],
            '403' => ['description' => 'Accès refusé'],
            '422' => ['description' => 'Erreur de validation'],
        ]
    ],
    'delete' => [
        'tags' => ['7. Évaluations & Bulletins'],
        'summary' => 'Supprimer une évaluation',
        'description' => 'Supprime l\'évaluation et traite les notes associées en transaction DB.',
        'operationId' => 'deleteEvaluation',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
        ],
        'responses' => [
            '200' => ['description' => 'Évaluation supprimée avec succès'],
            '403' => ['description' => 'Accès refusé'],
        ]
    ]
];

// 3. PATCH /evaluations/{id}/status
$swagger['paths']['/evaluations/{id}/status'] = [
    'patch' => [
        'tags' => ['7. Évaluations & Bulletins'],
        'summary' => 'Basculer le statut d\'une évaluation (actif/inactif)',
        'operationId' => 'toggleEvaluationStatus',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
        ],
        'responses' => [
            '200' => ['description' => 'Statut mis à jour'],
        ]
    ]
];

// 4. GET /evaluations/{id}/notes-grid
$swagger['paths']['/evaluations/{id}/notes-grid'] = [
    'get' => [
        'tags' => ['7. Évaluations & Bulletins'],
        'summary' => 'Grille de saisie des notes pour les élèves de la classe',
        'description' => 'Fournit la liste des catéchumènes inscrits dans la classe de l\'évaluation avec leurs notes actuelles pour saisie rapide.',
        'operationId' => 'getEvaluationNotesGrid',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
            ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']]
        ],
        'responses' => [
            '200' => ['description' => 'Grille de catéchumènes et notes existantes'],
            '403' => ['description' => 'Accès refusé'],
        ]
    ]
];

// 5. GET & POST /evaluations/{id}/notes
$swagger['paths']['/evaluations/{id}/notes'] = [
    'get' => [
        'tags' => ['7. Évaluations & Bulletins'],
        'summary' => 'Liste des notes d\'une évaluation',
        'operationId' => 'getEvaluationNotes',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
        ],
        'responses' => [
            '200' => ['description' => 'Liste des notes'],
        ]
    ],
    'post' => [
        'tags' => ['7. Évaluations & Bulletins'],
        'summary' => 'Enregistrement en lot des notes pour une évaluation',
        'description' => 'Enregistre ou met à jour plusieurs notes en une seule transaction. Valide 0 <= note <= note_max. Recalcule automatiquement les moyennes.',
        'operationId' => 'saveEvaluationNotesBatch',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]
        ],
        'requestBody' => [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['notes'],
                        'properties' => [
                            'notes' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'required' => ['catechumene_id'],
                                    'properties' => [
                                        'catechumene_id' => ['type' => 'string'],
                                        'note_obtenue' => ['type' => 'number', 'nullable' => true],
                                        'appreciation' => ['type' => 'string', 'nullable' => true],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ],
        'responses' => [
            '200' => ['description' => 'Notes enregistrées et moyennes recalculées'],
            '422' => ['description' => 'Erreur de validation (note supérieure au barème, etc.)'],
            '403' => ['description' => 'Accès refusé'],
        ]
    ]
];

// 6. GET /evaluations/classes/{classe}/moyennes
$swagger['paths']['/evaluations/classes/{classe}/moyennes'] = [
    'get' => [
        'tags' => ['7. Évaluations & Bulletins'],
        'summary' => 'Calcul automatique des moyennes de classe',
        'description' => 'Source de vérité backend pour les moyennes. Calcule la moyenne pondérée de chaque catéchumène à partir des évaluations notées (les non notées ne comptent pas comme 0). Retourne également les statistiques de classe (moyenne classe, plus forte, plus faible note).',
        'operationId' => 'getClasseMoyennes',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'classe', 'in' => 'path', 'required' => true, 'description' => 'UUID ou ID de la classe', 'schema' => ['type' => 'string']],
            ['name' => 'annee_catechese_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ],
        'responses' => [
            '200' => [
                'description' => 'Moyennes de classe et détails de chaque élève',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'example' => 'success'],
                                'data' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'classe' => ['type' => 'object'],
                                        'statistiques' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'total_evaluations' => ['type' => 'integer'],
                                                'total_eleves' => ['type' => 'integer'],
                                                'eleves_evalues' => ['type' => 'integer'],
                                                'eleves_non_evalues' => ['type' => 'integer'],
                                                'moyenne_classe' => ['type' => 'number', 'nullable' => true],
                                                'meilleure_moyenne' => ['type' => 'number', 'nullable' => true],
                                                'plus_faible_moyenne' => ['type' => 'number', 'nullable' => true],
                                            ]
                                        ],
                                        'eleves' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'catechumene_id' => ['type' => 'string'],
                                                    'matricule' => ['type' => 'string'],
                                                    'nom_prenoms' => ['type' => 'string'],
                                                    'nombre_evaluations' => ['type' => 'integer'],
                                                    'nombre_notes' => ['type' => 'integer'],
                                                    'moyenne' => ['type' => 'number', 'nullable' => true],
                                                    'appreciation' => ['type' => 'string'],
                                                    'details_notes' => ['type' => 'array'],
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '403' => ['description' => 'Accès refusé pour cette classe'],
            '404' => ['description' => 'Classe introuvable'],
        ]
    ]
];

// 7. GET /evaluations/catechumenes/{catechumene}/synthese
$swagger['paths']['/evaluations/catechumenes/{catechumene}/synthese'] = [
    'get' => [
        'tags' => ['7. Évaluations & Bulletins'],
        'summary' => 'Synthèse des évaluations d\'un catéchumène (Bulletin de notes)',
        'description' => 'Retourne l\'ensemble des évaluations, notes obtenues, barèmes, coefficients, appréciations et la moyenne générale de l\'élève pour son bulletin.',
        'operationId' => 'getCatechumeneSynthese',
        'security' => [['bearerAuth' => []]],
        'parameters' => [
            ['name' => 'catechumene', 'in' => 'path', 'required' => true, 'description' => 'UUID ou ID du catéchumène', 'schema' => ['type' => 'string']],
            ['name' => 'annee_catechese_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ],
        'responses' => [
            '200' => ['description' => 'Synthèse complète des notes du catéchumène'],
            '403' => ['description' => 'Accès refusé'],
            '404' => ['description' => 'Catéchumène introuvable'],
        ]
    ]
];

file_put_contents($swaggerPath, json_encode($swagger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Swagger updated successfully!\n";

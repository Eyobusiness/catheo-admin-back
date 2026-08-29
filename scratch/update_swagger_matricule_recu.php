<?php

$swaggerPath = __DIR__ . '/../public/swagger.json';
$swagger = json_decode(file_get_contents($swaggerPath), true);

// Update CatecheseConfiguration schema if exists
if (isset($swagger['components']['schemas'])) {
    foreach ($swagger['components']['schemas'] as $name => &$schema) {
        if (str_contains(strtolower($name), 'catecheseconfiguration') || str_contains(strtolower($name), 'paroisseconfiguration')) {
            if (isset($schema['properties'])) {
                $schema['properties']['prefixe_matricule'] = [
                    'type' => 'string',
                    'example' => 'CIM',
                    'description' => 'Préfixe alphabétique pour la génération des matricules des catéchumènes de la paroisse.'
                ];
                $schema['properties']['prefixe_recu'] = [
                    'type' => 'string',
                    'example' => 'REC',
                    'description' => 'Préfixe alphabétique pour la génération des numéros de reçus de paiement de la paroisse.'
                ];
            }
        }
        if (str_contains(strtolower($name), 'catechumene')) {
            if (isset($schema['properties']['matricule'])) {
                $schema['properties']['matricule']['example'] = 'CIM26-1001124002A';
                $schema['properties']['matricule']['description'] = 'Matricule officiel du catéchumène ({prefixe_matricule}{AA}-{JJMMHHmmss}{LETTRE}).';
            }
        }
        if (str_contains(strtolower($name), 'paiement')) {
            if (isset($schema['properties']['numero_recu'])) {
                $schema['properties']['numero_recu']['example'] = 'REC26-124002-0001';
                $schema['properties']['numero_recu']['description'] = 'Numéro de reçu officiel du paiement ({prefixe_recu}{AA}-{HHmmss}-{SEQUENCE}).';
            }
        }
    }
}

file_put_contents($swaggerPath, json_encode($swagger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Swagger mis à jour avec les nouveaux formats de matricules et reçus.\n";

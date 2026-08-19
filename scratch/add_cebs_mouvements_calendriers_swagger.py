import json

def update_swagger():
    swagger_path = 'public/swagger.json'
    with open(swagger_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    schemas = data.get('components', {}).get('schemas', {})
    paths = data.get('paths', {})

    # 1. CEB Schemas
    schemas['CebDto'] = {
        "type": "object",
        "required": ["id", "nom", "statut"],
        "properties": {
            "id": {"type": "string", "format": "uuid", "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5c01"},
            "nom": {"type": "string", "example": "CEB Saint-Joseph"},
            "responsable": {"type": "string", "nullable": True, "example": "KOUASSI Emmanuel"},
            "telephone": {"type": "string", "nullable": True, "example": "+225 0701020304"},
            "adresse": {"type": "string", "nullable": True, "example": "Quartier Résidentiel, Secteur 2"},
            "description": {"type": "string", "nullable": True, "example": "Communauté de base du secteur nord"},
            "statut": {"type": "string", "enum": ["Active", "Inactive"], "example": "Active"},
            "total_inscriptions": {"type": "integer", "example": 18},
            "created_at": {"type": "string", "format": "date-time", "example": "2026-08-17T12:00:00Z"}
        }
    }

    schemas['CreateCebDto'] = {
        "type": "object",
        "required": ["nom"],
        "properties": {
            "nom": {"type": "string", "example": "CEB Sainte-Monique"},
            "responsable": {"type": "string", "nullable": True, "example": "KOFFI Marie"},
            "telephone": {"type": "string", "nullable": True, "example": "+225 0505060708"},
            "adresse": {"type": "string", "nullable": True, "example": "Carrefour des Jeunes"},
            "description": {"type": "string", "nullable": True, "example": "Communauté familiale"},
            "statut": {"type": "string", "enum": ["Active", "Inactive"], "example": "Active"}
        }
    }

    # 2. Mouvement Schemas
    schemas['MouvementDto'] = {
        "type": "object",
        "required": ["id", "nom", "statut"],
        "properties": {
            "id": {"type": "string", "format": "uuid", "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5m01"},
            "nom": {"type": "string", "example": "Enfance Missionnaire"},
            "responsable": {"type": "string", "nullable": True, "example": "YAO Berthe"},
            "telephone": {"type": "string", "nullable": True, "example": "+225 0102030405"},
            "description": {"type": "string", "nullable": True, "example": "Mouvement missionnaire pour enfants"},
            "statut": {"type": "string", "enum": ["Active", "Inactive"], "example": "Active"},
            "total_inscriptions": {"type": "integer", "example": 35},
            "created_at": {"type": "string", "format": "date-time", "example": "2026-08-17T12:00:00Z"}
        }
    }

    schemas['CreateMouvementDto'] = {
        "type": "object",
        "required": ["nom"],
        "properties": {
            "nom": {"type": "string", "example": "Servants de Messe"},
            "responsable": {"type": "string", "nullable": True, "example": "BROU Jean"},
            "telephone": {"type": "string", "nullable": True, "example": "+225 0707080910"},
            "description": {"type": "string", "nullable": True, "example": "Service à l'autel"},
            "statut": {"type": "string", "enum": ["Active", "Inactive"], "example": "Active"}
        }
    }

    # 3. Calendrier Schemas
    schemas['CalendrierDto'] = {
        "type": "object",
        "required": ["id", "titre", "type", "date", "cible_type", "statut"],
        "properties": {
            "id": {"type": "string", "format": "uuid", "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5k01"},
            "titre": {"type": "string", "example": "Messe de Rentrée Pastorale"},
            "type": {"type": "string", "example": "Célébration"},
            "date": {"type": "string", "format": "date", "example": "2026-10-04"},
            "heure_debut": {"type": "string", "nullable": True, "example": "09:00"},
            "heure_fin": {"type": "string", "nullable": True, "example": "11:30"},
            "lieu": {"type": "string", "nullable": True, "example": "Église Principale"},
            "cible_type": {"type": "string", "enum": ["TOUS", "ANIMATEURS", "SECTION", "NIVEAU", "CLASSE", "CEB", "MOUVEMENT"], "example": "TOUS"},
            "cible_id": {"type": "string", "format": "uuid", "nullable": True, "example": None},
            "cible_nom": {"type": "string", "nullable": True, "example": None},
            "description": {"type": "string", "nullable": True, "example": "Messe d'ouverture solennelle pour tous les catéchumènes et animateurs"},
            "statut": {"type": "string", "enum": ["Planifié", "Réalisé", "Annulé"], "example": "Planifié"},
            "annee_catechese": {"$ref": "#/components/schemas/AnneeCatecheseDto"},
            "created_at": {"type": "string", "format": "date-time", "example": "2026-08-17T12:00:00Z"}
        }
    }

    schemas['CreateCalendrierDto'] = {
        "type": "object",
        "required": ["titre", "type", "date"],
        "properties": {
            "annee_catechese_id": {"type": "string", "format": "uuid", "description": "UUID de l'année pastorale (optionnel, active par défaut)", "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d11"},
            "titre": {"type": "string", "example": "Récollection des Catéchumènes"},
            "type": {"type": "string", "example": "Récollection"},
            "date": {"type": "string", "format": "date", "example": "2026-11-15"},
            "heure_debut": {"type": "string", "example": "08:30"},
            "heure_fin": {"type": "string", "example": "16:00"},
            "lieu": {"type": "string", "example": "Centre Spirituel Sainte-Thérèse"},
            "cible_type": {"type": "string", "enum": ["TOUS", "ANIMATEURS", "SECTION", "NIVEAU", "CLASSE", "CEB", "MOUVEMENT"], "example": "SECTION"},
            "cible_id": {"type": "string", "format": "uuid", "nullable": True, "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d21"},
            "description": {"type": "string", "nullable": True, "example": "Journée de prière et de formation"},
            "statut": {"type": "string", "enum": ["Planifié", "Réalisé", "Annulé"], "example": "Planifié"}
        }
    }

    # 4. CEB Paths
    paths['/cebs'] = {
        "get": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[GET] Liste des CEB (Communautés Ecclésiales de Base)",
            "description": "Retourne la liste des CEB de la paroisse avec filtres de recherche et de statut.",
            "security": [{"bearerAuth": []}],
            "responses": {
                "200": {
                    "description": "Liste des CEB",
                    "content": {
                        "application/json": {
                            "example": {
                                "status": "success",
                                "meta": {"total_elements": 2},
                                "data": [
                                    {"id": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5c01", "nom": "CEB Saint-Joseph", "responsable": "KOUASSI Emmanuel", "telephone": "+225 0701020304", "statut": "Active", "total_inscriptions": 18}
                                ]
                            }
                        }
                    }
                }
            }
        },
        "post": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[POST] Création d'une nouvelle CEB",
            "description": "Crée une nouvelle Communauté Ecclésiale de Base pour la paroisse.",
            "security": [{"bearerAuth": []}],
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateCebDto"}}}
            },
            "responses": {
                "201": {
                    "description": "CEB créée avec succès",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CebDto"}}}
                }
            }
        }
    }

    paths['/cebs/{id}'] = {
        "get": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[GET] Détails d'une CEB",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "responses": {"200": {"description": "Détails de la CEB", "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CebDto"}}}}}
        },
        "put": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[PUT] Modification d'une CEB",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "requestBody": {"required": True, "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateCebDto"}}}},
            "responses": {"200": {"description": "CEB mise à jour avec succès"}}
        },
        "patch": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[PATCH] Mise à jour partielle d'une CEB",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "responses": {"200": {"description": "CEB mise à jour avec succès"}}
        },
        "delete": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[DELETE] Suppression d'une CEB",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "responses": {"200": {"description": "CEB supprimée avec succès"}}
        }
    }

    paths['/cebs/{id}/status'] = {
        "patch": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[PATCH] Basculer le statut d'une CEB (Active <-> Inactive)",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "responses": {"200": {"description": "Statut de la CEB mis à jour"}}
        }
    }

    # 5. Mouvements Paths
    paths['/mouvements'] = {
        "get": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[GET] Liste des mouvements paroissiaux",
            "description": "Retourne la liste des mouvements paroissiaux (Enfance Missionnaire, Servants de Messe, Chorale, Scoutisme, etc.).",
            "security": [{"bearerAuth": []}],
            "responses": {
                "200": {
                    "description": "Liste des mouvements",
                    "content": {
                        "application/json": {
                            "example": {
                                "status": "success",
                                "meta": {"total_elements": 2},
                                "data": [
                                    {"id": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5m01", "nom": "Enfance Missionnaire", "responsable": "YAO Berthe", "telephone": "+225 0102030405", "statut": "Active", "total_inscriptions": 35}
                                ]
                            }
                        }
                    }
                }
            }
        },
        "post": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[POST] Création d'un nouveau mouvement paroissial",
            "security": [{"bearerAuth": []}],
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateMouvementDto"}}}
            },
            "responses": {
                "201": {
                    "description": "Mouvement créé avec succès",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/MouvementDto"}}}
                }
            }
        }
    }

    paths['/mouvements/{id}'] = {
        "get": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[GET] Détails d'un mouvement paroissial",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "responses": {"200": {"description": "Détails du mouvement", "content": {"application/json": {"schema": {"$ref": "#/components/schemas/MouvementDto"}}}}}
        },
        "put": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[PUT] Modification d'un mouvement",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "requestBody": {"required": True, "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateMouvementDto"}}}},
            "responses": {"200": {"description": "Mouvement mis à jour avec succès"}}
        },
        "patch": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[PATCH] Mise à jour partielle d'un mouvement",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "responses": {"200": {"description": "Mouvement mis à jour avec succès"}}
        },
        "delete": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[DELETE] Suppression d'un mouvement",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "responses": {"200": {"description": "Mouvement supprimé avec succès"}}
        }
    }

    paths['/mouvements/{id}/status'] = {
        "patch": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[PATCH] Basculer le statut d'un mouvement (Active <-> Inactive)",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "responses": {"200": {"description": "Statut du mouvement mis à jour"}}
        }
    }

    # 6. Calendrier Paths
    paths['/calendriers'] = {
        "get": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[GET] Calendrier pastoral (Événements & Activités planifiées)",
            "description": "Retourne les activités du calendrier pastoral avec filtres par année, cible_type, statut et plage de dates.",
            "security": [{"bearerAuth": []}],
            "parameters": [
                {"name": "annee_catechese_id", "in": "query", "required": False, "schema": {"type": "string", "format": "uuid"}},
                {"name": "cible_type", "in": "query", "required": False, "schema": {"type": "string", "enum": ["TOUS", "ANIMATEURS", "SECTION", "NIVEAU", "CLASSE", "CEB", "MOUVEMENT"]}},
                {"name": "statut", "in": "query", "required": False, "schema": {"type": "string", "enum": ["Planifié", "Réalisé", "Annulé"]}},
                {"name": "date_debut", "in": "query", "required": False, "schema": {"type": "string", "format": "date"}},
                {"name": "date_fin", "in": "query", "required": False, "schema": {"type": "string", "format": "date"}}
            ],
            "responses": {
                "200": {
                    "description": "Liste des activités du calendrier",
                    "content": {
                        "application/json": {
                            "example": {
                                "status": "success",
                                "meta": {"total_elements": 1},
                                "data": [
                                    {
                                        "id": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5k01",
                                        "titre": "Messe de Rentrée Pastorale",
                                        "type": "Célébration",
                                        "date": "2026-10-04",
                                        "heure_debut": "09:00",
                                        "heure_fin": "11:30",
                                        "lieu": "Église Principale",
                                        "cible_type": "TOUS",
                                        "statut": "Planifié"
                                    }
                                ]
                            }
                        }
                    }
                }
            }
        },
        "post": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[POST] Planifier une activité au calendrier pastoral",
            "description": "Ajoute un événement au calendrier pour une cible donnée (TOUS, ANIMATEURS, SECTION, NIVEAU, CLASSE, CEB, MOUVEMENT).",
            "security": [{"bearerAuth": []}],
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateCalendrierDto"}}}
            },
            "responses": {
                "201": {
                    "description": "Activité planifiée avec succès",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CalendrierDto"}}}
                }
            }
        }
    }

    paths['/calendriers/{id}'] = {
        "get": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[GET] Détails d'une activité du calendrier",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "responses": {"200": {"description": "Détails de l'activité", "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CalendrierDto"}}}}}
        },
        "put": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[PUT] Modification d'une activité du calendrier",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "requestBody": {"required": True, "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateCalendrierDto"}}}},
            "responses": {"200": {"description": "Activité mise à jour avec succès"}}
        },
        "patch": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[PATCH] Mise à jour partielle d'une activité du calendrier",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "responses": {"200": {"description": "Activité mise à jour avec succès"}}
        },
        "delete": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[DELETE] Suppression d'une activité du calendrier",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "responses": {"200": {"description": "Activité supprimée avec succès"}}
        }
    }

    paths['/calendriers/{id}/status'] = {
        "patch": {
            "tags": ["3. Organisation Pastorale & Calendrier"],
            "summary": "[PATCH] Modification du statut d'une activité (Planifié / Réalisé / Annulé)",
            "security": [{"bearerAuth": []}],
            "parameters": [{"name": "id", "in": "path", "required": True, "schema": {"type": "string", "format": "uuid"}}],
            "requestBody": {
                "required": True,
                "content": {
                    "application/json": {
                        "example": {"statut": "Réalisé"}
                    }
                }
            },
            "responses": {"200": {"description": "Statut de l'activité mis à jour"}}
        }
    }

    with open(swagger_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    print("Swagger updated successfully with Cebs, Mouvements, and Calendriers!")

if __name__ == '__main__':
    update_swagger()

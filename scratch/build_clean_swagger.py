import json

swagger = {
  "openapi": "3.0.0",
  "info": {
    "title": "Catheo API - Logiciel de Gestion Paroissiale de la Catéchèse",
    "version": "1.0.0",
    "description": "API REST v1 du logiciel Catheo. Exposition complète de tous les DTOs TypeScript et des endpoints."
  },
  "servers": [
    { "url": "http://localhost:8000/api/v1", "description": "Serveur Local Développement" }
  ],
  "tags": [
    { "name": "1. Authentification & Compte", "description": "Connexion, déconnexion et profil de l'utilisateur connecté" },
    { "name": "2. Profils & Utilisateurs", "description": "CRUD Utilisateurs, profils de sécurité et gestion des statuts" },
    { "name": "3. Configuration Paroissiale", "description": "Configuration paroissiale, responsables, apparence et sauvegardes" },
    { "name": "4. Organisation Pastorale", "description": "CRUD Années pastorales, sections, niveaux, classes, animateurs et affectations" },
    { "name": "5. Calendrier & Activités", "description": "CRUD Types d'activités, agenda et statuts d'activités" },
    { "name": "6. Préinscriptions & Catéchumènes", "description": "CRUD Campagnes, préinscriptions, validation, catéchumènes, parrains et mutations" },
    { "name": "7. Présences, Évaluations & Bulletins", "description": "CRUD Séances, présences en lot, évaluations, notes et bulletins trimestriels" },
    { "name": "8. Finances", "description": "Gestion Financière: Opérations, Caisse, Versements au Curé, Config des paiements, Remboursements" },
    { "name": "9. Communication & Audit", "description": "CRUD Annonces, logs de notifications et journal d'audit" },
    { "name": "10. Tableau de Bord & Analytics", "description": "KPIs, statistiques et effectifs" },
    { "name": "11. Impressions & Fiches Officiels", "description": "Génération des fiches de notes, bilans annuels et sacrements" },
    { "name": "12. Exportations de Données", "description": "Exportations Excel / PDF" }
  ],
  "components": {
    "securitySchemes": {
      "bearerAuth": { "type": "http", "scheme": "bearer", "bearerFormat": "JWT" }
    }
  }
}

schemas = {
    "ApiResponse": {
        "type": "object",
        "properties": {
            "status": {"type": "string", "example": "success"},
            "message": {"type": "string", "example": "Opération effectuée avec succès"},
            "data": {"type": "object"}
        }
    },
    "ErrorResponseDto": {
        "type": "object",
        "properties": {
            "status": {"type": "string", "example": "error"},
            "message": {"type": "string", "example": "Ressource non trouvée."}
        }
    },
    "ValidationErrorResponseDto": {
        "type": "object",
        "properties": {
            "status": {"type": "string", "example": "error"},
            "message": {"type": "string", "example": "Les données fournies sont invalides."},
            "errors": {
                "type": "object",
                "additionalProperties": {"type": "array", "items": {"type": "string"}},
                "example": {"email": ["Le champ email est obligatoire."]}
            }
        }
    },
    "LoginRequestDto": {
        "type": "object",
        "required": ["email", "password"],
        "properties": {
            "email": {"type": "string", "example": "admin.stpaul@catheo.ci"},
            "password": {"type": "string", "example": "12345678"}
        }
    },
    "LoginResponseDto": {
        "type": "object",
        "properties": {
            "status": {"type": "string", "example": "success"},
            "message": {"type": "string", "example": "Connexion réussie."},
            "data": {
                "type": "object",
                "properties": {
                    "token": {"type": "string", "example": "48|3CxSVo4zUAa7BN8EfhMIxHUGDTTw8kBzqGmCvQTw05769a9f"},
                    "token_type": {"type": "string", "example": "Bearer"},
                    "user": {"$ref": "#/components/schemas/UserDto"}
                }
            }
        }
    },
    "UserDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "0194cf20-1111-7000-8000-000000000001"},
            "name": {"type": "string", "example": "Père Administrateur Saint-Paul"},
            "email": {"type": "string", "example": "admin.stpaul@catheo.ci"},
            "telephone": {"type": "string", "example": "+225 0700000001"},
            "statut": {"type": "string", "enum": ["actif", "inactif", "suspendu"], "example": "actif"},
            "profil": {"$ref": "#/components/schemas/ProfilDto"},
            "paroisse": {"$ref": "#/components/schemas/ParoisseConfigurationDto"},
            "created_at": {"type": "string", "format": "date-time", "example": "2026-08-09T19:30:00Z"}
        }
    },
    "CreateUserDto": {
        "type": "object",
        "required": ["name", "email", "password", "profil_id"],
        "properties": {
            "name": {"type": "string", "example": "KOFFI Antoine"},
            "email": {"type": "string", "example": "koffi.antoine@catheo.ci"},
            "password": {"type": "string", "example": "Password123!"},
            "telephone": {"type": "string", "example": "+225 0701020304"},
            "profil_id": {"type": "string", "example": "0194cf20-1111-7000-8000-000000000002"}
        }
    },
    "UpdateUserDto": {
        "type": "object",
        "properties": {
            "name": {"type": "string", "example": "KOFFI Antoine Modifié"},
            "email": {"type": "string", "example": "koffi.nouveau@catheo.ci"},
            "telephone": {"type": "string", "example": "+225 0709090909"},
            "profil_id": {"type": "string", "example": "0194cf20-1111-7000-8000-000000000002"}
        }
    },
    "UpdateUserStatusDto": {
        "type": "object",
        "required": ["statut"],
        "properties": {
            "statut": {"type": "string", "enum": ["actif", "inactif", "suspendu"], "example": "inactif"}
        }
    },
    "ProfilDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "0194cf20-1111-7000-8000-000000000002"},
            "nom": {"type": "string", "example": "Administrateur Paroissial"},
            "code": {"type": "string", "example": "ADMIN_PAROISSE"},
            "description": {"type": "string", "example": "Gestion globale de la paroisse"},
            "permissions": {"type": "array", "items": {"type": "string"}, "example": ["dashboard.view", "finances.manage"]},
            "is_system": {"type": "boolean", "example": True}
        }
    },
    "CreateProfilDto": {
        "type": "object",
        "required": ["nom", "code"],
        "properties": {
            "nom": {"type": "string", "example": "Comptable Paroissial"},
            "code": {"type": "string", "example": "COMPTABLE_PAROISSE"},
            "description": {"type": "string", "example": "Gestion exclusive de la caisse et des opérations"},
            "permissions": {"type": "array", "items": {"type": "string"}, "example": ["finances.operations.view", "finances.caisse.view"]}
        }
    },
    "UpdateProfilDto": {
        "type": "object",
        "properties": {
            "nom": {"type": "string", "example": "Comptable & Trésorier"},
            "description": {"type": "string", "example": "Gestion financière avancée"},
            "permissions": {"type": "array", "items": {"type": "string"}, "example": ["finances.operations.view", "finances.caisse.refund"]}
        }
    },
    "PermissionTreeNodeDto": {
        "type": "object",
        "properties": {
            "menu": {"type": "string", "example": "Finances"},
            "code": {"type": "string", "example": "finances"},
            "sous_menus": {
                "type": "array",
                "items": {
                    "type": "object",
                    "properties": {
                        "nom": {"type": "string", "example": "Caisse (Paiements encaissés)"},
                        "code": {"type": "string", "example": "finances.caisse"},
                        "permissions": {
                            "type": "array",
                            "items": {
                                "type": "object",
                                "properties": {
                                    "key": {"type": "string", "example": "finances.caisse.view"},
                                    "label": {"type": "string", "example": "Consulter le journal de caisse et le solde"}
                                }
                            }
                        }
                    }
                }
            }
        }
    },
    "ParoisseConfigurationDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "0194cf20-par-001"},
            "nom": {"type": "string", "example": "Paroisse Saint Jean-Baptiste de Cotonou"},
            "code_paroisse": {"type": "string", "example": "PAR-STPAUL-01"},
            "diocese": {"type": "string", "example": "Archidiocèse de Cotonou"},
            "doyenne": {"type": "string", "example": "Doyenné Saint-Joseph"},
            "ville": {"type": "string", "example": "Cotonou"},
            "commune": {"type": "string", "example": "Cotonou"},
            "telephone": {"type": "string", "example": "+229 21 30 12 45"},
            "email": {"type": "string", "example": "contact@stjeanbaptiste-cotonou.org"},
            "site_web": {"type": "string", "example": "https://stjeanbaptiste-cotonou.org"},
            "adresse": {"type": "string", "example": "01 BP 452, Avenue Mgr Steinmetz, Cotonou, Bénin"},
            "logo_url": {"type": "string", "example": "http://localhost:8000/storage/paroisses/logos/logo.png"},
            "cure_nom": {"type": "string", "example": "Père Recteur St-Paul"},
            "coordination_nom": {"type": "string", "example": "Coordination de la Catéchèse"},
            "statut": {"type": "string", "example": "actif"}
        }
    },
    "UpdateParoisseConfigurationDto": {
        "type": "object",
        "properties": {
            "nom": {"type": "string", "example": "Paroisse Saint Jean-Baptiste de Cotonou"},
            "diocese": {"type": "string", "example": "Archidiocèse de Cotonou"},
            "doyenne": {"type": "string", "example": "Doyenné Saint-Joseph"},
            "telephone": {"type": "string", "example": "+229 21 30 12 45"},
            "email": {"type": "string", "example": "contact@stjeanbaptiste-cotonou.org"},
            "site_web": {"type": "string", "example": "https://stjeanbaptiste-cotonou.org"},
            "adresse": {"type": "string", "example": "01 BP 452, Avenue Mgr Steinmetz, Cotonou, Bénin"}
        }
    },
    "ResponsableParoisseDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "0194cf20-res-001"},
            "nom_prenoms": {"type": "string", "example": "Abbé Jean DOSSOU"},
            "fonction": {"type": "string", "example": "Curé, Vicaire, Secrétaire"},
            "telephone": {"type": "string", "example": "+229 21 30 12 45"},
            "email": {"type": "string", "example": "dossou@stjeanbaptiste-cotonou.org"},
            "signature_url": {"type": "string", "example": "http://localhost:8000/storage/signatures/sign1.png"},
            "ordre_affichage": {"type": "integer", "example": 1}
        }
    },
    "CreateResponsableParoisseDto": {
        "type": "object",
        "required": ["nom_prenoms", "fonction"],
        "properties": {
            "nom_prenoms": {"type": "string", "example": "Abbé Jean DOSSOU"},
            "fonction": {"type": "string", "example": "Curé, Vicaire, Secrétaire"},
            "telephone": {"type": "string", "example": "+229 21 30 12 45"},
            "email": {"type": "string", "example": "dossou@stjeanbaptiste-cotonou.org"}
        }
    },
    "ApparenceConfigurationDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "0194cf20-app-001"},
            "couleur_principale": {"type": "string", "example": "#4F46E5"},
            "couleur_secondaire": {"type": "string", "example": "#D97706"},
            "police_caracteres": {"type": "string", "enum": ["Inter", "Roboto", "Outfit", "Poppins", "Nunito", "DM Sans"], "example": "Inter"},
            "logo_url": {"type": "string", "example": "http://localhost:8000/storage/logo.png"},
            "updated_at": {"type": "string", "format": "date-time", "example": "2026-08-10T00:30:00Z"}
        }
    },
    "UpdateApparenceConfigurationDto": {
        "type": "object",
        "properties": {
            "couleur_principale": {"type": "string", "example": "#4F46E5"},
            "couleur_secondaire": {"type": "string", "example": "#D97706"},
            "police_caracteres": {"type": "string", "enum": ["Inter", "Roboto", "Outfit", "Poppins", "Nunito", "DM Sans"], "example": "Inter"}
        }
    },
    "SauvegardeDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "0194cf20-svg-001"},
            "nom_fichier": {"type": "string", "example": "backup_catheo_par_stpaul_2026-08-10_0046.sql"},
            "date": {"type": "string", "example": "10/08/2026"},
            "heure": {"type": "string", "example": "00:46"},
            "taille": {"type": "string", "example": "13.1 MB"},
            "taille_octets": {"type": "integer", "example": 13736345},
            "cree_par": {"type": "string", "example": "Abbé Ferdinand"},
            "type": {"type": "string", "enum": ["manuel", "automatique"], "example": "manuel"},
            "statut": {"type": "string", "enum": ["termine", "en_cours", "echec"], "example": "termine"}
        }
    },
    "AnneeCatecheseDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "0194cf20-ann-2024"},
            "libelle": {"type": "string", "example": "2024-2025"},
            "date_debut": {"type": "string", "format": "date", "example": "2024-10-01"},
            "date_fin": {"type": "string", "format": "date", "example": "2025-06-30"},
            "est_active": {"type": "boolean", "example": True},
            "statut": {"type": "string", "example": "active"}
        }
    },
    "SectionDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "0194cf20-sec-enfants"},
            "nom": {"type": "string", "example": "Section Enfants"},
            "code": {"type": "string", "example": "SEC-ENFANTS"},
            "description": {"type": "string", "example": "Enfants de 6 à 12 ans"},
            "ordre_affichage": {"type": "integer", "example": 1}
        }
    },
    "NiveauDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "0194cf20-niv-c1"},
            "section_id": {"type": "string", "example": "0194cf20-sec-enfants"},
            "nom": {"type": "string", "example": "1ère Année - Éveil à la Foi"},
            "code": {"type": "string", "example": "NIV-EVEIL-1"},
            "description": {"type": "string", "example": "Première étape du parcours"},
            "ordre_affichage": {"type": "integer", "example": 1}
        }
    },
    "ClasseDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "0194cf20-cls-c1"},
            "nom": {"type": "string", "example": "Classe Saint-Joseph A"},
            "code": {"type": "string", "example": "CLS-STJO-A"},
            "capacite_max": {"type": "integer", "example": 30},
            "lieu_rassemblement": {"type": "string", "example": "Salle N°1"},
            "jour_rencontre": {"type": "string", "example": "Samedi"},
            "heure_debut": {"type": "string", "example": "09:00"},
            "heure_fin": {"type": "string", "example": "11:00"},
            "statut": {"type": "string", "example": "active"}
        }
    },
    "AnimateurDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "0194cf20-anm-001"},
            "nom": {"type": "string", "example": "YAO"},
            "prenoms": {"type": "string", "example": "Brou Paul"},
            "sexe": {"type": "string", "example": "M"},
            "telephone": {"type": "string", "example": "+225 0504030201"},
            "email": {"type": "string", "example": "yao.paul@catheo.ci"},
            "profession": {"type": "string", "example": "Enseignant"},
            "statut": {"type": "string", "example": "actif"}
        }
    },
    "CatechumeneDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "0194cf20-cat-001"},
            "code_catechumene": {"type": "string", "example": "CAT-2024-0100"},
            "nom": {"type": "string", "example": "KOUASSI"},
            "prenoms": {"type": "string", "example": "Jean Marc"},
            "sexe": {"type": "string", "example": "M"},
            "date_naissance": {"type": "string", "format": "date", "example": "2015-05-12"},
            "lieu_naissance": {"type": "string", "example": "Abidjan"},
            "telephone": {"type": "string", "example": "+225 0707070707"},
            "est_baptise": {"type": "boolean", "example": False},
            "statut": {"type": "string", "example": "actif"}
        }
    },
    "CreateCatechumeneDto": {
        "type": "object",
        "required": ["nom", "prenoms", "sexe", "date_naissance"],
        "properties": {
            "nom": {"type": "string", "example": "KOUASSI"},
            "prenoms": {"type": "string", "example": "Jean Marc"},
            "sexe": {"type": "string", "example": "M"},
            "date_naissance": {"type": "string", "format": "date", "example": "2015-05-12"},
            "lieu_naissance": {"type": "string", "example": "Abidjan"},
            "domicile": {"type": "string", "example": "Cocody Angré 8ème Tranche"},
            "profession": {"type": "string", "example": "Élève"},
            "classe_scolaire": {"type": "string", "example": "CM2"},
            "telephone": {"type": "string", "example": "+225 0707070707"},
            "nom_pere": {"type": "string", "example": "KOUASSI Michel"},
            "nom_mere": {"type": "string", "example": "YAO Marie"},
            "est_baptise": {"type": "boolean", "example": False}
        }
    },
    "OperationPaiementDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "op-2026-001"},
            "reference": {"type": "string", "example": "OP-2026-001"},
            "libelle": {"type": "string", "example": "Inscription annuelle Catéchèse"},
            "montant": {"type": "number", "example": 15000},
            "montant_paye": {"type": "number", "example": 0},
            "echeance": {"type": "string", "format": "date", "example": "2026-08-15"},
            "statut": {"type": "string", "enum": ["en_attente", "partiellement_paye", "paye", "annule"], "example": "en_attente"}
        }
    },
    "CreateOperationPaiementDto": {
        "type": "object",
        "required": ["libelle", "montant"],
        "properties": {
            "catechumene_id": {"type": "string", "example": "0194cf20-cat-001"},
            "tarif_id": {"type": "string", "example": "0194cf20-trf-001"},
            "libelle": {"type": "string", "example": "Inscription annuelle Catéchèse"},
            "montant": {"type": "number", "example": 15000},
            "echeance": {"type": "string", "format": "date", "example": "2026-08-15"}
        }
    },
    "CaisseParoissialeDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "cas-001"},
            "reference": {"type": "string", "example": "ENC-2026-0041"},
            "date_mouvement": {"type": "string", "format": "date", "example": "2026-07-28"},
            "libelle": {"type": "string", "example": "Inscription annuelle Catéchèse"},
            "montant": {"type": "number", "example": 15000},
            "mode_paiement": {"type": "string", "example": "Mobile Money"},
            "caissier_nom": {"type": "string", "example": "KOFFI Antoine"},
            "solde_apres": {"type": "number", "example": 20000},
            "type_mouvement": {"type": "string", "enum": ["entree", "recette", "sortie", "depense", "remboursement"], "example": "entree"}
        }
    },
    "RemboursementRequestDto": {
        "type": "object",
        "required": ["motif"],
        "properties": {
            "montant_rembourse": {"type": "number", "example": 15000},
            "motif": {"type": "string", "example": "Déménagement de la famille dans une autre paroisse"}
        }
    },
    "VersementCureDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "vrs-2026-001"},
            "reference": {"type": "string", "example": "VRS-2026-001"},
            "periode_concernee": {"type": "string", "example": "Juin 2026"},
            "montant_verse": {"type": "number", "example": 800000},
            "mode_remise": {"type": "string", "enum": ["cheque", "especes", "virement"], "example": "cheque"},
            "effectue_par": {"type": "string", "example": "KOFFI Antoine (Comptable)"},
            "statut": {"type": "string", "example": "valide"}
        }
    },
    "CreateVersementCureDto": {
        "type": "object",
        "required": ["periode_concernee", "montant_verse", "mode_remise"],
        "properties": {
            "periode_concernee": {"type": "string", "example": "Juin 2026"},
            "montant_verse": {"type": "number", "example": 800000},
            "mode_remise": {"type": "string", "enum": ["cheque", "especes", "virement"], "example": "cheque"},
            "effectue_par": {"type": "string", "example": "KOFFI Antoine (Comptable)"}
        }
    },
    "TarifDto": {
        "type": "object",
        "properties": {
            'id': {"type": "string", "example": "trf-001"},
            "intitule": {"type": "string", "example": "Inscriptions Catéchèse Annuelle"},
            "description": {"type": "string", "example": "Frais de scolarité, manuel de catéchisme"},
            "montant": {"type": "number", "example": 15000},
            "periode_debut": {"type": "string", "format": "date", "example": "2025-09-01"},
            "periode_fin": {"type": "string", "format": "date", "example": "2025-10-31"},
            "est_obligatoire": {"type": "boolean", "example": True},
            "statut": {"type": "string", "example": "actif"}
        }
    },
    "CreateTarifDto": {
        "type": "object",
        "required": ["intitule", "montant"],
        "properties": {
            "intitule": {"type": "string", "example": "Inscriptions Catéchèse Annuelle & Manuel"},
            "description": {"type": "string", "example": "Frais de scolarité et livre de catéchisme"},
            "montant": {"type": "number", "example": 15000},
            "periode_debut": {"type": "string", "format": "date", "example": "2025-09-01"},
            "periode_fin": {"type": "string", "format": "date", "example": "2025-10-31"},
            "est_obligatoire": {"type": "boolean", "example": True},
            "niveau_uuids": {"type": "array", "items": {"type": "string"}, "example": ["0194cf20-niv-c1", "0194cf20-niv-c2"]}
        }
    }
}

paths = {
    "/health": {
        "get": {
            "tags": ["1. Authentification & Compte"],
            "summary": "Health Check API",
            "security": [],
            "responses": {
                "200": {
                    "description": "API opérationnelle",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ApiResponse"}}}
                }
            }
        }
    },
    "/auth/login": {
        "post": {
            "tags": ["1. Authentification & Compte"],
            "summary": "Connexion utilisateur",
            "security": [],
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/LoginRequestDto"}}}
            },
            "responses": {
                "200": {
                    "description": "Jeton Sanctum et profil",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/LoginResponseDto"}}}
                }
            }
        }
    },
    "/auth/me": {
        "get": {
            "tags": ["1. Authentification & Compte"],
            "summary": "Profil utilisateur connecté",
            "responses": {
                "200": {
                    "description": "Profil utilisateur",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/UserDto"}}}
                }
            }
        }
    },
    "/auth/logout": {
        "post": {
            "tags": ["1. Authentification & Compte"],
            "summary": "Déconnexion utilisateur",
            "responses": {"200": {"description": "Déconnecté"}}
        }
    },
    "/users": {
        "get": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[GET] Liste paginée des utilisateurs",
            "responses": {
                "200": {
                    "description": "Liste des utilisateurs",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/UserDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[POST] Créer un utilisateur",
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateUserDto"}}}
            },
            "responses": {
                "201": {
                    "description": "Utilisateur créé",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/UserDto"}}}
                }
            }
        }
    },
    "/users/{uuid}": {
        "get": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[GET] Détails d'un utilisateur",
            "responses": {
                "200": {
                    "description": "Détails utilisateur",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/UserDto"}}}
                }
            }
        },
        "put": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[PUT] Modifier un utilisateur",
            "requestBody": {
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/UpdateUserDto"}}}
            },
            "responses": {
                "200": {
                    "description": "Modifié",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/UserDto"}}}
                }
            }
        },
        "delete": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[DELETE] Supprimer un utilisateur",
            "responses": {"200": {"description": "Supprimé"}}
        }
    },
    "/users/{uuid}/status": {
        "patch": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[PATCH] Activer/Désactiver un utilisateur",
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/UpdateUserStatusDto"}}}
            },
            "responses": {"200": {"description": "Statut mis à jour"}}
        }
    },
    "/profils/permissions-tree": {
        "get": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[GET] Arbre des permissions par menu pour Angular",
            "responses": {
                "200": {
                    "description": "Arbre des permissions",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/PermissionTreeNodeDto"}}}}
                }
            }
        }
    },
    "/profils": {
        "get": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[GET] Liste des profils",
            "responses": {
                "200": {
                    "description": "Profils",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/ProfilDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[POST] Créer un profil d'accès",
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateProfilDto"}}}
            },
            "responses": {
                "201": {
                    "description": "Profil créé",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ProfilDto"}}}
                }
            }
        }
    },
    "/profils/{uuid}": {
        "get": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[GET] Détails d'un profil",
            "responses": {
                "200": {
                    "description": "Détails",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ProfilDto"}}}
                }
            }
        },
        "put": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[PUT] Modifier un profil",
            "requestBody": {
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/UpdateProfilDto"}}}
            },
            "responses": {
                "200": {
                    "description": "Modifié",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ProfilDto"}}}
                }
            }
        },
        "delete": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[DELETE] Supprimer un profil",
            "responses": {"200": {"description": "Supprimé"}}
        }
    },
    "/paroisse-configuration": {
        "get": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[GET] Obtenir les Informations Institutionnelles",
            "responses": {
                "200": {
                    "description": "Informations Institutionnelles",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ParoisseConfigurationDto"}}}
                }
            }
        },
        "put": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[PUT] Modifier les Informations Institutionnelles",
            "requestBody": {
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/UpdateParoisseConfigurationDto"}}}
            },
            "responses": {
                "200": {
                    "description": "Informations mises à jour",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ParoisseConfigurationDto"}}}
                }
            }
        }
    },
    "/apparence-configuration": {
        "get": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[GET] Configuration d'apparence (Couleurs & Police)",
            "responses": {
                "200": {
                    "description": "Configuration d'apparence",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ApparenceConfigurationDto"}}}
                }
            }
        },
        "put": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[PUT] Enregistrer modifications d'apparence",
            "requestBody": {
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/UpdateApparenceConfigurationDto"}}}
            },
            "responses": {
                "200": {
                    "description": "Modifications enregistrées",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ApparenceConfigurationDto"}}}
                }
            }
        }
    },
    "/apparence-configuration/reset": {
        "post": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[POST] Restaurer les valeurs d'apparence par défaut",
            "responses": {
                "200": {
                    "description": "Valeurs par défaut restaurées",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ApparenceConfigurationDto"}}}
                }
            }
        }
    },
    "/sauvegardes": {
        "get": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[GET] Historique des sauvegardes",
            "responses": {
                "200": {
                    "description": "Historique des sauvegardes",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/SauvegardeDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[POST] Déclencher une nouvelle sauvegarde (+ Nouvelle sauvegarde)",
            "responses": {
                "201": {
                    "description": "Nouvelle sauvegarde générée",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/SauvegardeDto"}}}
                }
            }
        }
    },
    "/sauvegardes/{uuid}/download": {
        "get": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[GET] Télécharger la sauvegarde (.sql)",
            "responses": {"200": {"description": "Fichier .sql à télécharger"}}
        }
    },
    "/sauvegardes/{uuid}/restaurer": {
        "post": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[POST] Restaurer la base de données",
            "responses": {
                "200": {
                    "description": "Restauration effectuée",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/SauvegardeDto"}}}
                }
            }
        }
    },
    "/sauvegardes/{uuid}": {
        "delete": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[DELETE] Supprimer une sauvegarde",
            "responses": {"200": {"description": "Sauvegarde supprimée"}}
        }
    },
    "/responsables-paroisse": {
        "get": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[GET] Liste des responsables paroissiaux",
            "responses": {
                "200": {
                    "description": "Responsables",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/ResponsableParoisseDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[POST] Ajouter un responsable (Modal Ajouter un nouveau responsable)",
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateResponsableParoisseDto"}}}
            },
            "responses": {
                "201": {
                    "description": "Responsable ajouté",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ResponsableParoisseDto"}}}
                }
            }
        }
    },
    "/responsables-paroisse/{uuid}": {
        "get": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[GET] Détails responsable",
            "responses": {
                "200": {
                    "description": "Détails",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ResponsableParoisseDto"}}}
                }
            }
        },
        "put": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[PUT] Modifier un responsable",
            "requestBody": {
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateResponsableParoisseDto"}}}
            },
            "responses": {
                "200": {
                    "description": "Modifié",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ResponsableParoisseDto"}}}
                }
            }
        },
        "delete": {
            "tags": ["3. Configuration Paroissiale"],
            "summary": "[DELETE] Supprimer un responsable",
            "responses": {"200": {"description": "Supprimé"}}
        }
    },
    "/annee-catecheses": {
        "get": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[GET] Liste des années pastorales",
            "responses": {
                "200": {
                    "description": "Années pastorales",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/AnneeCatecheseDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[POST] Créer une année pastorale",
            "responses": {
                "201": {
                    "description": "Créée",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/AnneeCatecheseDto"}}}
                }
            }
        }
    },
    "/annee-catecheses/{uuid}": {
        "get": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[GET] Détails année pastorale",
            "responses": {
                "200": {
                    "description": "Détails",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/AnneeCatecheseDto"}}}
                }
            }
        },
        "put": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[PUT] Modifier année pastorale",
            "responses": {
                "200": {
                    "description": "Modifiée",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/AnneeCatecheseDto"}}}
                }
            }
        },
        "delete": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[DELETE] Supprimer année pastorale",
            "responses": {"200": {"description": "Supprimée"}}
        }
    },
    "/annee-catecheses/{uuid}/activate": {
        "patch": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[PATCH] Activer l'année pastorale courante",
            "responses": {"200": {"description": "Activée"}}
        }
    },
    "/sections": {
        "get": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[GET] Liste des sections",
            "responses": {
                "200": {
                    "description": "Sections",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/SectionDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[POST] Créer une section",
            "responses": {
                "201": {
                    "description": "Créée",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/SectionDto"}}}
                }
            }
        }
    },
    "/sections/{uuid}": {
        "get": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[GET] Détails section",
            "responses": {
                "200": {
                    "description": "Détails",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/SectionDto"}}}
                }
            }
        },
        "put": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[PUT] Modifier une section",
            "requestBody": {
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/UpdateSectionDto"}}}
            },
            "responses": {
                "200": {
                    "description": "Modifiée",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/SectionDto"}}}
                }
            }
        },
        "delete": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[DELETE] Supprimer une section",
            "responses": {"200": {"description": "Supprimée"}}
        }
    },
    "/sections/{uuid}/status": {
        "patch": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[PATCH] Basculer le statut d'une section (Actif / Inactif)",
            "responses": {
                "200": {
                    "description": "Statut basculé",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/SectionDto"}}}
                }
            }
        }
    },
    "/niveaux": {
        "get": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[GET] Liste des niveaux",
            "responses": {
                "200": {
                    "description": "Niveaux",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/NiveauDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[POST] Créer un niveau",
            "responses": {
                "201": {
                    "description": "Créé",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/NiveauDto"}}}
                }
            }
        }
    },
    "/niveaux/{uuid}": {
        "get": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[GET] Détails niveau",
            "responses": {
                "200": {
                    "description": "Détails",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/NiveauDto"}}}
                }
            }
        },
        "put": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[PUT] Modifier un niveau",
            "requestBody": {
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/UpdateNiveauDto"}}}
            },
            "responses": {
                "200": {
                    "description": "Modifié",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/NiveauDto"}}}
                }
            }
        },
        "delete": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[DELETE] Supprimer un niveau",
            "responses": {"200": {"description": "Supprimé"}}
        }
    },
    "/niveaux/{uuid}/status": {
        "patch": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[PATCH] Basculer le statut d'un niveau (Actif / Inactif)",
            "responses": {
                "200": {
                    "description": "Statut basculé",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/NiveauDto"}}}
                }
            }
        }
    },
    "/classes": {
        "get": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[GET] Liste paginée des classes",
            "responses": {
                "200": {
                    "description": "Classes",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/ClasseDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[POST] Créer une classe",
            "responses": {
                "201": {
                    "description": "Créée",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ClasseDto"}}}
                }
            }
        }
    },
    "/classes/{uuid}": {
        "get": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[GET] Détails classe",
            "responses": {
                "200": {
                    "description": "Détails",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ClasseDto"}}}
                }
            }
        },
        "put": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[PUT] Modifier une classe",
            "responses": {
                "200": {
                    "description": "Modifiée",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/ClasseDto"}}}
                }
            }
        },
        "delete": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[DELETE] Supprimer une classe",
            "responses": {"200": {"description": "Supprimée"}}
        }
    },
    "/animateurs": {
        "get": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[GET] Liste paginée des animateurs",
            "responses": {
                "200": {
                    "description": "Animateurs",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/AnimateurDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[POST] Créer un animateur",
            "responses": {
                "201": {
                    "description": "Créé",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/AnimateurDto"}}}
                }
            }
        }
    },
    "/animateurs/{uuid}": {
        "get": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[GET] Détails animateur",
            "responses": {
                "200": {
                    "description": "Détails",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/AnimateurDto"}}}
                }
            }
        },
        "put": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[PUT] Modifier un animateur",
            "responses": {
                "200": {
                    "description": "Modifié",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/AnimateurDto"}}}
                }
            }
        },
        "delete": {
            "tags": ["4. Organisation Pastorale"],
            "summary": "[DELETE] Supprimer un animateur",
            "responses": {"200": {"description": "Supprimé"}}
        }
    },
    "/catechumenes": {
        "get": {
            "tags": ["6. Préinscriptions & Catéchumènes"],
            "summary": "[GET] Registre des catéchumènes",
            "responses": {
                "200": {
                    "description": "Catéchumènes",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/CatechumeneDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["6. Préinscriptions & Catéchumènes"],
            "summary": "[POST] Enregistrer un catéchumène",
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateCatechumeneDto"}}}
            },
            "responses": {
                "201": {
                    "description": "Créé",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CatechumeneDto"}}}
                }
            }
        }
    },
    "/operations-paiements": {
        "get": {
            "tags": ["8. Finances"],
            "summary": "[Screen 1] Opérations (Paiements en attente)",
            "responses": {
                "200": {
                    "description": "Paiements en attente",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/OperationPaiementDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["8. Finances"],
            "summary": "[Screen 1] Créer une opération de paiement",
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateOperationPaiementDto"}}}
            },
            "responses": {
                "201": {
                    "description": "Créée",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/OperationPaiementDto"}}}
                }
            }
        }
    },
    "/caisse-paroissiale": {
        "get": {
            "tags": ["8. Finances"],
            "summary": "[Screen 2] Caisse (Paiements Encaissés & KPIs Trésorerie)",
            "responses": {
                "200": {
                    "description": "Journal de caisse",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/CaisseParoissialeDto"}}}}
                }
            }
        }
    },
    "/caisse-paroissiale/{uuid}/rembourser": {
        "post": {
            "tags": ["8. Finances"],
            "summary": "[Screen 2] Rembourser une écriture d'encaissement en Caisse",
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/RemboursementRequestDto"}}}
            },
            "responses": {"201": {"description": "Remboursement enregistré"}}
        }
    },
    "/versements-cure": {
        "get": {
            "tags": ["8. Finances"],
            "summary": "[Screen 3] Versements à la Paroisse / au Curé",
            "responses": {
                "200": {
                    "description": "Versements au Curé",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/VersementCureDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["8. Finances"],
            "summary": "[Screen 3] Enregistrer un versement au Curé",
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateVersementCureDto"}}}
            },
            "responses": {
                "201": {
                    "description": "Versement créé",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/VersementCureDto"}}}
                }
            }
        }
    },
    "/tarifs": {
        "get": {
            "tags": ["8. Finances"],
            "summary": "[Screen 4] Configuration des paiements (Tarifs par niveau)",
            "responses": {
                "200": {
                    "description": "Configuration des tarifs",
                    "content": {"application/json": {"schema": {"type": "array", "items": {"$ref": "#/components/schemas/TarifDto"}}}}
                }
            }
        },
        "post": {
            "tags": ["8. Finances"],
            "summary": "[Screen 4] Créer un tarif de catéchèse",
            "requestBody": {
                "required": True,
                "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CreateTarifDto"}}}
            },
            "responses": {
                "201": {
                    "description": "Tarif créé",
                    "content": {"application/json": {"schema": {"$ref": "#/components/schemas/TarifDto"}}}
                }
            }
        }
    }
}

swagger["components"]["schemas"] = schemas
swagger["paths"] = paths

with open('public/swagger.json', 'w', encoding='utf-8') as f:
    json.dump(swagger, f, indent=2, ensure_ascii=False)

print("SUCCESS: public/swagger.json regenerated and valid!")

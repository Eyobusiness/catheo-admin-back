import json

def update_swagger():
    swagger_path = 'public/swagger.json'
    with open(swagger_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    paths = data.get('paths', {})
    schemas = data.get('components', {}).get('schemas', {})

    # 1. Schemas definitions
    schemas['ChangePasswordRequestDto'] = {
        "type": "object",
        "required": ["current_password", "password", "password_confirmation"],
        "properties": {
            "current_password": {
                "type": "string",
                "format": "password",
                "description": "Mot de passe actuel de l'utilisateur",
                "example": "AncienPassword123"
            },
            "password": {
                "type": "string",
                "format": "password",
                "minLength": 8,
                "description": "Nouveau mot de passe souhaité (minimum 8 caractères)",
                "example": "NouveauPassword2026!"
            },
            "password_confirmation": {
                "type": "string",
                "format": "password",
                "description": "Confirmation du nouveau mot de passe",
                "example": "NouveauPassword2026!"
            }
        }
    }

    schemas['ForgotPasswordRequestDto'] = {
        "type": "object",
        "required": ["email"],
        "properties": {
            "email": {
                "type": "string",
                "format": "email",
                "description": "Adresse email liée au compte utilisateur",
                "example": "admin.stpaul@catheo.ci"
            }
        }
    }

    schemas['VerifyResetCodeRequestDto'] = {
        "type": "object",
        "required": ["email", "code"],
        "properties": {
            "email": {
                "type": "string",
                "format": "email",
                "description": "Adresse email de l'utilisateur",
                "example": "admin.stpaul@catheo.ci"
            },
            "code": {
                "type": "string",
                "minLength": 6,
                "maxLength": 6,
                "description": "Code de vérification à 6 chiffres reçu par email",
                "example": "481923"
            }
        }
    }

    schemas['ResetPasswordRequestDto'] = {
        "type": "object",
        "required": ["email", "code", "password", "password_confirmation"],
        "properties": {
            "email": {
                "type": "string",
                "format": "email",
                "description": "Adresse email du compte",
                "example": "admin.stpaul@catheo.ci"
            },
            "code": {
                "type": "string",
                "minLength": 6,
                "maxLength": 6,
                "description": "Code OTP de vérification à 6 chiffres",
                "example": "481923"
            },
            "password": {
                "type": "string",
                "format": "password",
                "minLength": 8,
                "description": "Nouveau mot de passe (minimum 8 caractères)",
                "example": "MonNouveauMotDePasse2026!"
            },
            "password_confirmation": {
                "type": "string",
                "format": "password",
                "description": "Confirmation identique du nouveau mot de passe",
                "example": "MonNouveauMotDePasse2026!"
            },
            "device_name": {
                "type": "string",
                "description": "Nom du terminal ou application cliente (optionnel)",
                "example": "Angular_Client_App"
            }
        }
    }

    schemas['RefreshTokenRequestDto'] = {
        "type": "object",
        "properties": {
            "device_name": {
                "type": "string",
                "description": "Nom du terminal / client (optionnel)",
                "example": "Angular_Web_Client"
            }
        }
    }

    # 2. Paths definitions
    # Refresh
    paths['/auth/refresh'] = {
        "post": {
            "tags": ["1. Authentification & Compte"],
            "summary": "[POST] Rafraîchissement & Rotation du jeton d'authentification",
            "description": "Révoque le jeton d'accès Sanctum actuel et en génère un nouveau pour prolonger la session de façon sécurisée.",
            "security": [{"bearerAuth": []}],
            "requestBody": {
                "required": False,
                "content": {
                    "application/json": {
                        "schema": {"$ref": "#/components/schemas/RefreshTokenRequestDto"},
                        "example": {"device_name": "Angular_Web_Client"}
                    }
                }
            },
            "responses": {
                "200": {
                    "description": "Nouveau jeton Sanctum et profil utilisateur",
                    "content": {
                        "application/json": {
                            "example": {
                                "status": "success",
                                "message": "Token rafraîchi avec succès.",
                                "data": {
                                    "token": "49|newSanctumTokenValue...",
                                    "token_type": "Bearer",
                                    "user_type": "admin",
                                    "user": {
                                        "id": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d4c",
                                        "name": "Père Administrateur Saint-Paul",
                                        "email": "admin.stpaul@catheo.ci",
                                        "telephone": "+225 0700000001",
                                        "statut": "actif"
                                    },
                                    "menus": []
                                }
                            }
                        }
                    }
                },
                "401": {
                    "description": "Non authentifié (Jeton Bearer manquant ou expiré)",
                    "content": {
                        "application/json": {
                            "schema": {"$ref": "#/components/schemas/UnauthenticatedResponseDto"}
                        }
                    }
                }
            }
        }
    }

    # Change Password
    paths['/auth/change-password'] = {
        "post": {
            "tags": ["1. Authentification & Compte"],
            "summary": "[POST] Modification du mot de passe (Utilisateur connecté)",
            "description": "Permet à l'utilisateur connecté de modifier son mot de passe actuel en fournissant son ancien mot de passe et le nouveau.",
            "security": [{"bearerAuth": []}],
            "requestBody": {
                "required": True,
                "content": {
                    "application/json": {
                        "schema": {"$ref": "#/components/schemas/ChangePasswordRequestDto"},
                        "example": {
                            "current_password": "AncienPassword123",
                            "password": "NouveauPassword2026!",
                            "password_confirmation": "NouveauPassword2026!"
                        }
                    }
                }
            },
            "responses": {
                "200": {
                    "description": "Mot de passe modifié avec succès",
                    "content": {
                        "application/json": {
                            "schema": {"$ref": "#/components/schemas/ApiResponse"},
                            "example": {
                                "status": "success",
                                "message": "Mot de passe modifié avec succès."
                            }
                        }
                    }
                },
                "400": {
                    "description": "Mot de passe actuel incorrect ou erreurs de validation",
                    "content": {
                        "application/json": {
                            "schema": {"$ref": "#/components/schemas/ValidationErrorResponseDto"},
                            "example": {
                                "status": "error",
                                "message": "Les données fournies sont invalides.",
                                "errors": {
                                    "current_password": ["Le mot de passe actuel est incorrect."]
                                }
                            }
                        }
                    }
                },
                "401": {
                    "description": "Non authentifié",
                    "content": {
                        "application/json": {
                            "schema": {"$ref": "#/components/schemas/UnauthenticatedResponseDto"}
                        }
                    }
                }
            }
        }
    }

    # Forgot Password
    paths['/auth/forgot-password'] = {
        "post": {
            "tags": ["1. Authentification & Compte"],
            "summary": "[POST] Mot de passe oublié - Demande d'envoi du code OTP à 6 chiffres",
            "description": "Initie la procédure de réinitialisation en générant un code de sécurité à 6 chiffres envoyé par email (valable 15 minutes).",
            "requestBody": {
                "required": True,
                "content": {
                    "application/json": {
                        "schema": {"$ref": "#/components/schemas/ForgotPasswordRequestDto"},
                        "example": {
                            "email": "admin.stpaul@catheo.ci"
                        }
                    }
                }
            },
            "responses": {
                "200": {
                    "description": "Code OTP envoyé avec succès",
                    "content": {
                        "application/json": {
                            "example": {
                                "status": "success",
                                "message": "Un code de réinitialisation à 6 chiffres a été envoyé à votre adresse email.",
                                "data": {
                                    "email": "admin.stpaul@catheo.ci",
                                    "expires_in_minutes": 15
                                }
                            }
                        }
                    }
                },
                "400": {
                    "description": "Email manquant ou invalide / Aucun compte associé",
                    "content": {
                        "application/json": {
                            "schema": {"$ref": "#/components/schemas/ValidationErrorResponseDto"},
                            "example": {
                                "status": "error",
                                "message": "Les données fournies sont invalides.",
                                "errors": {
                                    "email": ["Aucun compte n'est associé à cette adresse email."]
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    # Verify Code
    paths['/auth/verify-code'] = {
        "post": {
            "tags": ["1. Authentification & Compte"],
            "summary": "[POST] Vérification du code OTP à 6 chiffres",
            "description": "Vérifie la validité et la non-expiration du code OTP à 6 chiffres avant l'étape de saisie du nouveau mot de passe.",
            "requestBody": {
                "required": True,
                "content": {
                    "application/json": {
                        "schema": {"$ref": "#/components/schemas/VerifyResetCodeRequestDto"},
                        "example": {
                            "email": "admin.stpaul@catheo.ci",
                            "code": "481923"
                        }
                    }
                }
            },
            "responses": {
                "200": {
                    "description": "Code de vérification valide",
                    "content": {
                        "application/json": {
                            "example": {
                                "status": "success",
                                "message": "Code de réinitialisation valide.",
                                "data": {
                                    "email": "admin.stpaul@catheo.ci",
                                    "code_valid": True
                                }
                            }
                        }
                    }
                },
                "422": {
                    "description": "Code incorrect, expiré ou demande introuvable",
                    "content": {
                        "application/json": {
                            "example": {
                                "status": "error",
                                "message": "Le code de réinitialisation à 6 chiffres est invalide."
                            }
                        }
                    }
                }
            }
        }
    }

    # Reset Password
    paths['/auth/reset-password'] = {
        "post": {
            "tags": ["1. Authentification & Compte"],
            "summary": "[POST] Réinitialisation définitive du mot de passe & Auto-connexion",
            "description": "Réinitialise le mot de passe avec le code OTP validé, détruit le code, révoque les anciennes sessions et renvoie un nouveau jeton Bearer valide.",
            "requestBody": {
                "required": True,
                "content": {
                    "application/json": {
                        "schema": {"$ref": "#/components/schemas/ResetPasswordRequestDto"},
                        "example": {
                            "email": "admin.stpaul@catheo.ci",
                            "code": "481923",
                            "password": "MonNouveauMotDePasse2026!",
                            "password_confirmation": "MonNouveauMotDePasse2026!",
                            "device_name": "Angular_App"
                        }
                    }
                }
            },
            "responses": {
                "200": {
                    "description": "Mot de passe réinitialisé & Session ouverte",
                    "content": {
                        "application/json": {
                            "example": {
                                "status": "success",
                                "message": "Votre mot de passe a été réinitialisé avec succès.",
                                "data": {
                                    "token": "50|newSanctumTokenAfterReset...",
                                    "token_type": "Bearer",
                                    "user_type": "admin",
                                    "user": {
                                        "id": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d4c",
                                        "name": "Père Administrateur Saint-Paul",
                                        "email": "admin.stpaul@catheo.ci",
                                        "telephone": "+225 0700000001",
                                        "statut": "actif"
                                    },
                                    "menus": []
                                }
                            }
                        }
                    }
                },
                "422": {
                    "description": "Code invalide ou expiré, ou confirmation du mot de passe erronée",
                    "content": {
                        "application/json": {
                            "example": {
                                "status": "error",
                                "message": "Le code de réinitialisation à 6 chiffres est invalide."
                            }
                        }
                    }
                }
            }
        }
    }

    # Save formatted JSON back
    with open(swagger_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    print("Swagger successfully updated!")

if __name__ == '__main__':
    update_swagger()

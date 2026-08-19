import json

def update_classes_in_swagger():
    swagger_path = 'public/swagger.json'
    with open(swagger_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    schemas = data.get('components', {}).get('schemas', {})

    # ClasseDto
    schemas['ClasseDto'] = {
        "type": "object",
        "required": ["id", "nom", "capacite_max", "statut"],
        "properties": {
            "id": {
                "type": "string",
                "format": "uuid",
                "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d31"
            },
            "nom": {
                "type": "string",
                "example": "Classe Sainte-Thérèse A"
            },
            "capacite_max": {
                "type": "integer",
                "example": 30
            },
            "statut": {
                "type": "string",
                "enum": ["active", "inactive"],
                "example": "active"
            },
            "effectif_actuel": {
                "type": "integer",
                "example": 25
            },
            "niveau": {
                "$ref": "#/components/schemas/NiveauDto"
            },
            "annee_catechese": {
                "$ref": "#/components/schemas/AnneeCatecheseDto"
            },
            "created_at": {
                "type": "string",
                "format": "date-time",
                "example": "2026-08-17T12:00:00Z"
            }
        },
        "example": {
            "id": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d31",
            "nom": "Classe Sainte-Thérèse A",
            "capacite_max": 30,
            "statut": "active",
            "effectif_actuel": 25
        }
    }

    # CreateClasseDto
    schemas['CreateClasseDto'] = {
        "type": "object",
        "required": ["niveau_id", "nom"],
        "properties": {
            "niveau_id": {
                "type": "string",
                "format": "uuid",
                "description": "UUID du niveau pédagogique",
                "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d22"
            },
            "annee_catechese_id": {
                "type": "string",
                "format": "uuid",
                "description": "UUID de l'année pastorale (optionnel, par défaut l'année active)",
                "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d11"
            },
            "nom": {
                "type": "string",
                "description": "Nom de la classe",
                "example": "Classe Sainte-Thérèse A"
            },
            "capacite_max": {
                "type": "integer",
                "description": "Capacité maximale d'élèves (défaut: 30)",
                "example": 30
            },
            "statut": {
                "type": "string",
                "enum": ["active", "inactive"],
                "description": "Statut de la classe",
                "example": "active"
            }
        },
        "example": {
            "niveau_id": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d22",
            "nom": "Classe Sainte-Thérèse A",
            "capacite_max": 30
        }
    }

    # UpdateClasseDto
    schemas['UpdateClasseDto'] = {
        "type": "object",
        "properties": {
            "niveau_id": {
                "type": "string",
                "format": "uuid",
                "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d22"
            },
            "annee_catechese_id": {
                "type": "string",
                "format": "uuid",
                "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d11"
            },
            "nom": {
                "type": "string",
                "example": "Classe Sainte-Thérèse A"
            },
            "capacite_max": {
                "type": "integer",
                "example": 35
            },
            "statut": {
                "type": "string",
                "enum": ["active", "inactive"],
                "example": "active"
            }
        }
    }

    # Update path examples
    paths = data.get('paths', {})
    if '/classes' in paths and 'post' in paths['/classes']:
        post_op = paths['/classes']['post']
        if 'requestBody' in post_op:
            content = post_op['requestBody'].get('content', {}).get('application/json', {})
            content['schema'] = {"$ref": "#/components/schemas/CreateClasseDto"}
            content['example'] = {
                "niveau_id": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d22",
                "nom": "Classe Sainte-Thérèse A",
                "capacite_max": 30
            }

    with open(swagger_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    print("Classes updated in Swagger successfully!")

if __name__ == '__main__':
    update_classes_in_swagger()

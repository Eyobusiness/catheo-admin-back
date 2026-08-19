import json

def update_niveaux_in_swagger():
    swagger_path = 'public/swagger.json'
    with open(swagger_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    schemas = data.get('components', {}).get('schemas', {})

    # Update NiveauDto
    if 'NiveauDto' in schemas:
        schemas['NiveauDto'] = {
            "type": "object",
            "required": [
                "id",
                "nom",
                "section_id"
            ],
            "properties": {
                "id": {
                    "type": "string",
                    "format": "uuid",
                    "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d22"
                },
                "section_id": {
                    "type": "string",
                    "format": "uuid",
                    "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d21"
                },
                "nom": {
                    "type": "string",
                    "example": "1ère Année d'Initiation"
                },
                "code": {
                    "type": "string",
                    "nullable": True,
                    "example": "INIT_1"
                },
                "description": {
                    "type": "string",
                    "nullable": True,
                    "example": "Niveau d'initiation chrétienne"
                },
                "statut": {
                    "type": "string",
                    "example": "Actif"
                },
                "duree_annees": {
                    "type": "integer",
                    "example": 1
                },
                "ordre_affichage": {
                    "type": "integer",
                    "example": 1
                },
                "section": {
                    "$ref": "#/components/schemas/SectionDto"
                },
                "created_at": {
                    "type": "string",
                    "format": "date-time",
                    "example": "2026-08-17T12:00:00Z"
                }
            },
            "example": {
                "id": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d22",
                "nom": "1ère Année d'Initiation",
                "code": "INIT_1",
                "description": "Niveau d'initiation chrétienne",
                "statut": "Actif",
                "duree_annees": 1,
                "ordre_affichage": 1
            }
        }

    # Update CreateNiveauDto
    if 'CreateNiveauDto' in schemas:
        schemas['CreateNiveauDto'] = {
            "type": "object",
            "required": [
                "nom",
                "section_id"
            ],
            "properties": {
                "section_id": {
                    "type": "string",
                    "format": "uuid",
                    "description": "UUID de la section parente",
                    "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d21"
                },
                "nom": {
                    "type": "string",
                    "description": "Nom du niveau",
                    "example": "2ème Année d'Initiation"
                },
                "code": {
                    "type": "string",
                    "nullable": True,
                    "description": "Code optionnel du niveau",
                    "example": "INIT_2"
                },
                "description": {
                    "type": "string",
                    "nullable": True,
                    "description": "Description du niveau",
                    "example": "Formation de base pour catéchumènes"
                },
                "statut": {
                    "type": "string",
                    "enum": ["actif", "inactif"],
                    "description": "Statut du niveau",
                    "example": "actif"
                },
                "duree_annees": {
                    "type": "integer",
                    "description": "Durée en années",
                    "example": 1
                },
                "ordre_affichage": {
                    "type": "integer",
                    "description": "Ordre d'affichage",
                    "example": 2
                }
            },
            "example": {
                "section_id": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d21",
                "nom": "2ème Année d'Initiation",
                "description": "Formation de base pour catéchumènes",
                "statut": "actif",
                "duree_annees": 1,
                "ordre_affichage": 2
            }
        }

    # Update paths example if needed
    paths = data.get('paths', {})
    if '/niveaux' in paths and 'post' in paths['/niveaux']:
        post_op = paths['/niveaux']['post']
        if 'requestBody' in post_op:
            content = post_op['requestBody'].get('content', {}).get('application/json', {})
            if 'example' in content:
                content['example'] = {
                    "section_id": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d21",
                    "nom": "1ère Année d'Initiation",
                    "description": "Premier niveau de catéchèse",
                    "statut": "actif",
                    "duree_annees": 1,
                    "ordre_affichage": 1
                }

    # Check and clean any other age_minimum/age_maximum across all schemas
    def clean_obj(obj):
        if isinstance(obj, dict):
            obj.pop('age_minimum', None)
            obj.pop('age_maximum', None)
            obj.pop('age_min', None)
            obj.pop('age_max', None)
            for k, v in list(obj.items()):
                clean_obj(v)
        elif isinstance(obj, list):
            for item in obj:
                clean_obj(item)

    clean_obj(data['components']['schemas'].get('NiveauDto', {}))
    clean_obj(data['components']['schemas'].get('CreateNiveauDto', {}))

    with open(swagger_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    print("Successfully updated Niveau in Swagger!")

if __name__ == '__main__':
    update_niveaux_in_swagger()

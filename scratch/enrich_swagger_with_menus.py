import re

with open('scratch/build_complete_swagger.py', 'r', encoding='utf-8') as f:
    content = f.read()

menu_dto_str = '''    "MenuDto": {
        "type": "object",
        "required": ["id", "libelle", "reference"],
        "properties": {
            "id": {"type": "string", "format": "uuid", "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d02"},
            "uuid": {"type": "string", "format": "uuid", "example": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d02"},
            "libelle": {"type": "string", "example": "Catéchumènes"},
            "icon": {"type": "string", "example": "bi bi-people"},
            "path": {"type": "string", "example": "#"},
            "reference": {"type": "string", "example": "main_catechumenes"},
            "ordre": {"type": "integer", "example": 2},
            "is_active": {"type": "boolean", "example": True},
            "permissions": {
                "type": "object",
                "properties": {
                    "create": {"type": "boolean", "example": True},
                    "read": {"type": "boolean", "example": True},
                    "update": {"type": "boolean", "example": True},
                    "delete": {"type": "boolean", "example": True},
                    "restore": {"type": "boolean", "example": True},
                    "force_delete": {"type": "boolean", "example": False}
                }
            }
        },
        "example": {
            "id": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d02",
            "uuid": "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d02",
            "libelle": "Catéchumènes",
            "icon": "bi bi-people",
            "path": "#",
            "reference": "main_catechumenes",
            "ordre": 2,
            "permissions": {
                "create": True,
                "read": True,
                "update": True,
                "delete": True,
                "restore": True,
                "force_delete": False
            }
        }
    },
'''

if '"MenuDto":' not in content:
    content = content.replace('    # 1. Auth DTOs', menu_dto_str + '\n    # 1. Auth DTOs')

menu_path_str = '''    "/menus": {
        "get": {
            "tags": ["2. Profils & Utilisateurs"],
            "summary": "[GET] Liste hiérarchique de tous les 13 menus et sous-menus configurés",
            "description": "Renvoie la liste ordonnée des menus officiels avec icônes, chemins et sous-menus pour le frontend.",
            "security": [{"bearerAuth": []}],
            "responses": std_responses("200", "Catalogue complet des menus", "MenuDto", is_array=True, has_400=False)
        }
    },
'''

if '"/menus":' not in content:
    content = content.replace('    "/profils/permissions-tree":', menu_path_str + '    "/profils/permissions-tree":')

with open('scratch/build_complete_swagger.py', 'w', encoding='utf-8') as f:
    f.write(content)

print("build_complete_swagger.py updated with MenuDto and /menus endpoint!")

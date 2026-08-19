import json

def remove_old_activites_from_swagger():
    swagger_path = 'public/swagger.json'
    with open(swagger_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    paths = data.get('paths', {})
    schemas = data.get('components', {}).get('schemas', {})

    # Routes to remove
    routes_to_remove = [
        '/types-activites',
        '/types-activites/{id}',
        '/activites',
        '/activites/{id}',
        '/activites/{id}/status'
    ]

    for r in routes_to_remove:
        if r in paths:
            del paths[r]

    # Schemas to remove
    schemas_to_remove = [
        'TypeActiviteDto',
        'CreateTypeActiviteDto',
        'ActiviteDto',
        'CreateActiviteDto',
        'UpdateActiviteStatusDto'
    ]

    for s in schemas_to_remove:
        if s in schemas:
            del schemas[s]

    with open(swagger_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    print("Successfully removed old types-activites and activites from Swagger!")

if __name__ == '__main__':
    remove_old_activites_from_swagger()

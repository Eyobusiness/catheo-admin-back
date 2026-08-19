import json
import copy

def add_patch_methods():
    swagger_path = 'public/swagger.json'
    with open(swagger_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    paths = data.get('paths', {})

    targets = [
        '/activites/{activite}',
        '/animateurs/{animateur}',
        '/annee-catecheses/{annee}',
        '/catechumenes/{catechumene}',
        '/classes/{classe}',
        '/niveaux/{niveau}',
        '/sections/{section}'
    ]

    for t in targets:
        if t in paths:
            if 'put' in paths[t] and 'patch' not in paths[t]:
                patch_op = copy.deepcopy(paths[t]['put'])
                patch_op['summary'] = patch_op['summary'].replace('[PUT]', '[PATCH]').replace('Modification', 'Mise à jour partielle')
                paths[t]['patch'] = patch_op
                print(f"Added PATCH to {t}")

    with open(swagger_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    print("All PATCH methods added!")

if __name__ == '__main__':
    add_patch_methods()

import json
import copy

def add_patch_to_all_put_endpoints():
    swagger_path = 'public/swagger.json'
    with open(swagger_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    paths = data.get('paths', {})

    count = 0
    for path, methods in list(paths.items()):
        if 'put' in methods and 'patch' not in methods:
            patch_op = copy.deepcopy(methods['put'])
            patch_op['summary'] = patch_op.get('summary', '').replace('[PUT]', '[PATCH]').replace('Modification', 'Mise à jour partielle')
            methods['patch'] = patch_op
            count += 1

    with open(swagger_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    print(f"Added PATCH to {count} endpoints in Swagger!")

if __name__ == '__main__':
    add_patch_to_all_put_endpoints()

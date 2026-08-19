import subprocess
import json

def check_and_sync():
    res = subprocess.run(['php', 'artisan', 'route:list', '--json'], capture_output=True, text=True, cwd='.')
    routes = json.loads(res.stdout)

    api_routes = []
    for r in routes:
        uri = r['uri']
        if uri.startswith('api/v1/'):
            clean_uri = '/' + uri[len('api/v1/'):]
            api_routes.append({
                'method': r['method'],
                'uri': clean_uri,
                'action': r['action']
            })

    with open('public/swagger.json', 'r', encoding='utf-8') as f:
        swagger = json.load(f)

    paths = swagger.get('paths', {})

    print(f"Total API routes in Laravel: {len(api_routes)}")
    print(f"Total paths in Swagger: {len(paths)}")

    # Check for missing paths
    missing = []
    for ar in api_routes:
        # standardise uri
        uri = ar['uri']
        methods = [m.lower() for m in ar['method'].split('|') if m.lower() in ['get', 'post', 'put', 'patch', 'delete']]
        
        found = False
        # direct match or parameter converted match
        # in swagger params are {param}
        if uri in paths:
            for m in methods:
                if m in paths[uri]:
                    found = True
        else:
            # check if path with param name mismatch exists
            import re
            # replace {var} with regex
            pattern = re.sub(r'\{[^\}]+\}', r'{[^}]+}', uri)
            for sp in paths:
                if re.fullmatch(pattern, sp):
                    for m in methods:
                        if m in paths[sp]:
                            found = True
        if not found:
            missing.append((ar['method'], ar['uri'], ar['action']))

    print(f"Missing in swagger: {len(missing)}")
    for m in missing:
        print(f"  {m[0]} {m[1]} -> {m[2]}")

if __name__ == '__main__':
    check_and_sync()

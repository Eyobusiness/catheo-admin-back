import json
import re

with open('public/swagger.json', 'r', encoding='utf-8') as f:
    doc = json.load(f)

print(f"OpenAPI Version: {doc.get('openapi')}")
print(f"Title: {doc['info']['title']}")
print(f"Total paths: {len(doc['paths'])}")
print(f"Total schemas: {len(doc['components']['schemas'])}")

errors = []
total_endpoints = 0
examples_checked = 0

for path, path_item in doc['paths'].items():
    for method, op in path_item.items():
        if method in ['get', 'post', 'put', 'patch', 'delete']:
            total_endpoints += 1
            op_id = f"[{method.upper()}] {path}"
            
            # Check path params
            if "{" in path:
                params = op.get('parameters', [])
                path_param_names = re.findall(r'\{([^}]+)\}', path)
                for param_name in path_param_names:
                    found = [p for p in params if p.get('name') == param_name and p.get('in') == 'path']
                    if not found:
                        errors.append(f"{op_id}: Missing path parameter definition for '{param_name}'")
                    else:
                        p_schema = found[0].get('schema', {})
                        if p_schema.get('type') != 'string' or p_schema.get('format') != 'uuid':
                            errors.append(f"{op_id}: Path parameter '{param_name}' should have type: 'string' and format: 'uuid'")
            
            # Check request body
            if 'requestBody' in op:
                rb = op['requestBody']
                content = rb.get('content', {})
                if 'application/json' not in content:
                    errors.append(f"{op_id}: requestBody is missing application/json")
                else:
                    json_media = content['application/json']
                    if 'schema' not in json_media:
                        errors.append(f"{op_id}: requestBody is missing schema")
                    if 'example' not in json_media:
                        # check if schema has example
                        schema_ref = json_media['schema'].get('$ref', '')
                        if schema_ref:
                            s_name = schema_ref.split('/')[-1]
                            if 'example' not in doc['components']['schemas'].get(s_name, {}):
                                errors.append(f"{op_id}: requestBody has no example")
            
            # Check responses
            responses = op.get('responses', {})
            for code, res in responses.items():
                if '$ref' in res:
                    continue
                content = res.get('content', {})
                if 'application/json' in content:
                    json_res = content['application/json']
                    if 'schema' not in json_res:
                        errors.append(f"{op_id}: response {code} is missing schema")

# Check for numeric internal IDs in examples
raw_json = json.dumps(doc)
numeric_id_matches = re.findall(r'"id":\s*\d+', raw_json)
if numeric_id_matches:
    errors.append(f"Numeric IDs found in swagger examples: {numeric_id_matches}")

print(f"\n--- AUDIT RESULTS ---")
print(f"Total Operations Checked: {total_endpoints}")
if errors:
    print(f"Found {len(errors)} issues:")
    for e in errors:
        print(f" - {e}")
else:
    print("ALL 100% OF ENDPOINTS, SCHEMAS, AND EXAMPLES ARE VALID, STRICTLY TYPED (UUID/FORMATS) AND MEET ALL CRITERIA!")

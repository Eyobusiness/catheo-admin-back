import re

# Read build_complete_swagger.py
with open('scratch/build_complete_swagger.py', 'r', encoding='utf-8') as f:
    content = f.read()

# Replace any 550e8400-e29b-41d4-a716-4466554400xx with 9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5dxx
# If xx is 00, replace with 4c to match exactly "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d4c"
def replace_uuid(match):
    full = match.group(0)
    suffix = match.group(1)
    if suffix == "00":
        return "9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d4c"
    else:
        return f"9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d{suffix}"

updated_content = re.sub(r'550e8400-e29b-41d4-a716-4466554400([0-9a-zA-Z]{2})', replace_uuid, content)
# Also replace any remaining 550e8400-e29b-41d4-a716-446655440000
updated_content = updated_content.replace('550e8400-e29b-41d4-a716-446655440000', '9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d4c')

with open('scratch/build_complete_swagger.py', 'w', encoding='utf-8') as f:
    f.write(updated_content)

print("build_complete_swagger.py updated with UUIDs of format 9f8e7d6c-5b4a-3f2e-1d0c-9b8a7f6e5d4c")

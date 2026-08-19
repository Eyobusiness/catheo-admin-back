import glob
import re

def refactor_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content

    # Replace _uuid with _id in field names/keys
    content = re.sub(r'\b([a-zA-Z0-9_]+)_uuid\b', r'\1_id', content)

    # In catheo-dtos.ts and Resource files and Tests: replace standalone uuid key/prop with id
    # e.g., 'uuid' => $this->uuid  -> 'id' => $this->uuid
    content = re.sub(r"['\"]uuid['\"]\s*=>\s*\$this->uuid", r"'id' => $this->uuid", content)
    # e.g. "uuid": { -> "id": { in JSON/dict schemas
    content = re.sub(r"['\"]uuid['\"]\s*:", r"'id':", content)
    # e.g. uuid: string; -> id: string; in TypeScript DTOs
    content = re.sub(r"\buuid(\??)\s*:\s*string\b", r"id\1: string", content)

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Refactored: {filepath}")

# Refactor catheo-dtos.ts
refactor_file("catheo-dtos.ts")

# Refactor Resources
for f in glob.glob("app/Http/Resources/Api/V1/*.php"):
    refactor_file(f)

# Refactor Controllers & Requests
for f in glob.glob("app/Http/Controllers/Api/V1/*.php"):
    refactor_file(f)
for f in glob.glob("app/Http/Requests/Api/V1/*.php"):
    refactor_file(f)

# Refactor Tests
for f in glob.glob("tests/Feature/*.php"):
    refactor_file(f)

# Refactor Swagger builder
if glob.glob("scratch/build_clean_swagger.py"):
    refactor_file("scratch/build_clean_swagger.py")

print("FINISHED REFACTORING uuid -> id & _uuid -> _id")

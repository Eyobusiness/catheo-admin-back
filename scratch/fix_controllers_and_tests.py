import glob
import re

# 1. Remove invalid unset($validated['..._id']) from controllers
for filepath in glob.glob("app/Http/Controllers/Api/V1/*.php"):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content

    # Patterns like: unset($validated['annee_catechese_id'], $validated['niveau_id']);
    # When an id field is mapped to $model->id, we must not unset it!
    # Let's inspect each line with unset and fix it
    lines = content.split('\n')
    new_lines = []
    for line in lines:
        if 'unset($validated[' in line:
            # Parse targets inside unset($validated['...'])
            targets = re.findall(r"\$validated\['([^']+)'\]", line)
            # Remove targets ending in _id or matching specific FKs that are assigned
            # Keep array targets like sections_uuids / sections_ids if they are many-to-many pivots
            remove_targets = []
            for t in targets:
                if t.endswith('_id') and not t.endswith('_ids'):
                    remove_targets.append(t)
            
            remaining_targets = [t for t in targets if t not in remove_targets]
            if remaining_targets:
                rem_str = ", ".join([f"$validated['{t}']" for t in remaining_targets])
                new_lines.append(f"        unset({rem_str});")
            else:
                # Completely remove line
                continue
        else:
            new_lines.append(line)

    content = '\n'.join(new_lines)

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Fixed unsets in controller: {filepath}")

# 2. Fix tests: replace any remaining 'uuid' / "uuid" assertions with 'id' / "id"
for filepath in glob.glob("tests/Feature/*.php"):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content

    content = re.sub(r"['\"]uuid['\"]", "'id'", content)

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Fixed test assertions in: {filepath}")

print("FINISHED FIXING CONTROLLERS AND TESTS")

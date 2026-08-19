import json

try:
    with open('public/swagger.json', 'r', encoding='utf-8') as f:
        data = json.load(f)
    print("VALID JSON!")
except json.JSONDecodeError as e:
    print(f"JSON ERROR: {e.msg} at line {e.lineno}, col {e.colno}, pos {e.pos}")
    with open('public/swagger.json', 'r', encoding='utf-8') as f:
        lines = f.readlines()
        start = max(0, e.lineno - 5)
        end = min(len(lines), e.lineno + 5)
        for i in range(start, end):
            print(f"{i+1}: {lines[i]}", end='')

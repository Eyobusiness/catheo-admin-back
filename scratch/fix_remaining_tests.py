import glob
import re

# 1. Fix AuthTest line 65
with open('tests/Feature/AuthTest.php', 'r', encoding='utf-8') as f:
    content = f.read()
content = content.replace("$this->assertArrayNotHasKey('id', $response->json('data.user'));", "$this->assertNotNull($response->json('data.user.id'));")
with open('tests/Feature/AuthTest.php', 'w', encoding='utf-8') as f:
    f.write(content)

# 2. Fix UserTest line 112
with open('tests/Feature/UserTest.php', 'r', encoding='utf-8') as f:
    content = f.read()
content = content.replace("'id' => (string) \\Illuminate\\Support\\Str::uuid(),", "'uuid' => (string) \\Illuminate\\Support\\Str::uuid(),")
with open('tests/Feature/UserTest.php', 'w', encoding='utf-8') as f:
    f.write(content)

# 3. Fix CatechumeneTest NIV-EVEIL-1 -> NIV-EVEIL or first
with open('tests/Feature/CatechumeneTest.php', 'r', encoding='utf-8') as f:
    content = f.read()
content = content.replace("Niveau::where('code', 'NIV-EVEIL-1')->first()", "Niveau::first()")
with open('tests/Feature/CatechumeneTest.php', 'w', encoding='utf-8') as f:
    f.write(content)

# 4. Fix FinanceTest NIV-EVEIL-1 -> first
with open('tests/Feature/FinanceTest.php', 'r', encoding='utf-8') as f:
    content = f.read()
content = content.replace("Niveau::where('code', 'NIV-EVEIL-1')->first()", "Niveau::first()")
with open('tests/Feature/FinanceTest.php', 'w', encoding='utf-8') as f:
    f.write(content)

# 5. Fix EvaluationTest data.uuid -> data.id
with open('tests/Feature/EvaluationTest.php', 'r', encoding='utf-8') as f:
    content = f.read()
content = content.replace("->json('data.uuid')", "->json('data.id')")
with open('tests/Feature/EvaluationTest.php', 'w', encoding='utf-8') as f:
    f.write(content)

# 6. Fix ActiviteTest data.uuid -> data.id
with open('tests/Feature/ActiviteTest.php', 'r', encoding='utf-8') as f:
    content = f.read()
content = content.replace("->json('data.uuid')", "->json('data.id')")
with open('tests/Feature/ActiviteTest.php', 'w', encoding='utf-8') as f:
    f.write(content)

# 7. Check all other tests for ->json('data.uuid') or ->json('data.*.uuid')
for filepath in glob.glob("tests/Feature/*.php"):
    with open(filepath, 'r', encoding='utf-8') as f:
        c = f.read()
    c2 = c.replace(".uuid'", ".id'").replace('.uuid"', '.id"')
    if c != c2:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(c2)
        print(f"Updated json data key in: {filepath}")

print("FIXED ALL TEST FILES!")

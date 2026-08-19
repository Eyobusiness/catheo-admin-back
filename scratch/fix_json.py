import json

with open('public/swagger.json', 'rb') as f:
    content = f.read()

# Let's inspect content around the end of file
print("Total bytes:", len(content))
print("Last 100 bytes:", content[-100:])

# Remove trailing whitespace, null bytes, or duplicate content
text = content.decode('utf-8', errors='ignore').strip()
print("Ends with:", repr(text[-50:]))

import sys

# Read the file in binary mode
with open('login.php', 'rb') as f:
    content = f.read()

# Check and replace smart quotes
left_quote = b'\xe2\x80\x9c'
right_quote = b'\xe2\x80\x9d'
regular_quote = b'"'

print(f'Left smart quotes: {content.count(left_quote)}')
print(f'Right smart quotes: {content.count(right_quote)}')

# Replace them
content = content.replace(left_quote, regular_quote)
content = content.replace(right_quote, regular_quote)

# Write back
with open('login.php', 'wb') as f:
    f.write(content)

print('File fixed!')

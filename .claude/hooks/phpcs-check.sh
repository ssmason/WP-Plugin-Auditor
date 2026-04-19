#!/bin/bash

# 1. Read the JSON Claude passes in — contains the file that was just written
INPUT=$(cat)
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

# 2. If it's not a PHP file, do nothing
if [[ "$FILE_PATH" != *.php ]]; then
    exit 0
fi

# 3. Run PHPCS against that specific file
RESULT=$(./vendor/bin/phpcs --standard=WordPress --report=emacs "$FILE_PATH" 2>&1)
EXIT_CODE=$?

# 4. If violations found, output them and exit 2
# exit 2 = surfaces error to Claude and forces it to fix
if [ $EXIT_CODE -ne 0 ]; then
    echo "PHPCS WordPress violations in $FILE_PATH:"
    echo "$RESULT"
    exit 2
fi

exit 0
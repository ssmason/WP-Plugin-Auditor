#!/bin/bash

# 1. Read the JSON Claude passes in — contains the file that was just written
INPUT=$(cat)
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

# 2. If it's not a JavaScript or TypeScript file, do nothing
if [[ "$FILE_PATH" != *.js && "$FILE_PATH" != *.ts ]]; then
    exit 0
fi

# 3. Run ESLint against that specific file
RESULT=$(./node_modules/.bin/eslint "$FILE_PATH" 2>&1)
EXIT_CODE=$?

# 4. If violations found, output them and exit 2
# exit 2 = surfaces error to Claude and forces it to fix
if [ $EXIT_CODE -ne 0 ]; then
    echo "ESLint violations in $FILE_PATH:"
    echo "$RESULT"
    exit 2
fi

exit 0
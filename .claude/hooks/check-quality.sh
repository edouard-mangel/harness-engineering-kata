#!/bin/bash
# Runs quality checks for each language whose config file is present.
# Presence of the config (e.g. typescript/.eslintrc.json) is the opt-in signal.
# Exit 0 = pass, exit 1 = violations found (with details on stdout).

ROOT=$(git rev-parse --show-toplevel 2>/dev/null)
VIOLATIONS=""

# ===================== TypeScript =====================
if [ -d "$ROOT/typescript/src" ] && [ -f "$ROOT/typescript/.eslintrc.json" ]; then
  cd "$ROOT/typescript" || true

  if [ -x node_modules/.bin/eslint ]; then
    ESLINT=$(node_modules/.bin/eslint src/ --ext .ts 2>&1)
    [ -n "$ESLINT" ] && VIOLATIONS+="$ESLINT"$'\n'
  fi

  while IFS= read -r f; do
    LINES=$(wc -l < "$f")
    if [ "$LINES" -gt 185 ]; then
      VIOLATIONS+="$f:1:1: FILE-LENGTH File has $LINES lines (max 185)"$'\n'
    fi
  done < <(find src/ -name "*.ts" ! -name "*.d.ts")
fi

if [ -n "$VIOLATIONS" ]; then
  echo "$VIOLATIONS"
  exit 1
fi

exit 0

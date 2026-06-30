#!/usr/bin/env bash
# Extract translatable strings from PHP and JS/Vue/TS sources into a POT file,
# then merge the updated POT into every existing language PO file.
#
# Usage: ./1_extract.sh
# Run from anywhere; the script locates itself.

set -euo pipefail

APP_ID="olvid"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "$(dirname "$0")/../../" && pwd)"
TRANS_DIR="$ROOT_DIR/translation"
POT="$TRANS_DIR/templates/$APP_ID.pot"

# Directories to exclude from PHP extraction (relative to ROOT_DIR)
EXCLUDE_DIRS=(
    build js tests img css
    composer openapi vendor-bin
    node_modules dist vendor
    translations translationfiles
)

mkdir -p "$TRANS_DIR/templates"
rm -f "$POT"
# --join-existing requires the output file to already exist; write a minimal seed
printf 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n' > "$POT"

# Build the -prune arguments for find
PRUNE_ARGS=()
for dir in "${EXCLUDE_DIRS[@]}"; do
    PRUNE_ARGS+=(-path "$ROOT_DIR/$dir" -prune -o)
done

XGETTEXT_COMMON=(
    xgettext
    --output="$POT"
    --join-existing
    --add-comments=TRANSLATORS
    --from-code=UTF-8
    --package-name="$APP_ID"
)

# --- PHP files ---
echo "Extracting PHP strings..."
while IFS= read -r -d '' file; do
    echo "  - $file" && "${XGETTEXT_COMMON[@]}" \
        --keyword=t --keyword=n:1,2 \
        --language=PHP \
        "$file" 2>/dev/null
done < <(find "$ROOT_DIR/appinfo" "$ROOT_DIR/lib" "$ROOT_DIR/templates" "${PRUNE_ARGS[@]}" -name "*.php" -print0)

# --- JS / TS / Vue files ---
# In Nextcloud JS: t(appId, string) → string is arg 2
#                  n(appId, singular, plural, count) → singular/plural are args 2,3
echo "Extracting JS/Vue/TS strings..."
while IFS= read -r -d '' file; do
    echo "  - $file" && "${XGETTEXT_COMMON[@]}" \
        --keyword=t:2 --keyword=n:2,3 \
        --language=Javascript \
        "$file" 2>/dev/null
done < <(find "$ROOT_DIR/src" \( -name "*.js" -o -name "*.ts" -o -name "*.vue" \) -print0)

echo "POT written: $POT"

# --- Merge into existing PO files ---
shopt -s nullglob
for lang_dir in "$TRANS_DIR"/*/; do
    lang="$(basename "$lang_dir")"
    [[ "${#lang}" -ne 2 ]] && continue

    po="$lang_dir$APP_ID.po"
    if [[ -f "$po" ]]; then
        echo "Merging  → $lang/$APP_ID.po"
        msgmerge --update --backup=none --quiet "$po" "$POT"
    else
        echo "Creating → $lang/$APP_ID.po"
        msginit --no-translator --input="$POT" --locale="$lang" --output="$po"
    fi
done

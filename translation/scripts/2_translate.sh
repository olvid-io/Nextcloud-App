#!/usr/bin/env bash
# Translate empty and fuzzy msgstr entries in <lang>/olvid.po using a local Ollama model.
#
# Usage: ./2_translate.sh [-m model] [lang...]
#   -m model   Ollama model to use (default: llama3.2)
#   lang...    Languages to process (default: all except 'en' and 'templates')
#
# Requires: OLLAMA_HOST env var (default: localhost), python3 with ollama package

set -euo pipefail

APP_ID="olvid"
ROOT_DIR="$(cd "$(dirname "$0")/../../" && pwd)"
TRANS_DIR="$ROOT_DIR/translation"
SCRIPTS_DIR="$ROOT_DIR/translation/scripts"
MODEL="translategemma:27b"

while getopts "m:" opt; do
    case $opt in
        m) MODEL="$OPTARG" ;;
    esac
done
shift $((OPTIND - 1))

if [[ $# -gt 0 ]]; then
    LANGS=("$@")
else
    LANGS=()
    shopt -s nullglob
    for d in "$TRANS_DIR"/*/; do
        lang="$(basename "$d")"
        [[ "$lang" == "templates" || "$lang" == "scripts" || "$lang" == "en" ]] && continue
        LANGS+=("$lang")
    done
fi

OLLAMA_URL="http://${OLLAMA_HOST:-localhost}:11434"

# handle venv
python3 -m venv $SCRIPTS_DIR/.venv && source $SCRIPTS_DIR/.venv/bin/activate
pip3 install -r $SCRIPTS_DIR/requirements.txt

python3 - "$APP_ID" "$TRANS_DIR" "$OLLAMA_URL" "$MODEL" "${LANGS[@]}" <<'PYTHON'
import sys, re, os
from ollama import Client

APP_ID    = sys.argv[1]
TRANS_DIR = sys.argv[2]
OLLAMA_URL = sys.argv[3]
MODEL     = sys.argv[4]
LANGS     = sys.argv[5:]

LANG_NAMES = {
    "fr": "French", "de": "German", "es": "Spanish", "it": "Italian",
    "pt": "Portuguese", "nl": "Dutch", "pl": "Polish", "ru": "Russian",
    "ja": "Japanese", "zh": "Chinese", "ko": "Korean", "ar": "Arabic",
}

_QUOTED = re.compile(r'^"((?:[^"\\]|\\.)*)"$')

def po_unescape(s):
    out, i = [], 0
    while i < len(s):
        if s[i] == '\\' and i + 1 < len(s):
            c = s[i + 1]
            if   c == 'n':  out.append('\n')
            elif c == 't':  out.append('\t')
            elif c == 'r':  out.append('\r')
            elif c == '"':  out.append('"')
            elif c == '\\': out.append('\\')
            else:           out += ['\\', c]
            i += 2
        else:
            out.append(s[i]); i += 1
    return ''.join(out)

def po_escape(s):
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n').replace('\t', '\\t').replace('\r', '\\r')

def read_value(block, j):
    m = re.match(r'^[a-zA-Z_]+(?:\[\d+\])?\s+"((?:[^"\\]|\\.)*)"$', block[j])
    raw = m.group(1) if m else ''
    j += 1
    while j < len(block):
        cm = _QUOTED.match(block[j])
        if cm:
            raw += cm.group(1); j += 1
        else:
            break
    return po_unescape(raw), j

def parse_blocks(path):
    with open(path, encoding='utf-8') as f:
        lines = [l.rstrip('\n') for l in f]

    blocks, cur = [], []
    for line in lines:
        if line.strip():
            cur.append(line)
        elif cur:
            blocks.append(cur); cur = []
    if cur:
        blocks.append(cur)
    return blocks

def extract_entry(block):
    is_fuzzy = False
    msgid = msgid_pl = msgstr = None
    msgstr_pl = {}
    j = 0
    while j < len(block):
        line = block[j]
        if line.startswith('#,') and 'fuzzy' in line:
            is_fuzzy = True; j += 1
        elif line.startswith('msgid_plural '):
            msgid_pl, j = read_value(block, j)
        elif line.startswith('msgid '):
            msgid, j = read_value(block, j)
        elif line.startswith('msgstr['):
            m = re.match(r'^msgstr\[(\d+)\]', line)
            idx = int(m.group(1)) if m else 0
            val, j = read_value(block, j)
            msgstr_pl[idx] = val
        elif line.startswith('msgstr '):
            msgstr, j = read_value(block, j)
        else:
            j += 1
    return is_fuzzy, msgid, msgid_pl, msgstr, msgstr_pl

def needs_translation(is_fuzzy, msgid, msgstr, msgstr_pl):
    if not msgid:
        return False
    if is_fuzzy:
        return True
    if msgstr_pl:
        return not any(msgstr_pl.values())
    return msgstr == '' or msgstr is None

def translate_string(client, lang_name, text):
    if not text.strip():
        return text
    prompt = (
        f"Translate this UI string from English to {lang_name}.\n"
        "Return ONLY the translated string — no explanation, no quotes, no extra text.\n"
        "Keep placeholders like {name}, {n}, {error} unchanged. Keep emoji unchanged.\n\n"
        f"String: {text}"
    )
    response = client.generate(model=MODEL, prompt=prompt)
    return response.response.strip()

def rebuild_block(block, new_msgstr, new_msgstr_pl):
    out = []
    skip_next_continuation = False
    for line in block:
        if line.startswith('#, fuzzy'):
            continue
        if skip_next_continuation:
            if _QUOTED.match(line):
                continue
            else:
                skip_next_continuation = False

        if line.startswith('msgstr ') and new_msgstr is not None:
            out.append(f'msgstr "{po_escape(new_msgstr)}"')
            skip_next_continuation = True
        elif line.startswith('msgstr[') and new_msgstr_pl:
            m = re.match(r'^msgstr\[(\d+)\]', line)
            idx = int(m.group(1)) if m else 0
            if idx in new_msgstr_pl:
                out.append(f'msgstr[{idx}] "{po_escape(new_msgstr_pl[idx])}"')
                skip_next_continuation = True
            else:
                out.append(line)
        else:
            out.append(line)
    return out

def translate_po(path, lang):
    lang_name = LANG_NAMES.get(lang, lang)
    client = Client(host=OLLAMA_URL)
    blocks = parse_blocks(path)
    total = changed = 0

    result_blocks = []
    for block in blocks:
        is_fuzzy, msgid, msgid_pl, msgstr, msgstr_pl = extract_entry(block)

        if not needs_translation(is_fuzzy, msgid, msgstr, msgstr_pl):
            result_blocks.append(block)
            continue

        total += 1
        print(f"  [{total}] {msgid[:60]}{'…' if len(msgid) > 60 else ''}")

        if msgid_pl is not None:
            new_pl = {
                0: translate_string(client, lang_name, msgid),
                1: translate_string(client, lang_name, msgid_pl),
            }
            block = rebuild_block(block, None, new_pl)
        else:
            new_str = translate_string(client, lang_name, msgid)
            block = rebuild_block(block, new_str, {})
        changed += 1
        result_blocks.append(block)

    with open(path, 'w', encoding='utf-8') as f:
        for block in result_blocks:
            f.write('\n'.join(block) + '\n\n')

    print(f"  → {changed}/{total} entries translated")

for lang in LANGS:
    po = os.path.join(TRANS_DIR, lang, f'{APP_ID}.po')
    if not os.path.isfile(po):
        print(f"skip {lang}: {po} not found")
        continue
    print(f"\n=== {lang} ({LANG_NAMES.get(lang, lang)}) ===")
    translate_po(po, lang)

print("\nDone.")
PYTHON

#!/usr/bin/env bash
# Convert <lang>/olvid.po files into Nextcloud l10n format (l10n/<lang>.js + .json).
#
# Usage: ./3_convert.sh [lang...]
#   With no arguments, converts all language directories found here.
#   With arguments, converts only the listed languages (e.g. ./3_convert.sh fr en).

set -euo pipefail

APP_ID="olvid"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "$(dirname "$0")/../../" && pwd)"
TRANS_DIR="$ROOT_DIR/translation"
L10N_DIR="$ROOT_DIR/l10n"

mkdir -p "$L10N_DIR"

# If specific languages were requested, use those; otherwise discover all dirs.
if [[ $# -gt 0 ]]; then
    LANGS=("$@")
else
    LANGS=()
    shopt -s nullglob
    for d in "$TRANS_DIR"/*/; do
        lang="$(basename "$d")"
        [[ "$lang" == "templates" || "$lang" == "scripts" ]] && continue
        LANGS+=("$lang")
    done
fi

python3 - "$APP_ID" "$TRANS_DIR" "$L10N_DIR" "${LANGS[@]}" <<'PYTHON'
import sys, re, json, os
from collections import OrderedDict

APP_ID   = sys.argv[1]
TRANS    = sys.argv[2]
L10N     = sys.argv[3]
LANGS    = sys.argv[4:]

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

def read_value(block, j):
    """Return (unescaped string, next j) for the keyword line at block[j] + continuations."""
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

def parse_po(path):
    with open(path, encoding='utf-8') as f:
        lines = [l.rstrip('\n') for l in f]

    plural_form = 'nplurals=2; plural=(n != 1);'
    entries = OrderedDict()

    # Split into blank-line-separated blocks
    blocks, cur = [], []
    for line in lines:
        if line.strip():
            cur.append(line)
        elif cur:
            blocks.append(cur); cur = []
    if cur:
        blocks.append(cur)

    for block in blocks:
        is_fuzzy   = False
        msgid      = None
        msgid_pl   = None
        msgstr     = None
        msgstr_pl  = {}

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

        # Header entry — extract Plural-Forms
        if msgid == '' and msgid_pl is None:
            pf = re.search(r'Plural-Forms: ([^\n]+)', msgstr or '')
            if pf:
                plural_form = pf.group(1).strip()
            continue

        if is_fuzzy or msgid is None:
            continue

        if msgid_pl is not None:
            if msgstr_pl and any(msgstr_pl.values()):
                key = f'_{msgid}_::_{msgid_pl}_'
                entries[key] = [msgstr_pl.get(i, '') for i in sorted(msgstr_pl)]
        else:
            if msgstr:
                entries[msgid] = msgstr

    return plural_form, entries

def jdump(s):
    return json.dumps(s, ensure_ascii=False)

def fmt_entry(key, value):
    """Format one entry line (without trailing comma)."""
    k = jdump(key)
    if isinstance(value, list):
        v = '[' + ','.join(jdump(x) for x in value) + ']'
    else:
        v = jdump(value)
    return f'    {k} : {v}'

def write_js(app_id, entries, plural_form, path):
    lines = [fmt_entry(k, v) for k, v in entries.items()]
    with open(path, 'w', encoding='utf-8') as f:
        f.write('OC.L10N.register(\n')
        f.write(f'    "{app_id}",\n')
        f.write('    {\n')
        f.write(',\n'.join(lines))
        if lines:
            f.write('\n')
        f.write('},\n')
        f.write(f'"{plural_form}");\n')

def write_json(entries, plural_form, path):
    lines = [fmt_entry(k, v) for k, v in entries.items()]
    with open(path, 'w', encoding='utf-8') as f:
        f.write('{ "translations": {\n')
        f.write(',\n'.join(lines))
        if lines:
            f.write('\n')
        f.write(f'}},"pluralForm" :"{plural_form}"\n')
        f.write('}\n')

for lang in LANGS:
    po = os.path.join(TRANS, lang, f'{APP_ID}.po')
    if not os.path.isfile(po):
        print(f'  skip {lang}: {po} not found')
        continue
    plural_form, entries = parse_po(po)
    write_js  (APP_ID, entries, plural_form, os.path.join(L10N, f'{lang}.js'))
    write_json(entries, plural_form,          os.path.join(L10N, f'{lang}.json'))
    print(f'  {lang}: {len(entries)} strings → l10n/{lang}.{{js,json}}')

PYTHON

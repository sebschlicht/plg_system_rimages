#!/bin/bash
TARGET='plg_system_rimages.zip'
rm -f "$TARGET"
zip -q -9 -x "/.gitignore" \
    -x "/.git/*" \
    -x "/.idea/*" \
    -x "/mkzip.sh" \
    -x "/.mkzip.sh.swp" \
    -x "/*.md" \
    -x "/images/*" \
    -x "/updates/*" \
    -r "$TARGET" .


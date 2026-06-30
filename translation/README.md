Run ./scripts/1_extract.sh to extract strings in source files.
This will generate ./template/olvid.pot and merge it in existing <lang>/olvid.po files.

Run ./scripts/2_translate.sh to use ollama to translate strings in <lang>/olvid.po files.

Run ./scripts/3_convert.sh to convert <lang>/olvid.po files to translation files used by Nextcloud application.

echo "=== Translate to Spanish ==="
msginit --locale=es_ES --input=h2-product-insight.pot --output=es_ES.po
msgfmt h2-product-insight-es_ES.po --output-file=h2-product-insight-es_ES.mo

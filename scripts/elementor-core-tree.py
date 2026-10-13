#!/usr/bin/env python3
"""Huella del núcleo de Elementor incluido en DIGITALÍSIMO Elements.

Uso: python3 scripts/elementor-core-tree.py digitalisimo-elements/elementor

Imprime en JSON el número de archivos y el SHA-256 de la lista «suma  ruta» ordenada de toda la carpeta,
sin contar su manifiesto (digitalisimo-core.json). scripts/sync-elementor-core.sh la guarda en el manifiesto
al sincronizar; scripts/build-packages.sh y tests/validate-suite.mjs la vuelven a calcular para que ningún
archivo ajeno (por ejemplo, los que otros plugins copian dentro de la carpeta de Elementor) llegue al ZIP.
"""
import hashlib
import json
import os
import sys

MANIFEST = 'digitalisimo-core.json'


def tree(root):
    entries = []
    for base, dirs, files in os.walk(root):
        dirs.sort()
        for name in files:
            path = os.path.relpath(os.path.join(base, name), root).replace(os.sep, '/')
            if path == MANIFEST:
                continue
            with open(os.path.join(base, name), 'rb') as handle:
                entries.append((path, hashlib.sha256(handle.read()).hexdigest()))
    entries.sort()
    listing = ''.join('%s  %s\n' % (digest, path) for path, digest in entries)
    return {'files': len(entries), 'tree_sha256': hashlib.sha256(listing.encode('utf-8')).hexdigest()}


if __name__ == '__main__':
    if len(sys.argv) != 2 or not os.path.isdir(sys.argv[1]):
        sys.exit('Uso: elementor-core-tree.py <carpeta del núcleo>')
    print(json.dumps(tree(sys.argv[1])))

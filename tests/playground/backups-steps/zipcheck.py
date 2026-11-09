"""Comprobación del ZIP del ensayo T2 con un lector ajeno al plugin (zipfile de Python): lee cada entrada entera,
compara su SHA-256 y su tamaño con manifest.json y devuelve un resumen en JSON. Uso: python3 -I zipcheck.py <zip>"""
import hashlib
import json
import sys
import zipfile

path = sys.argv[1]
result = {"ok": False}
with zipfile.ZipFile(path) as archive:
    bad = archive.testzip()
    manifest = json.loads(archive.read("manifest.json"))
    names = [info.filename for info in archive.infolist()]
    mismatched = []
    total = 0
    for info in archive.infolist():
        if info.filename == "manifest.json":
            continue
        digest = hashlib.sha256()
        with archive.open(info) as handle:
            for chunk in iter(lambda: handle.read(1048576), b""):
                digest.update(chunk)
        expected = manifest["entries"].get(info.filename)
        total += info.file_size
        if not expected or expected["sha256"] != digest.hexdigest() or expected["size"] != info.file_size:
            mismatched.append(info.filename)
    result = {
        "ok": bad is None and not mismatched and len(manifest["entries"]) == len(names) - 1 and names[-1] == "manifest.json" and names[0] == "database.sql",
        "testzip": bad,
        "mismatched": mismatched[:10],
        "entries": len(names),
        "megabytes": round(total / 1048576, 1),
        "stored": sum(1 for info in archive.infolist() if info.compress_type == zipfile.ZIP_STORED),
        "deflated": sum(1 for info in archive.infolist() if info.compress_type == zipfile.ZIP_DEFLATED),
        "manifest": {key: manifest.get(key) for key in ("format", "packer", "database_consistency", "file_count", "uncompressed_size")},
    }
print(json.dumps(result))
sys.exit(0 if result["ok"] else 1)

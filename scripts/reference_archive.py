"""Lectura de un ZIP de referencia sin extraerlo ni ejecutar nada de él.

Antes de leer una sola entrada se valida el archivo completo y se rechaza entero si trae rutas absolutas o con `..`,
barras invertidas, enlaces simbólicos, entradas cifradas o duplicadas, demasiadas entradas, tamaños excesivos o una
razón de compresión propia de una bomba ZIP. Cada lectura vuelve a acotar los bytes, por si la cabecera miente.
Los scripts que lo usan se ejecutan con `python3 -I` para que nada del directorio actual se importe.
"""

import hashlib
import re
import stat
import zipfile
from pathlib import Path, PurePosixPath

MAX_ARCHIVE_BYTES = 256 * 1024 * 1024
MAX_ENTRIES = 20000
MAX_TOTAL_BYTES = 1024 * 1024 * 1024
MAX_ENTRY_BYTES = 16 * 1024 * 1024
MAX_RATIO = 100
RATIO_FLOOR_BYTES = 1024 * 1024


class UnsafeArchive(Exception):
    """El ZIP no se lee: alguna entrada no supera las comprobaciones."""


class ReferenceArchive:
    def __init__(self, path):
        path = Path(path)
        if path.is_symlink() or not path.is_file():
            raise UnsafeArchive(f'{path}: no es un archivo regular')
        size = path.stat().st_size
        if size > MAX_ARCHIVE_BYTES:
            raise UnsafeArchive(f'{path}: {size} bytes supera el máximo de {MAX_ARCHIVE_BYTES}')
        digest = hashlib.sha256()
        with path.open('rb') as handle:
            for chunk in iter(lambda: handle.read(1024 * 1024), b''):
                digest.update(chunk)
        self.path = path
        self.sha256 = digest.hexdigest()
        try:
            self._zip = zipfile.ZipFile(path)
        except zipfile.BadZipFile as error:
            raise UnsafeArchive(f'{path}: no es un ZIP válido ({error})') from error
        infos = self._zip.infolist()
        if len(infos) > MAX_ENTRIES:
            self._zip.close()
            raise UnsafeArchive(f'{path}: {len(infos)} entradas supera el máximo de {MAX_ENTRIES}')
        self._files = {}
        self._dirs = set()
        total = 0
        try:
            for info in infos:
                name = info.filename
                self._check_name(name)
                if stat.S_ISLNK(info.external_attr >> 16):
                    raise UnsafeArchive(f'{name}: enlace simbólico')
                if info.flag_bits & 0x1:
                    raise UnsafeArchive(f'{name}: entrada cifrada')
                if name in self._files or name in self._dirs:
                    raise UnsafeArchive(f'{name}: entrada duplicada')
                if info.file_size > MAX_ENTRY_BYTES:
                    raise UnsafeArchive(f'{name}: {info.file_size} bytes supera el máximo por entrada')
                if info.file_size > RATIO_FLOOR_BYTES and info.file_size > MAX_RATIO * max(info.compress_size, 1):
                    raise UnsafeArchive(f'{name}: razón de compresión excesiva')
                total += info.file_size
                if info.is_dir():
                    self._dirs.add(name)
                else:
                    self._files[name] = info
                parts = name.rstrip('/').split('/')
                for depth in range(1, len(parts)):
                    self._dirs.add('/'.join(parts[:depth]) + '/')
            if total > MAX_TOTAL_BYTES:
                raise UnsafeArchive(f'{path}: {total} bytes descomprimidos supera el máximo de {MAX_TOTAL_BYTES}')
        except UnsafeArchive as error:
            self._zip.close()
            raise UnsafeArchive(f'{path}: {error}') from None

    @staticmethod
    def _check_name(name):
        if not name or '\x00' in name or '\\' in name:
            raise UnsafeArchive(f'{name!r}: nombre no válido')
        if name.startswith('/') or PurePosixPath(name).is_absolute() or re.match(r'^[A-Za-z]:', name):
            raise UnsafeArchive(f'{name}: ruta absoluta')
        if any(part in ('..', '.') for part in name.rstrip('/').split('/')) or '//' in name:
            raise UnsafeArchive(f'{name}: ruta con «..», «.» o vacía')

    def __enter__(self):
        return self

    def __exit__(self, *_):
        self.close()

    def close(self):
        self._zip.close()

    def files(self, prefix=''):
        return sorted(name for name in self._files if name.startswith(prefix))

    def exists(self, name):
        return name in self._files

    def is_dir(self, name):
        return name.rstrip('/') + '/' in self._dirs

    def top_dirs(self):
        return sorted({name.split('/', 1)[0] for name in list(self._files) + list(self._dirs) if '/' in name})

    def read_bytes(self, name, limit=MAX_ENTRY_BYTES):
        info = self._files.get(name)
        if info is None:
            raise KeyError(name)
        if info.file_size > limit:
            raise UnsafeArchive(f'{name}: {info.file_size} bytes supera el límite de lectura')
        with self._zip.open(info) as handle:
            data = handle.read(limit + 1)
        if len(data) > limit:
            raise UnsafeArchive(f'{name}: la entrada descomprime más de lo que declara')
        return data

    def read_text(self, name, limit=MAX_ENTRY_BYTES):
        return self.read_bytes(name, limit).decode('utf-8', errors='replace')

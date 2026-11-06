#!/usr/bin/env python3
"""Descifra un respaldo cifrado de DIGITALÍSIMO Backups (contenedor DGBK v1) sin WordPress ni PHP.

Es una implementación independiente del formato descrito en docs/BACKUPS.md («Formato DGBK v1»): HChaCha20 escrito
aquí, ChaCha20-IETF y Poly1305 de la biblioteca `cryptography`, y crypto_secretstream_xchacha20poly1305 tal como lo
define libsodium. Sirve para recuperar el ZIP interior si el plugin no está disponible; verifica cada bloque, el orden,
la etiqueta FINAL, el fin del archivo y el SHA-256 interior, y no deja nada escrito si algo falla.

Uso (la clave o la frase nunca van en la línea de órdenes, que otros usuarios ven con `ps`):
  python3 -I scripts/dgbk-decrypt.py respaldo.dgbk salida.zip            # pide la frase o la clave por la terminal
  python3 -I scripts/dgbk-decrypt.py respaldo.dgbk salida.zip --secret-file secreto.txt
Requiere `pip install cryptography`.
"""
import argparse
import base64
import getpass
import hashlib
import json
import os
import struct
import sys

from cryptography.hazmat.primitives.ciphers import Cipher, algorithms
from cryptography.hazmat.primitives.poly1305 import Poly1305


def rotl(value, bits):
    return ((value << bits) & 0xFFFFFFFF) | (value >> (32 - bits))


def hchacha20(key, nonce16):
    s = [0x61707865, 0x3320646E, 0x79622D32, 0x6B206574] + list(struct.unpack('<8I', key)) + list(struct.unpack('<4I', nonce16))

    def quarter(a, b, c, d):
        s[a] = (s[a] + s[b]) & 0xFFFFFFFF; s[d] = rotl(s[d] ^ s[a], 16)
        s[c] = (s[c] + s[d]) & 0xFFFFFFFF; s[b] = rotl(s[b] ^ s[c], 12)
        s[a] = (s[a] + s[b]) & 0xFFFFFFFF; s[d] = rotl(s[d] ^ s[a], 8)
        s[c] = (s[c] + s[d]) & 0xFFFFFFFF; s[b] = rotl(s[b] ^ s[c], 7)

    for _ in range(10):
        quarter(0, 4, 8, 12); quarter(1, 5, 9, 13); quarter(2, 6, 10, 14); quarter(3, 7, 11, 15)
        quarter(0, 5, 10, 15); quarter(1, 6, 11, 12); quarter(2, 7, 8, 13); quarter(3, 4, 9, 14)
    return struct.pack('<8I', *(s[0:4] + s[12:16]))


def chacha20_xor(key, nonce12, counter, data):
    return Cipher(algorithms.ChaCha20(key, struct.pack('<I', counter) + nonce12), mode=None).encryptor().update(data)


def b64url(text, size):
    raw = base64.urlsafe_b64decode(text + '=' * (-len(text) % 4))
    if len(raw) != size:
        raise ValueError('valor base64 de tamaño inesperado')
    return raw


def blake2b(message, key, size):
    return hashlib.blake2b(message, key=key, digest_size=size).digest()


def root_key(descriptor, secret):
    """La clave raíz: base64 de 32 bytes (clave de wp-config.php) o la frase con su PBKDF2."""
    candidates = []
    try:
        raw = base64.b64decode(secret.strip().replace('-', '+').replace('_', '/') + '=' * (-len(secret.strip()) % 4), validate=True)
        if len(raw) == 32:
            candidates.append(raw)
    except ValueError:
        pass
    kdf = descriptor.get('kdf')
    if descriptor.get('source') == 'passphrase' and kdf:
        if kdf.get('alg') != 'pbkdf2-sha256' or not 100000 <= int(kdf['iterations']) <= 5000000:
            raise SystemExit('Parámetros de derivación no admitidos.')
        candidates.append(hashlib.pbkdf2_hmac('sha256', secret.encode('utf-8'), b64url(kdf['salt'], 16), int(kdf['iterations']), 32))
    for candidate in candidates:
        if blake2b(b'DGBK1/key-id', candidate, 16).hex() == descriptor['id']:
            return candidate
    raise SystemExit('La frase o la clave no corresponde a la clave %s del respaldo.' % descriptor['id'][:8])


def decrypt(source, target, secret):
    size = os.path.getsize(source)
    with open(source, 'rb') as handle:
        fixed = handle.read(12)
        if fixed[:4] != b'DGBK' or fixed[4] != 1 or fixed[5:8] != b'\0\0\0':
            raise SystemExit('No es un respaldo DGBK v1.')
        length = struct.unpack('>I', fixed[8:12])[0]
        if not 2 <= length <= 16384:
            raise SystemExit('Cabecera no válida.')
        raw_header = handle.read(length)
        header = json.loads(raw_header)
        prefix = fixed + raw_header
        chunk, inner_size = int(header['chunk_size']), int(header['inner_size'])
        if chunk < 4096 or chunk > 8388608 or chunk & (chunk - 1) or inner_size < 1:
            raise SystemExit('Cabecera no válida.')
        blocks = (inner_size + chunk - 1) // chunk
        if size != len(prefix) + 24 + inner_size + blocks * 17:
            raise SystemExit('El archivo está incompleto o tiene bytes de más.')
        root = root_key(header['key'], secret)
        key = blake2b(b'DGBK1/file|' + b64url(header['file_salt'], 32), root, 32)
        stream_header = handle.read(24)
        subkey = hchacha20(key, stream_header[:16])
        nonce = bytearray(struct.pack('<I', 1) + stream_header[16:24])
        digest = hashlib.sha256()
        partial = target + '.part'
        with open(partial, 'xb') as output:
            try:
                written = 0
                for index in range(blocks):
                    mlen = min(chunk, inner_size - written)
                    block = handle.read(1 + mlen + 16)
                    if len(block) != 1 + mlen + 16:
                        raise SystemExit('El archivo está incompleto.')
                    ad = prefix if index == 0 else b''
                    poly = Poly1305(chacha20_xor(subkey, bytes(nonce), 0, b'\0' * 64)[:32])
                    poly.update(ad + b'\0' * ((0x10 - len(ad)) & 0xF))
                    tag_block = chacha20_xor(subkey, bytes(nonce), 1, b'\0' * 64)
                    tag = block[0] ^ tag_block[0]
                    body = block[1:1 + mlen]
                    poly.update(bytes([block[0]]) + tag_block[1:])
                    poly.update(body + b'\0' * ((0x10 - 64 + mlen) & 0xF))
                    poly.update(struct.pack('<Q', len(ad)) + struct.pack('<Q', 64 + mlen))
                    mac = poly.finalize()
                    if mac != block[1 + mlen:] or tag != (3 if index == blocks - 1 else 0):
                        raise SystemExit('El respaldo fue modificado o está dañado (bloque %d).' % (index + 1))
                    plain = chacha20_xor(subkey, bytes(nonce), 2, body)
                    digest.update(plain)
                    output.write(plain)
                    written += mlen
                    nonce[4:12] = bytes(x ^ y for x, y in zip(nonce[4:12], mac[:8]))
                    nonce[0:4] = struct.pack('<I', (struct.unpack('<I', bytes(nonce[0:4]))[0] + 1) & 0xFFFFFFFF)
                if handle.read(1) or digest.hexdigest() != header['inner_sha256']:
                    raise SystemExit('El respaldo fue modificado o está dañado.')
            except BaseException:
                output.close()
                os.unlink(partial)
                raise
    os.rename(partial, target)
    return header


def main():
    parser = argparse.ArgumentParser(description='Descifra un respaldo DGBK v1 de DIGITALÍSIMO Backups.')
    parser.add_argument('source')
    parser.add_argument('target')
    parser.add_argument('--secret-file', help='archivo con la frase o la clave base64 (sin salto final)')
    args = parser.parse_args()
    if os.path.exists(args.target):
        raise SystemExit('El destino ya existe.')
    if args.secret_file:
        with open(args.secret_file, 'r', encoding='utf-8') as handle:
            secret = handle.read().rstrip('\n')
    else:
        secret = getpass.getpass('Frase o clave del respaldo: ')
    header = decrypt(args.source, args.target, secret)
    print('ZIP interior verificado: %s (%d bytes, respaldo %s).' % (args.target, header['inner_size'], header['backup_id']))


if __name__ == '__main__':
    sys.exit(main())

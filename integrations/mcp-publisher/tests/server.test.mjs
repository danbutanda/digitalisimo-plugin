import test from 'node:test';
import assert from 'node:assert/strict';
import { makeMcpServer } from '../server.mjs';
import { readSites } from '../wordpress.mjs';

test('registra herramientas del servidor MCP con el SDK real', () => {
  const sites = readSites(JSON.stringify([{
    id: 'demo', url: 'https://example.com',
    username: 'editor', applicationPassword: 'fake-test-password'
  }]));
  const server = makeMcpServer(sites);
  assert.ok(server);
  assert.equal(typeof server.connect, 'function');
});

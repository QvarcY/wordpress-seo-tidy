import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/worker.js';

const env = {
  PUBLIC_ORIGIN: 'https://wall.seo-tidy.dev',
  TURNSTILE_SITE_KEY: 'public-test-key',
  DB: { prepare(sql) { return { bind(...args) { return this; }, async all() { return { results: [] }; } }; } },
  ASSETS: { fetch: async () => new Response('homepage') }
};
const get = (url, init) => worker.fetch(new Request(url, init), env);

test('static homepage served through asset binding', async () => {
  const response = await get('https://wall.seo-tidy.dev/');
  assert.equal(response.status, 200);
  assert.equal(await response.text(), 'homepage');
});
test('only approved entries queried and no secrets returned', async () => {
  const response = await get('https://wall.seo-tidy.dev/api/sites');
  assert.equal(response.status, 200);
  assert.deepEqual((await response.json()).items, []);
});
test('registration never works without configured Turnstile', async () => {
  const response = await get('https://wall.seo-tidy.dev/api/apply', {
    method: 'POST', headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ name: 'Example Site', url: 'https://example.org',
      description: 'A website', consent: true, turnstileToken: 'dummy' })
  });
  assert.equal(response.status, 403);
});
test('registration requires consent', async () => {
  const response = await get('https://wall.seo-tidy.dev/api/apply', {
    method: 'POST', headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ name: 'Example Site', url: 'https://example.org',
      description: 'A website', consent: false })
  });
  assert.equal(response.status, 400);
});
test('rejects insecure URL and invalid domains', async () => {
  for (const url of ['http://example.org', 'https://127.0.0.1/', 'https://localhost/', 'https://example.org:444/']) {
    const response = await get('https://wall.seo-tidy.dev/api/apply', {
      method: 'POST', headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ name: 'Example Site', url, description: 'Website', consent: true })
    });
    assert.equal(response.status, 400, url);
  }
});
test('moderation requires administrator token', async () => {
  const response = await get('https://wall.seo-tidy.dev/api/admin/submissions');
  assert.equal(response.status, 401);
});
test('rejects cross-origin requests', async () => {
  const response = await get('https://wall.seo-tidy.dev/api/sites', {
    headers: { origin: 'https://malicious.example' }
  });
  assert.equal(response.status, 403);
});

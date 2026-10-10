// SEO-TidY Wall of Fame — standalone Cloudflare Worker
const json = (data, status = 200, extra = {}) => new Response(JSON.stringify(data), {
  status, headers: { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-store',
    'x-content-type-options': 'nosniff', ...extra }
});
const error = (message, status = 400) => json({ error: message }, status);
const hash = async (value) => [...new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(value)))].map(x => x.toString(16).padStart(2, '0')).join('');
const token = () => [...crypto.getRandomValues(new Uint8Array(32))].map(x => x.toString(16).padStart(2, '0')).join('');
const safe = (s, max) => typeof s === 'string' ? s.trim().slice(0, max) : '';
const NAME = /^[\p{L}\p{N} .,'()&@_-]{2,100}$/u;
const HOST = /^(?=.{1,253}$)(?!-)[a-z0-9-]+(?:\.[a-z0-9-]+)+$/;
function siteUrl(raw) {
  try {
    const u = new URL(raw);
    if (u.protocol !== 'https:' || u.username || u.password || u.port || u.search || u.hash) return null;
    const host = u.hostname.toLowerCase().replace(/\.$/, '');
    if (!HOST.test(host) || host.endsWith('.localhost') || host.endsWith('.local') || host.endsWith('.internal') ||
        host.endsWith('.test') || host.endsWith('.invalid') || host.endsWith('.example') ||
        /^\d+\.\d+\.\d+\.\d+$/.test(host) || host.includes('..')) return null;
    return { host, url: 'https://' + host + '/' };
  } catch { return null; }
}
function cors(request, env) {
  const origin = request.headers.get('origin');
  if (!origin) return null;
  const expected = new URL(env.PUBLIC_ORIGIN).origin;
  return origin === expected ? null : error('Origin not allowed', 403);
}
async function body(request) {
  const type = request.headers.get('content-type') || '';
  if (!type.startsWith('application/json')) throw new Error('Expected JSON');
  if (Number(request.headers.get('content-length') || 0) > 8192) throw new Error('Request too large');
  const raw = await request.text();
  if (raw.length > 8192) throw new Error('Request too large');
  return JSON.parse(raw);
}
async function captcha(request, env, response) {
  if (!env.TURNSTILE_SECRET) return false;
  const form = new FormData();
  form.set('secret', env.TURNSTILE_SECRET);
  form.set('response', safe(response, 2048));
  const ip = request.headers.get('CF-Connecting-IP');
  if (ip) form.set('remoteip', ip);
  try {
    const res = await fetch('https://challenges.cloudflare.com/turnstile/v0/siteverify',
      { method: 'POST', body: form });
    const result = await res.json();
    return result.success === true && (!result.hostname || result.hostname === new URL(env.PUBLIC_ORIGIN).hostname);
  } catch { return false; }
}
async function dnsVerified(host, challenge) {
  const res = await fetch('https://cloudflare-dns.com/dns-query?name=' +
    encodeURIComponent('_seo-tidy.' + host) + '&type=TXT', {
    headers: { accept: 'application/dns-json' }, redirect: 'error'
  });
  if (!res.ok) return false;
  const result = await res.json();
  return Array.isArray(result.Answer) && result.Answer.some(x =>
    x.type === 16 && typeof x.data === 'string' &&
    x.data.replace(/^"|"$/g, '').replace(/"\s*"/g, '') === 'seo-tidy-verification=' + challenge);
}
async function owner(request, env, input) {
  const id = safe(input.id, 36);
  const secret = safe(input.secret, 128);
  if (!/^[a-f0-9-]{36}$/.test(id) || !/^[a-f0-9]{64}$/.test(secret)) return null;
  const secretHash = await hash(secret);
  return env.DB.prepare('SELECT * FROM submissions WHERE id = ? AND owner_hash = ?')
    .bind(id, secretHash).first();
}
async function isAdmin(request, env) {
  if (!env.ADMIN_TOKEN || env.ADMIN_TOKEN.length < 32) return false;
  const supplied = request.headers.get('authorization') || '';
  if (!supplied.startsWith('Bearer ') || supplied.length > 256) return false;
  const a = new TextEncoder().encode(await hash(supplied.slice(7)));
  const b = new TextEncoder().encode(await hash(env.ADMIN_TOKEN));
  if (a.length !== b.length) return false;
  let mismatch = 0;
  for (let i = 0; i < a.length; i++) mismatch |= a[i] ^ b[i];
  return mismatch === 0;
}
function publicEntry(r) {
  return { name: r.name, url: r.url, description: r.description, joinedAt: r.approved_at };
}
const headers = { 'x-content-type-options': 'nosniff', 'referrer-policy': 'no-referrer', 'x-frame-options': 'DENY',
  'content-security-policy': "default-src 'none'; frame-ancestors 'none'; base-uri 'none'" };
export default {
  async fetch(request, env) {
    const u = new URL(request.url), path = u.pathname;
    if (!path.startsWith('/api/')) return env.ASSETS.fetch(request);
    if (!env.DB || !env.PUBLIC_ORIGIN) return error('Service not configured', 503);
    if (request.method === 'OPTIONS') return new Response(null, { status: 405, headers });
    const originError = cors(request, env);
    if (originError) return originError;
    try {
      if (path === '/api/config' && request.method === 'GET') {
        return json({ turnstileSiteKey: env.TURNSTILE_SITE_KEY || '' });
      }
      if (path === '/api/sites' && request.method === 'GET') {
        const page = Math.min(100, Math.max(1, Number.parseInt(u.searchParams.get('page') || '1', 10) || 1));
        const rows = await env.DB.prepare(
          "SELECT name, url, description, approved_at FROM submissions WHERE status = 'approved' ORDER BY approved_at DESC, id DESC LIMIT 13 OFFSET ?"
        ).bind((page - 1) * 12).all();
        return json({ items: rows.results.slice(0, 12).map(publicEntry), hasMore: rows.results.length > 12, page },
          200, { 'cache-control': 'public, max-age=60' });
      }
      if (path === '/api/apply' && request.method === 'POST') {
        const input = await body(request), name = safe(input.name, 101);
        const description = safe(input.description, 301), site = siteUrl(input.url);
        if (!NAME.test(name) || !description || description.length > 300 || !site)
          return error('Invalid name, description or HTTPS site URL');
        if (input.consent !== true) return error('Explicit consent required');
        if (!(await captcha(request, env, input.turnstileToken))) return error('Bot verification failed', 403);
        const existing = await env.DB.prepare(
          "SELECT id FROM submissions WHERE host = ? AND status != 'rejected'"
        ).bind(site.host).first();
        if (existing) return error('This domain already has an active application', 409);
        const id = crypto.randomUUID(), ownerSecret = token(), challenge = token();
        try {
          await env.DB.prepare(
            "INSERT INTO submissions (id, host, name, url, description, owner_hash, challenge, status, consent_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', datetime('now'))"
          ).bind(id, site.host, name, site.url, description, await hash(ownerSecret), challenge).run();
        } catch (e) {
          if (String(e).includes('UNIQUE')) return error('This domain already has an active application', 409);
          throw e;
        }
        return json({ id, ownerSecret, dnsName: '_seo-tidy.' + site.host,
          dnsValue: 'seo-tidy-verification=' + challenge, status: 'pending' }, 201);
      }
      if (path === '/api/verify' && request.method === 'POST') {
        const input = await body(request), row = await owner(request, env, input);
        if (!row) return error('Application not found', 404);
        if (row.status === 'approved') return json({ status: 'approved' });
        if (row.status === 'rejected') return json({ status: 'rejected' });
        if (!(await dnsVerified(row.host, row.challenge)))
          return error('DNS TXT record not found yet', 409);
        await env.DB.prepare(
          "UPDATE submissions SET status = 'verified', verified_at = datetime('now') WHERE id = ? AND status IN ('pending','verified')"
        ).bind(row.id).run();
        return json({ status: 'verified', message: 'Domain verified; awaiting review' });
      }
      if (path === '/api/status' && request.method === 'POST') {
        const row = await owner(request, env, await body(request));
        return row ? json({ status: row.status, name: row.name, url: row.url }) : error('Application not found', 404);
      }
      if (path === '/api/remove' && request.method === 'POST') {
        const row = await owner(request, env, await body(request));
        if (!row) return error('Application not found', 404);
        await env.DB.prepare('DELETE FROM submissions WHERE id = ?').bind(row.id).run();
        return json({ removed: true });
      }
      if (path.startsWith('/api/admin/')) {
        if (!(await isAdmin(request, env))) return error('Unauthorized', 401);
        if (path === '/api/admin/submissions' && request.method === 'GET') {
          const rows = await env.DB.prepare(
            "SELECT id, host, name, url, description, status, consent_at, verified_at, approved_at FROM submissions ORDER BY consent_at DESC LIMIT 200"
          ).all();
          return json({ items: rows.results });
        }
        if (path === '/api/admin/moderate' && request.method === 'POST') {
          const input = await body(request), id = safe(input.id, 36), decision = input.decision;
          if (!/^[a-f0-9-]{36}$/.test(id) || !['approve', 'reject', 'delete'].includes(decision))
            return error('Invalid moderation request');
          const row = await env.DB.prepare('SELECT * FROM submissions WHERE id = ?').bind(id).first();
          if (!row) return error('Application not found', 404);
          if (decision === 'delete') {
            await env.DB.prepare('DELETE FROM submissions WHERE id = ?').bind(id).run();
          } else if (decision === 'reject') {
            await env.DB.prepare("UPDATE submissions SET status = 'rejected', approved_at = NULL WHERE id = ?")
              .bind(id).run();
          } else {
            if (row.status !== 'verified' && row.status !== 'approved')
              return error('Domain has not been verified', 409);
            if (!(await dnsVerified(row.host, row.challenge)))
              return error('DNS proof expired or missing', 409);
            await env.DB.prepare(
              "UPDATE submissions SET status = 'approved', approved_at = COALESCE(approved_at, datetime('now')) WHERE id = ?"
            ).bind(id).run();
          }
          return json({ ok: true, status: decision });
        }
      }
      return error('Not found', 404);
    } catch (e) {
      if (e instanceof SyntaxError || e.message === 'Expected JSON' || e.message === 'Request too large')
        return error('Invalid request payload');
      console.error('Wall of Fame request failed', path);
      return error('Server error', 500);
    }
  }
};

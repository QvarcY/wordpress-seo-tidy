const $ = id => document.getElementById(id);
const message = text => { $('admin-message').textContent = text; };
async function api(path, body) {
  const res = await fetch(path, {
    method: body ? 'POST' : 'GET', credentials: 'same-origin',
    headers: { ...(body ? {'content-type':'application/json', 'x-seo-tidy-admin':'1'} : {}) },
    ...(body ? {body:JSON.stringify(body)} : {})
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Request failed');
  return data;
}
async function login(token) {
  const res = await fetch('api/admin/login', { method:'POST',credentials:'same-origin',
    headers:{'content-type':'application/json'},body:JSON.stringify({token}) });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Invalid token');
  await load();
}
function textNode(type, value) { const el = document.createElement(type); el.textContent = value; return el; }
function card(item) {
  const element = document.createElement('article'); element.className = 'site-card';
  const h = textNode('h3', item.name); const host = textNode('small', item.host);
  const desc = textNode('p', item.description); const status = textNode('p', 'Status: ' + item.status);
  const actions = document.createElement('div'); actions.className = 'buttons';
  for (const decision of ['approve','reject','delete']) {
    const button = textNode('button', decision);
    button.type = 'button';
    if (decision === 'delete') button.className = 'danger';
    button.addEventListener('click', async () => {
      if (!window.confirm(decision + ' ' + item.host + '?')) return;
      button.disabled = true;
      try { await api('api/admin/moderate', {id:item.id,decision}); await load(); }
      catch (e) { message(e.message); button.disabled = false; }
    });
    actions.append(button);
  }
  element.append(h,host,desc,status,actions); return element;
}
function profileCard(item) {
  const el = document.createElement('article'); el.className = 'site-card';
  const title = textNode('h3', item.host);
  const info = textNode('p', item.short_description + ' — ' + item.category);
  const full = textNode('p', item.long_description);
  const tags = textNode('small', item.tags_json);
  const buttons = document.createElement('div'); buttons.className = 'buttons';
  for (const decision of ['approve','reject']) {
    const btn = textNode('button', decision); btn.type = 'button';
    btn.addEventListener('click', async () => {
      if (!confirm(decision + ' profile for ' + item.host + '?')) return;
      btn.disabled = true;
      try { await api('api/admin/profile-moderate',{id:Number(item.id),decision}); await load(); }
      catch(e) { message(e.message); btn.disabled = false; }
    });
    buttons.append(btn);
  }
  el.append(title,info,full,tags,buttons);
  return el;
}
async function loadProfiles() {
  const data = await api('api/admin/profiles');
  $('profiles').replaceChildren(...data.items.map(profileCard));
}
async function load() {
  try {
    const data = await api('api/admin/submissions');
    $('applications').replaceChildren(...data.items.map(card));
    await loadProfiles();
    message(data.items.length + ' applications loaded');
  } catch(e) { message(e.message); }
}
$('load').addEventListener('click', async () => {
  const key = $('admin-token').value;
  $('admin-token').value = '';
  try { await login(key); $('login-panel').hidden = true; }
  catch(e) { message(e.message); }
});
$('clear').addEventListener('click', async () => {
  try { await api('api/admin/logout', {}); } catch {}
  $('applications').replaceChildren();
  $('profiles').replaceChildren();
  $('login-panel').hidden = false;
  message('Signed out');
});
api('api/admin/session').then(data => {
  if (data.authenticated) { $('login-panel').hidden = true; load(); }
}).catch(() => message('Could not check login session'));

let token = '';
const $ = id => document.getElementById(id);
const message = text => { $('admin-message').textContent = text; };
async function api(path, body) {
  const res = await fetch(path, {
    method: body ? 'POST' : 'GET', credentials:'omit',
    headers: { authorization: 'Bearer ' + token, ...(body ? {'content-type':'application/json'} : {}) },
    ...(body ? {body:JSON.stringify(body)} : {})
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Request failed');
  return data;
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
      try { await api('/api/admin/moderate', {id:item.id,decision}); await load(); }
      catch (e) { message(e.message); button.disabled = false; }
    });
    actions.append(button);
  }
  element.append(h,host,desc,status,actions); return element;
}
async function load() {
  try {
    const data = await api('/api/admin/submissions');
    $('applications').replaceChildren(...data.items.map(card));
    message(data.items.length + ' applications loaded');
  } catch(e) { message(e.message); }
}
$('load').addEventListener('click', () => {
  token = $('admin-token').value;
  $('admin-token').value = '';
  if (token.length < 32) { token = ''; message('Invalid token'); return; }
  load();
});
$('clear').addEventListener('click', () => {
  token = ''; $('admin-token').value = ''; $('applications').replaceChildren(); message('Key cleared');
});
window.addEventListener('pagehide', () => { token = ''; });

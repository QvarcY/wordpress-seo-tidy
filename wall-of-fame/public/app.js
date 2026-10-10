const labels = {
  en: {
    eyebrow:'The open-source community', headline:'Real websites. Real people. Shared progress.',
    intro:'A voluntary showcase of websites that support SEO-TidY. Listings require domain verification and approval. No analytics data is collected or displayed here.',
    browse:'Explore websites',join:'Join Wall of Fame',members:'COMMUNITY',directory:'Featured websites',
    'directory-note':'Only verified, approved sites appear here.',more:'Load more',
    participate:'PARTICIPATE','join-title':'Put your website on the map.',
    'join-info':'Joining is free and optional. Submit a profile, prove control of the domain with a DNS TXT record and wait for review. You can withdraw at any time with your private management key.',
    step1:'Submit your profile',step2:'Verify your domain',step3:'Await approval',name:'Website name',
    url:'Website HTTPS URL',description:'Short description (max 300 characters)',
    consent:'I consent to public display of my website name, URL and description after domain verification and administrator approval.',
    send:'Submit for review',privacy:'No visitor analytics, WordPress credentials or IP addresses are saved with the public listing.',
    submitted:'Application received','save-key':'Save these private details now. The management key is shown only once.',
    id:'Application ID',secret:'Private management key',dns:'Add this DNS TXT record', 'manage-now':'Manage this application',
    account:'SELF-SERVICE',manage:'Check or withdraw your application',check:'Check status',verify:'Verify DNS record',
    remove:'Withdraw / delete',open:'Free and open source',empty:'No approved websites yet. Be the first!',
    failed:'Could not load listings.',submitting:'Submitting...', verified:'Domain verified. Awaiting approval.',
    removed:'Application and public listing permanently deleted.', confirm:'Permanently delete your application and listing?',
    noCaptcha:'Registration is unavailable until bot protection is configured.', ready:'Application created. Save the management key and configure your DNS record.'
  },
  lv: {
    eyebrow:'Atvērtā koda kopiena',headline:'Īstas vietnes. Īsti cilvēki. Kopīga izaugsme.',
    intro:'Brīvprātīgs SEO-TidY atbalstītāju vietņu katalogs. Pirms publicēšanas jāpārbauda domēns un jāsaņem apstiprinājums. Šeit neapkopo apmeklējumu statistiku.',
    browse:'Apskatīt vietnes',join:'Pievienoties Slavas sienai',members:'KOPIENA',directory:'Kopienas vietnes',
    'directory-note':'Redzamas tikai verificētas un apstiprinātas vietnes.',more:'Rādīt vēl',
    participate:'PIEDALIES','join-title':'Parādi savu vietni kopienai.',
    'join-info':'Dalība ir bez maksas un brīvprātīga. Iesniedz profilu, apliecini domēna kontroli ar DNS TXT ierakstu un sagaidi apstiprinājumu. Pieteikumu var atsaukt jebkurā brīdī ar privāto pārvaldības atslēgu.',
    step1:'Iesniedz profilu',step2:'Apliecini domēnu',step3:'Sagaidi apstiprinājumu',
    name:'Vietnes nosaukums',url:'Vietnes HTTPS adrese',description:'Īss apraksts (līdz 300 rakstzīmēm)',
    consent:'Piekrītu, ka pēc domēna pārbaudes un administratora apstiprinājuma publiski būs redzams vietnes nosaukums, adrese un apraksts.',
    send:'Nosūtīt pārskatīšanai',privacy:'Publiskajā profilā neglabā apmeklētāju statistiku, WordPress pieejas datus vai IP adreses.',
    submitted:'Pieteikums saņemts','save-key':'Saglabā privātos datus tagad. Pārvaldības atslēga tiek parādīta tikai vienreiz.',
    id:'Pieteikuma ID',secret:'Privātā pārvaldības atslēga',dns:'Pievieno šādu DNS TXT ierakstu','manage-now':'Pārvaldīt pieteikumu',
    account:'PAŠAPKALPOŠANĀS',manage:'Pārbaudi vai atsauc pieteikumu',check:'Pārbaudīt statusu',verify:'Pārbaudīt DNS',
    remove:'Atsaukt / dzēst',open:'Bezmaksas un atvērtais kods',empty:'Vēl nav apstiprinātu vietņu. Esi pirmais!',
    failed:'Neizdevās ielādēt katalogu.',submitting:'Nosūta...',verified:'Domēns ir pārbaudīts. Gaida apstiprinājumu.',
    removed:'Pieteikums un publiskais ieraksts ir neatgriezeniski izdzēsti.',
    confirm:'Neatgriezeniski dzēst pieteikumu un publisko ierakstu?',
    noCaptcha:'Pieteikšanās nav pieejama, kamēr nav iestatīta aizsardzība pret robotiem.',
    ready:'Pieteikums izveidots. Saglabā pārvaldības atslēgu un pievieno DNS ierakstu.'
  }
};
let lang = (navigator.language || '').toLowerCase().startsWith('lv') ? 'lv' : 'en';
let page = 1, hasMore = false, config = {}, newDetails = null;
const $ = (id) => document.getElementById(id), t = (key) => labels[lang][key] || key;
const show = (value, bad = false) => { $('message').textContent = value; $('message').className = bad ? 'error' : 'success'; };
const request = async (url, options) => {
  const response = await fetch(url, { ...options, headers: { 'content-type': 'application/json' }, credentials: 'omit' });
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.error || 'Request failed');
  return payload;
};
function setLang(next) {
  lang = next;
  document.documentElement.lang = lang;
  $('lang').textContent = lang === 'en' ? 'LV' : 'EN';
  for (const element of document.querySelectorAll('[data-i18n]'))
    element.textContent = t(element.dataset.i18n);
  if (!$('sites').children.length) $('sites').textContent = t('empty');
  try { localStorage.setItem('seo-tidy-lang', lang); } catch {}
}
$('lang').addEventListener('click', () => setLang(lang === 'en' ? 'lv' : 'en'));
function siteCard(site) {
  const card = document.createElement('article'); card.className = 'site-card';
  const head = document.createElement('div'); head.className = 'site-head';
  const monogram = document.createElement('span'); monogram.className = 'site-mark';
  monogram.textContent = Array.from(site.name)[0]?.toUpperCase() || 'S';
  const a = document.createElement('a'); a.href = site.url; a.target = '_blank';
  a.rel = 'nofollow noopener noreferrer'; a.textContent = site.name;
  head.append(monogram, a);
  const body = document.createElement('p'); body.textContent = site.description;
  const domain = document.createElement('small'); domain.textContent = new URL(site.url).hostname;
  card.append(head, body, domain); return card;
}
async function listings(append = false) {
  try {
    const data = await request('/api/sites?page=' + page);
    if (!append) $('sites').replaceChildren();
    for (const site of data.items) $('sites').append(siteCard(site));
    if (!$('sites').children.length) $('sites').textContent = t('empty');
    hasMore = data.hasMore; $('more').hidden = !hasMore;
  } catch { show(t('failed'), true); }
}
$('more').addEventListener('click', () => { page++; listings(true); });
async function loadCaptcha() {
  config = await request('/api/config');
  if (!config.turnstileSiteKey) {
    $('apply').querySelector('button[type="submit"]').disabled = true;
    show(t('noCaptcha'), true);
    return;
  }
  const script = document.createElement('script');
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
  script.async = true; script.defer = true;
  script.onload = () => window.turnstile.render('#turnstile', { sitekey: config.turnstileSiteKey });
  document.head.append(script);
}
$('apply').addEventListener('submit', async (e) => {
  e.preventDefault();
  const button = $('apply').querySelector('button[type="submit"]');
  const turnstileToken = window.turnstile?.getResponse();
  if (!turnstileToken) { show(t('noCaptcha'), true); return; }
  button.disabled = true; show(t('submitting'));
  try {
    const result = await request('/api/apply', { method:'POST', body:JSON.stringify({
      name:$('name').value, url:$('url').value, description:$('description').value,
      consent:$('consent').checked, turnstileToken
    }) });
    newDetails = result;
    $('new-id').textContent = result.id; $('new-secret').textContent = result.ownerSecret;
    $('dns-name').textContent = result.dnsName; $('dns-value').textContent = result.dnsValue;
    $('apply').hidden = true; $('submitted').hidden = false; show(t('ready'));
  } catch (err) { show(err.message, true); window.turnstile?.reset(); }
  finally { button.disabled = false; }
});
$('use-details').addEventListener('click', () => {
  if (!newDetails) return;
  $('application-id').value = newDetails.id;
  $('management-key').value = newDetails.ownerSecret;
  $('manage').scrollIntoView({ behavior:'smooth' });
});
async function manage(action) {
  const id = $('application-id').value.trim(), secret = $('management-key').value.trim();
  if (!id || !secret) { show(t('id') + ' / ' + t('secret'), true); return; }
  if (action === 'remove' && !window.confirm(t('confirm'))) return;
  try {
    const result = await request('/api/' + action, { method:'POST', body:JSON.stringify({id,secret}) });
    show(action === 'remove' ? t('removed') : action === 'verify' ? t('verified') : (result.status || ''));
    if (action === 'remove') { $('management-key').value = ''; $('application-id').value = ''; page = 1; await listings(); }
  } catch (err) { show(err.message, true); }
}
for (const action of ['status','verify','remove']) $(action).addEventListener('click', () => manage(action));
try { const saved = localStorage.getItem('seo-tidy-lang'); if (saved === 'lv' || saved === 'en') lang = saved; } catch {}
setLang(lang); listings(); loadCaptcha().catch(() => show(t('noCaptcha'), true));

const labels = {
  en: {
    eyebrow:'The open-source community', headline:'Real websites. Real people. Shared progress.',
    intro:'A public directory of verified websites using the SEO-TidY WordPress plugin. Join through the plugin; listings can be discovered by people and crawlers.',
    browse:'Explore websites',join:'Join Wall of Fame',members:'COMMUNITY',directory:'Featured websites',
    'directory-note':'Only verified, approved sites appear here.',more:'Load more',
    participate:'PARTICIPATE','join-title':'Put your website on the map.',
    'join-info':'Install SEO-TidY on your WordPress website and submit from the plugin. Ownership is verified automatically; an administrator reviews the entry. Participation is free and optional.',
    step1:'Install SEO-TidY',step2:'Apply from the plugin',step3:'Await approval',name:'Website name',
    url:'Website HTTPS URL',description:'Short description (max 300 characters)',
    consent:'I consent to public display of my website name, URL and description after domain verification and administrator approval.',
    send:'Submit for review',privacy:'No visitor analytics, WordPress credentials or IP addresses are saved with the public listing.',
    submitted:'Application received','save-key':'Save these private details now. The management key is shown only once.',
    'plugin-title':'SEO-TidY plugin required',download:'Download SEO-TidY',
    'plugin-rule':'Only websites running the SEO-TidY WordPress plugin are eligible. Install it, then use SEO-TidY → Wall of Fame in the WordPress admin to apply. Ownership is verified before approval.',
    'plugin-explanation':'Approved websites appear in a public directory accessible to visitors, search engines and AI crawlers. Indexing, additional visits or recommendations are not guaranteed.',
    id:'Application ID',secret:'Private management key',dns:'Add this DNS TXT record', 'manage-now':'Manage this application',
    account:'SELF-SERVICE',manage:'Check or withdraw your application',check:'Check status',verify:'Verify DNS record',
    remove:'Withdraw / delete',open:'Free and open source',empty:'No approved websites yet. Be the first!',
    failed:'Could not load listings.',submitting:'Submitting...', verified:'Domain verified. Awaiting approval.',
    removed:'Application and public listing permanently deleted.', confirm:'Permanently delete your application and listing?',
    noCaptcha:'Registration is unavailable until bot protection is configured.', ready:'Application created. Save the management key and configure your DNS record.'
  },
  lv: {
    eyebrow:'Atvērtā koda kopiena',headline:'Īstas vietnes. Īsti cilvēki. Kopīga izaugsme.',
    intro:'Publisks SEO-TidY WordPress spraudņa lietotāju vietņu katalogs. Pieteikšanās notiek tikai caur spraudni; vietnes var atklāt cilvēki un meklēšanas roboti.',
    browse:'Apskatīt vietnes',join:'Pievienoties Slavas sienai',members:'KOPIENA',directory:'Kopienas vietnes',
    'directory-note':'Redzamas tikai verificētas un apstiprinātas vietnes.',more:'Rādīt vēl',
    participate:'PIEDALIES','join-title':'Parādi savu vietni kopienai.',
    'join-info':'Uzstādi SEO-TidY savā WordPress vietnē un piesakies caur spraudni. Vietnes īpašumtiesības tiek pārbaudītas automātiski, bet ierakstu apstiprina administrators. Dalība ir brīvprātīga un bez maksas.',
    step1:'Uzstādi SEO-TidY',step2:'Piesakies spraudnī',step3:'Sagaidi apstiprinājumu',
    name:'Vietnes nosaukums',url:'Vietnes HTTPS adrese',description:'Īss apraksts (līdz 300 rakstzīmēm)',
    consent:'Piekrītu, ka pēc domēna pārbaudes un administratora apstiprinājuma publiski būs redzams vietnes nosaukums, adrese un apraksts.',
    send:'Nosūtīt pārskatīšanai',privacy:'Publiskajā profilā neglabā apmeklētāju statistiku, WordPress pieejas datus vai IP adreses.',
    submitted:'Pieteikums saņemts','save-key':'Saglabā privātos datus tagad. Pārvaldības atslēga tiek parādīta tikai vienreiz.',
    'plugin-title':'Nepieciešams SEO-TidY spraudnis',download:'Lejupielādēt SEO-TidY',
    'plugin-rule':'Slavas sienai drīkst pieteikt tikai vietnes, kurās darbojas SEO-TidY WordPress spraudnis. Uzstādi to un WordPress administrācijā atver SEO-TidY → Slavas siena. Pirms apstiprināšanas tiek pārbaudītas vietnes īpašumtiesības.',
    'plugin-explanation':'Apstiprinātās vietnes redzamas publiskā katalogā, kuram var piekļūt apmeklētāji, meklētāji un AI roboti. Indeksēšana, papildu apmeklējumi un ieteikumi netiek garantēti.',
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
    const data = await request('api/sites?page=' + page);
    if (!append) $('sites').replaceChildren();
    for (const site of data.items) $('sites').append(siteCard(site));
    if (!$('sites').children.length) $('sites').textContent = t('empty');
    hasMore = data.hasMore; $('more').hidden = !hasMore;
  } catch { show(t('failed'), true); }
}
$('more').addEventListener('click', () => { page++; listings(true); });
try {
  const saved = localStorage.getItem('seo-tidy-lang');
  if (saved === 'lv' || saved === 'en') lang = saved;
} catch {}
setLang(lang);
listings();

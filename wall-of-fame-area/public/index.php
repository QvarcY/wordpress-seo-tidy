<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
$profileHost = isset($_GET['site']) && is_string($_GET['site']) ? strtolower(trim($_GET['site'])) : '';
if ($profileHost !== '' && (!preg_match('/^[a-z0-9.-]{4,253}$/D', $profileHost) || !str_contains($profileHost,'.'))) {
    http_response_code(404); exit('Not found');
}
$page = max(1, min(100, (int)($_GET['page'] ?? 1)));
$items = [];
$more = false;
$profile = null;
try {
    $path = getenv('SEO_TIDY_WOF_CONFIG') ?: '/home/kasidlv/seo-tidy-wall-of-fame-private/config.php';
    $c = require $path;
    $db = new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4', $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $q = $db->query("SELECT name,url,description FROM submissions WHERE status='approved' ORDER BY approved_at DESC,id DESC LIMIT 13 OFFSET ".(($page-1)*12));
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    if ($profileHost !== '') {
        $stmt=$db->prepare("SELECT s.name,s.url,s.description,r.short_description,r.long_description,
            r.category,r.tags_json,r.locale,r.country FROM submissions s
            JOIN profile_revisions r ON r.submission_id=s.id AND r.state='approved'
            WHERE s.host=? AND s.status='approved' ORDER BY r.id DESC LIMIT 1");
        $stmt->execute([$profileHost]);
        $profile=$stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $more = count($rows) > 12;
    $items = array_slice($rows,0,12);
} catch (Throwable $e) {
    error_log('Wall of Fame SSR unavailable');
}
$esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
$base='https://kas.id.lv/SEO-TidY/Wall-Of-Fame/';
$canonical=$base.($page>1?'?page='.$page:'');
if ($profileHost !== '') {
    if (!$profile) {
        http_response_code(404);
        header('X-Robots-Tag: noindex');
        exit('Profile not found');
    }
    $siteUrl = (string)$profile['url'];
    $name = (string)$profile['name'];
    $summary = (string)$profile['short_description'];
    $detail = (string)$profile['long_description'];
    $tagList = json_decode((string)$profile['tags_json'],true);
    if (!is_array($tagList)) $tagList=[];
    $tagHtml='';
    foreach ($tagList as $tag) {
        if (is_string($tag)) $tagHtml.='<span class="profile-tag">'.$esc($tag).'</span> ';
    }
    $profileUrl=$base.'?site='.rawurlencode($profileHost);
    $heading=$esc($name).' | SEO-TidY Wall of Fame';
    $structured=json_encode([
        '@context'=>'https://schema.org','@type'=>'WebPage','name'=>$name,
        'description'=>$summary,'url'=>$profileUrl,
        'about'=>['@type'=>'WebSite','name'=>$name,'url'=>$siteUrl,'description'=>$detail]
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    $profileHtml='<!doctype html><html lang="'.$esc($profile['locale']).'"><head><meta charset="utf-8">'.
        '<meta name="viewport" content="width=device-width,initial-scale=1">'.
        '<meta name="robots" content="index,follow">'.
        '<title>'.$heading.'</title><meta name="description" content="'.$esc($summary).'">'.
        '<link rel="canonical" href="'.$esc($profileUrl).'"><link rel="stylesheet" href="style.css">'.'<script type="application/ld+json">'.$structured.'</script>'.
        '</head><body><header class="shell"><div class="brand">SEO-TidY <small>Wall of Fame</small></div>'.
        '<nav><a href="'.$esc($base).'">← Directory</a></nav></header>'.
        '<main class="shell profile-detail"><div class="eyebrow">'.$esc($profile['category']).' · '.
        $esc($profile['country']).'</div><h1>'.$esc($name).'</h1>'.
        '<p class="profile-summary">'.$esc($summary).'</p>'.
        '<div class="profile-content">'.nl2br($esc($detail)).'</div>'.
        '<div class="profile-tags">'.$tagHtml.'</div>'.
        '<p><a class="primary" href="'.$esc($siteUrl).'" rel="noopener noreferrer" target="_blank">Visit website ↗</a></p>'.
        '</main><footer class="shell">SEO-TidY · Wall of Fame</footer></body></html>';
    echo $profileHtml; exit;
}
$cards='';
$elements=[];
foreach ($items as $i=>$item) {
    $url=(string)$item['url'];
    if (!filter_var($url,FILTER_VALIDATE_URL) || parse_url($url,PHP_URL_SCHEME)!=='https') continue;
    $host=parse_url($url,PHP_URL_HOST);
    $mark=mb_strtoupper(mb_substr((string)$item['name'],0,1,'UTF-8'),'UTF-8');
    $profileLink=$base.'?site='.rawurlencode((string)$host);
    $hasProfile=false;
    try {
        $check=$db->prepare("SELECT 1 FROM profile_revisions r JOIN submissions s ON s.id=r.submission_id WHERE s.host=? AND r.state='approved' LIMIT 1");
        $check->execute([$host]);
        $hasProfile=(bool)$check->fetchColumn();
    } catch (Throwable $e) {}
    $cards.='<article class="site-card"><div class="site-head"><span class="site-mark">'.$esc($mark).'</span><a href="'.$esc($url).'" target="_blank" rel="noopener noreferrer">'.$esc($item['name']).'</a></div><p>'.$esc($item['description']).'</p><small>'.$esc($host).'</small>'.($hasProfile?'<a class="profile-open" href="'.$esc($profileLink).'">View profile ↗</a>':'').'</article>';
    $elements[]=['@type'=>'ListItem','position'=>($page-1)*12+$i+1,'name'=>(string)$item['name'],'url'=>$url];
}
$schema=['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>'SEO-TidY Wall of Fame','url'=>$canonical,'mainEntity'=>['@type'=>'ItemList','itemListElement'=>$elements]];
$json=json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$html=file_get_contents(__DIR__.'/index.html');
if ($html===false) {http_response_code(500);exit;}
$links='<nav class="directory-crawl-pages" aria-label="Directory page links">';
if ($page>1) $links.='<a rel="prev" href="'.$esc($base.($page===2?'':'?page='.($page-1))).'">Previous page</a> ';
$links.='<span>Page '.$page.'</span>';
if ($more) $links.=' <a rel="next" href="'.$esc($base.'?page='.($page+1)).'">Next page</a>';
$links.='</nav>';
$html=str_replace('<div id="sites" class="cards" aria-live="polite"></div>','<div id="sites" class="cards" aria-live="polite">'.$cards.'</div>'.$links,$html);
$html=str_replace('<link rel="canonical" href="'.$base.'">','<link rel="canonical" href="'.$esc($canonical).'">',$html);
$html=str_replace('</head>','<script type="application/ld+json">'.$json.'</script></head>',$html);
echo $html;

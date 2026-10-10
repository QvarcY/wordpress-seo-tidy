<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
$page = max(1, min(100, (int)($_GET['page'] ?? 1)));
$items = [];
$more = false;
try {
    $path = getenv('SEO_TIDY_WOF_CONFIG') ?: '/home/kasidlv/seo-tidy-wall-of-fame-private/config.php';
    $c = require $path;
    $db = new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4', $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $q = $db->query("SELECT name,url,description FROM submissions WHERE status='approved' ORDER BY approved_at DESC,id DESC LIMIT 13 OFFSET ".(($page-1)*12));
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    $more = count($rows) > 12;
    $items = array_slice($rows,0,12);
} catch (Throwable $e) {
    error_log('Wall of Fame SSR unavailable');
}
$esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
$base='https://kas.id.lv/SEO-TidY/Wall-Of-Fame/';
$canonical=$base.($page>1?'?page='.$page:'');
$cards='';
$elements=[];
foreach ($items as $i=>$item) {
    $url=(string)$item['url'];
    if (!filter_var($url,FILTER_VALIDATE_URL) || parse_url($url,PHP_URL_SCHEME)!=='https') continue;
    $host=parse_url($url,PHP_URL_HOST);
    $mark=mb_strtoupper(mb_substr((string)$item['name'],0,1,'UTF-8'),'UTF-8');
    $cards.='<article class="site-card"><div class="site-head"><span class="site-mark">'.$esc($mark).'</span><a href="'.$esc($url).'" target="_blank" rel="noopener noreferrer">'.$esc($item['name']).'</a></div><p>'.$esc($item['description']).'</p><small>'.$esc($host).'</small></article>';
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

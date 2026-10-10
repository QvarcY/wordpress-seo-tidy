<?php
declare(strict_types=1);
header('Content-Type: application/xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
$base='https://kas.id.lv/SEO-TidY/Wall-Of-Fame/';
$urls=[$base];
try {
    $configPath=getenv('SEO_TIDY_WOF_CONFIG') ?: '/home/kasidlv/seo-tidy-wall-of-fame-private/config.php';
    $config=require $configPath;
    $db=new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',
        $config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $count=(int)$db->query("SELECT COUNT(*) FROM submissions WHERE status='approved'")->fetchColumn();
    for($p=2;$p<=min(100,(int)ceil($count/12));$p++) $urls[]=$base.'?page='.$p;
    $result=$db->query("SELECT s.host FROM submissions s JOIN profile_revisions r
        ON r.submission_id=s.id AND r.state='approved' WHERE s.status='approved'
        GROUP BY s.host ORDER BY s.host LIMIT 10000");
    foreach($result as $row) $urls[]=$base.'?site='.rawurlencode($row['host']);
} catch (Throwable $e) {
    error_log('Wall of Fame sitemap generation failed');
    http_response_code(503);
    exit;
}
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach($urls as $url) echo '<url><loc>'.htmlspecialchars($url,ENT_XML1|ENT_QUOTES,'UTF-8').'</loc></url>';
echo '</urlset>';

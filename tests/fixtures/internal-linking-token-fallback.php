<?php
/** Run with php -n: verify tokenization when optional Unicode extensions are absent. */
declare(strict_types=1);
require __DIR__ . '/internal-linking-test-bootstrap.php';

use QvarcY\SeoTidy\InternalLinkingPreview as Preview;

if (function_exists('mb_strtolower') || class_exists('Normalizer')) {
    fwrite(STDERR, 'Run this extension-fallback check with php -n.' . PHP_EOL);
    exit(1);
}
$checks = [
    'Uppercase Latvian without mbstring/intl' => Preview::termTokens('ĀDAŽI ĢIMENĒM RĪGAS') === ['ādaži', 'ģimenēm', 'rīgas'],
    'Decomposed Latvian without mbstring/intl' => Preview::termTokens("RI\u{0304}GAS G\u{0327}IMENE\u{0304}M") === ['rīgas', 'ģimenēm'],
    'HTML entities without mbstring/intl' => Preview::termTokens('R&#299;gas &amp; muzeji') === ['rīgas', 'muzeji'],
    'Punctuation/mixed language without mbstring/intl' => Preview::termTokens('Airport—transfer; ceļošana!') === ['airport', 'transfer', 'ceļošana'],
];
$failures = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failures += $ok ? 0 : 1;
}
echo count($checks) . ' assertions, ' . $failures . ' failures (no mbstring/intl).' . PHP_EOL;
exit($failures ? 1 : 0);

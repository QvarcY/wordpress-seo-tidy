<?php
namespace QvarcY\SeoTidy;

defined('ABSPATH') || exit;

/** Fallback LV strings for the experimental PHP-only administrator screens. */
final class InternalLinkingTranslations
{
    public static function init(): void
    {
        if (!defined('SEO_TIDY_INTERNAL_LINKING_PREVIEW') || SEO_TIDY_INTERNAL_LINKING_PREVIEW !== true) {
            return;
        }
        add_filter('gettext', [self::class, 'translate'], 10, 3);
    }

    public static function translate(string $translation, string $original, string $domain): string
    {
        if ($domain !== 'seo-tidy' || !str_starts_with(determine_locale(), 'lv')) {
            return $translation;
        }
        static $lv = [
            'Internal Linking Preview' => 'Iekšējo saišu pārskats',
            'SEO-TidY Smart Topics' => 'SEO-TidY tematiskie marķējumi',
            'Permission denied.' => 'Piekļuve liegta.',
            'Invalid article.' => 'Nederīgs raksts.',
            'Review Smart Topics' => 'Pārskatīt tematiskos marķējumus',
            'Approved topics' => 'Apstiprinātie marķējumi',
            'Suggested topics' => 'Ieteiktie marķējumi',
            'Rejected topics' => 'Noraidītie marķējumi',
            'Save topics' => 'Saglabāt marķējumus',
            'Approved topics (comma separated):' => 'Apstiprinātie marķējumi (atdalīti ar komatiem):',
            'Suggested topics — select to approve:' => 'Ieteiktie marķējumi — atzīmē apstiprināšanai:',
            'Rejected topics (comma separated; will not be suggested again):' => 'Noraidītie marķējumi (atdalīti ar komatiem; netiks piedāvāti atkārtoti):',
            'Saving the article saves these private topics. They do not create public tag pages.' => 'Saglabājot rakstu, saglabājas arī privātie marķējumi. Publiskas tagu lapas netiek veidotas.',
            'Search engines are discouraged at site level; indexability cannot be confirmed.' => 'Vietnes līmenī ir liegta indeksēšana; indeksējamību nevar apstiprināt.',
            'Experimental read-only preview. Suggestions need manual review. Candidate retrieval uses the completed local index, capped at 30 targets per source. Results remain experimental.' => 'Eksperimentāls tikai lasāms pārskats. Ieteikumi jāpārskata manuāli. Atlase izmanto pabeigto lokālo indeksu, ne vairāk kā 30 kandidātus katram rakstam.',
            'Indexed articles: %d. Indexing is manual and resumable.' => 'Indeksētie raksti: %d. Indeksēšanu var turpināt pa daļām.',
            'Index next 25 articles' => 'Indeksēt nākamos 25 rakstus',
            'Rebuild index from beginning' => 'Pārbūvēt indeksu no sākuma',
            'Complete the index scan before candidate results are displayed.' => 'Pirms saišu kandidātu attēlošanas pabeidz indeksēšanu.',
            'Review manually; topical similarity is not proof of complementary intent.' => 'Pārskati manuāli — tematiska līdzība negarantē, ka raksti viens otru papildina.',
            'No reviewable candidates in the bounded sample.' => 'Atlasītajā kopā nav pārskatāmu kandidātu.',
            'Previous' => 'Iepriekšējā',
            'Next' => 'Nākamā',
        ];
        return $lv[$original] ?? $translation;
    }
}

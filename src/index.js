import { createElement, createRoot, useState, useEffect } from '@wordpress/element';
import { Button, Card, CardBody, Notice, ToggleControl, Spinner, TextControl, TextareaControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const sections = [
    { id: 'dashboard', label: __('Dashboard', 'seo-tidy') },
    { id: 'metadata', label: __('Metadata', 'seo-tidy') },
    { id: 'analytics', label: __('Analytics', 'seo-tidy') },
    { id: 'badge', label: __('Stats Badge', 'seo-tidy') },
    { id: 'community', label: __('Wall of Fame', 'seo-tidy') },
    { id: 'schema', label: __('Smart Schema', 'seo-tidy') },
    { id: 'audit', label: __('SEO Audit', 'seo-tidy') },
    { id: 'answers', label: __('Answer Readiness', 'seo-tidy') },
    { id: 'migration', label: __('Migration', 'seo-tidy') },
    { id: 'settings', label: __('Settings', 'seo-tidy') },
];


function SchemaSettings() {
    const [enabled, setEnabled] = useState(true);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);

    useEffect(() => {
        let active = true;

        apiFetch({ path: '/wp/v2/settings' })
            .then((settings) => {
                if (active) {
                    setEnabled(settings.seo_tidy_schema_enabled !== false);
                    setLoading(false);
                }
            })
            .catch(() => {
                if (active) {
                    setMessage({
                        status: 'error',
                        text: __('Could not load Schema settings.', 'seo-tidy'),
                    });
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, []);

    const save = async (nextValue) => {
        setSaving(true);
        setMessage(null);

        try {
            const result = await apiFetch({
                path: '/wp/v2/settings',
                method: 'POST',
                data: { seo_tidy_schema_enabled: nextValue },
            });

            setEnabled(result.seo_tidy_schema_enabled === true);
            setMessage({
                status: 'success',
                text: __('Schema settings saved.', 'seo-tidy'),
            });
        } catch {
            setMessage({
                status: 'error',
                text: __('Could not save Schema settings.', 'seo-tidy'),
            });
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="tidy-settings-panel">
            <p>
                {__('Automatically add structured data to published posts and pages.', 'seo-tidy')}
            </p>

            {loading ? (
                <Spinner />
            ) : (
                <div className="tidy-setting-row">
                <ToggleControl
                    label={__('Enable automatic Schema', 'seo-tidy')}
                    help={__('Posts use BlogPosting; pages use WebPage.', 'seo-tidy')}
                    checked={enabled}
                    disabled={saving || message?.text === __('Could not load Schema settings.', 'seo-tidy')}
                    onChange={save}
                />
                </div>
            )}

            {saving && <Spinner />}

            {message && (
                <Notice
                    status={message.status}
                    isDismissible={false}
                >
                    {message.text}
                </Notice>
            )}
        </div>
    );
}


function DashboardOverview({ onOpenSchema, onOpenMetadata }) {
    const [data, setData] = useState(null);
    const [error, setError] = useState(false);

    useEffect(() => {
        let active = true;

        apiFetch({ path: '/seo-tidy/v1/dashboard' })
            .then((result) => {
                if (active) {
                    setData(result);
                }
            })
            .catch(() => {
                if (active) {
                    setError(true);
                }
            });

        return () => {
            active = false;
        };
    }, []);

    if (error) {
        return (
            <Notice status="error" isDismissible={false}>
                {__('Could not load dashboard statistics.', 'seo-tidy')}
            </Notice>
        );
    }

    if (!data) {
        return <Spinner />;
    }

    const metrics = [
        {
            label: __('Published posts', 'seo-tidy'),
            value: data.posts,
        },
        {
            label: __('Published pages', 'seo-tidy'),
            value: data.pages,
        },
        {
            label: __('SEO titles completed', 'seo-tidy'),
            value: data.titles,
        },
        {
            label: __('Meta descriptions completed', 'seo-tidy'),
            value: data.descriptions,
        },
        {
            label: __('Missing SEO titles', 'seo-tidy'),
            value: data.missingTitles,
            filter: 'missing_title',
        },
        {
            label: __('Missing meta descriptions', 'seo-tidy'),
            value: data.missingDescriptions,
            filter: 'missing_description',
        },
    ];

    return (
        <div className="tidy-dashboard">
            <div className="tidy-dashboard-summary">
                <span className="tidy-dashboard-summary-label">
                    {__('Published posts and pages:', 'seo-tidy')}
                </span>
                <strong className="tidy-dashboard-total">
                    {data.total}
                </strong>
            </div>

            <div className="tidy-metrics-grid">
                {metrics.map((metric) => (
                    <div
                        key={metric.label}
                        className={
                            'tidy-metric-card' +
                            (metric.filter ? ' tidy-metric-card-action' : '')
                        }
                    >
                        <span className="tidy-metric-label">
                            {metric.label}
                        </span>

                        {metric.filter ? (
                            <Button
                                variant="link"
                                className="tidy-metric-link"
                                onClick={() => onOpenMetadata(metric.filter)}
                            >
                                {metric.value}
                            </Button>
                        ) : (
                            <strong className="tidy-metric-value">
                                {metric.value}
                            </strong>
                        )}
                    </div>
                ))}
            </div>

            <div className="tidy-dashboard-bottom">
                <div className="tidy-schema-panel">
                    <div>
                        <strong className="tidy-panel-heading">
                            {__('Smart Schema:', 'seo-tidy')}
                        </strong>
                        <span className={
                            'tidy-schema-status' +
                            (data.schemaEnabled
                                ? ' tidy-schema-status-enabled'
                                : ' tidy-schema-status-disabled')
                        }>
                            {data.schemaEnabled
                                ? __('Enabled', 'seo-tidy')
                                : __('Disabled', 'seo-tidy')}
                        </span>
                    </div>

                    <Button
                        variant="secondary"
                        onClick={onOpenSchema}
                    >
                        {__('Manage Schema', 'seo-tidy')}
                    </Button>
                </div>

                <div className="tidy-dashboard-actions">
                    <Button variant="secondary" href={data.links.posts}>
                        {__('Edit posts', 'seo-tidy')}
                    </Button>
                    <Button variant="secondary" href={data.links.pages}>
                        {__('Edit pages', 'seo-tidy')}
                    </Button>
                </div>
            </div>

            <p className="tidy-dashboard-footnote">
                {__('Counts include published posts and pages only.', 'seo-tidy')}
            </p>
        </div>
    );
}



function AnalyticsOverview() {
    const [enabled, setEnabled] = useState(null);
    const [days, setDays] = useState(30);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [refresh, setRefresh] = useState(0);
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        let active = true;

        setLoading(true);
        setError('');

        Promise.all([
            apiFetch({ path: '/wp/v2/settings' }),
            apiFetch({ path: '/seo-tidy/v1/analytics?days=' + days }),
        ])
            .then(([settings, summary]) => {
                if (!active) return;

                setEnabled(
                    [true, 1, '1'].includes(
                        settings.seo_tidy_analytics_enabled
                    )
                );
                setData(summary);
            })
            .catch(() => {
                if (active) {
                    setError(
                        __('Could not load analytics.', 'seo-tidy')
                    );
                }
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, [days, refresh]);

    const toggle = async (next) => {
        setSaving(true);
        setError('');

        try {
            const result = await apiFetch({
                path: '/wp/v2/settings',
                method: 'POST',
                data: {
                    seo_tidy_analytics_enabled: next,
                },
            });

            setEnabled(
                [true, 1, '1'].includes(
                    result.seo_tidy_analytics_enabled
                )
            );

            setRefresh((current) => current + 1);
        } catch {
            setError(
                __('Could not save analytics settings.', 'seo-tidy')
            );
        } finally {
            setSaving(false);
        }
    };

    const resetStatistics = async () => {
        if (!window.confirm(
            __('Delete all SEO-TidY analytics statistics? This cannot be undone.', 'seo-tidy')
        )) {
            return;
        }

        setDeleting(true);
        setError('');

        try {
            await apiFetch({
                path: '/seo-tidy/v1/analytics/reset',
                method: 'POST',
                data: { confirm: 'DELETE' },
            });

            setRefresh((current) => current + 1);
        } catch {
            setError(
                __('Could not delete analytics statistics.', 'seo-tidy')
            );
        } finally {
            setDeleting(false);
        }
    };

    return (
        <div className="tidy-dashboard">
            <p>
                {__(
                    'Optional local pageview statistics. Collection is disabled by default.',
                    'seo-tidy'
                )}
            </p>

            {enabled !== null && (
                <div className="tidy-setting-row">
                    <ToggleControl
                        label={__('Enable local analytics', 'seo-tidy')}
                        help={__(
                            'Counts eligible WordPress page requests without storing visitor IP addresses.',
                            'seo-tidy'
                        )}
                        checked={enabled}
                        disabled={saving || loading}
                        onChange={toggle}
                    />
                </div>
            )}

            {(loading || saving) && <Spinner />}

            {error && (
                <Notice status="error" isDismissible={false}>
                    {error}
                </Notice>
            )}

            {enabled === false && !loading && (
                <Notice status="info" isDismissible={false}>
                    {__(
                        'Analytics is disabled. No visits are being recorded by SEO-TidY.',
                        'seo-tidy'
                    )}
                </Notice>
            )}

            {enabled === true && data && !loading && (
                <>
                    <p>
                        <label>
                            {__('Statistics period', 'seo-tidy')}{' '}
                            <select
                                value={days}
                                onChange={(event) =>
                                    setDays(Number(event.target.value))
                                }
                            >
                                <option value={1}>
                                    {__('Today', 'seo-tidy')}
                                </option>
                                <option value={7}>
                                    {__('Last 7 days', 'seo-tidy')}
                                </option>
                                <option value={30}>
                                    {__('Last 30 days', 'seo-tidy')}
                                </option>
                                <option value={90}>
                                    {__('Last 90 days', 'seo-tidy')}
                                </option>
                                <option value={365}>
                                    {__('Last 365 days', 'seo-tidy')}
                                </option>
                            </select>
                        </label>
                    </p>

                    <div className="tidy-metrics-grid">
                        <div className="tidy-metric-card">
                            <span className="tidy-metric-label">
                                {__('Human-classified pageviews', 'seo-tidy')}
                            </span>
                            <strong className="tidy-metric-value">
                                {Number(
                                    data.humanPageviews || 0
                                ).toLocaleString()}
                            </strong>
                        </div>
                        <div className="tidy-metric-card">
                            <span className="tidy-metric-label">
                                {__('Suspected bot requests', 'seo-tidy')}
                            </span>
                            <strong className="tidy-metric-value">
                                {Number(
                                    data.suspectedBotRequests || 0
                                ).toLocaleString()}
                            </strong>
                        </div>
                    </div>

                    <p className="tidy-dashboard-footnote">
                        {__(
                            'Pageviews are not unique visitors. Bot identification is approximate. Cached pages and some requests may not be counted.',
                            'seo-tidy'
                        )}
                    </p>

                    <Button
                        variant="secondary"
                        onClick={() =>
                            setRefresh((current) => current + 1)
                        }
                        disabled={loading || saving}
                    >
                        {__('Refresh statistics', 'seo-tidy')}
                    </Button>

                    <div style={{
                        marginTop: 24,
                        paddingTop: 16,
                        borderTop: '1px solid #dcdcde',
                    }}>
                        <h3>{__('Data management', 'seo-tidy')}</h3>
                        <p>
                            {__(
                                'Statistics older than 365 days are removed automatically. You can also delete all collected statistics now.',
                                'seo-tidy'
                            )}
                        </p>
                        <Button
                            variant="secondary"
                            isDestructive
                            isBusy={deleting}
                            disabled={deleting || loading || saving}
                            onClick={resetStatistics}
                        >
                            {__('Delete all statistics', 'seo-tidy')}
                        </Button>
                    </div>
                </>
            )}
        </div>
    );
}



const badgeDefaults = {
    seo_tidy_badge_footer: false,
    seo_tidy_badge_branding: false,
    seo_tidy_badge_bots: true,
    seo_tidy_badge_humans: true,
    seo_tidy_badge_labels: true,
    seo_tidy_badge_theme: 'transparent',
};

function StatsBadgeSettings() {
    const [values, setValues] = useState(null);
    const [saved, setSaved] = useState(null);
    const [analytics, setAnalytics] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState(false);

    useEffect(() => {
        let active = true;

        Promise.all([
            apiFetch({ path: '/wp/v2/settings' }),
            apiFetch({ path: '/seo-tidy/v1/analytics?days=30' }),
        ]).then(([settings, result]) => {
            if (!active) return;

            const initial = {};

            for (const [key, fallback] of Object.entries(badgeDefaults)) {
                if (typeof fallback === 'boolean') {
                    initial[key] = [true, 1, '1'].includes(settings[key]);
                } else {
                    initial[key] = settings[key] || fallback;
                }
            }

            setValues(initial);
            setSaved(initial);
            setAnalytics(result);
        }).catch(() => {
            if (active) {
                setError(__('Could not load badge settings.', 'seo-tidy'));
            }
        }).finally(() => {
            if (active) setLoading(false);
        });

        return () => { active = false; };
    }, []);

    const update = (key, value) => {
        setSuccess(false);
        setValues((current) => ({
            ...current,
            [key]: value,
        }));
    };

    const save = async () => {
        setSaving(true);
        setError('');
        setSuccess(false);

        try {
            const result = await apiFetch({
                path: '/wp/v2/settings',
                method: 'POST',
                data: values,
            });

            const confirmed = {};
            for (const [key, fallback] of Object.entries(badgeDefaults)) {
                confirmed[key] = typeof fallback === 'boolean'
                    ? [true, 1, '1'].includes(result[key])
                    : result[key] || fallback;
            }

            setValues(confirmed);
            setSaved(confirmed);
            setSuccess(true);
        } catch {
            setError(__('Could not save badge settings.', 'seo-tidy'));
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <Spinner />;

    if (!values) {
        return (
            <Notice status="error" isDismissible={false}>
                {error || __('Badge settings unavailable.', 'seo-tidy')}
            </Notice>
        );
    }

    const changed = Object.keys(badgeDefaults).some(
        (key) => values[key] !== saved?.[key]
    );

    const theme = values.seo_tidy_badge_theme;

    const palette = theme === 'dark'
        ? {
            background: '#152033',
            border: '#34435b',
            color: '#eef4fb',
        }
        : theme === 'light'
            ? {
                background: '#fff',
                border: '#d9e0e9',
                color: '#263449',
            }
            : {
                background: 'transparent',
                border: 'currentColor',
                color: 'inherit',
            };

    const fields = [
        {
            key: 'seo_tidy_badge_footer',
            label: __('Automatically show badge in footer', 'seo-tidy'),
            help: __('Optional. Off by default.', 'seo-tidy'),
        },
        {
            key: 'seo_tidy_badge_humans',
            label: __('Show pageviews', 'seo-tidy'),
            help: __('Human-classified page requests, not unique people.', 'seo-tidy'),
        },
        {
            key: 'seo_tidy_badge_bots',
            label: __('Show suspected bot requests', 'seo-tidy'),
            help: __('Bot detection is approximate.', 'seo-tidy'),
        },
        {
            key: 'seo_tidy_badge_labels',
            label: __('Show descriptions next to icons', 'seo-tidy'),
            help: __('When disabled, accessible descriptions remain available.', 'seo-tidy'),
        },
        {
            key: 'seo_tidy_badge_branding',
            label: __('Show Powered by SEO-TidY link', 'seo-tidy'),
            help: __('Optional project attribution. Off by default.', 'seo-tidy'),
        },
    ];

    const previewMetrics = [];

    if (values.seo_tidy_badge_humans) {
        previewMetrics.push({
            id: 'human',
            icon: '?',
            label: __('Human-classified pageviews', 'seo-tidy'),
            value: Number(analytics?.humanPageviews || 0),
        });
    }

    if (values.seo_tidy_badge_bots) {
        previewMetrics.push({
            id: 'bot',
            icon: '?',
            label: __('Suspected bot requests', 'seo-tidy'),
            value: Number(analytics?.suspectedBotRequests || 0),
        });
    }

    return (
        <div className="tidy-settings-panel">
            <p>
                {__(
                    'Customize the optional public statistics badge.',
                    'seo-tidy'
                )}
            </p>

            {!analytics?.enabled && (
                <Notice status="info" isDismissible={false}>
                    {__(
                        'Analytics is disabled. Enable it in the Analytics tab before publishing the badge.',
                        'seo-tidy'
                    )}
                </Notice>
            )}

            <div className="tidy-setting-row">
                <label htmlFor="tidy-badge-theme">
                    <strong>{__('Badge appearance', 'seo-tidy')}</strong>
                </label>
                <p>
                    <select
                        id="tidy-badge-theme"
                        value={theme}
                        disabled={saving}
                        onChange={(event) =>
                            update('seo_tidy_badge_theme', event.target.value)
                        }
                    >
                        <option value="transparent">
                            {__('Transparent', 'seo-tidy')}
                        </option>
                        <option value="light">
                            {__('Light', 'seo-tidy')}
                        </option>
                        <option value="dark">
                            {__('Dark', 'seo-tidy')}
                        </option>
                    </select>
                </p>
            </div>

            <div className="tidy-settings-list">
                {fields.map((field) => (
                    <div className="tidy-setting-row" key={field.key}>
                        <ToggleControl
                            label={field.label}
                            help={field.help}
                            checked={values[field.key]}
                            disabled={saving}
                            onChange={(next) => update(field.key, next)}
                        />
                    </div>
                ))}
            </div>

            <h3>{__('Live preview', 'seo-tidy')}</h3>
            <div style={{
                padding: 24,
                borderRadius: 12,
                background: theme === 'transparent'
                    ? '#e8edf3'
                    : '#f0f2f5',
                color: '#263449',
                textAlign: 'center',
            }}>
                <div
                    role="group"
                    aria-label={__('Badge preview', 'seo-tidy')}
                    style={{
                        display: 'inline-flex',
                        flexWrap: 'wrap',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '10px 16px',
                        maxWidth: '100%',
                        padding: '10px 14px',
                        border: '1px solid ' + palette.border,
                        borderRadius: 10,
                        background: palette.background,
                        color: palette.color,
                        fontSize: 12,
                        fontFamily: 'system-ui, sans-serif',
                    }}
                >
                    {previewMetrics.map((metric) => (
                        <span
                            key={metric.id}
                            title={metric.label}
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 6,
                            }}
                        >
                            <span aria-hidden="true">{metric.icon}</span>
                            <strong>{metric.value.toLocaleString()}</strong>
                            {values.seo_tidy_badge_labels ? (
                                <span>{metric.label}</span>
                            ) : (
                                <span className="screen-reader-text">
                                    {metric.label}
                                </span>
                            )}
                        </span>
                    ))}

                    {values.seo_tidy_badge_branding && (
                        <span style={{ textDecoration: 'underline' }}>
                            {__('Powered by SEO-TidY', 'seo-tidy')}
                        </span>
                    )}

                    {previewMetrics.length === 0 &&
                        !values.seo_tidy_badge_branding && (
                        <span>
                            {__('Badge contains no visible items.', 'seo-tidy')}
                        </span>
                    )}
                </div>
            </div>

            <p className="tidy-dashboard-footnote">
                {__(
                    'Preview uses the current 30-day statistics. Changes are not published until saved. The final badge uses SVG icons.',
                    'seo-tidy'
                )}
            </p>

            <p>
                <strong>{__('Shortcode:', 'seo-tidy')}</strong>{' '}
                <code>[seo_tidy_stats]</code>
            </p>
            <p>
                <code>[seo_tidy_stats theme="dark"]</code>
            </p>

            {error && (
                <Notice status="error" isDismissible={false}>
                    {error}
                </Notice>
            )}

            {success && (
                <Notice status="success" isDismissible={false}>
                    {__('Badge settings saved.', 'seo-tidy')}
                </Notice>
            )}

            <Button
                variant="primary"
                disabled={!changed || saving}
                isBusy={saving}
                onClick={save}
            >
                {__('Save badge settings', 'seo-tidy')}
            </Button>
        </div>
    );
}



function WallOfFame() {
    const [profile, setProfile] = useState(null);
    const [saved, setSaved] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState(false);

    useEffect(() => {
        let active = true;

        apiFetch({ path: '/wp/v2/settings' })
            .then((settings) => {
                if (!active) return;

                const initial = {
                    seo_tidy_community_opt_in:
                        [true, 1, '1'].includes(
                            settings.seo_tidy_community_opt_in
                        ),
                    seo_tidy_community_name:
                        settings.seo_tidy_community_name || '',
                    seo_tidy_community_url:
                        settings.seo_tidy_community_url || '',
                    seo_tidy_community_description:
                        settings.seo_tidy_community_description || '',
                };

                setProfile(initial);
                setSaved(initial);
            })
            .catch(() => {
                if (active) {
                    setError(__('Could not load community profile.', 'seo-tidy'));
                }
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => { active = false; };
    }, []);

    const change = (key, value) => {
        setSuccess(false);
        setProfile((current) => ({
            ...current,
            [key]: value,
        }));
    };

    const save = async () => {
        setSaving(true);
        setError('');
        setSuccess(false);

        try {
            const result = await apiFetch({
                path: '/wp/v2/settings',
                method: 'POST',
                data: profile,
            });

            const confirmed = {
                seo_tidy_community_opt_in:
                    [true, 1, '1'].includes(
                        result.seo_tidy_community_opt_in
                    ),
                seo_tidy_community_name:
                    result.seo_tidy_community_name || '',
                seo_tidy_community_url:
                    result.seo_tidy_community_url || '',
                seo_tidy_community_description:
                    result.seo_tidy_community_description || '',
            };

            setProfile(confirmed);
            setSaved(confirmed);
            setSuccess(true);
        } catch {
            setError(__('Could not save community profile.', 'seo-tidy'));
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <Spinner />;

    if (!profile) {
        return (
            <Notice status="error" isDismissible={false}>
                {error || __('Community profile unavailable.', 'seo-tidy')}
            </Notice>
        );
    }

    const changed = Object.keys(profile).some(
        (key) => profile[key] !== saved?.[key]
    );

    return (
        <div className="tidy-settings-panel">
            <p>
                {__(
                    'Prepare your website profile for the future SEO-TidY community showcase.',
                    'seo-tidy'
                )}
            </p>

            <Notice status="info" isDismissible={false}>
                {__(
                    'The public Wall of Fame directory is not active yet. Saving this profile stores data only on your WordPress website. No information is submitted or published externally.',
                    'seo-tidy'
                )}
            </Notice>

            <div className="tidy-setting-row">
                <ToggleControl
                    label={__('I would like to join Wall of Fame', 'seo-tidy')}
                    help={__(
                        'Express interest in future voluntary registration. No automatic submission takes place.',
                        'seo-tidy'
                    )}
                    checked={profile.seo_tidy_community_opt_in}
                    disabled={saving}
                    onChange={(value) =>
                        change('seo_tidy_community_opt_in', value)
                    }
                />
            </div>

            <div className="tidy-setting-row">
                <TextControl
                    label={__('Website name', 'seo-tidy')}
                    value={profile.seo_tidy_community_name}
                    disabled={saving}
                    maxLength={100}
                    onChange={(value) =>
                        change('seo_tidy_community_name', value)
                    }
                />
            </div>

            <div className="tidy-setting-row">
                <TextControl
                    label={__('Public website URL', 'seo-tidy')}
                    type="url"
                    value={profile.seo_tidy_community_url}
                    disabled={saving}
                    onChange={(value) =>
                        change('seo_tidy_community_url', value)
                    }
                />
            </div>

            <div className="tidy-setting-row">
                <TextareaControl
                    label={__('Short website description', 'seo-tidy')}
                    value={profile.seo_tidy_community_description}
                    disabled={saving}
                    maxLength={300}
                    onChange={(value) =>
                        change('seo_tidy_community_description', value)
                    }
                />
            </div>

            <h3>{__('Profile preview', 'seo-tidy')}</h3>

            <div className="tidy-metric-card" style={{
                maxWidth: 520,
                padding: 18,
            }}>
                <strong>
                    {profile.seo_tidy_community_name ||
                        __('Your website name', 'seo-tidy')}
                </strong>
                <p style={{ overflowWrap: 'anywhere' }}>
                    {profile.seo_tidy_community_url ||
                        __('Your website URL', 'seo-tidy')}
                </p>
                <p>
                    {profile.seo_tidy_community_description ||
                        __('Your website description', 'seo-tidy')}
                </p>
            </div>

            {error && (
                <Notice status="error" isDismissible={false}>
                    {error}
                </Notice>
            )}

            {success && (
                <Notice status="success" isDismissible={false}>
                    {__('Community profile saved locally.', 'seo-tidy')}
                </Notice>
            )}

            <p>
                <Button
                    variant="primary"
                    disabled={!changed || saving}
                    isBusy={saving}
                    onClick={save}
                >
                    {__('Save community profile', 'seo-tidy')}
                </Button>
            </p>
        </div>
    );
}


function SearchPreview({ title, description, fallbackTitle }) {
    const shownTitle = title.trim() || fallbackTitle || '';
    const shownDescription = description.trim();

    const guidance = (value, min, max) => {
        if (!value.trim()) {
            return __('Not customized', 'seo-tidy');
        }

        const length = [...value].length;

        if (length < min) {
            return __('Consider adding more detail', 'seo-tidy');
        }
        if (length > max) {
            return __('Consider shortening the text', 'seo-tidy');
        }
        return __('Within the suggested range', 'seo-tidy');
    };

    const titleGuidance = title.trim()
        ? guidance(title, 30, 65)
        : __('Using the WordPress title', 'seo-tidy');

    return (
        <div style={{
            margin: '16px 0',
            padding: '16px',
            border: '1px solid #dcdcde',
            borderRadius: '8px',
            maxWidth: '650px',
            background: '#fff',
        }}>
            <strong style={{
                display: 'block',
                marginBottom: '12px',
                fontSize: '13px',
            }}>
                {__('Search result preview', 'seo-tidy')}
            </strong>

            <div style={{
                color: '#1a0dab',
                fontSize: '20px',
                lineHeight: '1.35',
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                whiteSpace: 'nowrap',
                marginBottom: '6px',
            }}>
                {shownTitle || __('Untitled', 'seo-tidy')}
            </div>

            <div style={{
                color: '#4d5156',
                fontSize: '14px',
                lineHeight: '1.55',
                overflowWrap: 'anywhere',
            }}>
                {shownDescription ||
                    __('No custom meta description.', 'seo-tidy')}
            </div>

            <div style={{
                borderTop: '1px solid #eee',
                paddingTop: '12px',
                marginTop: '14px',
                display: 'grid',
                gap: '8px',
                fontSize: '13px',
            }}>
                <div style={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    justifyContent: 'space-between',
                    gap: '8px',
                }}>
                    <span>{__('SEO title', 'seo-tidy')}</span>
                    <span>{titleGuidance}</span>
                </div>
                <div style={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    justifyContent: 'space-between',
                    gap: '8px',
                }}>
                    <span>{__('Meta description', 'seo-tidy')}</span>
                    <span>{guidance(description, 70, 160)}</span>
                </div>
            </div>

            <p style={{
                fontSize: '12px',
                color: '#646970',
                margin: '12px 0 0',
            }}>
                {__('Illustrative only. Search engines may show different text.', 'seo-tidy')}
            </p>
        </div>
    );
}

function MetadataList({ filter, setFilter }) {
    const [type, setType] = useState('all');
    const [page, setPage] = useState(1);
    const [result, setResult] = useState(null);
    const [error, setError] = useState(false);
    const [editing, setEditing] = useState(null);
    const [draftTitle, setDraftTitle] = useState('');
    const [draftDescription, setDraftDescription] = useState('');
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState(false);
    const [refresh, setRefresh] = useState(0);

    useEffect(() => {
        let active = true;
        setResult(null);
        setError(false);

        const query = new URLSearchParams({
            filter,
            type,
            page: String(page),
        });

        apiFetch({ path: '/seo-tidy/v1/content?' + query.toString() })
            .then((data) => {
                if (active) setResult(data);
            })
            .catch(() => {
                if (active) setError(true);
            });

        return () => { active = false; };
    }, [filter, type, page, refresh]);

    const startEditing = (item) => {
        setEditing(item.id);
        setDraftTitle(item.seoTitle || '');
        setDraftDescription(item.seoDescription || '');
        setSaveError(false);
    };

    const cancelEditing = () => {
        setEditing(null);
        setSaveError(false);
    };

    const saveMetadata = async (item) => {
        setSaving(true);
        setSaveError(false);

        try {
            await apiFetch({
                path: '/wp/v2/' + (item.type === 'page' ? 'pages' : 'posts') +
                    '/' + item.id,
                method: 'POST',
                data: {
                    meta: {
                        _seo_tidy_title: draftTitle,
                        _seo_tidy_description: draftDescription,
                    },
                },
            });

            setEditing(null);
            setRefresh((value) => value + 1);
        } catch {
            setSaveError(true);
        } finally {
            setSaving(false);
        }
    };

    const filters = [
        ['all', __('All published content', 'seo-tidy')],
        ['missing_title', __('Missing SEO titles', 'seo-tidy')],
        ['missing_description', __('Missing meta descriptions', 'seo-tidy')],
    ];

    const types = [
        ['all', __('Posts and pages', 'seo-tidy')],
        ['post', __('Posts', 'seo-tidy')],
        ['page', __('Pages', 'seo-tidy')],
    ];

    return (
        <div className="tidy-metadata">
            <div className="tidy-metadata-filters">
                <label className="tidy-metadata-filter">
                    {__('Show', 'seo-tidy')}{' '}
                    <select value={filter} onChange={(event) => {
                        setFilter(event.target.value);
                        setPage(1);
                    }}>
                        {filters.map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                </label>
                <label className="tidy-metadata-filter">
                    {__('Content type', 'seo-tidy')}{' '}
                    <select value={type} onChange={(event) => {
                        setType(event.target.value);
                        setPage(1);
                    }}>
                        {types.map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                </label>
            </div>

            {error && (
                <Notice status="error" isDismissible={false}>
                    {__('Could not load content.', 'seo-tidy')}
                </Notice>
            )}

            {!error && !result && <Spinner />}

            {result && (
                <>
                    <p className="tidy-metadata-count">{__('Matching items:', 'seo-tidy')} <strong>{result.total}</strong></p>
                    <div className="tidy-metadata-table-wrap">
                    <table className="widefat striped tidy-metadata-table">
                        <thead>
                            <tr>
                                <th>{__('Title', 'seo-tidy')}</th>
                                <th>{__('Type', 'seo-tidy')}</th>
                                <th>{__('SEO title', 'seo-tidy')}</th>
                                <th>{__('Meta description', 'seo-tidy')}</th>
                                <th>{__('Action', 'seo-tidy')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {result.items.map((item) => (
                                <tr key={item.id}>
                                    <td className="tidy-metadata-title">{item.title || __('Untitled', 'seo-tidy')}</td>
                                    <td className="tidy-metadata-type">{item.type === 'post'
                                        ? __('Post', 'seo-tidy')
                                        : __('Page', 'seo-tidy')}</td>
                                    <td><span className={'tidy-metadata-status ' + (item.hasTitle ? 'tidy-metadata-status-added' : 'tidy-metadata-status-missing')}>
                                        {item.hasTitle
                                            ? __('Added', 'seo-tidy')
                                            : __('Missing', 'seo-tidy')}
                                    </span></td>
                                    <td><span className={'tidy-metadata-status ' + (item.hasDescription ? 'tidy-metadata-status-added' : 'tidy-metadata-status-missing')}>
                                        {item.hasDescription
                                            ? __('Added', 'seo-tidy')
                                            : __('Missing', 'seo-tidy')}
                                    </span></td>
                                    <td className="tidy-metadata-actions">
                                        <Button
                                            variant="link"
                                            disabled={saving}
                                            onClick={() => startEditing(item)}
                                        >
                                            {__('Quick edit', 'seo-tidy')}
                                        </Button>
                                        {item.editUrl && (
                                            <a
                                                href={item.editUrl}
                                                className="tidy-metadata-edit-link"
                                            >
                                                {__('Edit', 'seo-tidy')}
                                            </a>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    </div>

                    {editing !== null && (() => {
                        const item = result.items.find((row) => row.id === editing);

                        if (!item) return null;

                        return (
                            <div className="tidy-metadata-quick-edit">
                                <h3>
                                    {__('Quick edit', 'seo-tidy')}: {item.title}
                                </h3>
                                <TextControl
                                    label={__('SEO title', 'seo-tidy')}
                                    value={draftTitle}
                                    help={String([...draftTitle].length) + ' / 30-65'}
                                    disabled={saving}
                                    onChange={setDraftTitle}
                                />
                                <TextareaControl
                                    label={__('Meta description', 'seo-tidy')}
                                    value={draftDescription}
                                    help={String([...draftDescription].length) + ' / 70-160'}
                                    disabled={saving}
                                    onChange={setDraftDescription}
                                />
                                <SearchPreview
                                    title={draftTitle}
                                    description={draftDescription}
                                    fallbackTitle={item.title}
                                />

                                {saveError && (
                                    <Notice status="error" isDismissible={false}>
                                        {__('Could not save metadata.', 'seo-tidy')}
                                    </Notice>
                                )}

                                <div className="tidy-metadata-edit-buttons">
                                    <Button
                                        variant="primary"
                                        isBusy={saving}
                                        disabled={saving}
                                        onClick={() => saveMetadata(item)}
                                    >
                                        {__('Save changes', 'seo-tidy')}
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        disabled={saving}
                                        onClick={cancelEditing}
                                    >
                                        {__('Cancel', 'seo-tidy')}
                                    </Button>
                                </div>
                            </div>
                        );
                    })()}

                    {result.total === 0 && (
                        <p>{__('No matching content found.', 'seo-tidy')}</p>
                    )}

                    {result.pages > 1 && (
                        <div className="tidy-metadata-pagination">
                            <Button variant="secondary"
                                disabled={page <= 1}
                                onClick={() => setPage(page - 1)}>
                                {__('Previous', 'seo-tidy')}
                            </Button>
                            <span>{page} / {result.pages}</span>
                            <Button variant="secondary"
                                disabled={page >= result.pages}
                                onClick={() => setPage(page + 1)}>
                                {__('Next', 'seo-tidy')}
                            </Button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}


const auditLabels = {
    missing_title: __('Missing SEO title', 'seo-tidy'),
    default_title: __('Using the WordPress title. A custom SEO title is optional.', 'seo-tidy'),
    short_title: __('SEO title may be too short', 'seo-tidy'),
    long_title: __('SEO title may be too long', 'seo-tidy'),
    missing_description: __('Missing meta description', 'seo-tidy'),
    short_description: __('Meta description may be too short', 'seo-tidy'),
    long_description: __('Meta description may be too long', 'seo-tidy'),
    review_h1: __('Review H1 heading (theme may provide it)', 'seo-tidy'),
    duplicate_title: __('Duplicate SEO title', 'seo-tidy'),
    duplicate_description: __('Duplicate meta description', 'seo-tidy'),
    site_noindex: __('Search engine indexing is discouraged site-wide', 'seo-tidy'),
    review_noindex: __('Review this page noindex setting', 'seo-tidy'),
    review_internal_links: __('Consider adding internal links to other pages', 'seo-tidy'),
    review_incoming_links: __('No incoming links found from other published content', 'seo-tidy'),
    review_unavailable_post_link: __('Review links to missing or unpublished WordPress content', 'seo-tidy'),
    review_image_alt: __('Review image alternative text', 'seo-tidy'),
};

const auditAttentionCodes = new Set([
    'missing_title',
    'duplicate_title',
    'duplicate_description',
    'site_noindex',
]);

function isAuditAttention(issue) {
    return auditAttentionCodes.has(issue);
}

function SEOAuditOverview() {
    const [page, setPage] = useState(1);
    const [data, setData] = useState(null);
    const [error, setError] = useState(false);
    const [issuesOnly, setIssuesOnly] = useState(true);
    const [editing, setEditing] = useState(null);
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState(false);
    const [refresh, setRefresh] = useState(0);
    const [scanKey, setScanKey] = useState(0);
    const [scan, setScan] = useState(null);
    const [categoryFilter, setCategoryFilter] = useState('all');

    useEffect(() => {
        if (scan?.status === 'complete') return;

        let active = true;
        setData(null);
        setError(false);

        apiFetch({ path: '/seo-tidy/v1/audit?page=' + page })
            .then((response) => {
                if (active) setData(response);
            })
            .catch(() => {
                if (active) setError(true);
            });

        return () => { active = false; };
    }, [page, refresh, scan?.status]);

    useEffect(() => {
        if (scanKey === 0) return;

        let active = true;

        const run = async () => {
            const collected = [];
            const seen = new Set();
            let total = null;
            let firstIds = [];

            try {
                for (let current = 1; current <= 100; ++current) {
                    const result = await apiFetch({
                        path: '/seo-tidy/v1/audit?page=' + current,
                    });

                    if (!active) return;

                    if (total === null) {
                        total = result.total;
                        firstIds = result.items.map((item) => item.id);

                        if (result.pages > 100) {
                            throw Error('scan_limit');
                        }
                    }

                    if (result.total !== total ||
                        result.pages !== Math.ceil(total / 20)) {
                        throw Error('content_changed');
                    }

                    for (const item of result.items) {
                        if (seen.has(item.id)) {
                            throw Error('duplicate_item');
                        }
                        seen.add(item.id);
                        collected.push(item);
                    }

                    setScan({
                        status: 'running',
                        processed: collected.length,
                        total,
                    });

                    if (current >= result.pages) break;
                }

                if (collected.length !== total) {
                    throw Error('incomplete_scan');
                }

                const verify = await apiFetch({
                    path: '/seo-tidy/v1/audit?page=1',
                });

                if (!active) return;

                if (verify.total !== total ||
                    JSON.stringify(verify.items.map((item) => item.id)) !==
                    JSON.stringify(firstIds)) {
                    throw Error('content_changed');
                }

                setPage(1);
                setCategoryFilter('all');
                setScan({
                    status: 'complete',
                    items: collected,
                    total,
                });
            } catch {
                if (active) {
                    setScan({ status: 'error' });
                }
            }
        };

        setScan({ status: 'running', processed: 0, total: 0 });
        run();

        return () => { active = false; };
    }, [scanKey, refresh]);

    const startEdit = (item) => {
        setEditing(item.id);
        setTitle(item.seoTitle || '');
        setDescription(item.seoDescription || '');
        setSaveError(false);
    };

    const save = async (item) => {
        setSaving(true);
        setSaveError(false);
        try {
            await apiFetch({
                path: '/wp/v2/' +
                    (item.type === 'page' ? 'pages' : 'posts') +
                    '/' + item.id,
                method: 'POST',
                data: {
                    meta: {
                        _seo_tidy_title: title,
                        _seo_tidy_description: description,
                    },
                },
            });
            setEditing(null);
            setRefresh((current) => current + 1);
        } catch {
            setSaveError(true);
        } finally {
            setSaving(false);
        }
    };

    const scanned = scan?.status === 'complete';
    const allItems = scanned ? scan.items : (data?.items || []);
    const filtered = allItems.filter((item) => {
        if (!scanned && issuesOnly && item.issues.length === 0) {
            return false;
        }
        if (scanned && categoryFilter !== 'all' &&
            item.category !== categoryFilter) {
            return false;
        }
        return true;
    });

    const items = scanned
        ? filtered.slice((page - 1) * 20, page * 20)
        : filtered;

    const resultPages = scanned
        ? Math.max(1, Math.ceil(filtered.length / 20))
        : (data?.pages || 1);

    const counts = scanned
        ? scan.items.reduce((totals, item) => {
            totals[item.category] += 1;
            return totals;
        }, { attention: 0, recommendations: 0, clear: 0 })
        : null;

    return (
        <div className="tidy-audit">
            <p>
                {__('Checks published posts and pages in batches of 20. Lengths are guidance, not SEO scores.', 'seo-tidy')}
            </p>
            <p>
                {__('H1 and image checks inspect stored content, not the final theme output.', 'seo-tidy')}
            </p>

            <p>
                {__('Incoming link checks cover up to 200 published posts and pages. Links added by themes or dynamic blocks are not included.', 'seo-tidy')}
            </p>
            {data?.incomingCheckSkipped && (
                <Notice status="warning" isDismissible={false}>
                    {__('Incoming link check skipped for this site.', 'seo-tidy')}
                </Notice>
            )}

            <div className="tidy-audit-scan-controls">
                <Button
                    variant="secondary"
                    disabled={scan?.status === 'running'}
                    onClick={() => setScanKey((key) => key + 1)}
                >
                    {__('Run full SEO audit', 'seo-tidy')}
                </Button>
                {scan?.status === 'running' && (
                    <>
                        <span role="status">
                            {__('Scanning:', 'seo-tidy')}
                            {' '}{scan.processed} / {scan.total || '?'}
                        </span>
                        <Button
                            variant="tertiary"
                            onClick={() => {
                                setScanKey(0);
                                setScan(null);
                                setCategoryFilter('all');
                                setPage(1);
                            }}
                        >
                            {__('Cancel scan', 'seo-tidy')}
                        </Button>
                    </>
                )}
                {scan?.status === 'error' && (
                    <Notice status="error" isDismissible={false}>
                        {__('Scan could not be completed. Results were not saved.', 'seo-tidy')}
                    </Notice>
                )}
            </div>

            {counts && (
                <div className="tidy-audit-scan-summary">
                    <strong>{__('Scan complete', 'seo-tidy')}</strong>
                    <div className="tidy-audit-metrics">
                        <div className="tidy-audit-metric">
                            <strong>{scan.total}</strong>
                            <span>{__('Published content:', 'seo-tidy')}</span>
                        </div>
                        <div className="tidy-audit-metric">
                            <strong>{counts.attention}</strong>
                            <span>{__('Needs attention', 'seo-tidy')}</span>
                        </div>
                        <div className="tidy-audit-metric">
                            <strong>{counts.recommendations}</strong>
                            <span>{__('Recommendations', 'seo-tidy')}</span>
                        </div>
                        <div className="tidy-audit-metric">
                            <strong>{counts.clear}</strong>
                            <span>{__('No findings', 'seo-tidy')}</span>
                        </div>
                    </div>
                    <label>
                        {__('Filter by priority', 'seo-tidy')}{' '}
                        <select
                            value={categoryFilter}
                            onChange={(event) => {
                                setCategoryFilter(event.target.value);
                                setPage(1);
                            }}
                        >
                            <option value="all">
                                {__('All categories', 'seo-tidy')}
                            </option>
                            <option value="attention">
                                {__('Needs attention', 'seo-tidy')}
                            </option>
                            <option value="recommendations">
                                {__('Recommendations', 'seo-tidy')}
                            </option>
                            <option value="clear">
                                {__('No findings', 'seo-tidy')}
                            </option>
                        </select>
                    </label>
                </div>
            )}

            {!scanned && (
            <label className="tidy-audit-filter">
                <input
                    type="checkbox"
                    checked={issuesOnly}
                    onChange={(event) => {
                        setIssuesOnly(event.target.checked);
                        setPage(1);
                    }}
                />
                {' '}
                {__('Show only items with findings', 'seo-tidy')}
            </label>
            )}

            {error && (
                <Notice status="error" isDismissible={false}>
                    {__('Could not load SEO audit.', 'seo-tidy')}
                </Notice>
            )}

            {!error && !data && <Spinner />}

            {data && (
                <>
                    {!scanned && (
                        <p>
                            {__('Published content:', 'seo-tidy')}
                            {' '}
                            <strong>{data.total}</strong>
                        </p>
                    )}

                    <div className="tidy-audit-table-wrap">
                    <table className="widefat striped tidy-audit-table">
                        <thead>
                            <tr>
                                <th>{__('Title', 'seo-tidy')}</th>
                                <th>{__('Findings', 'seo-tidy')}</th>
                                <th>{__('Action', 'seo-tidy')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item.id} className={item.issues.length ? "tidy-audit-row-issues" : "tidy-audit-row-clear"}>
                                    <td className="tidy-audit-title">{item.title || __('Untitled', 'seo-tidy')}</td>
                                    <td className="tidy-audit-findings">
                                        {(() => {
                                            const attention = item.issues.filter(
                                                isAuditAttention
                                            );
                                            const advice = item.issues.filter(
                                                (issue) => !isAuditAttention(issue)
                                            );

                                            if (!item.issues.length) {
                                                return <span className="tidy-audit-clear">{__('No findings', 'seo-tidy')}</span>;
                                            }

                                            return (
                                                <>
                                                    {attention.length > 0 && (
                                                        <div className="tidy-audit-attention">
                                                            <strong>
                                                                {__('Needs attention', 'seo-tidy')}
                                                            </strong>
                                                            <ul className="tidy-audit-issue-list">
                                                                {attention.map((issue) => (
                                                                    <li key={issue}>
                                                                        {auditLabels[issue]}
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        </div>
                                                    )}
                                                    {advice.length > 0 && (
                                                        <details className="tidy-audit-recommendations">
                                                            <summary>
                                                                {__('Recommendations', 'seo-tidy')}
                                                                {' (' + advice.length + ')'}
                                                            </summary>
                                                            <ul className="tidy-audit-issue-list">
                                                                {advice.map((issue) => (
                                                                    <li key={issue}>
                                                                        {auditLabels[issue]}
                                                                        {issue === 'review_unavailable_post_link' &&
                                                                            Array.isArray(item.unavailableLinks) &&
                                                                            item.unavailableLinks.length > 0 && (
                                                                                <div className="tidy-audit-affected">
                                                                                    <strong>
                                                                                        {__('Affected links:', 'seo-tidy')}
                                                                                    </strong>
                                                                                    <ul className="tidy-audit-link-list">
                                                                                        {item.unavailableLinks.map((href) => (
                                                                                            <li key={href}>
                                                                                                <code>{href}</code>
                                                                                            </li>
                                                                                        ))}
                                                                                    </ul>
                                                                                </div>
                                                                            )}
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        </details>
                                                    )}
                                                </>
                                            );
                                        })()}
                                    </td>
                                    <td className="tidy-audit-actions">
                                        <Button
                                            variant="link"
                                            disabled={saving}
                                            onClick={() => startEdit(item)}
                                        >
                                            {__('Quick edit', 'seo-tidy')}
                                        </Button>
                                        {item.editUrl && (
                                            <a href={item.editUrl}
                                                className="tidy-audit-edit-link">
                                                {__('Edit', 'seo-tidy')}
                                            </a>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    </div>

                    {editing !== null && (() => {
                        const item = allItems.find((entry) =>
                            entry.id === editing
                        );
                        if (!item) return null;

                        return (
                            <div className="tidy-audit-quick-edit">
                                <h3>
                                    {__('Quick edit', 'seo-tidy')}: {item.title}
                                </h3>
                                <TextControl
                                    label={__('SEO title', 'seo-tidy')}
                                    value={title}
                                    help={String([...title].length) + ' / 30-65'}
                                    onChange={setTitle}
                                    disabled={saving}
                                />
                                <TextareaControl
                                    label={__('Meta description', 'seo-tidy')}
                                    value={description}
                                    help={String([...description].length) + ' / 70-160'}
                                    onChange={setDescription}
                                    disabled={saving}
                                />
                                <SearchPreview
                                    title={title}
                                    description={description}
                                    fallbackTitle={item.title}
                                />
                                {saveError && (
                                    <Notice status="error" isDismissible={false}>
                                        {__('Could not save metadata.', 'seo-tidy')}
                                    </Notice>
                                )}
                                <div className="tidy-audit-edit-buttons">
                                    <Button
                                        variant="primary"
                                        isBusy={saving}
                                        disabled={saving}
                                        onClick={() => save(item)}
                                    >
                                        {__('Save changes', 'seo-tidy')}
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        disabled={saving}
                                        onClick={() => setEditing(null)}
                                    >
                                        {__('Cancel', 'seo-tidy')}
                                    </Button>
                                </div>
                            </div>
                        );
                    })()}

                    {items.length === 0 && (
                        <p>
                            {scanned
                                ? __('No items in the selected category.', 'seo-tidy')
                                : __('No findings on this page.', 'seo-tidy')}
                        </p>
                    )}

                    {resultPages > 1 && (
                        <div className="tidy-audit-pagination">
                            <Button variant="secondary"
                                disabled={page <= 1}
                                onClick={() => setPage(page - 1)}>
                                {__('Previous', 'seo-tidy')}
                            </Button>
                            <span>{page} / {resultPages}</span>
                            <Button variant="secondary"
                                disabled={page >= resultPages}
                                onClick={() => setPage(page + 1)}>
                                {__('Next', 'seo-tidy')}
                            </Button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}


const answerSuggestions = {
    empty_content: __('The content is empty.', 'seo-tidy'),
    review_introduction: __('Review the introduction: explain the subject clearly near the beginning.', 'seo-tidy'),
    review_sections: __('Consider dividing longer content into descriptive sections.', 'seo-tidy'),
    consider_questions: __('Consider adding a question-and-answer section if relevant.', 'seo-tidy'),
};

function AnswerReadinessOverview() {
    const [page, setPage] = useState(1);
    const [data, setData] = useState(null);
    const [error, setError] = useState(false);
    const [suggestionsOnly, setSuggestionsOnly] = useState(false);

    useEffect(() => {
        let active = true;
        setData(null);
        setError(false);

        apiFetch({ path: '/seo-tidy/v1/answers?page=' + page })
            .then((result) => {
                if (active) setData(result);
            })
            .catch(() => {
                if (active) setError(true);
            });

        return () => { active = false; };
    }, [page]);

    const items = data
        ? data.items.filter((item) =>
            !suggestionsOnly || item.suggestions.length > 0
        )
        : [];

    return (
        <div className="tidy-answers">
            <p>
                {__('Content structure review for humans and answer systems. This does not predict AI citations or search rankings.', 'seo-tidy')}
            </p>
            <p>
                {__('Checks saved WordPress content; dynamic blocks and themes may produce different final HTML.', 'seo-tidy')}
            </p>
            <label className="tidy-answers-filter">
                <input
                    type="checkbox"
                    checked={suggestionsOnly}
                    onChange={(event) => setSuggestionsOnly(event.target.checked)}
                />
                {' '}
                {__('Show only content with suggestions', 'seo-tidy')}
            </label>

            {error && (
                <Notice status="error" isDismissible={false}>
                    {__('Could not load content review.', 'seo-tidy')}
                </Notice>
            )}
            {!error && !data && <Spinner />}

            {data && (
                <>
                    <p className="tidy-answers-count">
                        {__('Published content:', 'seo-tidy')}
                        {' '}
                        <strong>{data.total}</strong>
                    </p>
                    <div className="tidy-answers-table-wrap">
                    <table className="widefat striped tidy-answers-table">
                        <thead>
                            <tr>
                                <th>{__('Title', 'seo-tidy')}</th>
                                <th>{__('Content structure', 'seo-tidy')}</th>
                                <th>{__('Suggestions', 'seo-tidy')}</th>
                                <th>{__('Action', 'seo-tidy')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item.id} className={item.suggestions.length ? "tidy-answers-row-review" : "tidy-answers-row-clear"}>
                                    <td className="tidy-answers-title">
                                        {item.title || __('Untitled', 'seo-tidy')}
                                    </td>
                                    <td className="tidy-answers-facts">
                                        <div>
                                            {__('Characters:', 'seo-tidy')}
                                            {' '}{item.facts.characters}
                                        </div>
                                        <div>
                                            {__('Section headings:', 'seo-tidy')}
                                            {' '}{item.facts.headings}
                                        </div>
                                        <div>
                                            {__('Question headings:', 'seo-tidy')}
                                            {' '}{item.facts.questionHeadings}
                                        </div>
                                    </td>
                                    <td className="tidy-answers-suggestions">
                                        {item.suggestions.length === 0
                                            ? <span className="tidy-answers-clear">{__('No suggestions', 'seo-tidy')}</span>
                                            : (
                                                <ul className="tidy-answers-suggestion-list">
                                                    {item.suggestions.map((code) => (
                                                        <li key={code}>
                                                            {answerSuggestions[code] || code}
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                    </td>
                                    <td className="tidy-answers-actions">
                                        {item.editUrl && (
                                            <a href={item.editUrl}>
                                                {__('Edit', 'seo-tidy')}
                                            </a>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    </div>
                    {items.length === 0 && (
                        <p>
                            {__('No matching content on this page.', 'seo-tidy')}
                        </p>
                    )}
                    {data.pages > 1 && (
                        <div className="tidy-answers-pagination">
                            <Button
                                variant="secondary"
                                disabled={page <= 1}
                                onClick={() => setPage(page - 1)}
                            >
                                {__('Previous', 'seo-tidy')}
                            </Button>
                            <span>{page} / {data.pages}</span>
                            <Button
                                variant="secondary"
                                disabled={page >= data.pages}
                                onClick={() => setPage(page + 1)}
                            >
                                {__('Next', 'seo-tidy')}
                            </Button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}


function MigrationOverview() {
    const [source, setSource] = useState('yoast');
    const [preview, setPreview] = useState(null);
    const [cursor, setCursor] = useState(0);
    const [confirmed, setConfirmed] = useState(false);
    const [busy, setBusy] = useState(false);
    const [finished, setFinished] = useState(false);
    const [error, setError] = useState(false);
    const [titles, setTitles] = useState(0);
    const [descriptions, setDescriptions] = useState(0);
    const [processed, setProcessed] = useState(0);

    useEffect(() => {
        let active = true;
        setPreview(null);
        setCursor(0);
        setConfirmed(false);
        setFinished(false);
        setError(false);
        setTitles(0);
        setDescriptions(0);
        setProcessed(0);

        apiFetch({ path: '/seo-tidy/v1/migration?source=' + source })
            .then((result) => {
                if (active) setPreview(result);
            })
            .catch(() => {
                if (active) setError(true);
            });

        return () => { active = false; };
    }, [source]);

    const importNext = async () => {
        setBusy(true);
        setError(false);

        try {
            const result = await apiFetch({
                path: '/seo-tidy/v1/migration',
                method: 'POST',
                data: {
                    source,
                    cursor,
                    token: preview.token,
                    confirmed,
                },
            });

            setCursor(result.cursor);
            setProcessed((value) => value + result.processed);
            setTitles((value) => value + result.importedTitles);
            setDescriptions((value) => value + result.importedDescriptions);
            setFinished(result.done);
        } catch {
            setError(true);
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="tidy-migration">
            <p>
                {__('Import SEO metadata from another plugin without deleting its data or replacing populated SEO-TidY fields.', 'seo-tidy')}
            </p>
            <p>
                {__('Only published posts and pages are included. Unresolved template variables are skipped.', 'seo-tidy')}
            </p>

            <label className="tidy-migration-source">
                {__('Source plugin', 'seo-tidy')}{' '}
                <select
                    value={source}
                    disabled={busy || processed > 0}
                    onChange={(event) => setSource(event.target.value)}
                >
                    <option value="yoast">Yoast SEO</option>
                    <option value="rank_math">Rank Math</option>
                </select>
            </label>

            {preview && (
                <div className="tidy-migration-preview">
                    <div className="tidy-migration-stats">
                    <p>
                        {__('Potential SEO titles:', 'seo-tidy')}
                        {' '}<strong>{preview.titles}</strong>
                    </p>
                    <p>
                        {__('Potential meta descriptions:', 'seo-tidy')}
                        {' '}<strong>{preview.descriptions}</strong>
                    </p>
                    </div>
                    <p className="tidy-migration-note">
                        {__('Preview counts may include values that are skipped during import.', 'seo-tidy')}
                    </p>

                    {!finished && (
                        <div className="tidy-migration-confirm">
                            <ToggleControl
                                label={__('I confirm that I want to import the available metadata.', 'seo-tidy')}
                                checked={confirmed}
                                disabled={busy || processed > 0}
                                onChange={setConfirmed}
                            />
                            <Button
                                variant="primary"
                                disabled={!confirmed || busy}
                                isBusy={busy}
                                onClick={importNext}
                                className="tidy-migration-import-button"
                            >
                                {__('Import next 20 posts or pages', 'seo-tidy')}
                            </Button>
                        </div>
                    )}

                    {processed > 0 && (
                        <p className="tidy-migration-progress">
                            {__('Processed:', 'seo-tidy')} {processed}
                            {' | '}
                            {__('Titles imported:', 'seo-tidy')} {titles}
                            {' | '}
                            {__('Descriptions imported:', 'seo-tidy')} {descriptions}
                        </p>
                    )}

                    {finished && (
                        <Notice status="success" isDismissible={false}>
                            {__('Migration finished.', 'seo-tidy')}
                        </Notice>
                    )}
                </div>
            )}

            {!preview && !error && <Spinner />}

            {error && (
                <Notice status="error" isDismissible={false}>
                    {__('Migration request failed. You can retry safely.', 'seo-tidy')}
                </Notice>
            )}
        </div>
    );
}

function SupportLinks() {
    return (
        <div style={{
            display: 'flex',
            flexWrap: 'wrap',
            gap: '16px',
            marginTop: '24px',
            paddingTop: '12px',
            borderTop: '1px solid #ddd',
        }}>
            <a href="https://github.com/QvarcY"
                target="_blank" rel="noopener noreferrer">
                GitHub - QvarcY
            </a>
            <a href="https://github.com/QvarcY/wordpress-seo-tidy/issues"
                target="_blank" rel="noopener noreferrer">
                {__('Report an issue', 'seo-tidy')}
            </a>
            <a href="https://buymeacoffee.com/craftin"
                target="_blank" rel="noopener noreferrer"
                style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: '8px',
                    padding: '7px 12px',
                    border: '1px solid #e4be46',
                    borderRadius: '8px',
                    background: '#fff3c4',
                    color: '#493711',
                    fontWeight: 600,
                    textDecoration: 'none',
                }}>
                <svg
                    width="19"
                    height="19"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.9"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                >
                    <path d="M4 8h13v7a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V8Z" />
                    <path d="M17 9h2a3 3 0 0 1 0 6h-2" />
                    <path d="M8 3v2M12 3v2" />
                    <path d="M3 22h17" />
                </svg>
                Buy Me a Coffee
            </a>
        </div>
    );
}


function GeneralSettings() {
    const keys = [
        {
            key: 'seo_tidy_title_enabled',
            label: __('Enable SEO title output', 'seo-tidy'),
            help: __('Use custom SEO titles on published posts and pages.', 'seo-tidy'),
        },
        {
            key: 'seo_tidy_description_enabled',
            label: __('Enable meta description output', 'seo-tidy'),
            help: __('Output saved meta descriptions in the HTML head.', 'seo-tidy'),
        },
        {
            key: 'seo_tidy_schema_enabled',
            label: __('Enable automatic Schema', 'seo-tidy'),
            help: __('Posts use BlogPosting; pages use WebPage.', 'seo-tidy'),
        },
        {
            key: 'seo_tidy_audit_h1_enabled',
            label: __('Enable H1 review suggestions', 'seo-tidy'),
            help: __('Show optional H1 findings in the SEO audit.', 'seo-tidy'),
        },
        {
            key: 'seo_tidy_beta_updates',
            label: __('Enable beta updates', 'seo-tidy'),
            help: __('Receive experimental SEO-TidY releases from GitHub. Stable updates remain available when disabled.', 'seo-tidy'),
        },
    ];

    const [values, setValues] = useState(null);
    const [saved, setSaved] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(false);

    useEffect(() => {
        let active = true;
        apiFetch({ path: '/wp/v2/settings' })
            .then((data) => {
                if (!active) return;

                const options = {};
                for (const item of keys) {
                    options[item.key] = item.key === 'seo_tidy_beta_updates'
                        ? [true, 1, '1'].includes(data[item.key])
                        : ![false, 0, '0', null].includes(data[item.key]);
                }

                setValues(options);
                setSaved(options);
            })
            .catch(() => {
                if (active) setError(true);
            });
        return () => { active = false; };
    }, []);

    const save = async () => {
        setBusy(true);
        setError(false);

        try {
            const result = await apiFetch({
                path: '/wp/v2/settings',
                method: 'POST',
                data: values,
            });

            const updated = {};
            for (const item of keys) {
                updated[item.key] = [true, 1, '1'].includes(result[item.key]);
            }

            setValues(updated);
            setSaved(updated);
        } catch {
            setError(true);
        } finally {
            setBusy(false);
        }
    };

    const changed = values && saved &&
        keys.some((item) => values[item.key] !== saved[item.key]);

    return (
        <div className="tidy-settings-panel tidy-general-settings">
            <p>
                {__('Manage SEO-TidY output and audit preferences.', 'seo-tidy')}
            </p>
            <p>
                {__('Disabling output keeps your saved SEO metadata.', 'seo-tidy')}
            </p>

            {!values && !error && <Spinner />}

            {error && (
                <Notice status="error" isDismissible={false}>
                    {__('Could not load or save settings.', 'seo-tidy')}
                </Notice>
            )}

            {values && (
                <>
                    <div className="tidy-settings-list">
                    {keys.map((item) => (
                        <div className="tidy-setting-row" key={item.key}>
                        <ToggleControl
                            label={item.label}
                            help={item.help}
                            checked={values[item.key]}
                            disabled={busy}
                            onChange={(next) => {
                                setValues((current) => ({
                                    ...current,
                                    [item.key]: next,
                                }));
                            }}
                        />
                        </div>
                    ))}
                    </div>

                    <Button
                        variant="primary"
                        className="tidy-settings-save"
                        disabled={!changed || busy}
                        isBusy={busy}
                        onClick={save}
                    >
                        {__('Save changes', 'seo-tidy')}
                    </Button>
                </>
            )}
        </div>
    );
}

function App() {
    const [active, setActive] = useState('dashboard');
    const [metadataFilter, setMetadataFilter] = useState('all');

    const openMetadata = (filter) => {
        setMetadataFilter(filter);
        setActive('metadata');
    };
    const current = sections.find((item) => item.id === active);

    return (
        <div className="wrap seo-tidy-admin">
            <header className="tidy-header">
                <h1>SEO-TidY</h1>
                <p>{__('SEO and AI search readiness for WordPress.', 'seo-tidy')}</p>
            </header>
            {active === 'dashboard' && window.seoTidyBranding?.heroUrl && (
                <img
                    src={window.seoTidyBranding.heroUrl}
                    className="tidy-hero"
                    alt="SEO-TidY by QvarcY"
                    style={{
                        display: 'block',
                        width: '100%',
                        maxWidth: '1200px',
                        height: 'auto',
                        borderRadius: '12px',
                        marginBottom: '20px',
                    }}
                />
            )}

            <nav
                aria-label={__('SEO-TidY navigation', 'seo-tidy')}
                className="tidy-navigation"
            >
                {sections.map((section) => (
                    <Button
                        key={section.id}
                        variant={active === section.id ? 'primary' : 'secondary'}
                        onClick={() => setActive(section.id)}
                        aria-pressed={active === section.id}
                    >
                        {section.label}
                    </Button>
                ))}
            </nav>

            <Card className="tidy-main-card">
                <CardBody>
                    <h2>{current.label}</h2>

                    {active === 'settings' ? (
                        <GeneralSettings />
                    ) : active === 'community' ? (
                        <WallOfFame />
                    ) : active === 'badge' ? (
                        <StatsBadgeSettings />
                    ) : active === 'analytics' ? (
                        <AnalyticsOverview />
                    ) : active === 'migration' ? (
                        <MigrationOverview />
                    ) : active === 'answers' ? (
                        <AnswerReadinessOverview />
                    ) : active === 'audit' ? (
                        <SEOAuditOverview />
                    ) : active === 'schema' ? (
                        <SchemaSettings />
                    ) : active === 'metadata' ? (
                        <MetadataList
                            filter={metadataFilter}
                            setFilter={setMetadataFilter}
                        />
                    ) : active === 'dashboard' ? (
                        <DashboardOverview
                            onOpenSchema={() => setActive('schema')}
                            onOpenMetadata={openMetadata}
                        />
                    ) : (
                        <p>
                            {__('This feature is planned and not available yet.', 'seo-tidy')}
                        </p>
                    )}
                </CardBody>
            </Card>
            <SupportLinks />
        </div>
    );
}

const root = document.getElementById('seo-tidy-app');

if (root) {
    createRoot(root).render(<App />);
}

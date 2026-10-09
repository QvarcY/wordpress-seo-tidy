import { createElement, createRoot, useState, useEffect } from '@wordpress/element';
import { Button, Card, CardBody, Notice, ToggleControl, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const sections = [
    { id: 'dashboard', label: __('Dashboard', 'seo-tidy') },
    { id: 'metadata', label: __('Metadata', 'seo-tidy') },
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
        <div>
            <p>
                {__('Automatically add structured data to published posts and pages.', 'seo-tidy')}
            </p>

            {loading ? (
                <Spinner />
            ) : (
                <ToggleControl
                    label={__('Enable automatic Schema', 'seo-tidy')}
                    help={__('Posts use BlogPosting; pages use WebPage.', 'seo-tidy')}
                    checked={enabled}
                    disabled={saving || message?.text === __('Could not load Schema settings.', 'seo-tidy')}
                    onChange={save}
                />
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


function DashboardOverview({ onOpenSchema }) {
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
        },
        {
            label: __('Missing meta descriptions', 'seo-tidy'),
            value: data.missingDescriptions,
        },
    ];

    return (
        <div>
            <p>
                {__('Published posts and pages:', 'seo-tidy')}
                {' '}
                <strong>{data.total}</strong>
            </p>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(185px, 1fr))',
                    gap: '12px',
                    marginBottom: '20px',
                }}
            >
                {metrics.map((metric) => (
                    <div
                        key={metric.label}
                        style={{
                            border: '1px solid #ddd',
                            borderRadius: '6px',
                            padding: '14px',
                        }}
                    >
                        <div style={{ fontSize: '13px', marginBottom: '8px' }}>
                            {metric.label}
                        </div>
                        <strong style={{ fontSize: '25px' }}>
                            {metric.value}
                        </strong>
                    </div>
                ))}
            </div>

            <p>
                <strong>{__('Smart Schema:', 'seo-tidy')}</strong>
                {' '}
                {data.schemaEnabled
                    ? __('Enabled', 'seo-tidy')
                    : __('Disabled', 'seo-tidy')}
                {' '}
                <Button variant="link" onClick={onOpenSchema}>
                    {__('Manage Schema', 'seo-tidy')}
                </Button>
            </p>

            <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                <Button variant="secondary" href={data.links.posts}>
                    {__('Edit posts', 'seo-tidy')}
                </Button>
                <Button variant="secondary" href={data.links.pages}>
                    {__('Edit pages', 'seo-tidy')}
                </Button>
            </div>

            <p style={{ marginTop: '18px', color: '#666' }}>
                {__('Counts include published posts and pages only.', 'seo-tidy')}
            </p>
        </div>
    );
}

function App() {
    const [active, setActive] = useState('dashboard');
    const current = sections.find((item) => item.id === active);

    return (
        <div className="wrap seo-tidy-admin">
            <h1>SEO-TidY</h1>
            <p>{__('SEO and AI search readiness for WordPress.', 'seo-tidy')}</p>

            <nav
                aria-label={__('SEO-TidY navigation', 'seo-tidy')}
                style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', marginBottom: '20px' }}
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

            <Card>
                <CardBody>
                    <h2>{current.label}</h2>

                    {active === 'schema' ? (
                        <SchemaSettings />
                    ) : active === 'dashboard' ? (
                        <DashboardOverview onOpenSchema={() => setActive('schema')} />
                    ) : (
                        <p>
                            {__('This feature is planned and not available yet.', 'seo-tidy')}
                        </p>
                    )}
                </CardBody>
            </Card>
        </div>
    );
}

const root = document.getElementById('seo-tidy-app');

if (root) {
    createRoot(root).render(<App />);
}

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
                        <Notice status="info" isDismissible={false}>
                            {__('SEO-TidY is under development. SEO analysis is not available yet.', 'seo-tidy')}
                        </Notice>
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

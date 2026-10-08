import { createElement, createRoot, useState } from '@wordpress/element';
import { Button, Card, CardBody, Notice } from '@wordpress/components';
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

                    {active === 'dashboard' ? (
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

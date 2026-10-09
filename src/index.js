import { createElement, createRoot, useState, useEffect } from '@wordpress/element';
import { Button, Card, CardBody, Notice, ToggleControl, Spinner, TextControl, TextareaControl } from '@wordpress/components';
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
                        {metric.filter ? (
                            <Button variant="link"
                                onClick={() => onOpenMetadata(metric.filter)}>
                                <strong style={{ fontSize: '25px' }}>
                                    {metric.value}
                                </strong>
                            </Button>
                        ) : (
                            <strong style={{ fontSize: '25px' }}>
                                {metric.value}
                            </strong>
                        )}
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
        <div>
            <div style={{
                display: 'flex', flexWrap: 'wrap',
                gap: '12px', marginBottom: '16px',
            }}>
                <label>
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
                <label>
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
                    <p>{__('Matching items:', 'seo-tidy')} <strong>{result.total}</strong></p>
                    <table className="widefat striped">
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
                                    <td>{item.title || __('Untitled', 'seo-tidy')}</td>
                                    <td>{item.type === 'post'
                                        ? __('Post', 'seo-tidy')
                                        : __('Page', 'seo-tidy')}</td>
                                    <td>{item.hasTitle
                                        ? __('Added', 'seo-tidy')
                                        : __('Missing', 'seo-tidy')}</td>
                                    <td>{item.hasDescription
                                        ? __('Added', 'seo-tidy')
                                        : __('Missing', 'seo-tidy')}</td>
                                    <td>
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
                                                style={{ marginLeft: '12px' }}
                                            >
                                                {__('Edit', 'seo-tidy')}
                                            </a>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {editing !== null && (() => {
                        const item = result.items.find((row) => row.id === editing);

                        if (!item) return null;

                        return (
                            <div style={{
                                border: '1px solid #ddd',
                                padding: '16px',
                                marginTop: '16px',
                                borderRadius: '6px',
                            }}>
                                <h3>
                                    {__('Quick edit', 'seo-tidy')}: {item.title}
                                </h3>
                                <TextControl
                                    label={__('SEO title', 'seo-tidy')}
                                    value={draftTitle}
                                    disabled={saving}
                                    onChange={setDraftTitle}
                                />
                                <TextareaControl
                                    label={__('Meta description', 'seo-tidy')}
                                    value={draftDescription}
                                    disabled={saving}
                                    onChange={setDraftDescription}
                                />

                                {saveError && (
                                    <Notice status="error" isDismissible={false}>
                                        {__('Could not save metadata.', 'seo-tidy')}
                                    </Notice>
                                )}

                                <div style={{
                                    display: 'flex',
                                    gap: '8px',
                                    marginTop: '12px',
                                }}>
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
                        <div style={{
                            display: 'flex', gap: '12px',
                            alignItems: 'center', marginTop: '16px',
                        }}>
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
    short_title: __('SEO title may be too short', 'seo-tidy'),
    long_title: __('SEO title may be too long', 'seo-tidy'),
    missing_description: __('Missing meta description', 'seo-tidy'),
    short_description: __('Meta description may be too short', 'seo-tidy'),
    long_description: __('Meta description may be too long', 'seo-tidy'),
    review_h1: __('Review H1 heading (theme may provide it)', 'seo-tidy'),
    review_image_alt: __('Review image alternative text', 'seo-tidy'),
};

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

    useEffect(() => {
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
    }, [page, refresh]);

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

    const items = data
        ? data.items.filter((item) =>
            !issuesOnly || item.issues.length > 0
        )
        : [];

    return (
        <div>
            <p>
                {__('Checks published posts and pages in batches of 20. Lengths are guidance, not SEO scores.', 'seo-tidy')}
            </p>
            <p>
                {__('H1 and image checks inspect stored content, not the final theme output.', 'seo-tidy')}
            </p>

            <label style={{ display: 'block', marginBottom: '14px' }}>
                <input
                    type="checkbox"
                    checked={issuesOnly}
                    onChange={(event) => setIssuesOnly(event.target.checked)}
                />
                {' '}
                {__('Show only items with findings', 'seo-tidy')}
            </label>

            {error && (
                <Notice status="error" isDismissible={false}>
                    {__('Could not load SEO audit.', 'seo-tidy')}
                </Notice>
            )}

            {!error && !data && <Spinner />}

            {data && (
                <>
                    <p>
                        {__('Published content:', 'seo-tidy')}
                        {' '}
                        <strong>{data.total}</strong>
                    </p>

                    <table className="widefat striped">
                        <thead>
                            <tr>
                                <th>{__('Title', 'seo-tidy')}</th>
                                <th>{__('Findings', 'seo-tidy')}</th>
                                <th>{__('Action', 'seo-tidy')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item.id}>
                                    <td>{item.title || __('Untitled', 'seo-tidy')}</td>
                                    <td>
                                        {(() => {
                                            const missing = item.issues.filter(
                                                (issue) => issue.startsWith('missing_')
                                            );
                                            const advice = item.issues.filter(
                                                (issue) => !issue.startsWith('missing_')
                                            );

                                            if (!item.issues.length) {
                                                return __('No findings', 'seo-tidy');
                                            }

                                            return (
                                                <>
                                                    {missing.length > 0 && (
                                                        <div>
                                                            <strong>
                                                                {__('Needs attention', 'seo-tidy')}
                                                            </strong>
                                                            <ul style={{ paddingLeft: '18px' }}>
                                                                {missing.map((issue) => (
                                                                    <li key={issue}>
                                                                        {auditLabels[issue]}
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        </div>
                                                    )}
                                                    {advice.length > 0 && (
                                                        <details>
                                                            <summary>
                                                                {__('Recommendations', 'seo-tidy')}
                                                                {' (' + advice.length + ')'}
                                                            </summary>
                                                            <ul style={{ paddingLeft: '18px' }}>
                                                                {advice.map((issue) => (
                                                                    <li key={issue}>
                                                                        {auditLabels[issue]}
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        </details>
                                                    )}
                                                </>
                                            );
                                        })()}
                                    </td>
                                    <td>
                                        <Button
                                            variant="link"
                                            disabled={saving}
                                            onClick={() => startEdit(item)}
                                        >
                                            {__('Quick edit', 'seo-tidy')}
                                        </Button>
                                        {item.editUrl && (
                                            <a href={item.editUrl}
                                                style={{ marginLeft: '12px' }}>
                                                {__('Edit', 'seo-tidy')}
                                            </a>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {editing !== null && (() => {
                        const item = data.items.find((entry) =>
                            entry.id === editing
                        );
                        if (!item) return null;

                        return (
                            <div style={{
                                marginTop: '16px',
                                padding: '16px',
                                border: '1px solid #ddd',
                                borderRadius: '6px',
                            }}>
                                <h3>
                                    {__('Quick edit', 'seo-tidy')}: {item.title}
                                </h3>
                                <TextControl
                                    label={__('SEO title', 'seo-tidy')}
                                    value={title}
                                    onChange={setTitle}
                                    disabled={saving}
                                />
                                <TextareaControl
                                    label={__('Meta description', 'seo-tidy')}
                                    value={description}
                                    onChange={setDescription}
                                    disabled={saving}
                                />
                                {saveError && (
                                    <Notice status="error" isDismissible={false}>
                                        {__('Could not save metadata.', 'seo-tidy')}
                                    </Notice>
                                )}
                                <div style={{
                                    display: 'flex',
                                    gap: '8px',
                                    marginTop: '12px',
                                }}>
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
                        <p>{__('No findings on this page.', 'seo-tidy')}</p>
                    )}

                    {data.pages > 1 && (
                        <div style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '12px',
                            marginTop: '16px',
                        }}>
                            <Button variant="secondary"
                                disabled={page <= 1}
                                onClick={() => setPage(page - 1)}>
                                {__('Previous', 'seo-tidy')}
                            </Button>
                            <span>{page} / {data.pages}</span>
                            <Button variant="secondary"
                                disabled={page >= data.pages}
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

                    {active === 'audit' ? (
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
        </div>
    );
}

const root = document.getElementById('seo-tidy-app');

if (root) {
    createRoot(root).render(<App />);
}

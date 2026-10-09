import { registerPlugin } from '@wordpress/plugins';
import {
    PluginSidebar,
    PluginSidebarMoreMenuItem,
    PluginPostStatusInfo,
} from '@wordpress/editor';
import {
    PanelBody,
    Button,
    TextControl,
    TextareaControl,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { createElement } from '@wordpress/element';


function LengthIndicator({ label, value, reference }) {
    const percentage = Math.min(100, (value / reference) * 100);

    return (
        <div style={{ marginBottom: '12px' }}>
            <div style={{ fontSize: '12px', marginBottom: '5px' }}>
                {label}: {value} {__('characters', 'seo-tidy')}
            </div>
            <div
                style={{
                    height: '6px',
                    backgroundColor: '#ddd',
                    borderRadius: '4px',
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        width: percentage + '%',
                        height: '100%',
                        backgroundColor: '#666',
                    }}
                />
            </div>
        </div>
    );
}

function SeoTidySidebar() {
    const meta = useSelect(
        (select) => select('core/editor').getEditedPostAttribute('meta'),
        []
    );

    const postTitle = useSelect(
        (select) => select('core/editor').getEditedPostAttribute('title'),
        []
    );

    const permalink = useSelect(
        (select) => select('core/editor').getPermalink(),
        []
    );

    const seoTitle = meta?._seo_tidy_title || '';
    const seoDescription = meta?._seo_tidy_description || '';
    const previewTitle = seoTitle || postTitle ||
        __('Untitled post', 'seo-tidy');
    const { editPost } = useDispatch('core/editor');
    const { openGeneralSidebar } = useDispatch('core/edit-post');

    const update = (key, value) => {
        editPost({
            meta: {
                [key]: value,
            },
        });
    };

    return (
        <>
            <PluginPostStatusInfo>
                <Button
                    variant="link"
                    onClick={() =>
                        openGeneralSidebar('seo-tidy-editor/seo-tidy-sidebar')
                    }
                >
                    {__('SEO-TidY Metadata', 'seo-tidy')}
                </Button>
            </PluginPostStatusInfo>

            <PluginSidebarMoreMenuItem target="seo-tidy-sidebar">
                {__('SEO-TidY Metadata', 'seo-tidy')}
            </PluginSidebarMoreMenuItem>

            <PluginSidebar
                name="seo-tidy-sidebar"
                title={__('SEO-TidY Metadata', 'seo-tidy')}
                icon="search"
            >
                <PanelBody>
                    <TextControl
                        label={__('SEO Title', 'seo-tidy')}
                        value={meta?._seo_tidy_title || ''}
                        onChange={(value) =>
                            update('_seo_tidy_title', value)
                        }
                    />

                    <TextareaControl
                        label={__('Meta Description', 'seo-tidy')}
                        value={meta?._seo_tidy_description || ''}
                        onChange={(value) =>
                            update('_seo_tidy_description', value)
                        }
                    />
                    <div
                        style={{
                            borderTop: '1px solid #ddd',
                            marginTop: '20px',
                            paddingTop: '16px',
                        }}
                    >
                        <h3>
                            {__('Search preview', 'seo-tidy')}
                        </h3>

                        <LengthIndicator
                            label={__('SEO Title', 'seo-tidy')}
                            value={seoTitle.length}
                            reference={65}
                        />
                        <LengthIndicator
                            label={__('Meta Description', 'seo-tidy')}
                            value={seoDescription.length}
                            reference={160}
                        />
                        <p style={{ fontSize: '12px', color: '#666' }}>
                            {__('Reference scale only, not an SEO score.', 'seo-tidy')}
                        </p>
                        {!seoTitle && (
                            <p style={{ fontSize: '12px' }}>
                                {__('The post title is used when the SEO title is empty.', 'seo-tidy')}
                            </p>
                        )}

                        <div
                            style={{
                                padding: '12px',
                                border: '1px solid #ddd',
                                borderRadius: '6px',
                                overflowWrap: 'anywhere',
                            }}
                        >
                            {permalink && (
                                <div
                                    style={{
                                        color: '#555',
                                        fontSize: '12px',
                                        marginBottom: '5px',
                                    }}
                                >
                                    {permalink}
                                </div>
                            )}

                            <div
                                style={{
                                    color: '#1a0dab',
                                    fontSize: '18px',
                                    lineHeight: '1.3',
                                    marginBottom: '6px',
                                }}
                            >
                                {previewTitle}
                            </div>

                            <div
                                style={{
                                    color: '#444',
                                    fontSize: '13px',
                                    lineHeight: '1.5',
                                }}
                            >
                                {seoDescription || __(
                                    'No custom description. Search engines may select text from the page.',
                                    'seo-tidy'
                                )}
                            </div>
                        </div>

                        <p
                            style={{
                                fontSize: '12px',
                                color: '#666',
                            }}
                        >
                            {__(
                                'Illustrative preview. Search engines may change the result.',
                                'seo-tidy'
                            )}
                        </p>
                    </div>
                </PanelBody>
            </PluginSidebar>
        </>
    );
}

registerPlugin('seo-tidy-editor', {
    render: SeoTidySidebar,
    icon: 'search',
});
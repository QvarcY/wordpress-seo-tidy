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
    const previewTitle = seoTitle || postTitle || '';
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

                        <p style={{ marginBottom: '6px' }}>
                            {__('SEO Title', 'seo-tidy')}: {seoTitle.length}
                            {' '}
                            {__('characters', 'seo-tidy')}
                        </p>

                        <p style={{ marginTop: 0 }}>
                            {__('Meta Description', 'seo-tidy')}:
                            {' '}{seoDescription.length}
                            {' '}
                            {__('characters', 'seo-tidy')}
                        </p>

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
                                {seoDescription}
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
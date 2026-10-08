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
                </PanelBody>
            </PluginSidebar>
        </>
    );
}

registerPlugin('seo-tidy-editor', {
    render: SeoTidySidebar,
    icon: 'search',
});
const { registerPlugin } = wp.plugins;
const { PluginSidebar } = wp.editPost;
const { PanelBody, TextControl } = wp.components;
const { createElement } = wp.element;

const MySidebar = () => {
    return createElement(
        PluginSidebar,
        {
            name: 'wordtown-sidebar',
            icon: 'admin-generic',
            title: 'WordTown',
        },
        createElement(
            PanelBody,
            { title: 'WordTown', initialOpen: true },
            createElement(TextControl, {
                label: 'Example Field',
                value: 'Hello World',
                onChange: () => {},
            })
        )
    );
};

registerPlugin('wordtown-sidebar-plugin', {
    render: MySidebar,
    icon: 'smiley',
});
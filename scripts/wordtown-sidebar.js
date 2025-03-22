const { registerPlugin } = wp.plugins;
const { PluginSidebar } = wp.editPost;
const { PanelBody, Spinner, Button } = wp.components;
const { createElement, useState, useEffect } = wp.element;
const { useSelect } = wp.data;
const apiFetch = wp.apiFetch;

const WordTownSidebar = () => {
	const [tileData, setTileData] = useState(null);
	const [isLoading, setIsLoading] = useState(true);
	const [error, setError] = useState(null);

	// Get the current post ID and meta data using wp.data
	const { postId, tileId } = useSelect(select => {
		const editor = select('core/editor');
		const postId = editor.getCurrentPostId();
		const meta = editor.getEditedPostAttribute('meta') || {};
		
		return {
			postId,
			tileId: meta.wordtown_tile || null
		};
	}, []);

	// Fetch the media data when the component mounts or tileId changes
	useEffect(() => {
		if (!postId) {
			setIsLoading(false);
			return;
		}

		if (!tileId) {
			setTileData(null);
			setIsLoading(false);
			return;
		}

		setIsLoading(true);
		setError(null);

		// Fetch the media details for the tile
		apiFetch({ path: `/wp/v2/media/${tileId}` })
			.then(media => {
				if (media) {
					setTileData({
						id: media.id,
						url: media.source_url,
						title: media.title.rendered,
						prompt: media.description.rendered || 'No prompt information available'
					});
				}
				setIsLoading(false);
			})
			.catch(err => {
				console.error('Error fetching WordTown tile:', err);
				setError('Failed to load tile data');
				setIsLoading(false);
			});
	}, [postId, tileId]);

	// Render the tile or appropriate message
	const renderTileContent = () => {
		if (isLoading) {
			return createElement('div', { className: 'wordtown-loading' },
				createElement(Spinner),
				createElement('p', {}, 'Loading tile data...')
			);
		}

		if (error) {
			return createElement('div', { className: 'wordtown-error' },
				createElement('p', {}, error)
			);
		}

		if (!tileId) {
			return createElement('div', { className: 'wordtown-no-tile' },
				createElement('p', {}, 'No tile has been generated for this post yet.'),
				createElement(Button, {
					isPrimary: true,
					onClick: () => {
						// This would need a custom endpoint to trigger tile generation
						alert('Tile generation would be triggered here. Currently this feature requires WP-CLI.');
					}
				}, 'Generate Tile')
			);
		}

		if (!tileData) {
			return createElement('div', { className: 'wordtown-error' },
				createElement('p', {}, 'Tile ID found but media data could not be loaded.')
			);
		}

		return createElement('div', { className: 'wordtown-tile-display' },
			createElement('h3', {}, 'Post Tile'),
			createElement('img', {
				src: tileData.url,
				alt: tileData.title,
				style: { maxWidth: '100%', height: 'auto' }
			}),
			createElement('h4', {}, 'Prompt Used:'),
			createElement('div', {
				className: 'wordtown-prompt',
				dangerouslySetInnerHTML: { __html: tileData.prompt }
			})
		);
	};

	return createElement(
		PluginSidebar,
		{
			name: 'wordtown-sidebar',
			icon: 'building',
			title: 'WordTown',
		},
		createElement(
			PanelBody,
			{ title: 'Isometric Tile', initialOpen: true },
			renderTileContent()
		)
	);
};

registerPlugin('wordtown-sidebar-plugin', {
	render: WordTownSidebar,
	icon: 'building',
});

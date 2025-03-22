const { registerPlugin } = wp.plugins;
const { PluginSidebar } = wp.editPost;
const { PanelBody, Spinner, Button } = wp.components;
const { createElement, useState, useEffect } = wp.element;
const { useSelect, useDispatch } = wp.data;
const apiFetch = wp.apiFetch;

const WordTownSidebar = () => {
	const [tileData, setTileData] = useState(null);
	const [isLoading, setIsLoading] = useState(true);
	const [error, setError] = useState(null);
	const [isGenerating, setIsGenerating] = useState(false);
	const [generationStatus, setGenerationStatus] = useState(null);

	// Get the current post ID and meta data using wp.data
	const { postId, tileId, prompt, scheduled } = useSelect(select => {
		const editor = select('core/editor');
		const postId = editor.getCurrentPostId();
		const meta = editor.getEditedPostAttribute('meta') || {};
		
		return {
			postId,
			tileId: meta.wordtown_tile || null,
			prompt: meta.wordtown_tile_prompt || null,
			scheduled: meta.wordtown_tile_scheduled || null,
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

	// Function to handle tile generation
	const generateTile = () => {
		if (!postId || isGenerating) return;

		setIsGenerating(true);
		setGenerationStatus('Requesting tile generation...');

		apiFetch({
			path: `/wordtown/v1/posts/${postId}/generate`,
			method: 'POST',
		})
			.then(response => {
				console.log('Tile generation response:', response);
				if (response.success && response.wordtown_tile_scheduled) {
					// Update the post meta with the scheduled job ID
					setGenerationStatus(`Tile generation scheduled (Job ID: ${response.wordtown_tile_scheduled})`);
				} else {
					setGenerationStatus('Error: Unexpected response from server');
					setIsGenerating(false);
				}
			})
			.catch(err => {
				console.error('Error generating tile:', err);
				setGenerationStatus(`Error: ${err.message || 'Failed to schedule tile generation'}`);
				setIsGenerating(false);
			});
	};

	// Render the tile content panel
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
			prompt ? createElement('div', {
				className: 'wordtown-prompt',
				dangerouslySetInnerHTML: { __html: prompt }
			}) : null
		);
	};

	// Render the tile generation panel
	const renderGenerationPanel = () => {
		return createElement('div', { className: 'wordtown-generation-panel' },
			scheduled || generationStatus
				? createElement('div', { className: 'wordtown-scheduled-info' },
					createElement('p', {}, 'Tile generation scheduled:'),
					createElement('p', { className: 'wordtown-scheduled-time' }, generationStatus || `Job ID: ${scheduled}`)
				)
				: (isGenerating
					? createElement('div', { className: 'wordtown-generating' },
						createElement(Spinner),
						createElement('p', {}, 'Requesting tile generation...')
					)
					: (tileId 
						? createElement(Button, {
							isPrimary: true,
							onClick: generateTile
						}, 'Regenerate Tile')
						: createElement(Button, {
							isPrimary: true,
							onClick: generateTile
						}, 'Generate Tile')
					)
				),
			!scheduled && !generationStatus && !isGenerating && createElement('p', { 
				className: 'wordtown-generation-note', 
				style: { marginTop: '10px', fontSize: '12px' } 
			}, 'Note: Tile generation may take a few minutes to complete.')
		);
	};

	return createElement(
		PluginSidebar,
		{
			name: 'wordtown-sidebar',
			icon: 'building',
			title: 'WordTown',
		},
		// Only show the tile panel if a tile exists
		tileId && createElement(
			PanelBody,
			{ title: 'Current Isometric Tile', initialOpen: true },
			renderTileContent()
		),
		// Always show the generation panel
		createElement(
			PanelBody,
			{ title: 'Tile Generation', initialOpen: true },
			renderGenerationPanel()
		)
	);
};

registerPlugin('wordtown-sidebar-plugin', {
	render: WordTownSidebar,
	icon: 'building',
});

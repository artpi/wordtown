/**
 * WordTown - Simple HTML-based isometric tile grid
 * 
 * This script creates an isometric grid of tiles using pure HTML/CSS/JavaScript
 * without relying on Phaser.js or other game engines.
 */

// Configuration
const config = {
	tileWidth: 1024,          // Width of each tile in pixels
	tileHeight: 512,         // Height of each tile in pixels (half of width for isometric)
	gridSize: 4,             // Grid size (4x4)
	zoomSpeed: 0.1,          // Zoom speed for mouse wheel
	minZoom: 0.1,            // Minimum zoom level
	maxZoom: 2,              // Maximum zoom level
	panSpeed: 1,             // Pan speed for keyboard/mouse
	dragThreshold: 5         // Pixels of movement to consider as dragging
};

// State variables
let currentZoom = 1;
let offsetX = 0;
let offsetY = 0;
let isDragging = false;
let lastMouseX = 0;
let lastMouseY = 0;
let htmlTiles = [];
let tileNameElement = null;

/**
 * Initialize the grid and controls
 */
function initWordTown() {
	// Create container
	const container = document.createElement('div');
	container.id = 'wordtown-container';
	container.style.position = 'relative';
	container.style.width = '100%';
	container.style.height = '100vh';
	container.style.overflow = 'hidden';
	container.style.backgroundColor = '#00ff00';
	container.style.userSelect = 'none';
	document.body.appendChild(container);
	
	// Create grid container (for centering and transforms)
	const gridContainer = document.createElement('div');
	gridContainer.id = 'wordtown-grid';
	gridContainer.style.position = 'absolute';
	gridContainer.style.top = '50%';
	gridContainer.style.left = '50%';
	gridContainer.style.transform = 'translate(-50%, -50%) scale(1)';
	gridContainer.style.transformOrigin = 'center center';
	container.appendChild(gridContainer);
	
	// Create tile name display element
	tileNameElement = document.createElement('div');
	tileNameElement.style.position = 'absolute';
	tileNameElement.style.padding = '5px 10px';
	tileNameElement.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
	tileNameElement.style.color = 'white';
	tileNameElement.style.borderRadius = '4px';
	tileNameElement.style.fontFamily = 'Arial, sans-serif';
	tileNameElement.style.fontSize = '16px';
	tileNameElement.style.pointerEvents = 'none';
	tileNameElement.style.zIndex = '1000';
	tileNameElement.style.display = 'none';
	container.appendChild(tileNameElement);
	
	// Add instructions
	const instructions = document.createElement('div');
	instructions.style.position = 'absolute';
	instructions.style.top = '10px';
	instructions.style.left = '10px';
	instructions.style.padding = '5px 10px';
	instructions.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
	instructions.style.color = 'black';
	instructions.style.fontFamily = 'Arial, sans-serif';
	instructions.style.fontSize = '14px';
	instructions.style.zIndex = '1000';
	instructions.textContent = 'Arrow Keys: Move | Mouse Wheel: Zoom | Mouse Drag: Pan | Click: Open Tile';
	container.appendChild(instructions);
	
	// Add loading indicator
	const loadingElement = document.createElement('div');
	loadingElement.id = 'wordtown-loading';
	loadingElement.style.position = 'absolute';
	loadingElement.style.top = '50%';
	loadingElement.style.left = '50%';
	loadingElement.style.transform = 'translate(-50%, -50%)';
	loadingElement.style.padding = '10px 20px';
	loadingElement.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
	loadingElement.style.color = 'white';
	loadingElement.style.borderRadius = '4px';
	loadingElement.style.fontFamily = 'Arial, sans-serif';
	loadingElement.style.fontSize = '24px';
	loadingElement.style.zIndex = '1000';
	loadingElement.textContent = 'Loading tiles...';
	container.appendChild(loadingElement);
	
	// Set up event listeners
	setupEventListeners(container, gridContainer);
	
	// Fetch and place tiles
	fetchTiles(gridContainer, loadingElement);
}

/**
 * Set up event listeners for controls
 */
function setupEventListeners(container, gridContainer) {
	// Keyboard controls
	document.addEventListener('keydown', (e) => {
		switch (e.key) {
			case 'ArrowUp':
				offsetY += config.panSpeed * 10;
				updateGridPosition(gridContainer);
				break;
			case 'ArrowDown':
				offsetY -= config.panSpeed * 10;
				updateGridPosition(gridContainer);
				break;
			case 'ArrowLeft':
				offsetX += config.panSpeed * 10;
				updateGridPosition(gridContainer);
				break;
			case 'ArrowRight':
				offsetX -= config.panSpeed * 10;
				updateGridPosition(gridContainer);
				break;
		}
	});
	
	// Mouse wheel zoom
	container.addEventListener('wheel', (e) => {
		e.preventDefault();
		
		// Calculate zoom change
		const zoomDelta = e.deltaY > 0 ? -config.zoomSpeed : config.zoomSpeed;
		const newZoom = Math.max(config.minZoom, Math.min(config.maxZoom, currentZoom + zoomDelta));
		
		// Apply zoom
		if (newZoom !== currentZoom) {
			currentZoom = newZoom;
			gridContainer.style.transform = `translate(calc(-50% + ${offsetX}px), calc(-50% + ${offsetY}px)) scale(${currentZoom})`;
		}
	});
	
	// Mouse drag
	container.addEventListener('mousedown', (e) => {
		isDragging = true;
		lastMouseX = e.clientX;
		lastMouseY = e.clientY;
		container.style.cursor = 'grabbing';
	});
	
	container.addEventListener('mousemove', (e) => {
		if (isDragging) {
			const deltaX = e.clientX - lastMouseX;
			const deltaY = e.clientY - lastMouseY;
			
			offsetX += deltaX;
			offsetY += deltaY;
			
			updateGridPosition(gridContainer);
			
			lastMouseX = e.clientX;
			lastMouseY = e.clientY;
		}
	});
	
	container.addEventListener('mouseup', () => {
		isDragging = false;
		container.style.cursor = 'default';
	});
	
	container.addEventListener('mouseleave', () => {
		isDragging = false;
		container.style.cursor = 'default';
	});
}

/**
 * Update the grid position based on current offset and zoom
 */
function updateGridPosition(gridContainer) {
	gridContainer.style.transform = `translate(calc(-50% + ${offsetX}px), calc(-50% + ${offsetY}px)) scale(${currentZoom})`;
}

/**
 * Fetch tiles from WordPress API
 */
function fetchTiles(gridContainer, loadingElement) {
	wp.apiFetch({ path: 'wordtown/v1/tiles' })
		.then(tiles => {
			console.log('API response:', tiles);
			
			if (tiles.length === 0) {
				loadingElement.textContent = 'No tiles found';
				return;
			}
			
			// Place tiles
			tiles.forEach((tile, index) => {
				const gridX = (index % 3) + 1;
				const gridY = Math.floor(index / 3) + 1;
				const tileUrl = tile.url || tile.tile_url || tile.image_url;
				const tileKey = `tile${index}`;
				
				if (!tileUrl || typeof tileUrl !== 'string' || !tileUrl.match(/^https?:\/\//)) {
					console.error('Invalid tile URL:', tileUrl);
					return;
				}
				
				createTile(gridContainer, gridX, gridY, tileKey, tileUrl);
			});
			
			// Hide loading indicator when done
			loadingElement.style.display = 'none';
		})
		.catch(error => {
			console.error('Error fetching tiles:', error);
			loadingElement.textContent = 'Error loading tiles';
			loadingElement.style.backgroundColor = 'rgba(255, 0, 0, 0.7)';
		});
}

/**
 * Create a tile at the specified grid position
 */
function createTile(gridContainer, gridX, gridY, tileKey, tileUrl) {
	// Calculate isometric position
	const screenX = (gridX - gridY) * (config.tileWidth / 2 - 100);
	const screenY = (gridX + gridY) * (config.tileHeight / 2 - 50);
	
	// Create tile container
	const tileContainer = document.createElement('div');
	tileContainer.className = 'wordtown-tile';
	tileContainer.style.position = 'absolute';
	tileContainer.style.width = `${config.tileWidth}px`;
	tileContainer.style.height = `${config.tileHeight}px`;
	tileContainer.style.left = `${screenX}px`;
	tileContainer.style.top = `${screenY}px`;
	tileContainer.style.transform = 'translate(-50%, -50%)';
	tileContainer.style.transition = 'transform 0.2s';
	tileContainer.dataset.tileKey = tileKey;
	tileContainer.dataset.tileUrl = tileUrl;
	
	// Create image element
	const imgElement = document.createElement('img');
	imgElement.src = tileUrl;
	imgElement.alt = tileKey;
	imgElement.style.width = '100%';
	imgElement.style.height = '100%';
	imgElement.style.objectFit = 'contain';
	
	// Add loading indicator
	const loadingIndicator = document.createElement('div');
	loadingIndicator.style.position = 'absolute';
	loadingIndicator.style.top = '0';
	loadingIndicator.style.left = '0';
	loadingIndicator.style.width = '100%';
	loadingIndicator.style.height = '100%';
	loadingIndicator.style.backgroundColor = 'rgba(255, 0, 0, 0.5)';
	loadingIndicator.style.display = 'flex';
	loadingIndicator.style.alignItems = 'center';
	loadingIndicator.style.justifyContent = 'center';
	loadingIndicator.textContent = 'Loading...';
	
	// Handle image load
	imgElement.onload = () => {
		loadingIndicator.style.display = 'none';
		console.log(`Tile ${tileKey} loaded successfully`);
	};
	
	// Handle image error
	imgElement.onerror = () => {
		loadingIndicator.textContent = 'Error';
		loadingIndicator.style.backgroundColor = 'rgba(255, 0, 0, 0.7)';
		console.error(`Error loading tile ${tileKey}`);
	};
	
	// Add hover effects
	tileContainer.addEventListener('mouseover', () => {
		tileContainer.style.transform = 'translate(-50%, -50%) scale(1.05)';
		tileContainer.style.zIndex = '10';
		
		// Show tile name
		tileNameElement.textContent = tileKey;
		tileNameElement.style.display = 'block';
		
		// Position the name above the tile
		const rect = tileContainer.getBoundingClientRect();
		tileNameElement.style.left = `${rect.left + rect.width/2 - tileNameElement.offsetWidth/2}px`;
		tileNameElement.style.top = `${rect.top - 30}px`;
	});
	
	tileContainer.addEventListener('mousemove', (e) => {
		// Update name position to follow cursor
		tileNameElement.style.left = `${e.clientX - tileNameElement.offsetWidth/2}px`;
		tileNameElement.style.top = `${e.clientY - 30}px`;
	});
	
	tileContainer.addEventListener('mouseout', () => {
		tileContainer.style.transform = 'translate(-50%, -50%)';
		tileContainer.style.zIndex = '1';
		tileNameElement.style.display = 'none';
	});
	
	// Add click handler
	tileContainer.addEventListener('click', () => {
		if (!isDragging) {
			window.open(tileUrl, '_blank');
		}
	});
	
	// Add elements to the DOM
	tileContainer.appendChild(imgElement);
	tileContainer.appendChild(loadingIndicator);
	gridContainer.appendChild(tileContainer);
	
	// Store reference to the tile
	htmlTiles.push({
		element: tileContainer,
		gridX: gridX,
		gridY: gridY,
		tileKey: tileKey,
		tileUrl: tileUrl
	});
}

// Initialize when the DOM is ready
document.addEventListener('DOMContentLoaded', initWordTown);
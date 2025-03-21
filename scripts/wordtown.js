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
	tileMarginX: 100,
	tileMarginY: 50,
	gridSize: 4,             // Grid size (4x4)
	zoomSpeed: 0.1,          // Zoom speed for mouse wheel
	minZoom: 0.1,            // Minimum zoom level
	maxZoom: 2,              // Maximum zoom level
	scrollbarWidth: 12,      // Width of scrollbars
	scrollbarColor: 'rgba(0, 0, 0, 0.5)',  // Color of scrollbars
	scrollbarTrackColor: 'rgba(0, 0, 0, 0.1)'  // Color of scrollbar track
};

// State variables
let currentZoom = 1;
let htmlTiles = [];
let tileNameElement = null;
let gridBounds = {
	minX: 0,
	maxX: 0,
	minY: 0,
	maxY: 0
};

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
	container.style.overflow = 'auto';  // Changed from 'hidden' to 'auto' to show scrollbars
	container.style.backgroundColor = '#00ff00';
	container.style.userSelect = 'none';
	
	// Add custom scrollbar styling
	container.style.scrollbarWidth = 'thin';
	container.style.scrollbarColor = `${config.scrollbarColor} ${config.scrollbarTrackColor}`;
	
	// For Webkit browsers (Chrome, Safari)
	const scrollbarStyle = document.createElement('style');
	scrollbarStyle.textContent = `
		#wordtown-container::-webkit-scrollbar {
			width: ${config.scrollbarWidth}px;
			height: ${config.scrollbarWidth}px;
		}
		#wordtown-container::-webkit-scrollbar-track {
			background: ${config.scrollbarTrackColor};
		}
		#wordtown-container::-webkit-scrollbar-thumb {
			background-color: ${config.scrollbarColor};
			border-radius: ${config.scrollbarWidth/2}px;
		}
	`;
	document.head.appendChild(scrollbarStyle);
	document.body.appendChild(container);
	
	// Create grid container
	const gridContainer = document.createElement('div');
	gridContainer.id = 'wordtown-grid';
	gridContainer.style.position = 'absolute';
	gridContainer.style.transformOrigin = 'top left';
	gridContainer.style.transform = `scale(${currentZoom})`;
	// We'll set the size and position after tiles are loaded
	container.appendChild(gridContainer);
	
	// Create tile name display element
	tileNameElement = document.createElement('div');
	tileNameElement.style.position = 'fixed'; // Changed from 'absolute' to 'fixed'
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
	instructions.style.position = 'fixed'; // Changed from 'absolute' to 'fixed'
	instructions.style.top = '10px';
	instructions.style.left = '10px';
	instructions.style.padding = '5px 10px';
	instructions.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
	instructions.style.color = 'black';
	instructions.style.fontFamily = 'Arial, sans-serif';
	instructions.style.fontSize = '14px';
	instructions.style.zIndex = '1000';
	instructions.textContent = 'Scroll to navigate | Mouse Wheel + Ctrl: Zoom | Click: Open Tile';
	container.appendChild(instructions);
	
	// Add loading indicator
	const loadingElement = document.createElement('div');
	loadingElement.id = 'wordtown-loading';
	loadingElement.style.position = 'fixed'; // Changed from 'absolute' to 'fixed'
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
	fetchTiles(gridContainer, loadingElement, container);
}

/**
 * Set up event listeners for controls
 */
function setupEventListeners(container, gridContainer) {
	// Mouse wheel zoom (only when Ctrl key is pressed)
	container.addEventListener('wheel', (e) => {
		// Only zoom when Ctrl key is pressed, otherwise let native scrolling work
		if (e.ctrlKey) {
			e.preventDefault();
			
			// Calculate zoom change
			const zoomDelta = e.deltaY > 0 ? -config.zoomSpeed : config.zoomSpeed;
			
			// Calculate the minimum zoom level needed to fit all tiles
			const containerWidth = container.clientWidth;
			const containerHeight = container.clientHeight;
			const gridWidth = gridBounds.maxX - gridBounds.minX;
			const gridHeight = gridBounds.maxY - gridBounds.minY;
			
			// Calculate zoom level that would fit the entire grid exactly
			const fitZoomWidth = containerWidth / gridWidth;
			const fitZoomHeight = containerHeight / gridHeight;
			const fitZoom = Math.min(fitZoomWidth, fitZoomHeight);
			
			// Use the exact fit zoom as minimum (no margin)
			const effectiveMinZoom = Math.max(config.minZoom, fitZoom);
			
			const newZoom = Math.max(effectiveMinZoom, Math.min(config.maxZoom, currentZoom + zoomDelta));
			
			// Apply zoom
			if (newZoom !== currentZoom) {
				// Get current scroll position and viewport dimensions
				const scrollLeft = container.scrollLeft;
				const scrollTop = container.scrollTop;
				
				// Calculate the point we're zooming around (mouse position)
				const zoomX = e.clientX + scrollLeft - container.offsetLeft;
				const zoomY = e.clientY + scrollTop - container.offsetTop;
				
				// Calculate new scroll position to keep the point under mouse
				const scaleFactor = newZoom / currentZoom;
				const newScrollLeft = zoomX * scaleFactor - containerWidth / 2;
				const newScrollTop = zoomY * scaleFactor - containerHeight / 2;
				
				// Apply new zoom
				currentZoom = newZoom;
				gridContainer.style.transform = `scale(${currentZoom})`;
				
				// Update grid size based on zoom
				updateGridSize(gridContainer);
				
				// Adjust scroll position
				container.scrollTo(newScrollLeft, newScrollTop);
			}
		}
	});
	
	// Prevent default zoom behavior on Ctrl+wheel
	document.addEventListener('keydown', (e) => {
		if (e.ctrlKey && e.key === '+' || e.key === '-') {
			e.preventDefault();
		}
	});
}

/**
 * Update the grid size based on tile positions and current zoom
 */
function updateGridSize(gridContainer) {
	// Set the grid container size based on the bounds and zoom
	const width = (gridBounds.maxX - gridBounds.minX) * currentZoom;
	const height = (gridBounds.maxY - gridBounds.minY) * currentZoom;
	
	gridContainer.style.width = `${width}px`;
	gridContainer.style.height = `${height}px`;
	gridContainer.style.left = `0px`; // Start at left edge
	gridContainer.style.top = `0px`;  // Start at top edge
}

/**
 * Fetch tiles from WordPress API
 */
function fetchTiles(gridContainer, loadingElement, container) {
	wp.apiFetch({ path: 'wordtown/v1/tiles' })
		.then(tiles => {
			console.log('API response:', tiles);
			
			if (tiles.length === 0) {
				loadingElement.textContent = 'No tiles found';
				return;
			}
			
			// Calculate grid dimensions for a more square-like layout
			const tileCount = tiles.length;
			const gridSize = Math.ceil(1.5 * Math.cbrt(tileCount)); // Cube root for 3D layout
			
			// Place tiles in a square-like grid layout
			tiles.forEach((tile, index) => {
				const gridX = index % gridSize;
				const gridY = Math.floor(index / gridSize);
				const tileUrl = tile.url || tile.tile_url || tile.image_url;
				const tileKey = `tile${index}`;
				
				if (!tileUrl || typeof tileUrl !== 'string' || !tileUrl.match(/^https?:\/\//)) {
					console.error('Invalid tile URL:', tileUrl);
					return;
				}
				
				createTile(gridContainer, gridX, gridY, tileKey, tileUrl);
			});
			
			// Calculate grid bounds after all tiles are placed
			calculateGridBounds();
			
			// Calculate initial zoom to fit all tiles
			calculateInitialZoom(container, gridContainer);
			
			// Set initial grid size and position
			updateGridSize(gridContainer);
			
			// Center the view on the grid
			centerView(container);
			
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
	// Calculate isometric position with proper spacing
	// Offset every second row by half a tile width
	const isOddRow = gridY % 2 === 1;
	// Use smaller spacing to make tiles closer together
	const tileSpacingX = config.tileWidth - config.tileMarginX * 2;
	const tileSpacingY = ( config.tileHeight - config.tileMarginY * 2 ) / 2;
		
	const rowOffset = isOddRow ? tileSpacingX / 2 : 0;
	
	// Position tiles in a staggered grid pattern
	// Add half tile width/height to ensure tiles start fully visible
	const screenX = gridX * tileSpacingX + rowOffset + config.tileWidth/2;
	const screenY = gridY * tileSpacingY + config.tileHeight/2;
	
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
	
	// Create tile content div with background image instead of img element
	const tileContent = document.createElement('div');
	tileContent.className = 'wordtown-tile-content';
	tileContent.style.width = '100%';
	tileContent.style.height = '100%';
	tileContent.style.backgroundImage = `url('${tileUrl}')`;
	tileContent.style.backgroundSize = 'contain';
	tileContent.style.backgroundPosition = 'center';
	tileContent.style.backgroundRepeat = 'no-repeat';
	
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
	
	// Create a hidden image to detect when the image is loaded
	const hiddenImg = new Image();
	hiddenImg.style.display = 'none';
	hiddenImg.src = tileUrl;
	
	// Handle image load
	hiddenImg.onload = () => {
		loadingIndicator.style.display = 'none';
		console.log(`Tile ${tileKey} loaded successfully`);
	};
	
	// Handle image error
	hiddenImg.onerror = () => {
		loadingIndicator.textContent = 'Error';
		loadingIndicator.style.backgroundColor = 'rgba(255, 0, 0, 0.7)';
		console.error(`Error loading tile ${tileKey}`);
	};
	
	// Add hover effects
	tileContainer.addEventListener('mouseover', (e) => {
		tileContainer.style.transform = 'translate(-50%, -50%) scale(1.05)';
		tileContainer.style.zIndex = '10';
		
		// Show tile name
		tileNameElement.textContent = tileKey;
		tileNameElement.style.display = 'block';
		
		// Position the name near the cursor
		tileNameElement.style.left = `${e.clientX - 100}px`;
		tileNameElement.style.top = `${e.clientY - 50}px`;
	});
	
	tileContainer.addEventListener('mousemove', (e) => {
		// Update name position to follow cursor
		tileNameElement.style.left = `${e.clientX - 100}px`;
		tileNameElement.style.top = `${e.clientY - 50}px`;
	});
	
	tileContainer.addEventListener('mouseout', () => {
		tileContainer.style.transform = 'translate(-50%, -50%)';
		tileContainer.style.zIndex = '1';
		tileNameElement.style.display = 'none';
	});
	
	// Add elements to the DOM
	tileContainer.appendChild(tileContent);
	tileContainer.appendChild(loadingIndicator);
	tileContainer.appendChild(hiddenImg); // Add hidden image for load detection
	gridContainer.appendChild(tileContainer);
	
	// Store reference to the tile
	htmlTiles.push({
		element: tileContainer,
		gridX: gridX,
		gridY: gridY,
		tileKey: tileKey,
		tileUrl: tileUrl,
		screenX: screenX,
		screenY: screenY
	});
}

/**
 * Calculate the bounds of the grid based on all tiles
 */
function calculateGridBounds() {
	// Initialize with extreme values
	gridBounds = {
		minX: Infinity,
		maxX: -Infinity,
		minY: Infinity,
		maxY: -Infinity
	};
	
	// Find the bounds of all placed tiles
	htmlTiles.forEach(tile => {
		const left = tile.screenX - config.tileWidth/2;
		const right = tile.screenX + config.tileWidth/2;
		const top = tile.screenY - config.tileHeight/2;
		const bottom = tile.screenY + config.tileHeight/2;
		
		gridBounds.minX = Math.min(gridBounds.minX, left);
		gridBounds.maxX = Math.max(gridBounds.maxX, right);
		gridBounds.minY = Math.min(gridBounds.minY, top);
		gridBounds.maxY = Math.max(gridBounds.maxY, bottom);
	});
	
	// No negative padding - ensure grid starts at 0,0 to make all tiles visible
	gridBounds.minX = 0;
	gridBounds.minY = 0;
	
	// Add padding only to the right and bottom
	const paddingRight = 100;
	const paddingBottom = 100;
	
	gridBounds.maxX += paddingRight;
	gridBounds.maxY += paddingBottom;
}

/**
 * Calculate the initial zoom level to fit all tiles in the viewport
 */
function calculateInitialZoom(container, gridContainer) {
	// Get container dimensions
	const containerWidth = container.clientWidth;
	const containerHeight = container.clientHeight;
	
	// Get grid dimensions
	const gridWidth = gridBounds.maxX - gridBounds.minX;
	const gridHeight = gridBounds.maxY - gridBounds.minY;
	
	// Calculate zoom level that would fit the entire grid
	const zoomX = containerWidth / gridWidth;
	const zoomY = containerHeight / gridHeight;
	
	// Use the smaller value to ensure the entire grid fits
	const fitZoom = Math.min(zoomX, zoomY) * 1.8;
	
	// Set the initial zoom between min and max constraints
	currentZoom = Math.max(config.minZoom, Math.min(config.maxZoom, fitZoom));
	
	// Update the grid container's transform
	gridContainer.style.transform = `scale(${currentZoom})`;
	
	console.log(`Initial zoom set to ${currentZoom} (container: ${containerWidth}x${containerHeight}, grid: ${gridWidth}x${gridHeight})`);
}

/**
 * Center the view on the grid
 */
function centerView(container) {
	// Calculate the center of the grid
	const gridWidth = gridBounds.maxX - gridBounds.minX;
	const gridHeight = gridBounds.maxY - gridBounds.minY;
	
	// Calculate the scroll position to center the grid
	const scrollLeft = (gridBounds.minX + gridWidth/2) * currentZoom - container.clientWidth/2;
	const scrollTop = (gridBounds.minY + gridHeight/2) * currentZoom - container.clientHeight/2;
	
	// Set the scroll position
	container.scrollLeft = scrollLeft;
	container.scrollTop = scrollTop;
}

// Initialize when the DOM is ready
document.addEventListener('DOMContentLoaded', initWordTown);
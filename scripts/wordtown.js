// Phaser game configuration
const config = {
	type: Phaser.AUTO,          // Automatically choose WebGL or Canvas
	width: 1440,                // Canvas width
	height: 768,                // Canvas height
	backgroundColor: '#00ff00', // Vibrant green background like the grassy field
	scene: {
		preload: preload,       // Preload function for assets
		create: create,         // Create function for grid and tiles
		update: update          // Update function for controls
	},
	physics: {
		default: 'arcade',
		arcade: {
			debug: false
		}
	}
};

// Initialize the game
const game = new Phaser.Game(config);

// Global variables for controls
let cursors;
let controlConfig = {
	camera: null,
	zoomIn: null,
	zoomOut: null,
	dragSpeed: 1,
	zoomSpeed: 0.05,
	minZoom: 0.5,
	maxZoom: 2
};

// Variables for click vs. drag detection
let clickStartTime = 0;
let clickStartPosition = { x: 0, y: 0 };
let isDragging = false;
const DRAG_THRESHOLD = 5; // Pixels of movement to consider as dragging
const CLICK_DELAY = 200; // Milliseconds to wait before considering a click

// Preload function (for loading tile assets later)
function preload() {
	// Example: Load your output tiles here when ready
	this.load.image('tile0', '/tiles/output_0.png');
	this.load.image('tile1', '/tiles/output_1.png');
	this.load.image('tile2', '/tiles/output_2.png');
	this.load.image('tile3', '/tiles/output_3.png');
	this.load.image('tile4', '/tiles/output_4.png');
	this.load.image('tile5', '/tiles/output_5.png');
	this.load.image('tile6', '/tiles/output_6.png');
	this.load.image('tile7', '/tiles/output_7.png');
	this.load.image('tile8', '/tiles/output_8.png');
}

// Create function to draw the grid and prepare for tiles
function create() {
	// Grid parameters
	const tileWidth = 412;      // Desired screen width of each tile
	const tileHeight = tileWidth / 2;      // Desired screen height of each tile
	const gridSize = 4;         // 4x4 grid
	
	// Calculate the total width and height of the isometric grid
	const gridWidthPx = gridSize * tileWidth;
	const gridHeightPx = gridSize * tileHeight;
	
	// Calculate center offsets based on canvas dimensions
	const offsetX = ( gridWidthPx) / 4 + tileWidth/2;
	const offsetY = (this.sys.game.config.height - gridHeightPx) / 2;

	// Create a container for all game objects to enable group scrolling
	this.gameContainer = this.add.container(0, 0);

	// Create graphics object for drawing grid lines
	const graphics = this.add.graphics();
	this.gameContainer.add(graphics);
	graphics.lineStyle(1, 0x000000, 1); // Black lines, 1px thick

	// // Draw vertical grid lines (constant gridX)
	// for (let gridX = 0; gridX <= gridSize; gridX++) {
	//     const x1 = (gridX - 0) * (tileWidth / 2) + offsetX;
	//     const y1 = (gridX + 0) * (tileHeight / 2) + offsetY;
	//     const x2 = (gridX - (gridSize - 1)) * (tileWidth / 2) + offsetX;
	//     const y2 = (gridX + (gridSize - 1)) * (tileHeight / 2) + offsetY;
	//     graphics.lineBetween(x1, y1, x2, y2);
	// }

	// // Draw horizontal grid lines (constant gridY)
	// for (let gridY = 0; gridY <= gridSize; gridY++) {
	//     const x1 = (0 - gridY) * (tileWidth / 2) + offsetX;
	//     const y1 = (0 + gridY) * (tileHeight / 2) + offsetY;
	//     const x2 = ((gridSize - 1) - gridY) * (tileWidth / 2) + offsetX;
	//     const y2 = ((gridSize - 1) + gridY) * (tileHeight / 2) + offsetY;
	//     graphics.lineBetween(x1, y1, x2, y2);
	// }

	// Calculate scale factor (assuming all tiles have the same original size)
	const desiredWidth = tileWidth;
	const originalWidth = this.textures.get('tile0').source[0].width;
	const scale = desiredWidth / (originalWidth - 200);

	// Create a text object for displaying tile names on hover
	const tileNameText = this.add.text(0, 0, '', {
		font: '24px Arial',
		fill: '#ffffff',
		stroke: '#000000',
		strokeThickness: 4,
		backgroundColor: 'rgba(0, 0, 0, 0.5)',
		padding: {
			x: 10,
			y: 5
		}
	});
	tileNameText.setDepth(200);
	tileNameText.setVisible(false);
	tileNameText.setScrollFactor(0); // Fix to camera

	// Store camera reference
	const camera = this.cameras.main;
	controlConfig.camera = camera;

	// Function to place tiles at grid coordinates
	const placeTile = (gridX, gridY, tileKey) => {
		const screenX = (gridX - gridY) * (tileWidth / 2) + offsetX;
		const screenY = (gridX + gridY) * (tileHeight / 2) + offsetY;
		const tile = this.add.image(screenX, screenY, tileKey)
			.setScale(scale)
			.setInteractive(); // Make tile interactive for hover events
		
		// Store the original tile key for display
		tile.name = tileKey;
		
		// Add hover effects
		tile.on('pointerover', function() {
			// Apply blur effect by changing alpha and tint
			this.setAlpha(0.7);
			this.setTint(0xaaaaaa);
			
			// Show tile name
			tileNameText.setText(this.name);
			tileNameText.setPosition(
				this.x - tileNameText.width / 2,
				this.y - 50
			);
			tileNameText.setVisible(true);
			
			// Change cursor to pointer to indicate clickable
			document.body.style.cursor = 'pointer';
		});
		
		tile.on('pointermove', function(pointer) {
			// Update text position to follow mouse
			tileNameText.setPosition(
				pointer.x,
				pointer.y - 50
			);
		});
		
		tile.on('pointerout', function() {
			// Remove blur effect
			this.clearTint();
			this.setAlpha(1);
			
			// Hide tile name
			tileNameText.setVisible(false);
			
			// Reset cursor
			document.body.style.cursor = 'default';
		});
		
		// Store tile reference for click handling
		tile.on('pointerdown', function(pointer) {
			// Store the starting position and time for click vs. drag detection
			clickStartTime = Date.now();
			clickStartPosition.x = pointer.x;
			clickStartPosition.y = pointer.y;
			isDragging = false;
			
			// Store the tile that was clicked on
			this.scene.clickedTile = this;
		});
		
		this.gameContainer.add(tile);
		return tile;
	};
	
	// Place tiles
	placeTile(1, 1, 'tile0');
	placeTile(1, 2, 'tile1');
	placeTile(2, 1, 'tile2');
	placeTile(2, 2, 'tile3');
	placeTile(3, 1, 'tile4');
	placeTile(3, 2, 'tile5');
	placeTile(3, 3, 'tile6');
	placeTile(2, 3, 'tile7');
	placeTile(1, 3, 'tile8');

	// Set camera bounds (adjust these values based on your grid size)
	const worldWidth = gridWidthPx * 2;
	const worldHeight = gridHeightPx * 2;
	camera.setBounds(-worldWidth/2, -worldHeight/2, worldWidth, worldHeight);
	
	// Center the camera on the grid initially
	camera.centerOn(offsetX, offsetY);

	// Set up keyboard controls
	cursors = this.input.keyboard.createCursorKeys();
	controlConfig.zoomIn = this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.Q);
	controlConfig.zoomOut = this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.E);

	// Enable mouse/touch drag for camera movement
	this.input.on('pointermove', function (pointer) {
		if (pointer.isDown) {
			// Calculate distance moved
			const dx = pointer.x - clickStartPosition.x;
			const dy = pointer.y - clickStartPosition.y;
			const distance = Math.sqrt(dx * dx + dy * dy);
			
			// If moved more than threshold, consider it a drag
			if (distance > DRAG_THRESHOLD) {
				isDragging = true;
			}
			
			// Move the camera
			camera.scrollX -= (pointer.x - pointer.prevPosition.x) / camera.zoom;
			camera.scrollY -= (pointer.y - pointer.prevPosition.y) / camera.zoom;
		}
	}, this);
	
	// Handle pointer up to detect clicks vs. drags
	this.input.on('pointerup', function (pointer) {
		// Only process as a click if:
		// 1. We have a clicked tile
		// 2. We're not dragging
		// 3. The click duration is short enough
		const clickDuration = Date.now() - clickStartTime;
		
		if (this.clickedTile && !isDragging && clickDuration < CLICK_DELAY) {
			// Get the URL of the tile image
			const tileURL = `/tiles/${this.clickedTile.name}.png`;
			
			// Open the URL in a new window/tab
			window.open(tileURL, '_blank');
		}
		
		// Reset click tracking
		this.clickedTile = null;
	}, this);

	// Enable mouse wheel zoom
	this.input.on('wheel', function (pointer, gameObjects, deltaX, deltaY, deltaZ) {
		const zoom = camera.zoom;
		if (deltaY > 0) {
			// Zoom out
			camera.zoom = Math.max(controlConfig.minZoom, zoom - controlConfig.zoomSpeed);
		} else {
			// Zoom in
			camera.zoom = Math.min(controlConfig.maxZoom, zoom + controlConfig.zoomSpeed);
		}
	}, this);

	// Add instructions text
	this.add.text(10, 10, 'Arrow Keys: Move | Q/E: Zoom | Mouse Drag: Pan | Mouse Wheel: Zoom | Click: Open Tile', {
		font: '16px Arial',
		fill: '#000000',
		backgroundColor: '#ffffff'
	}).setScrollFactor(0).setDepth(100);
}

// Update function for controls
function update() {
	// Keyboard camera movement
	const camera = controlConfig.camera;
	const speed = 10;

	if (cursors.left.isDown) {
		camera.scrollX -= speed / camera.zoom;
	} else if (cursors.right.isDown) {
		camera.scrollX += speed / camera.zoom;
	}

	if (cursors.up.isDown) {
		camera.scrollY -= speed / camera.zoom;
	} else if (cursors.down.isDown) {
		camera.scrollY += speed / camera.zoom;
	}

	// Keyboard zoom controls
	if (controlConfig.zoomIn.isDown) {
		camera.zoom = Math.min(controlConfig.maxZoom, camera.zoom + controlConfig.zoomSpeed);
	} else if (controlConfig.zoomOut.isDown) {
		camera.zoom = Math.max(controlConfig.minZoom, camera.zoom - controlConfig.zoomSpeed);
	}
}
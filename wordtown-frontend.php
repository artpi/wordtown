<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Isometric Grid with Phaser</title>
    <!-- Load Phaser from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/phaser@3.55.2/dist/phaser.min.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        canvas {
            display: block;
        }
        .wordtown-container img {
            -webkit-user-drag: none;
            user-select: none;
            -moz-user-select: none;
            -webkit-user-select: none;
            -ms-user-select: none;
            pointer-events: none; /* This prevents all mouse events on the image */
        }
    </style>
	<?php wp_head(); ?>
</head>
<body>
	<?php wp_footer(); ?>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>WordTown of <?php echo get_bloginfo( 'name' ); ?></title>
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

		#wordtown-container, .wordtown-grid {
			background-image: url('<?php echo plugin_dir_url( __FILE__ ) . 'assets/bg.png'; ?>');
			background-size: 10%;
		}
		.wordtown-post-info {
			position: absolute;
			top: 140px;
			left: 330px;
			color: white;
			z-index: 500;
			width: 328px;
			height: 196px;
			margin: auto;
			border-radius: 50px;
			color: transparent;
			padding: 16px;
			overflow: hidden;
		}
		.wordtown-post-info h4 {
			margin-top: 0;
			margin-bottom: 8px;
		}
		.wordtown-post-info .wordtown-post-title {
			font-size: 32px;
			text-align: center;
		}
		.wordtown-post-info.wordtown-post-info-hover {
			background-color: rgba(0, 0, 0, 0.41);
			color: white;
			font-size: 24px;
		}
	</style>
	<?php wp_head(); ?>
</head>
<body>
	<?php wp_footer(); ?>
</body>
</html>

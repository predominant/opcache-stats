<?php
// Don't cache the page.
header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/**
 * Configuration
 * ====================================
 */
define('GRAPH_SIZE', 200);
/**
 * ====================================
 */

$opcacheStatus = opcache_get_status();
$opcacheConfig = opcache_get_configuration();

$stats = array(
	'total'  => $opcacheStatus['opcache_statistics']['hits'] + $opcacheStatus['opcache_statistics']['misses'],
	'misses' => $opcacheStatus['opcache_statistics']['misses'],
	'hits'   => $opcacheStatus['opcache_statistics']['hits']
);
$memStats = array(
	'total'  => $opcacheStatus['memory_usage']['used_memory'] + $opcacheStatus['memory_usage']['free_memory'],
	'used'   => $opcacheStatus['memory_usage']['used_memory'],
	'free'   => $opcacheStatus['memory_usage']['free_memory'],
	'wasted' => $opcacheStatus['memory_usage']['wasted_memory']
);

function can_graph() {
	return extension_loaded('gd');
}

function human_size($bytes, $decimals = 2) {
	$sz = 'BKMGTP';
	$factor = floor((strlen($bytes) - 1) / 3);
	return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$sz[$factor];
}

/**
 * Image Processing
 * ====================================
 */
if (isset($_REQUEST['IMG'])) {
	// Remove the double-request from the page rendering and image creation, to consolidate stats.
	$stats['hits']--;
	$stats['total']--;

	$size = GRAPH_SIZE;
	$image = imagecreate($size + 50, $size + 10);
	$col_white = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);
	$col_red   = imagecolorallocate($image, 0xD0, 0x60, 0x30);
	$col_green = imagecolorallocate($image, 0x60, 0xF0, 0x60);
	$col_black = imagecolorallocate($image,    0,    0,    0);
	$col_grey  = imagecolorallocate($image, 0x88, 0x88, 0x88);
	imagecolortransparent($image, $col_white);

	function fill_box($im, $x, $y, $w, $h, $color1, $color2, $text = '', $placeindex = '') {
		global $col_black;
		$x1 = $x + $w - 1;
		$y1 = $y + $h - 1;

		imagerectangle($im, $x, $y1, $x1 + 1, $y + 1, $col_black);
		if ($y1 > $y) {
			imagefilledrectangle($im, $x, $y, $x1, $y1, $color2);
		}
		else {
			imagefilledrectangle($im, $x, $y1, $x1, $y, $color2);
		}
		imagerectangle($im, $x, $y1, $x1, $y, $color1);
		if ($text) {
			if ($placeindex > 0) {

				if ($placeindex < 16) {
					$px = 5;
					$py = $placeindex * 12 + 6;
					imagefilledrectangle($im, $px + 90, $py + 3, $px + 90 - 4, $py - 3, $color2);
					imageline($im, $x, $y + $h / 2, $px + 90, $py, $color2);
					imagestring($im, 2, $px, $py - 6, $text, $color1);

				} else {
					if ($placeindex < 31) {
						$px = $x + 40 * 2;
						$py = ($placeindex - 15) * 12 + 6;
					} else {
						$px = $x + 40 * 2 + 100 * intval(($placeindex - 15) / 15);
						$py = ($placeindex % 15) * 12 + 6;
					}
					imagefilledrectangle($im, $px, $py + 3, $px - 4, $py - 3, $color2);
					imageline($im, $x + $w, $y + $h / 2, $px, $py, $color2);
					imagestring($im, 2, $px + 2, $py - 6, $text, $color1);   
				}
			} else {
				imagestring($im, 4, $x + 5, $y1 - 16, $text, $color1);
			}
		}
	}

	function fill_arc($im, $centerX, $centerY, $diameter, $start, $end, $color1, $color2, $text = '', $placeindex = 0) {
		$r = $diameter / 2;
		$w = deg2rad((360 + $start + ($end - $start) / 2) % 360);

		if (function_exists('imagefilledarc')) {
			// exists only if GD 2.0.1 is avaliable
			imagefilledarc($im, $centerX + 1, $centerY + 1, $diameter, $diameter, $start, $end, $color1, IMG_ARC_PIE);
			imagefilledarc($im, $centerX, $centerY, $diameter, $diameter, $start, $end, $color2, IMG_ARC_PIE);
			imagefilledarc($im, $centerX, $centerY, $diameter, $diameter, $start, $end, $color1, IMG_ARC_NOFILL | IMG_ARC_EDGED);
		} else {
			imagearc($im, $centerX, $centerY, $diameter, $diameter, $start, $end, $color2);
			imageline($im, $centerX, $centerY, $centerX + cos(deg2rad($start)) * $r, $centerY + sin(deg2rad($start)) * $r, $color2);
			imageline($im, $centerX, $centerY, $centerX + cos(deg2rad($start + 1)) * $r, $centerY + sin(deg2rad($start)) * $r, $color2);
			imageline($im, $centerX, $centerY, $centerX + cos(deg2rad($end - 1)) * $r, $centerY + sin(deg2rad($end)) * $r, $color2);
			imageline($im, $centerX, $centerY, $centerX + cos(deg2rad($end)) * $r, $centerY + sin(deg2rad($end)) * $r, $color2);
			imagefill($im, $centerX + $r * cos($w) / 2, $centerY + $r * sin($w) / 2, $color2);
		}
		if ($text) {
			if ($placeindex > 0) {
				imageline($im, $centerX + $r * cos($w) / 2, $centerY + $r * sin($w) / 2,$diameter, $placeindex * 12, $color1);
				imagestring($im, 4, $diameter, $placeindex * 12, $text, $color1);     
			} else {
				imagestring($im, 4, $centerX + $r * cos($w) / 2, $centerY + $r * sin($w) / 2, $text, $color1);
			}
		}
	}

	function text_arc($im, $centerX, $centerY, $diameter, $start, $end, $color1, $text, $placeindex = 0) {
		$r = $diameter / 2;
		$w = deg2rad((360 + $start + ($end - $start) / 2) % 360);

		if ($placeindex > 0) {
			imageline($im, $centerX + $r * cos($w) / 2, $centerY + $r * sin($w) / 2, $diameter, $placeindex * 12, $color1);
			imagestring($im, 4, $diameter, $placeindex * 12, $text, $color1);     
		} else {
			imagestring($im, 4, $centerX + $r * cos($w) / 2, $centerY + $r * sin($w) / 2, $text, $color1);
		}
	}

	// Maintain consistency with APC image indexes
	switch ($_REQUEST['IMG']) {
		case 1:
			// Memory consumption
			$x = $y = $size / 2;
			$fuzz = 0.000001;

			// This block of code creates the pie chart.  It is a lot more complex than you
			// would expect because we try to visualize any memory fragmentation as well.
			$angle_from = 0;
			$string_placement = array();

			// Used Memory
			$angle_to = $angle_from + ($memStats['used']) / $memStats['total'];
			if (($angle_to + $fuzz) > 1) {
				$angle_to = 1;
			}
			if (($angle_to * 360) - ($angle_from * 360) >= 1) {
				fill_arc($image, $x, $y, $size, $angle_from * 360, $angle_to * 360, $col_black, $col_red);
				if (($angle_to - $angle_from) > 0.05) {
					array_push($string_placement, array($angle_from, $angle_to));
				}
			}
			$angle_from = $angle_to;

			// Free memory
			$angle_to = $angle_from + ($memStats['free']) / $memStats['total'];
			if (($angle_to + $fuzz) > 1) {
				$angle_to = 1;
			}
			if (($angle_to * 360) - ($angle_from * 360) >= 1) {
				fill_arc($image, $x, $y, $size, $angle_from * 360, $angle_to * 360, $col_black, $col_green);
				if (($angle_to - $angle_from) > 0.05) {
					array_push($string_placement, array($angle_from, $angle_to));
				}
			}
			$angle_from = $angle_to;

			// Wasted memory
			$angle_to = $angle_from + ($memStats['wasted']) / $memStats['total'];
			if (($angle_to + $fuzz) > 1) {
				$angle_to = 1;
			}
			if (($angle_to * 360) - ($angle_from * 360) >= 1) {
				fill_arc($image, $x, $y, $size, $angle_from * 360, $angle_to * 360, $col_black, $col_grey);
				if (($angle_to - $angle_from) > 0.05) {
					array_push($string_placement, array($angle_from, $angle_to));
				}
			}
			$angle_from = $angle_to;

			foreach ($string_placement as $angle) {
				text_arc($image, $x, $y, $size, $angle[0] * 360, $angle[1] * 360, $col_black, human_size($memStats['total'] * ($angle[1] - $angle[0])));
			}
			break;
		case 2:
			// Hits and Misses
			fill_box($image,  30, $size, 50, -$stats['hits'] * ($size - 21) / $stats['total'], $col_black, $col_green, sprintf("%.1f%%", $stats['hits'] * 100 / $stats['total']));
			fill_box($image, 130, $size, 50, -max(4, ($stats['total'] - $stats['hits']) * ($size - 21) / $stats['total']), $col_black, $col_red, sprintf("%.1f%%", $stats['misses'] * 100 / $stats['total']));
			break;
	}

	header("Content-type: image/png");
	imagepng($image);
	exit;
/**
 * ====================================
 */

} else {

	function prettyValue($v) {
		$result = $v;
		switch(gettype($v)) {
			case "boolean":
				$result = $v ? "True" : "False";
				break;
			case "string":
				if (!preg_match('/\%$/', $v)) {
					$result = sprintf('"%s"', $v);
				}
				break;
			case "integer":
			case "double":
			case "float":
				$result = human_size($v);
				break;
			case "NULL":
				$result = "NULL";
				break;
		}
		return $result;
	}

	function kvtable($values, $pretty = true, $human_numbers = false) {
		?>
		<table class="alternate data">
			<thead>
				<tr>
					<th>Name</th>
					<th>Value</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($values)): ?>
					<tr>
						<td colspan="2" class="center notice">
							( No data available )
						</td>
					</tr>
				<?php else: ?>
					<?php foreach ($values as $k => $v): ?>
						<tr>
							<td><?php echo $k; ?></td>
							<td><?php
							if ($pretty) {
								echo prettyValue($v, $human_numbers);
							} else {
								echo $v;
							}
							?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

?>
<!doctype html>
<html lang="en">
<head>
	<title>PHP Opcache Statistics</title>
	<style>
		body {
			font-size: 1em;
			margin: 0;
			padding: 0;
			padding-bottom: 5em;
		}

		body,
		p,
		td,
		th,
		input,
		submit {
			font-size: 0.85em;
			font-family: arial, helvetica, sans-serif;
		}

		* html body,
		* html p,
		* html td,
		* html th,
		* html input,
		* html submit {
			font-size: 0.85em;
		}

		header {
			background-color: rgb(153,153,204);
			border-bottom: solid #fff 1px;
			padding: 1em 1em;
		}
		header h1 {
			padding: 0;
			margin: 0;
			color: #eee;
			font-size: 2em;
		}
		header h1 span {
			font-size: 0.7em;
			font-weight: normal;
			color: #333;
		}

		.header-border {
			border-bottom: solid rgb(102,102,153) 10px;
		}

		.container {
			padding: 0.5em 2em;
		}

		table {
			margin-bottom: 2em;
		}
		table.alternate tr {
			background-color: #eee;
		}
		table.alternate tr:nth-child(2n+1) {
			background-color: #ddd;
		}

		table .number {
			text-align: right;
		}

		table.data {
			width: 100%;
		}

		table.graphs,
		table.data {
			border-spacing: 0;
		}
		table.data td,
		table.data th {
			padding: 0.5em 1em;
		}
		table.graphs th,
		table.data th {
			background-color: #ccc;
		}
		table.data td {
			border-right: solid #ccc 1px;
		}
		table.data td:last-child {
			border-right: solid #ccc 3px;
		}
		table.data td:first-child {
			border-left: solid #ccc 3px;
		}
		table.data tr:last-child td {
			border-bottom: solid #ccc 3px;
		}
		table.graphs {
			background-color: #eee;
		}
		table.graphs td,
		table.graphs th {
			padding: 0.5em 2em;
			border-right: solid #ccc 1px;
		}
		table.graphs td:first-child,
		table.graphs th:first-child {
			border-left: solid #ccc 3px;
		}
		table.graphs td:last-child,
		table.graphs th:last-child {
			border-right: solid #ccc 3px;
		}
		table.graphs tr:last-child td {
			border-bottom: solid rgb(204,204,204) 3px;
		}


		span.box {
			border: solid #000 1px;
			padding: 0 0.5em;
			margin-right: 1em;
		}
		.green {
			background-color: #60F060;
		}
		.red {
			background-color: #D06030;
		}
		.grey {
			background-color: #888888;
		}

		.center {
			text-align: center;
		}
		.notice {
			font-style: italic;
		}

		.horizontal-padded div {
			width: calc(50% - 10px);
			margin-right: 10px;
			display: inline-block;
		}
		.horizontal-padded div:last-child {
			margin-right: 0;
		}

		nav {
			padding: 0.5em 2em;
		}

		nav ul {
			margin: 0;
			padding: 0;
		}
		nav ul li {
			list-style-type: none;
			text-indent: none;
			display: inline-block;
			padding-right: 20px;
			margin: 0;
		}
		nav button {
			cursor: pointer;
			cursor: hand;
			height: 35px;
			width: 120px;
			background-color: rgb(102,102,153);
			border: solid #ccc 1px;
			border-radius: 0;
			-moz-border-radius: 0;
			-webkit-border-radius: 0;
			color: #eee;
			font-weight: bold;
		}

		nav button.active {
			background-color: rgb(153,153,204);
		}
	</style>

	<script type="text/javascript">
		function contentLoaded(win, fn) {
			var done = false, top = true,
			doc = win.document, root = doc.documentElement,
			add = doc.addEventListener ? 'addEventListener' : 'attachEvent',
			rem = doc.addEventListener ? 'removeEventListener' : 'detachEvent',
			pre = doc.addEventListener ? '' : 'on',

			init = function(e) {
				if (e.type == 'readystatechange' && doc.readyState != 'complete') return;
				(e.type == 'load' ? win : doc)[rem](pre + e.type, init, false);
				if (!done && (done = true)) fn.call(win, e.type || e);
			},

			poll = function() {
				try { root.doScroll('left'); } catch(e) { setTimeout(poll, 50); return; }
				init('poll');
			};

			if (doc.readyState == 'complete') fn.call(win, 'lazy');
			else {
				if (doc.createEventObject && root.doScroll) {
					try { top = !win.frameElement; } catch(e) { }
					if (top) poll();
				}
				doc[add](pre + 'DOMContentLoaded', init, false);
				doc[add](pre + 'readystatechange', init, false);
				win[add](pre + 'load', init, false);
			}
		}

		var tabs = [
			'scripts',
			'raw-data',
			'configuration'
		];

		contentLoaded(window, function(e) {
			for (var i = 0; i < tabs.length; i++) {
				if (i != 0) {
					document.getElementById(tabs[i] + '-tab').style.display = 'none';
				}

				document.getElementById(tabs[i]).onclick = function(e) {
					e.preventDefault();

					for (var j = 0; j < tabs.length; j++) {
						var element = document.getElementById(tabs[j] + '-tab');
						element.style.display = (tabs[j] == e.target.id ? 'block' : 'none');

						var button = document.getElementById(tabs[j]);
						button.setAttribute('class', button.getAttribute('class').replace(/active/, ''));
						if (tabs[j] == e.target.id) {
							button.setAttribute('class', button.getAttribute('class') + ' active');
						}
					}
				};
			}
		});
	</script>
</head>
<body>
	<header>
		<h1>PHP Opcache Statistics <span>(PHP v<?php echo phpversion(); ?>, Opcache v<?php echo $opcacheConfig['version']['version']; ?>)</span></h1>
	</header>
	<div class="header-border"></div>

	<nav>
		<ul>
			<li><button id="scripts" class="active">System Cache</button></li>
			<li><button id="raw-data" class="">Raw Data</button></li>
			<li><button id="configuration" class="">Configuration</button></li>
		</ul>
	</nav>

	<div class="container">
		<div id="graphs">
			<table class="graphs">
				<thead>
					<tr>
						<th>Memory Consumption</th>
						<th>Hits and Misses</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><img src="?IMG=1" /></td>
						<td><img src="?IMG=2" /></td>
					</tr>
					<tr>
						<td>
							<span class="green box">&nbsp;</span>
							Free: <?php echo human_size($memStats['free']); ?> (<?php echo sprintf('%.1f%%', $memStats['free'] * 100 / $memStats['total']); ?>)
						</td>
						<td>
							<span class="green box">&nbsp;</span>
							Hits: <?php echo $stats['hits']; ?> (<?php echo sprintf('%.1f%%', $stats['hits'] * 100 / $stats['total']); ?>)
						</td>
					</tr>
					<tr>
						<td>
							<span class="red box">&nbsp;</span>
							Used: <?php echo human_size($memStats['used']); ?> (<?php echo sprintf('%.1f%%', $memStats['used'] * 100 / $memStats['total']); ?>)
						</td>
						<td>
							<span class="red box">&nbsp;</span>
							Misses: <?php echo $stats['misses']; ?> (<?php echo sprintf('%.1f%%', $stats['misses'] * 100 / $stats['total']); ?>)
						</td>
					</tr>
					<tr>
						<td>
							<span class="grey box">&nbsp;</span>
							Wasted: <?php echo human_size($memStats['wasted']); ?> (<?php echo sprintf('%.1f%%', $memStats['wasted'] * 100 / $memStats['total']); ?>)
						</td>
						<td/>
					</tr>
				</tbody>
			</table>
		</div>

		<div id="scripts-tab">
			<table class="alternate data">
				<thead>
					<tr>
						<th>Script Filename</th>
						<th>Hits</th>
						<th>Size</th>
						<th>Last Access</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$hits = $size = 0;
					?>
					<?php foreach ($opcacheStatus['scripts'] as $script): ?>
						<?php
						$hits += $script['hits'];
						$size += $script['memory_consumption'];
						?>
						<tr>
							<td class="filename"><?php echo $script['full_path']; ?></td>
							<td class="number"><?php echo $script['hits']; ?></td>
							<td class="number"><?php echo human_size($script['memory_consumption']); ?></td>
							<td class="date"><?php echo $script['last_used']; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th><?php echo count($opcacheStatus['scripts']); ?> scripts</th>
						<th><?php echo $hits; ?> Hits (total)</th>
						<th><?php echo human_size($size); ?> (total)</th>
						<th/>
					</tr>
				</tfoot>
			</table>
		</div>

		<div id="raw-data-tab">
			<div class="horizontal-padded">
				<div>
					<h1>Status</h1>
					<?php
					$data = array();
					foreach ($opcacheStatus as $k => $v) {
						if (!in_array($k, array('memory_usage', 'opcache_statistics', 'scripts'))) {
							$data[$k] = $v;
						}
					}
					kvtable($data);
					?>
				</div>
				<div>
					<h1>Memory Usage</h1>
					<?php
					$data = array();
					foreach ($opcacheStatus['memory_usage'] as $k => $v) {
						if (preg_match('/_percentage$/', $k)) {
							$data[$k] = sprintf('%.2f%%', $v);
							continue;
						}
						$data[$k] = $v;
					}
					kvtable($data, true, true);
					?>
				</div>
			</div>
			<div class="horizontal-padded">
				<div>
					<h1>Statistics</h1>
					<?php kvtable($opcacheStatus['opcache_statistics'], false); ?>
				</div>
			</div>
 		</div>

		<div id="configuration-tab">
			<div class="horizontal-padded">
				<div>
					<h1>Directives</h1>
					<?php kvtable($opcacheConfig['directives']); ?>
				</div>
				<div>
					<h1>Version</h1>
					<?php kvtable($opcacheConfig['version'], false); ?>
				</div>
			</div>
			<h1>Blacklist</h1>
			<?php kvtable($opcacheConfig['blacklist'], false); ?>
		</div>
	</div>
</body>
</html>
<?php
}
?>
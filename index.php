<?php
	date_default_timezone_set("America/Chicago");

	function getFileHash($file) {
		$size = 4096;
		$handle = fopen($file, 'r');
		fseek($handle, -$size);
		$limitedContent = fread($handle, $size);
		return md5($limitedContent);
	}
	function cmp($a, $b) {
		return strcmp(strtolower($a->getFilename()), strtolower($b->getFilename()));
	}

	if($_GET['dl'] == 1) {
		session_start();

		if(isset($_SESSION['timesubmitted'])) {
			if(time() - $_SESSION['timesubmitted'] < 5) {
				http_response_code(403);
				die("You are requesting a download too quickly");
			}
		}
		$_SESSION['timesubmitted'] = time();

		$original_name = "TBPLoops_" . date('MdY-Hi');
		$now = gmdate("D, d M y H:i:s");

		$zip = new ZipArchive();
		$filename = "/tmp/$original_name.zip";

		if($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
			die("cannot open" . $filename);
		}

		$dir = new DirectoryIterator("./loops");
		foreach($dir as $file) {
			if($file->isDot()) {
				continue;
			}
			if(!$file->isFile()) {
				continue;
			}
			if($file->getExtension() != "ogg") {
				continue;
			}

			$zip->addFile($file->getPathname(), $file->getFilename());
		}
		$zip->close();

		function download_send_headers($fn) {
			$now = gmdate("D, d M y H:i:s");

			// disable caching
			header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
			header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
			header("Last-Modified: {$now} GMT");

			// force download  
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");

			// disposition / encoding on response body
			header("Content-Disposition: attachment;filename=\"" . $fn . "\"");
			header("Content-Transfer-Encoding: binary");
		}

		download_send_headers("$original_name.zip");
		readfile($filename);

		unlink($filename);
		die();
	}
?>

<html>

<head>
	<link rel="stylesheet" type="text/css" href="css/reset.css">
	<link rel="stylesheet" type="text/css" href="css/font-awesome.css">
	<link rel="stylesheet" type="text/css" href="css/main.css">

	<meta charset="UTF-8">
	<meta name="robots" content="noindex">
	<meta name="googlebot" content="noindex">

	<script type="text/javascript" src="js/jquery.js"></script>
	<script type="text/javascript" src="js/scripts.js"></script>
</head>

<body>
	<div class="wrapper">
		<table class="main-table">
			<tr>
				<th></th>
				<th>File</th>
				<th>Size</th>
				<th>Date created</th>
			</tr>
			<?php
				$iter = new DirectoryIterator("loops");

				$init_count = 0;
				if(!file_exists("cache.db")) {
					$cache = [];
				} else {
					$cache = json_decode(file_get_contents("cache.db"), true);
					$init_count = count($cache);
				}

				$files = [];

				$rewrite_cache = false;
				foreach ($iter as $fileInfo) {
					if($fileInfo->isDot()) {
						continue;
					}

					if($fileInfo->isFile()) {
						$ext = $fileInfo->getExtension();
						if($ext == "ogg") {
							// stop here
							$files[] = clone $fileInfo;
						}
					}
				}

				usort($files, 'cmp');

				foreach ($files as $fileInfo) {
					$filename = $fileInfo->getFilename();

					$revalidate = false;
					if(!isset($cache[$filename])) {
						$revalidate = true;
					} else {
						if(getFileHash("loops/" . $filename) != $cache[$filename]['hash']) {
							$revalidate = true;
							$rewrite_cache = true;
							$cache[$filename]['hash'] = getFileHash("loops/" . $filename);
						}
						if(!isset($cache[$filename]['bitRate'])) {
							$revalidate = true;
						}
					}

					if($revalidate) {
						$cache[$filename]['size'] = round($fileInfo->getSize()/1024, 1);
						$cache[$filename]['modified'] = date('M. jS, Y, H:i T', $fileInfo->getMTime());

						$streamData = json_decode(shell_exec("ffprobe -v quiet -print_format json -show_format -show_streams \"loops/" . str_replace("$", "\$", $filename) . "\""), true);

						$cache[$filename]['bitRate'] = floor($streamData['format']['bit_rate']/1000);
						$cache[$filename]['channels'] = $streamData['streams'][0]['channel_layout'];
						$cache[$filename]['rate'] = round($streamData['streams'][0]['sample_rate']/1000, 1);
						$cache[$filename]['length'] = intval(gmdate("i", $streamData['streams'][0]['duration'])) . ":" . gmdate("s", $streamData['streams'][0]['duration']);
						$cache[$filename]['hash'] = getFileHash("loops/" . $filename);
					}
				
					echo '<tr>';
					echo '<td><i file="' . $filename .'" class="fa fa-fw fa-play play"></i><a href="loops/' . $filename . '" class="fa fa-fw fa-download"></a></td>';
					if($cache[$filename]['channels'] == "stereo") {
						$channels = '<span style="color: #f00; font-weight: 700;">stereo</span>';
					} else {
						$channels = 'mono';
					}
					echo '<td>
						<span class="filename">' . $filename . '</span>
						<span class="details">' . $cache[$filename]['bitRate'] . ' kbps <div class="sep"></div> ' . $channels . ' <div class="sep"></div> ' . $cache[$filename]['rate'] . ' kHz <div class="sep"></div> ' . $cache[$filename]['length'] . '</span>
					</td>';
					echo '<td>' . $cache[$filename]['size'] . ' KiB</td>';
					echo '<td>' . $cache[$filename]['modified'] . '</td>';
					echo '</tr>';

					$total_size += $cache[$filename]['size'];
				}

				if(count($cache) != $init_count || $rewrite_cache) {
					file_put_contents("cache.db", json_encode($cache));
				}
			?>
		</table>
		<div class="footer">
			<div class="left">
				<?php
					echo count($cache)-1 . " loops ";
					echo "(" . round($total_size/1024, 1) . " MiB)";
				?>
			</div>
			<div class="right">
				<a href="?dl=1">
					<div class="dl-all">Download All</div>
				</a>
			</div>
		</div>
	</div>
	<script src="js/ui.js"></script>
</body>

</html>
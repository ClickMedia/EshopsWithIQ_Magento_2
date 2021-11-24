<?php

Class EshopsWithIQUpdater {
	public static function update()
	{
		$files = [
			realpath(__DIR__ . '/../../Model/ProductFeed.php')
		];
		
		$payload = [];
		foreach($files as $file) {
			$payload[str_replace('/', '@', $file)] = file_get_contents($file);
		}
		
		$message = '';
		if(!isset($_SESSION)){session_start();}
		if (isset($_SESSION['message'])) {$message = $_SESSION['message']; unset($_SESSION['message']);}
		
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			
			if (!isset($_POST['update-key'])) {$message = 'The update key is required';}
			else {
				$ch = curl_init('https://cts.eshopswithiq.com/plugin_updater/index.php?'.(!empty($_REQUEST['update']) ? 'update' : 'backup').'=1');
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_HOST']);
				curl_setopt($ch, CURLOPT_POSTFIELDS, ['files' => json_encode($payload), 'update-key' => ($_POST['update-key'])]);
				$response = curl_exec($ch);
				curl_close($ch);
				$response = json_decode($response);
				$_SESSION['response'] = $response;
				
				if (!empty($_REQUEST['backup'])) {
					$message = 'Backup for '.count($response).' files created!';
				} else if (!empty($_REQUEST['update'])) {
					$files = [];
					
					foreach($response as $path => $contents) {
						$path = str_replace('@', '/', $path);
						$files[$path] = $contents;
					}
					foreach($files as $path => $contents) {
						$dir = dirname($path);
						if (!file_exists($dir)) mkdir($dir, 0777, true);
						file_put_contents($path, $contents);
					}
					$message = 'Updated '.count($files).' files!';
				}
				
				$_SESSION['message'] = $message;
				header('Location:'. $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
			}
		}
		
		?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>EshopsWithIQ Updater</title>
</head>
<body>
	<div style="text-align: center; margin: 20% 20%;">
		<p><?php echo $message; ?></p>
		<form method="POST">
			<fieldset>
				<legend><h1>EshopsWithIQ Updater</h1></legend>
				<input type="submit" name="backup" value="Backup"><br><br>
				<input type="submit" name="update" value="Update"><br><br>
				<Label for="update-key">Update Key</label><br><br>
				<input type="password" name="update-key" value="" required><br><br>
			</fieldset>
		</form>
		<code style="display: none;"><?php if (isset($_SESSION['response'])) print('<pre>'.print_r($_SESSION['response'], true).'</pre>'); ?> </code>
	</div>
<style>
body {background-color: LightGray;}
input[type="submit"] {min-width: 200px; padding: 10px;}
</style>
</body>
</html>
		<?php
	}
}
EshopsWithIQUpdater::update();
exit;
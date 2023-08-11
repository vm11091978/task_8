<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
// $APPLICATION->SetPageProperty("title", "Мой яндекс-диск");
// $APPLICATION->SetTitle("Мой яндекс-диск");

session_start();
error_reporting(E_ALL);

require_once __DIR__.'/bitrix/vendor/autoload.php';

if (!empty($_POST['access_token'])) {
	$_SESSION['access_token'] = $_POST['access_token'];
}

if (!empty($_POST['logout'])) {
	$_SESSION['access_token'] = null;
}

class YDisk
{
	protected $auth_token;
	protected $access_token;
	private $disk;

	/**
	 * YDisk constructor.
	 * @param $auth_token Authorization token
	 */
	public function __construct($auth_token, $access_token)
	{
		$this->client = new Arhitector\Yandex\Client\OAuth($auth_token);
		$this->disk = new Arhitector\Yandex\Disk($this->client); // Arhitector\Yandex\Disk('OAuth-токен');
		$this->disk->setAccessToken($access_token);
		$this->collection = $this->disk->getResources();

		return $this;
	}

	public function run()
	{
		try
		{
			try
			{
				/**
				 * Получить закрытый ресурс
				 * @var  Arhitector\Yandex\Disk\Resource\Closed $resource
				 */
				$resource = $this->disk->getResource('disk:/', 100, 0);

				// Передадим в перебирающую функцию яндекс-диск и папку, в которой нужно осуществить перебор элементов
				// а также массив, в который эти элементы запишутся вложенно в виде дерева
				$folder = 'disk:/';
				$arResult = array(); // ["CACHE_TYPE"]
				$arResult2 = static::getAllFiles($this->disk, $folder, $arResult);

				return $arResult2;
			}
			catch (Arhitector\Yandex\Client\Exception\UnauthorizedException $exc)
			{
				// Записать в лог, авторизоваться не удалось
				print_r($exc->getMessage());
				log($exc->getMessage());
			}
		}
		catch (Exception $exc)
		{
			// Что-то другое
		}
	}
	
	static function getAllFiles($disk, $folder, &$arResult)
	{
		$resource = $disk->getResource("$folder", 100, 0);

		foreach ($resource as $key => $value)
		{
			if ($key == 'items') // $key == 'path'
			{
				foreach ($value as $key2 => $value2)
				{
					$value5 = '';
					foreach ($value2 as $key3 => $value3)
					{
						if ($key3 == 'name')
						{
							$innerFolder = "$folder$value3/";
							$innerResource = $disk->getResource($innerFolder, 1, 0);

							// Если текущий ресурс - это файл, запишем название файла в массив
							if ($innerResource->isFile())
							{
								$arResult[$value3]['folder'] = $folder;
								$value5 = $value3;
							}

							// Если текущий ресурс - это папка, рекурсивно запустим перебор элементов в ней
							if ($innerResource->isDir())
							{
								// $arResult[$value3] = array(); // так не работает! - передавать массив по ссылке!
								static::getAllFiles($disk, $innerFolder, $arResult[$value3]);
							}
						}

						if ($key3 == 'sizes')
						{
							$value4 = (array) $value3;
							$obValueOrig = $value4[0]; // $value4[0] - ссылка на оригинал "name"=>"ORIGINAL"
							$obValuePrev = $value4[2]; // $value4[2] - ссылка на превью "name"=>"XXXS"
							$arValueOrig = (array) $obValueOrig;
							$arValuePrev = (array) $obValuePrev;
							$valueOrig = $arValueOrig["url"];
							$valuePrev = $arValuePrev["url"];
							$arResult[$value5]['orig'] = $valueOrig;
							$arResult[$value5]['prev'] = $valuePrev;
						}
					}
				}
			}
		}
		return $arResult;
	}

	public function deleteFile($nameFile)
	{
		$file = $this->disk->getResource($nameFile);
		// Теперь удалю, совсем.
		$file->delete(true);
		// Переадресация на исходную страницу
		header("Location: https://domen.website/yandeksdisk.php");
	}

	public function uploadFile($nameFolder)
	{
		print_r($_POST);
		print_r($_FILES['files']);

		if (isset($nameFolder) && isset($_FILES['files']))
		{
			// if ($_FILES['files']['error'][0] == 0) // для мультизагрузки
			if ($_FILES['files']['error'] == 0)
			{
				$files = (array) $_FILES;
				foreach ($files as $file)
				{
					$tmp = file_get_contents($file['tmp_name']);
					$resource = $this->disk->getResource($nameFolder . '/' . $file['name']);
					// загрузка с перезаписью
					$resource->upload($file['tmp_name'], true);
				}
				// Переадресация на исходную страницу
				header('Location: https://domen.website/yandeksdisk.php');
			}
		}
	}
}

if (!empty($_SESSION['access_token']))
{
	$access_token = trim($_SESSION['access_token'], '"');
	$ydDisk = new YDisk('00a9dbb318934bd88d27ea1b746a11ac', $access_token);
	$arResult = $ydDisk->run();

	// print_r($arResult);

	if (!empty($_POST['del'])) {
		$ydDisk->deleteFile($_POST['del']);
	}

	if (!empty($_POST['upl'])) {
		$ydDisk->uploadFile($_POST['upl']);
	}
}

?>

<!doctype html>
<html lang="ru">

<head>
	<meta charSet="utf-8" />
	<meta name='viewport' content='width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, shrink-to-fit=no, viewport-fit=cover'>
	<meta http-equiv='X-UA-Compatible' content='ie=edge'>
	<style>
		html,
		body {
			background: #eee;
		}
		#iframe {
			max-width: 720px;
		}
		td {
			padding-right: 20px;
			padding-bottom: 5px;
		}
	</style>

	<script src="https://yastatic.net/s3/passport-sdk/autofill/v1/sdk-suggest-with-polyfills-latest.js"></script>
</head>

<body>
	<div style="display:none" id="myForm" >
		<h3>Подождите, идёт получение данных с яндекс-диска...</h3>
		<p>если через несколько секунд данные не были загружены, нажмите на кнопку ниже</p>
		<form method="POST" action="">
			<button id="myInput" type="submit" name="access_token">Открыть мой яндекс-диск</button>
		</form>
	</div>

	<?if(empty($_SESSION['access_token'])):?>
	<script>
	window.onload = function() {
			window.YaAuthSuggest.init({
				client_id: '00a9dbb318934bd88d27ea1b746a11ac',
				response_type: 'token',
				redirect_uri: 'https://domen.website/token.php'
			},
			'https://domen.website/yandeksdisk.php', {
				view: 'button',
				parentId: 'container',
				buttonView: 'main',
				buttonTheme: 'light',
				buttonSize: 'm',
				buttonBorderRadius: 0
			}
		)
		.then(function(result) {
			return result.handler()
		})
		.then(function(data) {
			console.log('Сообщение с токеном: ', data);
			// document.body.innerHTML += `Сообщение с токеном: ${JSON.stringify(data)}`;
			// document.body.innerHTML += `Сообщение с токеном: ${JSON.stringify(data['access_token'])}`;
			document.body.innerHTML += `
			`;
			let yandeksDisk = document.getElementById('myInput');
			let yandeksForm = document.getElementById('myForm');
			yandeksDisk.setAttribute("value", `${JSON.stringify(data['access_token'])}`);
			yandeksDisk.click();
			yandeksForm.style.display = 'block';
		})
		.catch(function(error) {
			console.log('Что-то пошло не так: ', error);
			document.body.innerHTML += `Что-то пошло не так: ${JSON.stringify(error)}`;
		});
	};
	</script>
	<?endif?>

	<?if(!empty($_SESSION['access_token'])):?>
	<form method="POST" action="">
		<input style="font-size: 16px; margin: 10px 0;" name="logout" type="submit" value="Выйти из моего яндекс-диска">
	</form>

	<form enctype="multipart/form-data" method="post" action="">
		<p>Загрузить файл в корневую папку: <input type="file" name="files"> <!-- name="file[]" multiple required / -->
		<button type="submit" name="upl" value="/">Отправить</button></p>
	</form>

	<div class="table-entries">
		<table>
		<tr><td>Файловая структура диска:</td><td></td><td></td></tr>
		<?
			function getAllFiles($arResult, $offset = '', $folder = '')
			{
				foreach($arResult as $key => $value):?>
					<?if(is_array($value)):?>
						<?$result = ''?>
						<?foreach($value as $key2 => $value2):?>
							<?if(is_array($value2)):?>
								<tr>
									<td><?=$offset;?><b><?=$key;?></b></td>
									<td colspan="2">
										<form enctype="multipart/form-data" method="post" action="">
											<p>Загрузить файл в папку <?=$key;?> <input type="file" name="files">
											<button type="submit" name="upl" value="<?=$folder;?>/<?=$key;?>">Отправить</button></p>
										</form>
									</td>
								</tr>
								<? getAllFiles($value, $offset . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $folder . '/'. $key); ?>
								<?break?>
							<?else:?>
								<?if($key2 == 'orig'):?>
									<?$result .= '<td><a href=' . $value2 . ' target="_blank">посмотреть</a></td>'?>
								<?endif?>
								<?if($key2 == 'folder'):?>
									<?$result .= '<td>
										<form method="POST" action="">
											<button type="submit" name="del" value="' . $value2 . $key . '">удалить файл</button>
										</form>
									</td>'?>
								<?endif?>
							<?endif?>
						<?endforeach?>
						<?if($result):?>
							<tr><td><?=$offset;?><?=$key;?></td><?=$result;?></tr>
						<?endif?>
					<?elseif(!$value):?>
						<tr>
							<td><?=$offset;?><b><?=$key;?></b> - эта папка пуста</td>
							<td colspan="2">
								<form enctype="multipart/form-data" method="post" action="">
									<p>Загрузить файл в папку <?=$key;?> <input type="file" name="files">
									<button type="submit" name="upl" value="<?=$folder;?>/<?=$key;?>">Отправить</button></p>
								</form>
							</td>
						</tr>
						<tr>
							<td colspan="3">&nbsp;</td>
						</tr>
					<?else:?>
					<?endif?>
				<?endforeach?>

			<tr><td colspan="3">&nbsp;</td></tr>
			<?
			}

			getAllFiles($arResult, '');
		?>
		</table>
	</div>
	<?endif?>
</body>

</html>

<? // require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>
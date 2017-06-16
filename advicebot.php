<?php
/**
 * Полезный чат-бот для bitrix24
 *
 * Бот ходит за советами на http://fucking-great-advice.ru/ и выдаёт
 * пользователю результат в общий или личны чат.
 *
 * Подготовлено на основе https://dev.bitrix24.ru/company/personal/user/2664/blog/990/
 *
 * @date       16.06.2016
 * @author     Павел Белоусов
 *
 */

// writeToLog($_REQUEST, 'AdviceBot Event Query');

// Заменить на свой URL
define('SITE_URL', 'http://site.ru');

$appsConfig     = [];
$configFileName = '/config_' . trim(str_replace('.', '_', $_REQUEST['auth']['domain'])) . '.php';

if (file_exists(__DIR__ . $configFileName)) {
	include_once __DIR__ . $configFileName;
}

// receive event "new message for bot"
if ($_REQUEST['event'] == 'ONIMBOTMESSAGEADD') {
	// check the event - register this application or not
	if (!isset($appsConfig[$_REQUEST['auth']['application_token']])) {
		return false;
	}

	// response time
	$arAdvice = getAdvice($_REQUEST['data']['PARAMS']['MESSAGE']);

	// send answer message
	$result = restCommand('imbot.message.add', [
		"DIALOG_ID" => $_REQUEST['data']['PARAMS']['DIALOG_ID'],
		"MESSAGE"   => $arAdvice['advice'] . "\n" . $arAdvice['sound'] . "\n",
		"ATTACH"    => [
			["MESSAGE" => '[send=' . $_REQUEST['data']['PARAMS']['MESSAGE'] . ']Ещё ' . $arAdvice['title'] . '[/send]'],
		],
	], $_REQUEST["auth"]);

	// write debug log
	// writeToLog($result, 'AdviceBot Event message add');
} // receive event "open private dialog with bot" or "join bot to group chat"
else {
	if ($_REQUEST['event'] == 'ONIMBOTJOINCHAT') {
		// check the event - register this application or not
		if (!isset($appsConfig[$_REQUEST['auth']['application_token']])) {
			return false;
		}

		// send help message how to use chat-bot. For private chat and for group chat need send different instructions.
		$result = restCommand('imbot.message.add', [
			'DIALOG_ID' => $_REQUEST['data']['PARAMS']['DIALOG_ID'],
			'MESSAGE'   => 'Привет! Я AdviceBot, даю полезные советы на все случаи жизни.',
			"ATTACH"    => [
				['MESSAGE' => '[send=random]Случайный совет[/send]'],
				['MESSAGE' => '[send=дизайн]Совет дизайнеру[/send]'],
				['MESSAGE' => '[send=frontend]Совет верстальщику[/send]'],
				['MESSAGE' => '[send=censor]Цензурный совет[/send]'],
			],
		], $_REQUEST["auth"]);

		// write debug log
		// writeToLog($result, 'AdviceBot Event join chat');
	} // receive event "delete chat-bot"
	else {
		if ($_REQUEST['event'] == 'ONIMBOTDELETE') {
			// check the event - register this application or not
			if (!isset($appsConfig[$_REQUEST['auth']['application_token']])) {
				return false;
			}

			// unset application variables
			unset($appsConfig[$_REQUEST['auth']['application_token']]);

			// save params
			saveParams($appsConfig);

			// write debug log
			// writeToLog($_REQUEST['event']['DATA'], 'AdviceBot unregister');
		} // receive event "Application install"
		else {
			if ($_REQUEST['event'] == 'ONAPPINSTALL') {
				// handler for events
				$handlerBackUrl = ($_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . (in_array($_SERVER['SERVER_PORT'], [
						80,
						443
					]) ? '' : ':' . $_SERVER['SERVER_PORT']) . $_SERVER['SCRIPT_NAME'];

				// If your application supports different localizations
				// use $_REQUEST['data']['LANGUAGE_ID'] to load correct localization

				// register new bot
				$result = restCommand('imbot.register', [
					'CODE'                  => 'AdviceBot',
					// строковой идентификатор бота, уникальный в рамках вашего приложения (обяз.)
					'TYPE'                  => 'B',
					// Тип бота, B - бот, ответы  поступают сразу, H - человек, ответы поступаю с задержкой от 2х до 10 секунд
					'EVENT_MESSAGE_ADD'     => SITE_URL . '/advicebot.php',
					// Ссылка на обработчик события отправки сообщения боту (обяз.)
					'EVENT_WELCOME_MESSAGE' => SITE_URL . '/advicebot.php',
					// Ссылка на обработчик события открытия диалога с ботом или приглашения его в групповой чат (обяз.)
					'EVENT_BOT_DELETE'      => SITE_URL . '/advicebot.php',
					// Ссылка на обработчик события удаление бота со стороны клиента (обяз.)
					'PROPERTIES'            => [ // Личные данные чат-бота (обяз.)
					                             'NAME'              => 'AdviceBot',
					                             // Имя бота (обязательное одно из полей NAME или LAST_NAME)
					                             'LAST_NAME'         => '',
					                             // Фамилия бота (обязательное одно из полей NAME или LAST_NAME)
					                             'COLOR'             => 'LIME',
					                             // Цвет бота для мобильного приложения RED,  GREEN, MINT, LIGHT_BLUE, DARK_BLUE, PURPLE, AQUA, PINK, LIME, BROWN,  AZURE, KHAKI, SAND, MARENGO, GRAY, GRAPHITE
					                             'EMAIL'             => 'pafnuty10@gmail.com',
					                             // Емейл для связи
					                             'PERSONAL_BIRTHDAY' => '2016-03-18',
					                             // День рождения в формате YYYY-mm-dd
					                             'WORK_POSITION'     => 'Даю полезные советы',
					                             // Занимаемая должность, используется как описание бота
					                             'PERSONAL_WWW'      => 'http://pafnuty.name',
					                             // Ссылка на сайт
					                             'PERSONAL_GENDER'   => 'M',
					                             // Пол бота, допустимые значения M -  мужской, F - женский, пусто если не требуется указывать
					                             'PERSONAL_PHOTO'    => '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',
					                             // Аватар бота - base64
					],
				], $_REQUEST["auth"]);

				// save params
				$appsConfig[$_REQUEST['auth']['application_token']] = [
					'BOT_ID'      => $result['result'],
					'LANGUAGE_ID' => $_REQUEST['data']['LANGUAGE_ID'],
				];
				saveParams($appsConfig);

				// write debug log
				// writeToLog($result, 'AdviceBot register');
			}
		}
	}
}

/**
 * Save application configuration.
 *
 * @param $params
 *
 * @return bool
 */
function saveParams($params) {
	$config = "<?php\n";
	$config .= "\$appsConfig = " . var_export($params, true) . ";\n";
	$config .= "?>";

	$configFileName = '/config_' . trim(str_replace('.', '_', $_REQUEST['auth']['domain'])) . '.php';

	file_put_contents(__DIR__ . $configFileName, $config);

	return true;
}

/**
 * Send rest query to Bitrix24.
 *
 * @param       $method - Rest method, ex: methods
 * @param array $params - Method params, ex: array()
 * @param array $auth   - Authorize data, ex: array('domain' => 'https://test.bitrix24.com', 'access_token' =>
 *                      '7inpwszbuu8vnwr5jmabqa467rqur7u6')
 *
 * @return mixed
 */
function restCommand($method, array $params = [], array $auth = []) {
	$queryUrl  = 'https://' . $auth['domain'] . '/rest/' . $method;
	$queryData = http_build_query(array_merge($params, ['auth' => $auth['access_token']]));

	// writeToLog(array('URL' => $queryUrl, 'PARAMS' => array_merge($params, array("auth" => $auth["access_token"]))), 'AdviceBot send data');

	$curl = curl_init();

	curl_setopt_array($curl, [
		CURLOPT_POST           => 1,
		CURLOPT_HEADER         => 0,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_URL            => $queryUrl,
		CURLOPT_POSTFIELDS     => $queryData,
	]);

	$result = curl_exec($curl);
	curl_close($curl);

	$result = json_decode($result, 1);

	return $result;
}

/**
 * Write data to log file.
 *
 * @param mixed  $data
 * @param string $title
 *
 * @return bool
 */
function writeToLog($data, $title = '') {
	$log = "\n------------------------\n";
	$log .= date("Y.m.d G:i:s") . "\n";
	$log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
	$log .= print_r($data, 1);
	$log .= "\n------------------------\n";

	file_put_contents(__DIR__ . '/imbot.log', $log, FILE_APPEND);

	return true;
}

/**
 * Получаем массив с данными совета
 *
 * @date       19.03.2016
 * @author     Павел Белоусов
 *
 * @param      string $text строка, которую отправил юзер
 *
 * @return     array
 */
function getAdvice($text = '') {
	$arParams   = getChecked($text);
	$jsonAdvice = file_get_contents('http://fucking-great-advice.ru/api/' . $arParams['link']);

	$jsonAdvice = json_decode($jsonAdvice, true);

	$arAdvice = [
		'title'  => $arParams['title'],
		'advice' => strip_tags(html_entity_decode(str_replace('&#151;', '—', $jsonAdvice['text']))),
		'sound'  => ($jsonAdvice['sound'] != '') ? 'http://fucking-great-advice.ru/files/sounds/' . $jsonAdvice['sound'] : '',
	];

	return $arAdvice;
}

/**
 * Проверка вхождения слова отправляемого боту и выдача массива с результатом
 * для запроса к api сервиса
 *
 * @date       19.03.2016
 * @author     Павел Белоусов
 *
 * @param      string $phrase (description)
 *
 * @return array
 */
function getChecked($phrase = '') {
	$arWord = [
		'title' => 'случайный совет',
		'link'  => 'random',
	];
	$words  = [
		'random_censor' => [
			'title' => 'цензурный совет',
			'link'  => 'random/censored/',
		],
		'random'        => [
			'title' => 'случайный совет',
			'link'  => 'random',
		],
		'latest'        => [
			'title' => 'свежий совет',
			'link'  => 'latest',
		],
		'design'        => [
			'title' => 'совет дизайнеру',
			'link'  => 'random_by_tag/дизайнеру',
		],
		'coding'        => [
			'title' => 'совет кодеру',
			'link'  => 'random_by_tag/кодеру',
		],
		'frontend'      => [
			'title' => 'совет верстальщику',
			'link'  => 'random_by_tag/верстальщику',
		],
		'foto'          => [
			'title' => 'совет фотографу',
			'link'  => 'random_by_tag/фотографу',
		],
		'copy'          => [
			'title' => 'совет копирайтеру',
			'link'  => 'random_by_tag/копирайтеру',
		],
		'marketing'     => [
			'title' => 'совет маркетологу',
			'link'  => 'random_by_tag/маркетологу',
		],
		'seo'           => [
			'title' => 'совет сеошнику',
			'link'  => 'random_by_tag/сеошнику',
		],
		'driver'        => [
			'title' => 'совет водителю',
			'link'  => 'random_by_tag/водителю',
		],
		'music'         => [
			'title' => 'совет музыканту',
			'link'  => 'random_by_tag/музыканту',
		],
		'magic'         => [
			'title' => 'совет фокуснику',
			'link'  => 'random_by_tag/фокуснику',
		],
		'doctor'        => [
			'title' => 'совет врачу',
			'link'  => 'random_by_tag/врачу',
		],
		'education'     => [
			'title' => 'совет студенту',
			'link'  => 'random_by_tag/студенту',
		],
		'live'          => [
			'title' => 'совет за жизнь',
			'link'  => 'random_by_tag/за жизнь',
		],
		'man'           => [
			'title' => 'совет для него',
			'link'  => 'random_by_tag/для него',
		],
		'woman'         => [
			'title' => 'совет для неё',
			'link'  => 'random_by_tag/для неё',
		],
	];

	$keys = [
		'random_censor' => [
			'цензура',
			'censor',
		],
		'random'        => [
			'случайный',
			'рандом',
			'совет',
			'rand',
		],
		'latest'        => [
			'свежи',
			'последн',
			'ново',
			'latest',
		],
		'design'        => [
			'дизайн',
			'design',
		],
		'coding'        => [
			'кодер',
			'линукс',
			'программ',
			'кодин',
			'php',
			'coding',
		],
		'frontend'      => [
			'frontend',
			'вёрстка',
			'верстка',
			'верстальщик',
			'css',
			'js',
			'html',
		],
		'foto'          => [
			'фотограф',
		],
		'copy'          => [
			'копирайтер',
		],
		'marketing'     => [
			'маркетолог',
		],
		'seo'           => [
			'сеошник',
			'seo',
		],
		'driver'        => [
			'водител',
		],
		'music'         => [
			'музыкант',
		],
		'magic'         => [
			'фокусник',
		],
		'doctor'        => [
			'врач',
		],
		'education'     => [
			'студент',
		],
		'live'          => [
			'жизнь',
			'live',
		],
		'man'           => [
			'ему',
		],
		'woman'         => [
			'ей',
		],
	];

	$found = false;
	foreach ($keys as $key => $arWords) {
		if (!$found) {
			foreach ($arWords as $worsItem) {
				if (strpos($phrase, $worsItem) !== false) {
					$found  = true;
					$arWord = $words[$key];
					break;
				}
			}
		} else {
			break;
		}
	}

	return $arWord; // $words['key']
}
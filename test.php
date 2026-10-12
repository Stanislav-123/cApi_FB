<?php
/**
 * Проверка окружения и тестовая отправка события после выкладки на сервер
 * Открой в браузере: https://ваш-домен.ru/_apifb/test.php
 */
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/config.php';

$step = isset($_POST['send_test']) ? 'send' : 'check';
$errors = [];
$ok = [];

// --- Проверка окружения ---
$ok[] = 'PHP ' . PHP_VERSION;
$ok[] = 'config.php загружен, FB API: ' . FB_GRAPH_API_VERSION;

if (!extension_loaded('curl')) {
    $errors[] = 'Расширение PHP curl не установлено';
} else {
    $ok[] = 'cURL доступен';
}

if (!is_dir(LOGS_DIR)) {
    $errors[] = 'Папка логов не существует: ' . LOGS_DIR;
} elseif (!is_writable(LOGS_DIR)) {
    $errors[] = 'Папка логов не доступна для записи: ' . LOGS_DIR;
} else {
    $ok[] = 'Папка logs/ существует и доступна для записи';
}

$cwd = getcwd();
if (is_writable($cwd)) {
    $ok[] = 'Текущая директория доступна для записи (текстовые логи *_facebook_log.txt)';
} else {
    $errors[] = 'Текущая директория не доступна для записи';
}

// --- Тестовая отправка по нажатию кнопки ---
$sendResult = null;
if ($step === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pixel_id = trim((string) ($_POST['pixel_id'] ?? ''));
    $access_token = trim((string) ($_POST['access_token'] ?? ''));
    $use_test = !empty($_POST['use_test_code']);
    $test_code = trim((string) ($_POST['test_event_code'] ?? ''));

    if ($pixel_id === '' || $access_token === '') {
        $sendResult = ['ok' => false, 'error' => 'Укажите Pixel ID и Access Token'];
    } else {
        $payload = [
            'data' => [
                [
                    'event_name' => 'Lead',
                    'event_time' => time(),
                    'user_data' => [
                        'em' => hash('sha256', 'test@example.com'),
                        'external_id' => 'test_' . time(),
                        'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'TestScript',
                        'event_source_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''),
                        'action_source' => 'website',
                    ],
                ],
            ],
            'access_token' => $access_token,
        ];
        if ($use_test && $test_code !== '') {
            $payload['test_event_code'] = $test_code;
        }

        $url = sprintf(FB_EVENTS_ENDPOINT, $pixel_id);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $success = ($httpCode >= 200 && $httpCode < 300) && $curlErr === '';
        $sendResult = [
            'ok' => $success,
            'http_code' => $httpCode,
            'response' => $response,
            'curl_error' => $curlErr ?: null,
        ];

        if ($success) {
            apifb_log_entry($pixel_id, $payload['data'], $payload, $response, $httpCode);
            $sendResult['log_jsonl'] = 'Записано в logs/' . log_file_for_pixel($pixel_id) . '.jsonl';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>apifb — тест</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 640px; margin: 24px auto; padding: 0 16px; background: #1a1a1a; color: #e4e4e7; }
    h1 { font-size: 1.25rem; }
    ul { padding-left: 1.25rem; }
    .ok { color: #22c55e; }
    .err { color: #ef4444; }
    pre { background: #2d2d35; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 0.85rem; }
    label { display: block; margin-top: 10px; color: #a1a1aa; font-size: 0.9rem; }
    input[type="text"], input[type="password"] { width: 100%; padding: 8px 10px; margin-top: 4px; background: #2d2d35; border: 1px solid #444; border-radius: 4px; color: #fff; box-sizing: border-box; }
    button { margin-top: 14px; padding: 10px 18px; background: #3b82f6; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 0.95rem; }
    button:hover { background: #2563eb; }
    .box { background: #252528; border: 1px solid #333; border-radius: 8px; padding: 16px; margin: 16px 0; }
    .small { font-size: 0.85rem; color: #a1a1aa; margin-top: 8px; }
  </style>
</head>
<body>
  <h1>apifb — проверка после выкладки</h1>

  <div class="box">
    <strong>Окружение</strong>
    <ul class="ok">
      <?php foreach ($ok as $line): ?>
        <li><?php echo htmlspecialchars($line); ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if (!empty($errors)): ?>
      <ul class="err">
        <?php foreach ($errors as $e): ?>
          <li><?php echo htmlspecialchars($e); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="box">
    <strong>Тестовая отправка события в Facebook</strong>
    <p class="small">Укажите реальные Pixel ID и Access Token. С test_event_code событие попадёт в Events Manager → Test Events и не засоряет отчёты.</p>
    <form method="post">
      <input type="hidden" name="send_test" value="1">
      <label>Pixel ID</label>
      <input type="text" name="pixel_id" placeholder="123456789012345" value="<?php echo htmlspecialchars($_POST['pixel_id'] ?? ''); ?>">
      <label>Access Token</label>
      <input type="password" name="access_token" placeholder="EAAx...">
      <label><input type="checkbox" name="use_test_code" value="1" <?php echo !empty($_POST['use_test_code']) ? 'checked' : ''; ?>> Использовать test_event_code</label>
      <label>Test event code <span class="small">(из Events Manager → Test Events)</span></label>
      <input type="text" name="test_event_code" placeholder="TEST12345" value="<?php echo htmlspecialchars($_POST['test_event_code'] ?? ''); ?>">
      <button type="submit">Отправить тестовый Lead</button>
    </form>
  </div>

  <?php if ($sendResult !== null): ?>
  <div class="box">
    <strong>Результат отправки</strong>
    <?php if ($sendResult['ok']): ?>
      <p class="ok">HTTP <?php echo (int)$sendResult['http_code']; ?> — событие принято.</p>
      <?php if (!empty($sendResult['log_jsonl'])): ?>
        <p class="small"><?php echo htmlspecialchars($sendResult['log_jsonl']); ?></p>
      <?php endif; ?>
    <?php else: ?>
      <p class="err">Ошибка: HTTP <?php echo (int)($sendResult['http_code'] ?? 0); ?>
        <?php if (!empty($sendResult['curl_error'])) echo ' — ' . htmlspecialchars($sendResult['curl_error']); ?>
      </p>
    <?php endif; ?>
    <pre><?php echo htmlspecialchars(is_string($sendResult['response'] ?? '') ? $sendResult['response'] : json_encode($sendResult['response'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
  </div>
  <?php endif; ?>

  <div class="box">
    <strong>Как ещё проверить</strong>
    <ul class="small">
      <li><strong>Постбек (S2S):</strong> открой в браузере или вызови из трекера:<br>
        <code>.../lead.php?sub_id_11=PIXEL_ID&sub_id_12=TOKEN&sub_id_10=fb.1.123.abc&external_id=test123&name=Test&email=test@test.com&phone=79001234567&client_ip_address=127.0.0.1&client_user_agent=Mozilla/5.0</code><br>
        После вызова проверь <code>logs/PIXEL_ID.jsonl</code> и <code>lead_facebook_log.txt</code>.
      </li>
      <li><strong>Веб-интерфейс:</strong> открой <a href="index.php" style="color:#3b82f6;">index.php</a>, отправь событие или переотправь с пикселя.</li>
    </ul>
  </div>
</body>
</html>

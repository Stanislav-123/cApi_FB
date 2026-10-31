<?php
require_once __DIR__ . '/config.php';
require_auth(); // Security: HTTP Basic Auth
$apiBase = 'api';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>apifb — Facebook CAPI</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0f0f12;
      --surface: #18181c;
      --surface2: #1f1f24;
      --border: #2d2d35;
      --text: #e4e4e7;
      --text2: #a1a1aa;
      --accent: #3b82f6;
      --accent-hover: #2563eb;
      --success: #22c55e;
      --error: #ef4444;
      --radius: 10px;
      --radius-sm: 6px;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'Outfit', sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.5;
      min-height: 100vh;
    }
    .wrap {
      max-width: 1100px;
      margin: 0 auto;
      padding: 24px 20px;
    }
    h1 {
      font-size: 1.75rem;
      font-weight: 700;
      margin: 0 0 8px;
      letter-spacing: -0.02em;
    }
    .sub {
      color: var(--text2);
      font-size: 0.9rem;
      margin-bottom: 28px;
    }
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px 24px;
      margin-bottom: 20px;
    }
    .card h2 {
      font-size: 1.1rem;
      font-weight: 600;
      margin: 0 0 16px;
      color: var(--text);
    }
    label {
      display: block;
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--text2);
      margin-bottom: 6px;
    }
    input[type="text"],
    input[type="password"],
    textarea,
    select {
      width: 100%;
      padding: 10px 12px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.9rem;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      color: var(--text);
      margin-bottom: 12px;
    }
    input:focus, textarea:focus, select:focus {
      outline: none;
      border-color: var(--accent);
    }
    textarea {
      min-height: 140px;
      resize: vertical;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 18px;
      font-family: inherit;
      font-size: 0.9rem;
      font-weight: 500;
      border: none;
      border-radius: var(--radius-sm);
      cursor: pointer;
      transition: background 0.15s;
    }
    .btn-primary {
      background: var(--accent);
      color: #fff;
    }
    .btn-primary:hover { background: var(--accent-hover); }
    .btn-secondary {
      background: var(--surface2);
      color: var(--text);
      border: 1px solid var(--border);
    }
    .btn-secondary:hover { background: var(--border); }
    .btn-sm { padding: 6px 12px; font-size: 0.85rem; }
    .row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 12px; }
    .row .field { flex: 1; min-width: 180px; }
    .row .field label { margin-bottom: 6px; }
    .row input, .row select { margin-bottom: 0; }
    .pixel-select-row {
      display: flex;
      gap: 12px;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }
    .pixel-select-row select { width: auto; min-width: 220px; margin-bottom: 0; }
    .events-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }
    .events-table th,
    .events-table td {
      text-align: left;
      padding: 10px 12px;
      border-bottom: 1px solid var(--border);
    }
    .events-table th {
      color: var(--text2);
      font-weight: 500;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .events-table tr:hover td { background: var(--surface2); }
    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: 500;
    }
    .badge-ok { background: rgba(34, 197, 94, 0.2); color: var(--success); }
    .badge-err { background: rgba(239, 68, 68, 0.2); color: var(--error); }
    .msg {
      padding: 12px 14px;
      border-radius: var(--radius-sm);
      margin-top: 12px;
      font-size: 0.9rem;
    }
    .msg.success { background: rgba(34, 197, 94, 0.15); color: var(--success); }
    .msg.error { background: rgba(239, 68, 68, 0.15); color: var(--error); }
    .msg pre { margin: 0; font-size: 0.85rem; overflow-x: auto; }
    .empty { color: var(--text2); text-align: center; padding: 32px 16px; }
    .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 700px) { .grid2 { grid-template-columns: 1fr; } }
    .file-upload {
      border: 2px dashed var(--border);
      border-radius: var(--radius-sm);
      padding: 20px;
      text-align: center;
      color: var(--text2);
      font-size: 0.9rem;
      margin-bottom: 12px;
    }
    .file-upload.has-file { border-color: var(--accent); color: var(--accent); }
    .api-version { font-size: 0.8rem; color: var(--text2); margin-top: 24px; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>apifb</h1>
    <p class="sub">Отправка и просмотр событий Facebook Conversions API. Логи по пикселям.</p>

    <div class="card">
      <h2>Выбор пикселя</h2>
      <div class="pixel-select-row">
        <div class="field">
          <label for="pixelSelect">Пиксель</label>
          <select id="pixelSelect">
            <option value="">— выберите пиксель —</option>
          </select>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" id="btnRefreshPixels">Обновить список</button>
        <button type="button" class="btn btn-secondary btn-sm" id="btnDownload" disabled>Скачать данные пикселя</button>
      </div>
    </div>

    <div class="card">
      <h2>События по выбранному пикселю</h2>
      <div id="eventsContainer">
        <p class="empty">Выберите пиксель, чтобы увидеть отправленные события.</p>
      </div>
    </div>

    <div class="grid2">
      <div class="card">
        <h2>Отправить события</h2>
        <div class="row">
          <div class="field">
            <label>Pixel ID</label>
            <input type="text" id="sendPixelId" placeholder="123456789">
          </div>
          <div class="field">
            <label>Access Token</label>
            <input type="password" id="sendToken" placeholder="EAAx...">
          </div>
        </div>
        <label>События (JSON, массив в data)</label>
        <textarea id="sendData" placeholder='{"data":[{"event_name":"Lead","event_time":1730000000,"user_data":{...},"event_source_url":"https://...","action_source":"website"}]}'></textarea>
        <label style="margin-top:8px;">
          <input type="checkbox" id="sendTestCode"> Тест (test_event_code)
        </label>
        <input type="text" id="sendTestCodeVal" placeholder="TEST12345" style="margin-top:4px; margin-bottom:12px;" disabled>
        <button type="button" class="btn btn-primary" id="btnSend">Отправить</button>
        <div id="sendResult"></div>
      </div>

      <div class="card">
        <h2>Переотправить на другой пиксель</h2>
        <p style="color: var(--text2); font-size: 0.9rem; margin-bottom: 14px;">Либо выберите пиксель из логов, либо загрузите файл с событиями.</p>
        <div class="row">
          <div class="field">
            <label>Пиксель-источник (из логов)</label>
            <select id="resendSourcePixel">
              <option value="">— выберите пиксель —</option>
            </select>
          </div>
        </div>
        <label>Или загрузите файл (JSON / JSONL)</label>
        <div class="file-upload" id="resendDrop">
          <span id="resendDropText">Перетащите файл сюда или <label for="resendFile" style="cursor:pointer; color: var(--accent);">выберите файл</label></span>
          <input type="file" id="resendFile" accept=".json,.jsonl" style="display:none;">
        </div>
        <div class="row">
          <div class="field">
            <label>Новый Pixel ID</label>
            <input type="text" id="resendPixelId" placeholder="987654321">
          </div>
          <div class="field">
            <label>Новый Access Token</label>
            <input type="password" id="resendToken" placeholder="EAAx...">
          </div>
        </div>
        <button type="button" class="btn btn-primary" id="btnResend">Переотправить</button>
        <div id="resendResult"></div>
      </div>
    </div>

    <p class="api-version">Facebook Graph API: <?php echo htmlspecialchars(FB_GRAPH_API_VERSION); ?></p>
  </div>

  <script>
(function() {
  const API = '<?php echo $apiBase; ?>';

  function show(el, html, isError) {
    el.innerHTML = '<div class="msg ' + (isError ? 'error' : 'success') + '">' + html + '</div>';
  }

  async function loadPixels() {
    const r = await fetch(API + '/pixels.php');
    const j = await r.json();
    const list = j.pixels || [];
    const opt = '<option value="">— выберите пиксель —</option>' + list.map(function(p) {
      return '<option value="' + escapeHtml(p.id) + '">Pixel ' + escapeHtml(p.id) + '</option>';
    }).join('');
    document.getElementById('pixelSelect').innerHTML = opt;
    document.getElementById('resendSourcePixel').innerHTML = opt;
  }

  async function loadEvents(pixelId) {
    const container = document.getElementById('eventsContainer');
    if (!pixelId) {
      container.innerHTML = '<p class="empty">Выберите пиксель, чтобы увидеть отправленные события.</p>';
      return;
    }
    const r = await fetch(API + '/events.php?pixel_id=' + encodeURIComponent(pixelId));
    const j = await r.json();
    const entries = j.entries || [];
    if (entries.length === 0) {
      container.innerHTML = '<p class="empty">По этому пикселю записей пока нет.</p>';
      return;
    }
    let html = '<table class="events-table"><thead><tr><th>Дата</th><th>События</th><th>HTTP</th><th>Ответ</th></tr></thead><tbody>';
    entries.forEach(function(entry) {
      const events = (entry.events || []).map(function(e) { return e.event_name || '?'; }).join(', ');
      const code = entry.http_code || '-';
      const ok = code >= 200 && code < 300;
      const resp = (entry.response || '').substring(0, 80) + (entry.response && entry.response.length > 80 ? '…' : '');
      html += '<tr><td>' + (entry.date || '-') + '</td><td>' + (events || '-') + '</td><td><span class="badge ' + (ok ? 'badge-ok' : 'badge-err') + '">' + code + '</span></td><td><code style="font-size:0.8rem;">' + escapeHtml(resp) + '</code></td></tr>';
    });
    html += '</tbody></table>';
    container.innerHTML = html;
  }

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  document.getElementById('pixelSelect').addEventListener('change', function() {
    const id = this.value;
    document.getElementById('btnDownload').disabled = !id;
    loadEvents(id);
  });

  document.getElementById('btnRefreshPixels').addEventListener('click', function() {
    loadPixels().then(function() { loadEvents(document.getElementById('pixelSelect').value); });
  });

  document.getElementById('btnDownload').addEventListener('click', function() {
    const id = document.getElementById('pixelSelect').value;
    if (!id) return;
    window.location.href = API + '/download.php?pixel_id=' + encodeURIComponent(id);
  });

  document.getElementById('btnSend').addEventListener('click', async function() {
    const pixelId = document.getElementById('sendPixelId').value.trim();
    const token = document.getElementById('sendToken').value.trim();
    let dataStr = document.getElementById('sendData').value.trim();
    const resultEl = document.getElementById('sendResult');
    if (!pixelId || !token) {
      show(resultEl, 'Укажите Pixel ID и Access Token.', true);
      return;
    }
    let data = null;
    try {
      data = JSON.parse(dataStr);
    } catch (e) {
      show(resultEl, 'Неверный JSON: ' + e.message, true);
      return;
    }
    const events = data && data.data ? data.data : (Array.isArray(data) ? data : null);
    if (!events || !events.length) {
      show(resultEl, 'В данных должен быть массив событий (data или корневой массив).', true);
      return;
    }
    const payload = { pixel_id: pixelId, access_token: token, data: events };
    if (document.getElementById('sendTestCode').checked) {
      const code = document.getElementById('sendTestCodeVal').value.trim();
      if (code) payload.test_event_code = code;
    }
    try {
      const r = await fetch(API + '/send.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const j = await r.json();
      if (j.ok) {
        show(resultEl, 'Отправлено. HTTP ' + j.http_code + '<pre>' + escapeHtml(j.response || '') + '</pre>');
        loadPixels();
        const sel = document.getElementById('pixelSelect');
        if (sel.value === pixelId) loadEvents(pixelId);
        else loadEvents(sel.value);
      } else {
        show(resultEl, 'Ошибка: ' + (j.error || j.response || 'HTTP ' + j.http_code) + (j.curl_error ? ' (' + j.curl_error + ')' : ''), true);
      }
    } catch (e) {
      show(resultEl, 'Ошибка запроса: ' + e.message, true);
    }
  });

  document.getElementById('sendTestCode').addEventListener('change', function() {
    document.getElementById('sendTestCodeVal').disabled = !this.checked;
  });

  let resendFileName = '';
  document.getElementById('resendFile').addEventListener('change', function() {
    const f = this.files[0];
    resendFileName = f ? f.name : '';
    document.getElementById('resendDrop').classList.toggle('has-file', !!f);
    document.getElementById('resendDropText').innerHTML = f ? ('Файл: ' + escapeHtml(f.name)) : 'Перетащите файл сюда или <label for="resendFile" style="cursor:pointer; color: var(--accent);">выберите файл</label>';
  });
  document.getElementById('resendDrop').addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('has-file'); });
  document.getElementById('resendDrop').addEventListener('dragleave', function(e) { e.preventDefault(); if (!resendFileName) this.classList.remove('has-file'); });
  document.getElementById('resendDrop').addEventListener('drop', function(e) {
    e.preventDefault();
    const f = e.dataTransfer.files[0];
    if (f) {
      document.getElementById('resendFile').files = e.dataTransfer.files;
      resendFileName = f.name;
      this.classList.add('has-file');
      document.getElementById('resendDropText').textContent = 'Файл: ' + f.name;
    }
  });

  document.getElementById('btnResend').addEventListener('click', async function() {
    const sourceId = document.getElementById('resendSourcePixel').value.trim();
    const newPixelId = document.getElementById('resendPixelId').value.trim();
    const token = document.getElementById('resendToken').value.trim();
    const fileInput = document.getElementById('resendFile');
    const resultEl = document.getElementById('resendResult');
    if (!newPixelId || !token) {
      show(resultEl, 'Укажите новый Pixel ID и Access Token.', true);
      return;
    }
    const useFile = fileInput.files.length > 0;
    if (!useFile && !sourceId) {
      show(resultEl, 'Выберите пиксель-источник или загрузите файл.', true);
      return;
    }
    const fd = new FormData();
    fd.append('new_pixel_id', newPixelId);
    fd.append('new_access_token', token);
    if (useFile) {
      fd.append('file', fileInput.files[0]);
    } else {
      fd.append('source_pixel_id', sourceId);
    }
    try {
      const r = await fetch(API + '/resend.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        show(resultEl, 'Переотправлено: ' + (j.events_sent || 0) + ' событий. HTTP ' + j.http_code + '<pre>' + escapeHtml(j.response || '') + '</pre>');
        loadPixels();
        const sel = document.getElementById('pixelSelect');
        if (sel.value === newPixelId) loadEvents(newPixelId);
      } else {
        show(resultEl, 'Ошибка: ' + (j.error || j.response || 'HTTP ' + j.http_code), true);
      }
    } catch (e) {
      show(resultEl, 'Ошибка запроса: ' + e.message, true);
    }
  });

  loadPixels();
})();
  </script>
</body>
</html>

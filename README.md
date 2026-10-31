# apifb — Facebook Conversions API (CAPI)

Решение для отправки серверных событий в Facebook Conversions API через постбеки из трекеров (Keitaro, Binom) и веб-интерфейс.

## 🎯 Что это?

**apifb** — это PHP-скрипт для интеграции трафик-арбитража с Facebook Conversions API. Позволяет отправлять события конверсий (лиды, регистрации, продажи, ребиллы) напрямую с сервера, минуя браузер, что критично для iOS14+ и повышает качество данных в Facebook Ads Manager.

## ✨ Возможности

- ✅ **Постбеки S2S** — прием событий из Keitaro, Binom, других трекеров через GET-параметры
- ✅ **Веб-интерфейс** — отправка и просмотр событий, переотправка на другой пиксель
- ✅ **Event Deduplication** — автоматическая генерация `event_id` для предотвращения дублей
- ✅ **Event Time Validation** — проверка timestamp (отклонение событий старше 7 дней или из будущего)
- ✅ **Retry с Exponential Backoff** — автоматические повторы при сбоях сети (3 попытки)
- ✅ **Хеширование данных** — SHA256 для email, phone, имен, geo (соответствие требованиям Meta)
- ✅ **FBC и FBP** — поддержка fbclid (fbc) и browser pixel cookie (fbp)
- ✅ **Multi-currency** — поддержка любых валют (USD, EUR, RUB, etc.)
- ✅ **Логирование** — JSONL-логи по каждому пикселю + текстовые логи для совместимости
- ✅ **Тестовые события** — поддержка `test_event_code` для Events Manager → Test Events
- ✅ **SSL Security** — включена проверка SSL-сертификатов (защита от MITM)
- ✅ **HTTP Basic Auth** — опциональная защита веб-интерфейса паролем
- ✅ **IP Whitelist** — опциональное ограничение доступа к постбекам по IP (с поддержкой CIDR)
- ✅ **.env конфигурация** — токены и настройки в переменных окружения

## 📦 Установка

### Требования
- PHP 7.4+ (рекомендуется 8.0+)
- php-curl, php-json, php-mbstring
- Apache/Nginx с поддержкой PHP

### Быстрый старт

1. **Клонируйте проект на сервер:**
```bash
git clone <repo_url> /var/www/apifb
cd /var/www/apifb
```

2. **Скопируйте .env.example в .env и настройте:**
```bash
cp .env.example .env
nano .env
```

**Минимальная конфигурация .env:**
```env
# Версия Facebook Graph API (v22.0 или новее)
FB_GRAPH_API_VERSION=v26.0

# (Опционально) HTTP Basic Auth для index.php
AUTH_USER=admin
AUTH_PASS=your_secure_password

# (Опционально) IP Whitelist для постбеков (через запятую, поддерживает CIDR)
ALLOWED_IPS=192.168.1.100,203.0.113.0/24

# (Опционально) Default credentials если не передаются в URL
DEFAULT_PIXEL_ID=
DEFAULT_ACCESS_TOKEN=

# Retry конфигурация
CURL_RETRY_COUNT=3
CURL_RETRY_DELAY=2

# Валюта по умолчанию
DEFAULT_CURRENCY=USD
```

3. **Настройте права:**
```bash
chmod 755 .
chmod 755 api/
mkdir -p logs
chmod 777 logs
```

4. **Настройте веб-сервер:**

**Apache** (`.htaccess` уже в корне):
```apache
<Directory /var/www/apifb>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

**Nginx** (nginx.conf):
```nginx
location /apifb/ {
    alias /var/www/apifb/;
    index index.php;
    
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
    }
}
```

5. **Проверьте окружение:**

Откройте в браузере: `https://ваш-домен.ru/apifb/test.php`

Скрипт проверит PHP, curl, доступность папки логов. Отправьте тестовый Lead с реальными Pixel ID и Access Token.

## 🚀 Использование

### 1. Постбеки из трекеров (S2S)

**Keitaro / Binom → Facebook Lead:**
```
https://ваш-домен.ru/apifb/lead.php?sub_id_11=PIXEL_ID&sub_id_12=ACCESS_TOKEN&sub_id_10={fbclid}&sub_id_14={fbp}&external_id={clickid}&email={email}&phone={phone}&name={name}&client_ip_address={ip}&client_user_agent={user_agent}&date={timestamp}
```

**Параметры:**
- `sub_id_11` — Pixel ID (обязательно)
- `sub_id_12` — Access Token пикселя (обязательно)
- `sub_id_10` — fbclid из URL (fbc), макрос `{fbclid}` или `{sub_id_10}`
- `sub_id_14` — _fbp cookie из браузера (если доступен)
- `sub_id_13` — event_source_url (URL лендинга), по умолчанию `https://facebook.com/`
- `external_id` — click_id / subid трекера (обязательно для дедупликации)
- `date` — Unix timestamp события (если не указан, используется текущее время)
- `email`, `phone`, `name`, `firstname`, `lastname` — персональные данные
- `ct`, `st`, `country`, `dob` — geo и дата рождения (опционально)
- `client_ip_address`, `client_user_agent` — данные пользователя
- `revenue` — сумма покупки (для sale.php, rebill.php), по умолчанию 1.0
- `currency` — валюта (для sale.php, rebill.php), по умолчанию USD (или из .env)

**Доступные эндпоинты:**
- `lead.php` — событие Lead
- `reg.php` — CompleteRegistration
- `sale.php` — Purchase (с revenue)
- `rebill.php` — Purchase (ребилл с revenue)

### 2. Веб-интерфейс

Откройте `https://ваш-домен.ru/apifb/index.php`

- **Просмотр событий** — выберите пиксель, увидите все отправленные события
- **Отправка событий** — вручную отправьте JSON с событиями
- **Переотправка** — возьмите события из лога одного пикселя и отправьте на другой (или загрузите JSON/JSONL файл)
- **Скачать данные** — экспорт всех событий пикселя в JSON

### 3. API (для кастомных интеграций)

**Отправить событие:**
```bash
curl -X POST https://ваш-домен.ru/apifb/api/send.php \
  -H "Content-Type: application/json" \
  -d '{
    "pixel_id": "123456789",
    "access_token": "EAAx...",
    "data": [
      {
        "event_name": "Lead",
        "event_time": 1730000000,
        "user_data": {
          "em": "7d8c3f...",
          "ph": "a665a4...",
          "external_id": "click123",
          "client_ip_address": "1.2.3.4",
          "client_user_agent": "Mozilla/5.0...",
          "fbc": "fb.1.1730000000.AbCdEf",
          "fbp": "fb.1.1730000000.123456789"
        },
        "event_source_url": "https://example.com/offer",
        "action_source": "website"
      }
    ]
  }'
```

**С test_event_code:**
```json
{
  "pixel_id": "123456789",
  "access_token": "EAAx...",
  "test_event_code": "TEST12345",
  "data": [...]
}
```

**Список пикселей:**
```bash
curl https://ваш-домен.ru/apifb/api/pixels.php
```

**События пикселя:**
```bash
curl https://ваш-домен.ru/apifb/api/events.php?pixel_id=123456789
```

## 🔐 Безопасность

### HTTP Basic Auth (веб-интерфейс)
Защитите `index.php` паролем в `.env`:
```env
AUTH_USER=admin
AUTH_PASS=SecurePassword123
```

Оставьте пустым для отключения. После включения браузер запросит логин/пароль при открытии веб-интерфейса.

### IP Whitelist (постбеки)
Ограничьте доступ к `lead.php`, `sale.php`, `reg.php`, `rebill.php` по IP трекера в `.env`:
```env
ALLOWED_IPS=192.168.1.100,203.0.113.0/24,10.0.0.0/8
```

Поддерживает:
- Одиночные IP: `192.168.1.100`
- CIDR-нотация: `192.168.1.0/24`, `10.0.0.0/8`
- Несколько IP через запятую

Оставьте пустым для отключения проверки.

### Retry с Exponential Backoff
При сбое сети автоматически повторяет запрос (по умолчанию 3 попытки с задержкой 2, 4, 8 сек):
```env
CURL_RETRY_COUNT=3
CURL_RETRY_DELAY=2
```

Ретраится только при:
- Сетевых ошибках (connection timeout, DNS failure)
- HTTP 5xx (server error)

**Не ретраится** при HTTP 4xx (клиентская ошибка: неверный токен, невалидные данные).

### Данные пользователей
Все персональные данные (email, phone, имена, geo) хешируются SHA256 **перед** отправкой в Facebook. Facebook сам не получает raw-данные, только хеши.

### Access Token
- **НЕ коммитьте** токены в git — используйте `.env` (уже в .gitignore)
- В логах токены заменяются на `[REDACTED]`
- Можно задать дефолтный токен в `.env` (`DEFAULT_ACCESS_TOKEN`), но лучше передавать в URL

### SSL
- По умолчанию включен `CURLOPT_SSL_VERIFYPEER = true` (проверка сертификатов)
- **Не отключайте** SSL verification в продакшене

### Event Time Validation
Facebook отклоняет события:
- Старше **7 дней** — автоматически заменяются на текущее время
- Из **будущего** (+5 минут на clock skew) — заменяются на текущее время

Поддерживаемые форматы `date` параметра:
- Unix timestamp: `1730000000`
- ISO 8601: `2026-10-31T12:00:00+00:00`
- RFC 2822: `31 Oct 2026 12:00:00 GMT`
- Любой формат, понятный PHP `strtotime()`

### Логи
- Логи пикселей: `logs/{pixel_id}.jsonl` — содержат HTTP-коды, ответы FB, но НЕ содержат токены
- Текстовые логи: `{event}_facebook_log.txt` — для обратной совместимости
- **Добавьте** `logs/` в `.gitignore` (уже сделано)

## 📋 Формат данных

### Event ID (дедупликация)
Facebook рекомендует передавать `event_id` для автоматической дедупликации (например, если одно событие отправлено и из браузера, и с сервера).

`event_id` генерируется автоматически: `SHA256(event_name + external_id + timestamp + uniqid())`

### User Data (хеширование)
- **email** → lowercase, trim, SHA256
- **phone** → удалить пробелы/дефисы, добавить `+` если нет, SHA256
- **fn/ln** (имена) → lowercase, SHA256
- **ct/st/country** (geo) → lowercase, SHA256
- **db** (date of birth) → формат YYYYMMDD, SHA256

### FBC и FBP
- **fbc** (Facebook Click ID) — формат `fb.1.{timestamp}.{fbclid}`, передается в `user_data.fbc`
- **fbp** (Facebook Browser Pixel) — cookie `_fbp` из браузера, передается в `user_data.fbp`

Если оба параметра доступны, Facebook сможет лучше матчить события.

## 🔧 Конфигурация

### API Version
По умолчанию используется **v22.0** (минимальная рекомендуемая). Актуальная версия на октябрь 2026 — **v26.0**.

Проверьте актуальную версию: https://developers.facebook.com/docs/graph-api/changelog/

Изменить версию в `.env`:
```env
FB_GRAPH_API_VERSION=v26.0
```

### Multi-currency Support
По умолчанию USD. Изменить в `.env`:
```env
DEFAULT_CURRENCY=EUR
```

Или передать в постбеке:
```
sale.php?...&currency=RUB&revenue=1000
```

Поддерживаются все трёхбуквенные коды валют (ISO 4217): USD, EUR, RUB, GBP, etc.

### Retry Configuration
Настройте количество попыток и задержку:
```env
CURL_RETRY_COUNT=5        # Количество попыток (по умолчанию 3)
CURL_RETRY_DELAY=1        # Начальная задержка в секундах (по умолчанию 2)
```

Задержка удваивается с каждой попыткой (exponential backoff): 1s, 2s, 4s, 8s, 16s...

### Default Credentials (опционально)
Если не хотите передавать pixel_id и token в URL каждый раз:
```env
DEFAULT_PIXEL_ID=123456789
DEFAULT_ACCESS_TOKEN=EAAx...
```

⚠️ **Внимание:** Если указано в URL (`sub_id_11`, `sub_id_12`), приоритет у URL.

### Логи
Размер текстовых логов ограничен 50 МБ (автоматическая ротация). JSONL-логи не ротируются (управляйте вручную или через cron).

## 📊 Логи и отладка

### Структура лога (JSONL)
```json
{
  "date": "2026-10-31T22:00:00+00:00",
  "events": [
    {
      "event_name": "Lead",
      "event_time": 1730413200,
      "event_id": "a3f7b8..."
    }
  ],
  "request": {
    "data": [...],
    "access_token": "[REDACTED]"
  },
  "response": "{\"events_received\":1,\"messages\":[],\"fbtrace_id\":\"...\"}",
  "http_code": 200
}
```

### HTTP коды
- **200** — успех
- **400** — ошибка в данных (проверьте структуру события)
- **401** — неверный Access Token
- **429** — rate limit (слишком много запросов)
- **500** — ошибка на стороне Facebook

### Test Events
Включите `test_event_code` (из Events Manager → Test Events) — события будут видны в тестовом разделе и не попадут в отчеты.

## 🐛 Известные проблемы

1. **iOS User Agent генерация** (`ios14_bypass.php`) — устаревшие версии iOS (13.x). Обновите до iOS 14-17.
2. **Rate limiting** — нет встроенной защиты от флуда. Используйте Nginx `limit_req` или аналогичное решение.
3. **HTTP Basic Auth** — использует plain text сравнение (не bcrypt). Для высокой безопасности используйте Nginx auth или OAuth.

## 🚦 Roadmap

- [ ] Поддержка батч-отправки (до 1000 событий за запрос)
- [ ] Dashboard со статистикой (события/день, rate успеха)
- [ ] Offline events support
- [ ] Product catalog events (AddToCart, InitiateCheckout)
- [ ] Docker контейнер для быстрого деплоя
- [ ] Webhook для offline conversions
- [ ] Telegram/Slack алерты

## 📝 Лицензия

Проект распространяется без лицензии. Используйте на свой страх и риск.

## 🤝 Контакты

По вопросам и предложениям — создавайте issues в репозитории.

---

**Версия:** 2.0.0 (октябрь 2026)  
**Совместимость:** Facebook Graph API v22.0+ (рекомендуется v26.0)  
**Changelog:** См. PR #1 для полного списка изменений

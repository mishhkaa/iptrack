# Налаштування на сервері (checkipweb.top)

## Оновлення на проді (deploy з GitHub)

Якщо код уже клонований з `https://github.com/mishhkaa/iptrack.git`:

```bash
cd /home/administrator/web/checkipweb.top/public_html
# або той шлях, де лежить репозиторій

git fetch origin
git pull origin main
composer install --no-dev
```

Якщо репозиторій ще не на сервері — клонуй і встанови залежності:

```bash
cd /home/administrator/web/checkipweb.top
git clone https://github.com/mishhkaa/iptrack.git public_html
cd public_html
composer install --no-dev
```

Після `git pull` переконайся, що права на запис у папках проектів залишились (див. розділ 2 нижче).

**Таблички (Excel і CSV):** у репозиторії їх немає (у `.gitignore`: `*.csv`, `*.xlsx`). При `git pull` вони ніколи не змінюються — залишаються ті, що на сервері.

### Якщо pull показує: "untracked working tree files would be overwritten by merge"

На сервері папки `cdcamp/` або `maisonellyse/` були створені вручну (не з git), тому git не перезаписує їх. Зроби так (**збережи дані з clicks.csv перед видаленням**):

```bash
cd /home/administrator/web/checkipweb.top/public_html

# 1) Зберегти логи (clicks.csv), якщо вони є
cp cdcamp/clicks.csv /tmp/cdcamp_clicks.csv.bak 2>/dev/null || true
cp maisonellyse/clicks.csv /tmp/maisonellyse_clicks.csv.bak 2>/dev/null || true

# 2) Видалити конфліктні папки, щоб git міг підтягнути їх з репо
rm -rf cdcamp maisonellyse

# 3) Підтягнути код
git pull origin main

# 4) Відновити збережені логи (щоб не втратити кліки/візити)
cp /tmp/cdcamp_clicks.csv.bak cdcamp/clicks.csv 2>/dev/null || true
cp /tmp/maisonellyse_clicks.csv.bak maisonellyse/clicks.csv 2>/dev/null || true

# 5) Залежності та права
composer install --no-dev
sudo chown -R www-data:www-data cdcamp fp-models maisonellyse
# або: sudo chmod -R g+w cdcamp fp-models maisonellyse
```

Після цього оновлення мають застосовуватись без помилки.

### Адмін-дашборд (проєкти під паролем)

- **URL:** `https://checkipweb.top/admin/`
- При першому відкритті — форма **«Встановити пароль»** (мінімум 6 символів). Пароль зберігається в SQLite (`admin/data/iptrack.db`), MySQL не потрібен.
- Далі вхід за цим паролем. У дашборді:
  - **Додати проєкт** — назва + slug (латиниця, цифри, дефіс); створюється папка з `log.php`, `download.php`, `google-ads/tracker.js`.
  - Для кожного проєкту показується **скрипт для вставки** на сайт (кнопка «Копіювати»).
  - **Імпортувати з диска** — додає в список існуючі папки з `log.php` (наприклад cdcamp, fp-models, maisonellyse), якщо їх ще немає в базі.
  - Кнопки «↓ Excel» та «Видалити» для кожного проєкту.

Переконайся, що PHP може писати в `admin/data/` (для SQLite):

```bash
sudo chown -R www-data:www-data /home/administrator/web/checkipweb.top/public_html/admin/data
# або під користувачем панелі:
# sudo chown -R administrator:www-data .../admin/data
```

### Моніторинг сайтів (відстеження) + Telegram

- У дашборді розділ **«Відстеження проектів»**: додай посилання (назва + URL). Скрипт перевіряє кожні 60 хв: чи сайт доступний, чи SSL дійсний. При збої — сповіщення в Telegram.
- **Telegram:** у розділі вкажи Bot Token (від @BotFather) та Chat ID. При падінні сайту або помилці SSL бот надсилає повідомлення.
- **Cron (кожні 60 хв):** додай у crontab (`crontab -e`):

```bash
0 * * * * cd /home/administrator/web/checkipweb.top/public_html/admin && php check_monitored.php
```

(замість шляху підстав свій `public_html/admin`). Перевірка працює і по кнопці «Перевірити зараз» в адмінці.

---

Якщо трекер дає **500** або **"No data"** при скачуванні — виконай кроки нижче на сервері.

## 1. Перевірка, що PHP бачить скрипти (помилка "Primary script unknown")

Переконайся, що домен **checkipweb.top** вказує на папку, де лежать проекти (`cdcamp`, `fp-models`, `maisonellyse`).

```bash
# Де лежить сайт
ls -la /home/administrator/web/checkipweb.top/public_html/cdcamp/log.php
```

У конфігурації Apache (vhost) для checkipweb.top має бути:

- **DocumentRoot** = `/home/administrator/web/checkipweb.top/public_html`  
  (або той шлях, де є папки `cdcamp/`, `fp-models/`, `maisonellyse/`).

Якщо використовуєш панель (ISPmanager, Vestacp тощо) — у налаштуваннях сайту вкажи цей же каталог як корінь домену.

Після змін конфігу Apache перезапусти його:

```bash
sudo systemctl reload apache2
```

## 2. Права на запис (щоб створювався clicks.csv)

PHP повинен мати право створювати файл `clicks.csv` у кожній папці проекту. Дозволь запис користувачу, під яким крутиться PHP (часто `www-data`):

```bash
# Дізнатися, хто виконує PHP (часто www-data)
ps aux | grep php-fpm
# або
ps aux | grep apache2
```

Потім вистави права (заміни `www-data` на користувача PHP, якщо інший):

```bash
cd /home/administrator/web/checkipweb.top/public_html

sudo chown -R www-data:www-data cdcamp fp-models maisonellyse
# або, якщо сайт крутиться під administrator:
# sudo chown -R administrator:www-data cdcamp fp-models maisonellyse
```

Якщо хочеш лишити власника root — дай групі право запису:

```bash
sudo chgrp -R www-data cdcamp fp-models maisonellyse
sudo chmod -R g+w cdcamp fp-models maisonellyse
```

## 3. Перевірка після налаштування

1. Відкрий у браузері: `https://checkipweb.top/cdcamp/log.php`  
   Очікується порожня відповідь або "ok" (GET без POST — нормально).
2. Зроби клік на сторінці з підключеним трекером (або просто відкрий сторінку — записаться візит).
3. Перевір, що з’явився файл:
   ```bash
   ls -la /home/administrator/web/checkipweb.top/public_html/cdcamp/clicks.csv
   ```
4. Відкрий: `https://checkipweb.top/cdcamp/download.php` — має завантажитися Excel з двома аркушами (Clicks та Visits).

## 4. Логи помилок

Якщо щось не працює, подивись логи:

```bash
# Apache
sudo tail -50 /var/log/apache2/error.log

# PHP-FPM (шлях може відрізнятися)
sudo tail -50 /var/log/php*-fpm.log
```

## 5. PHP

Потрібен **PHP 7.0+** (у скриптах використовується `??`). Перевірка:

```bash
php -v
```

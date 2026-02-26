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

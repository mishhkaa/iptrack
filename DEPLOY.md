# Налаштування на сервері (checkipweb.top)

Якщо трекер дає **500** або **"No data"** при скачуванні — виконай кроки нижче на сервері.

## 1. Перевірка, що PHP бачить скрипти (помилка "Primary script unknown")

Переконайся, що домен **checkipweb.top** вказує на папку, де лежать проекти (`skn`, `fp-models`, `ekbebeauty`).

```bash
# Де лежить сайт
ls -la /home/administrator/web/checkipweb.top/public_html/skn/log.php
```

У конфігурації Apache (vhost) для checkipweb.top має бути:

- **DocumentRoot** = `/home/administrator/web/checkipweb.top/public_html`  
  (або той шлях, де є папки `skn/`, `fp-models/`, `ekbebeauty/`).

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

sudo chown -R www-data:www-data skn fp-models ekbebeauty
# або, якщо сайт крутиться під administrator:
# sudo chown -R administrator:www-data skn fp-models ekbebeauty
```

Якщо хочеш лишити власника root — дай групі право запису:

```bash
sudo chgrp -R www-data skn fp-models ekbebeauty
sudo chmod -R g+w skn fp-models ekbebeauty
```

## 3. Перевірка після налаштування

1. Відкрий у браузері: `https://checkipweb.top/skn/log.php`  
   Очікується порожня відповідь або "ok" (GET без POST — нормально).
2. Зроби клік на сторінці з підключеним трекером skn.
3. Перевір, що з’явився файл:
   ```bash
   ls -la /home/administrator/web/checkipweb.top/public_html/skn/clicks.csv
   ```
4. Відкрий: `https://checkipweb.top/skn/download.php` — має завантажитися Excel.

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

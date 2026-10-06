# Controle de projetos

Shop time tracking for one plastics plant. People clock internal subtasks. Leaders keep a budget in reais and a planned number of hours next to the time already worked. The screen is in Brazilian Portuguese. Money is BRL. Dates and the clock use `America/Sao_Paulo`.

This is the v1 pilot. The client tests it, then further changes are quoted again. The build contract is [docs/tech-spec.md](docs/tech-spec.md). The short handover for the people who will use it is [docs/guia-do-piloto.md](docs/guia-do-piloto.md).

## Roles

| Role | What they can do |
|---|---|
| Administrador | Everything: projects, time logs, reports, CSV, users, and the alerts that have been reached |
| Líder | Projects, subtasks, and any time log. No reports and no user admin |
| Operador | Start and stop their own timer, and add an internal subtask. No budgets, planned hours, logged totals, history, or anyone else's timer |

One open timer per person. Logging out does not stop it. Deactivating a user does.

A project has internal subtasks (the clock) and third-party subtasks (a typed budget, no clock). Each subtask can also store planned hours, a planned amount, a realized amount someone types in, and an hour alarm. The alarm is a percentage plus an on/off switch. When it is on and finished hours cross that percentage, the admin sees it on the home screen. A running timer does not count.

A project can be copied under a new name. The copy keeps the plan and the alarms, and drops the hours already logged and the realized amounts.

## Stack

Laravel 13, PHP 8.4, MySQL, Apache. Blade and plain CSS in `public/css`. No Node build, no Docker, no public API.

Apache on this machine loads PHP 8.4. Artisan, Composer, and tests need that same major. If `php -v` is something else, call the 8.4 binary (`/opt/homebrew/opt/php@8.4/bin/php` here) instead of `php`.

```sh
php artisan test
```

## Run it locally

The app is served by the Apache already on this machine. The vhost `labone.localhost` points at `public/`.

```sh
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
```

`.env.example` expects MySQL database `labone` on `127.0.0.1:3306`, user `root`, empty password. Then open [http://labone.localhost](http://labone.localhost).

The seed creates a small plant. Every demo user starts with the password `senha-segura`. Change those passwords before the client uses the address. Accounts and what each role sees are in the handover guide.

## Hostgator deployment

The client site is [https://ponto.laboneindustrial.com.br](https://ponto.laboneindustrial.com.br), on Hostgator server `br608.hostgator.com.br` (`108.179.253.229`). The application is in `/home2/labone44/ponto`; the subdomain document root must be `/home2/labone44/ponto/public`. The main company website keeps its own directory and PHP setting.

The subdomain uses PHP 8.4 (`ea-php84`) and the existing Let’s Encrypt wildcard certificate. `public/.htaccess` redirects HTTP to HTTPS. Production has debug disabled and uses database sessions/cache with secure session cookies. Database: `labone44_ponto`, user: `labone44_ponto_user`, host: `localhost`. Password and application key are only in the server `.env`; preserve that file during updates.

SSH is disabled on this hosting account. Prepare a release locally using PHP 8.4 and `composer install --no-dev --prefer-dist --optimize-autoloader`, then upload/extract it with cPanel File Manager. Include `vendor`, the public assets, and empty writable Laravel storage directories. Exclude local data, logs, cached config/views, development dependencies, and local `.env` files. After extracting, reapply PHP 8.4 in cPanel’s PHP manager if `.htaccess` was replaced. Run `migrate --force`, `config:cache`, and `view:cache` with `/usr/local/bin/ea-php84` through a temporary cPanel cron job whose script stays outside `public`; remove the temporary job and transfer files afterward. Never expose an Artisan or deployment endpoint on the website.

The initial data was copied from `https://labone-horas.parmezani.com` at 09:56 Brasília time on 5 October 2026: 9 users, 4 projects, 82 subtasks, and 0 time logs. Password hashes were preserved; sessions and cache were not copied. Staging remains a separate installation; subsequent changes do not synchronize.

Both the client site and staging at `https://labone-horas.parmezani.com` return `X-Robots-Tag: noindex, nofollow` on HTTPS pages, static files, application redirects, and application errors. Apache sets this in `public/.htaccess`; staging also has the header in `/opt/caddy/sites/labone.caddy`. Preserve these rules on updates, along with Hostgator's HTTPS redirect and PHP handler. `robots.txt` deliberately allows crawling so search engines can read `noindex`; a blanket `Disallow: /` can prevent them from seeing it. Existing search results disappear after the search engine revisits the URL, not immediately. The login requirement continues to protect application data.

A private CLI helper at `/home2/labone44/ponto/storage/app/private/hostgator-backup.php` runs `mysqldump` with a temporary protected client configuration, compresses the dump, and retains seven daily backups in the adjacent `backups` folder. The cPanel backup job runs daily at 03:00 server time with `/usr/local/bin/ea-php84`; its output is in `storage/backup-output.txt`, and `backups/status.json` records success or failure. These files are outside the document root.

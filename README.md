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

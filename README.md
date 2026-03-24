# Balance API

## 1 - Setup project

```bash
composer install
cp .env.example .env
php artisan key:generate
```

## 2 - Run tests

```bash
php artisan test
```

## 3 - Run Laravel API

```bash
php artisan serve
```

## 4 - Expose to the internet with ngrok

```bash
ngrok http 8000
```

## Trello

Project board:

[Kanban Balance API](https://trello.com/invite/b/69c2ba5005a8556f5df390dc/ATTI59bd643bc2f184bd6ad58345420ced20BB3E9F8F/kanban-balance-api)

## Architecture used

- HTTP: Controllers validate input and delegate to use cases
- Application: Use cases with business rules
- Domain: `AccountRepositoryInterface` defines persistence operations
- Infrastructure: `JsonFileAccountRepository` persists state in `storage/app/ebanx_state.json`

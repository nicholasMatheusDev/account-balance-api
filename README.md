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

## Architecture used

- HTTP: Controllers validate input and delegate to use cases
- Application: Use cases with business rules
- Domain: `AccountRepositoryInterface` defines persistence operations
- Infrastructure: `JsonFileAccountRepository` persists state in `storage/app/ebanx_state.json`


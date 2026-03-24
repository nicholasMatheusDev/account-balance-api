# Balance API

Small Laravel API built for the EBANX take-home assignment.

The project keeps the HTTP layer thin and pushes the business rules into services. State is stored in a local JSON file, which is enough for the scope of the challenge and keeps the implementation easy to inspect.

## Running locally

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

## Tests

```bash
php artisan test
```

## Expose with ngrok

```bash
ngrok http 8000
```

## API docs

- OpenAPI: `/openapi.yaml`
- Swagger UI: `/swagger.html`

## Trello

[Kanban Balance API](https://trello.com/invite/b/69c2ba5005a8556f5df390dc/ATTI59bd643bc2f184bd6ad58345420ced20BB3E9F8F/kanban-balance-api)

## Structure

- `Controllers`: request validation and HTTP responses
- `Services`: balance lookup, event processing and reset flow
- `Repositories`: file-based state access in `storage/app/ebanx_state.json`

## Notes

- `GET /balance` has no side effects.
- `POST /event` supports idempotency through the `Idempotency-Key` header.
- Withdraw and transfer operations reject insufficient funds.
- State is protected with file locking, which is enough for a single-instance exercise but not something I would keep for production.

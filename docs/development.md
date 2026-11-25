# Development

Target: Shopware 6.7.16.1, PHP 8.4 and MariaDB 10.11. Composer, Docker Compose, Node.js 24, Python 3 and Shopware CLI are required. Use an isolated Shopware installation and synthetic data.

Run `make setup`, `make start`, `make install` and `make build`. The development HTTP endpoint binds to localhost:18868; never expose the supplied synthetic database credentials publicly. Start the queue worker and scheduler with the Compose workers profile when needed.

Run `make test-unit`, `make test-integration`, `make test-e2e`, `make test-security` and `make validate` as appropriate. E2E tests require a synthetic BP_TEST_PASSWORD. The coverage gate requires 90% statement coverage for critical areas and has not been established as passed. BestPrice live testing requires authorized provider access and a safe procedure; `make test-live` refuses to simulate live acceptance.

`make package` creates `artifacts/StPiGhBestPriceIntegration-0.9.0.zip` and its SHA256 file. Install that exact ZIP into a fresh disposable shop to verify installation, activation, feed generation and uninstall behavior. No automatic migration from the old private plugin identity is provided.

See [implementation status](status.md), [architecture](architecture.md) and [interface research](api/interfaces.md).

COMPOSE = docker compose -f dev/compose.yaml
EXEC = $(COMPOSE) exec -T runtime
PLUGIN = custom/plugins/StPiGhBestPriceIntegration
VERSION = $(shell python3 -c 'import json; print(json.load(open("composer.json"))["version"])')
.PHONY: setup start stop install build lint test test-unit test-integration test-e2e test-security test-feed test-tracking test-consent test-analytics test-pricing test-iap test-live validate screenshots package
setup:
	mkdir -p dev/shop
	docker run --rm --entrypoint composer -v "$(CURDIR)/dev/shop:/app" -w /app ghcr.io/shopware/docker-dev:php8.4-node24-caddy create-project shopware/production:6.7.16.1 . --no-interaction
start:
	$(COMPOSE) up -d
stop:
	$(COMPOSE) stop
install:
	$(EXEC) php bin/console system:install --basic-setup --force
	$(EXEC) composer --working-dir=$(PLUGIN) install --no-plugins --no-scripts --no-interaction
	$(EXEC) php bin/console plugin:refresh
	$(EXEC) php bin/console plugin:install --activate StPiGhBestPriceIntegration
	$(EXEC) php bin/console assets:install
build:
	shopware-cli extension build .
	python3 scripts/clean_build_outputs.py
lint:
	$(EXEC) sh -c 'cd $(PLUGIN) && find src -name "*.php" -print0 | xargs -0 -n1 php -l && php vendor/bin/phpstan analyse'
test: test-unit test-integration test-e2e
test-unit:
	$(EXEC) sh -c 'cd $(PLUGIN) && php vendor/bin/phpunit'
test-integration:
	$(EXEC) env BP_ISOLATED_TESTS=1 php $(PLUGIN)/vendor/bin/phpunit --no-configuration --bootstrap $(PLUGIN)/tests/integration-bootstrap.php $(PLUGIN)/tests/Integration
test-e2e:
	@test -n "$$BP_TEST_PASSWORD" || (echo 'Set synthetic BP_TEST_PASSWORD for the disposable shop'; exit 1)
	mkdir -p artifacts/browser artifacts/screenshots
	npm --prefix artifacts/browser install --no-save playwright@1.63.0
	$(EXEC) env BP_ISOLATED_TESTS=1 php $(PLUGIN)/tests/fixtures/seed.php
	BP_QUEUE_BUILD=1 BP_BASE_URL=http://localhost:18868 node tests/E2E/browser.cjs
	$(EXEC) env BP_ISOLATED_TESTS=1 php $(PLUGIN)/tests/run-fixture-build.php
	BP_BASE_URL=http://localhost:18868 node tests/E2E/browser.cjs
test-security: test-unit
	python3 scripts/secret_scan.py
test-feed test-analytics test-pricing test-iap: test-unit
	@echo "Local unit checks only; official account acceptance is recorded separately in docs/status.md"
test-tracking test-consent:
	@echo "BLOCKED: explicit transmission permission and official test setup required; see docs/status.md"; exit 1
test-live:
	@echo "BLOCKED: authorized merchant validation and Shopware wildcard evidence required; developer sandbox access was refused; no live event is simulated"; exit 1
validate:
	shopware-cli extension validate .
screenshots: test-e2e
package:
	shopware-cli extension package . --disable-git --filename StPiGhBestPriceIntegration-$(VERSION).zip --output-directory artifacts
	python3 scripts/check_archive.py artifacts/StPiGhBestPriceIntegration-$(VERSION).zip

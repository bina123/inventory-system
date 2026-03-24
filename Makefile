.PHONY: help install setup jwt-keys migrate fixtures test test-unit test-integration cc

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## Install dependencies
	composer install

setup: install jwt-keys migrate ## Full project setup

jwt-keys: ## Generate JWT RSA key pair
	mkdir -p config/jwt
	openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096
	openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem

migrate: ## Run database migrations
	php bin/console doctrine:database:create --if-not-exists
	php bin/console doctrine:migrations:migrate --no-interaction

migrate-test: ## Run migrations on test database
	APP_ENV=test php bin/console doctrine:database:create --if-not-exists
	APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction

fixtures: ## Load test fixtures
	php bin/console doctrine:fixtures:load --no-interaction

test: ## Run all tests
	php bin/phpunit

test-unit: ## Run unit tests only
	php bin/phpunit --testsuite Unit

test-integration: ## Run integration tests only
	php bin/phpunit --testsuite Integration

cc: ## Clear cache
	php bin/console cache:clear

schema-validate: ## Validate Doctrine schema
	php bin/console doctrine:schema:validate

schema-update: ## Update schema (dev only)
	php bin/console doctrine:schema:update --force

DOCKER_COMPOSE ?= docker compose

.PHONY: up down shell test lint migrate ci logs ldap-logs

up:
	$(DOCKER_COMPOSE) up -d --build

down:
	$(DOCKER_COMPOSE) down --remove-orphans

shell:
	$(DOCKER_COMPOSE) exec app bash

test:
	$(DOCKER_COMPOSE) exec app php artisan test

lint:
	$(DOCKER_COMPOSE) exec app composer lint

migrate:
	$(DOCKER_COMPOSE) exec app php artisan migrate --force

ci:
	$(DOCKER_COMPOSE) exec app composer lint
	$(DOCKER_COMPOSE) exec app php artisan test

logs:
	$(DOCKER_COMPOSE) logs -f app mysql ldap ldap-admin

ldap-logs:
	$(DOCKER_COMPOSE) logs -f ldap ldap-admin

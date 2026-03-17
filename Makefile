DOCKER_COMPOSE ?= docker compose

.PHONY: up down shell test lint migrate ci logs ldap-logs seed-system seed-demo reset-demo

up:
	$(DOCKER_COMPOSE) up -d

build:
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

seed-system:
	$(DOCKER_COMPOSE) exec app php artisan db:seed --class=Database\\\\Seeders\\\\SystemBootstrapSeeder --force

seed-demo:
	$(DOCKER_COMPOSE) exec app php artisan db:seed --class=Database\\\\Seeders\\\\DemoCompanySeeder --force

reset-demo:
	$(DOCKER_COMPOSE) exec app php artisan migrate:fresh --seed --seeder=Database\\\\Seeders\\\\DemoCompanySeeder --force

ci:
	$(DOCKER_COMPOSE) exec app composer lint
	$(DOCKER_COMPOSE) exec app php artisan test

logs:
	$(DOCKER_COMPOSE) logs -f app mysql ldap ldap-admin

ldap-logs:
	$(DOCKER_COMPOSE) logs -f ldap ldap-admin

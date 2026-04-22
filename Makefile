DOCKER_COMPOSE ?= docker compose
MCP_TOKEN_PRINCIPAL ?= principal-palaueb
MCP_TOKEN_LABEL ?= MCP local test token
MCP_TOKEN_ORG ?= org-museo-coconut
MCP_TOKEN_SCOPE ?= MUSEO-COCONUT
MCP_TOKEN_DAYS ?= 30
MCP_TOKEN_CREATED_BY ?= principal-palaueb
MCP_SMOKE_BIN ?= ./dist/pymesec-mcp-linux-amd64
MCP_SMOKE_API_BASE_URL ?= http://127.0.0.1:18080
MCP_SMOKE_TOKEN ?=
MCP_SMOKE_OPERATION_ID ?= coreGetMcpServerProfile
MCP_SMOKE_REQUEST_TIMEOUT ?= 30s

.PHONY: up down shell test lint migrate ci logs ldap-logs seed-system seed-demo reset-demo compile smoke-app token-issue mcp-smoke

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

compile:
	mkdir -p dist
	cd mcp && CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -trimpath -ldflags="-s -w" -o ../dist/pymesec-mcp-linux-amd64 ./cmd/pymesec-mcp
	cd mcp && CGO_ENABLED=0 GOOS=linux GOARCH=arm64 go build -trimpath -ldflags="-s -w" -o ../dist/pymesec-mcp-linux-arm64 ./cmd/pymesec-mcp
	cd mcp && CGO_ENABLED=0 GOOS=darwin GOARCH=amd64 go build -trimpath -ldflags="-s -w" -o ../dist/pymesec-mcp-darwin-amd64 ./cmd/pymesec-mcp
	cd mcp && CGO_ENABLED=0 GOOS=darwin GOARCH=arm64 go build -trimpath -ldflags="-s -w" -o ../dist/pymesec-mcp-darwin-arm64 ./cmd/pymesec-mcp
	cd mcp && CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -trimpath -ldflags="-s -w" -o ../dist/pymesec-mcp-windows-amd64.exe ./cmd/pymesec-mcp
	cd mcp && CGO_ENABLED=0 GOOS=windows GOARCH=arm64 go build -trimpath -ldflags="-s -w" -o ../dist/pymesec-mcp-windows-arm64.exe ./cmd/pymesec-mcp

smoke-app:
	$(DOCKER_COMPOSE) up -d --build app
	$(DOCKER_COMPOSE) exec app php artisan about

token-issue:
	$(DOCKER_COMPOSE) exec app php artisan api-tokens:issue "$(MCP_TOKEN_PRINCIPAL)" "$(MCP_TOKEN_LABEL)" --organization_id="$(MCP_TOKEN_ORG)" --scope_id="$(MCP_TOKEN_SCOPE)" --expires_in_days="$(MCP_TOKEN_DAYS)" --created_by="$(MCP_TOKEN_CREATED_BY)"

mcp-smoke:
	@if [ -z "$(MCP_SMOKE_TOKEN)" ]; then echo "MCP_SMOKE_TOKEN is required"; exit 1; fi
	@if [ ! -x "$(MCP_SMOKE_BIN)" ]; then echo "Missing MCP binary at $(MCP_SMOKE_BIN). Run 'make compile' or override MCP_SMOKE_BIN."; exit 1; fi
	@cd mcp && go run ./cmd/pymesec-mcp-smoke --mcp-bin="$(abspath $(MCP_SMOKE_BIN))" --api-base-url="$(MCP_SMOKE_API_BASE_URL)" --api-token="$(MCP_SMOKE_TOKEN)" --operation-id="$(MCP_SMOKE_OPERATION_ID)" --request-timeout="$(MCP_SMOKE_REQUEST_TIMEOUT)"

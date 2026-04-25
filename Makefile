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
RELEASE_VERSION ?= $(shell awk -F= '/^CORE_VERSION=/{print $$2; exit}' core/.env.example)
RELEASE_TAG ?= v$(RELEASE_VERSION)
RELEASE_DIR ?= dist/releases/$(RELEASE_TAG)

.PHONY: up down shell test lint migrate ci logs ldap-logs seed-system seed-demo reset-demo compile release smoke-app token-issue mcp-smoke

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

release: compile
	@test -n "$(RELEASE_VERSION)" || (echo "RELEASE_VERSION could not be determined. Set RELEASE_VERSION=... or update core/.env.example."; exit 1)
	@rm -rf "$(RELEASE_DIR)"
	@mkdir -p "$(RELEASE_DIR)"
	@set -e; \
	has_zip=0; \
	if command -v zip >/dev/null 2>&1; then has_zip=1; fi; \
	for binary in dist/pymesec-mcp-*; do \
		[ -f "$$binary" ] || continue; \
		[ -x "$$binary" ] || continue; \
		name="$$(basename "$$binary")"; \
		archive_name="$${name%.exe}"; \
		archive_base="$(RELEASE_DIR)/$${archive_name}-$(RELEASE_TAG)"; \
		rm -f "$${archive_base}.zip" "$${archive_base}.tar.gz"; \
		if [ "$$has_zip" -eq 1 ]; then \
			zip -q -j "$${archive_base}.zip" "$$binary"; \
		fi; \
		tar -C dist -czf "$${archive_base}.tar.gz" "$$name"; \
	done
	@set -e; \
	sha256_line() { \
		file="$$1"; \
		name="$$(basename "$$file")"; \
		if command -v sha256sum >/dev/null 2>&1; then \
			set -- $$(sha256sum "$$file"); \
		elif command -v shasum >/dev/null 2>&1; then \
			set -- $$(shasum -a 256 "$$file"); \
		else \
			echo "sha256sum or shasum is required to build SHA256SUMS" >&2; \
			exit 1; \
		fi; \
		printf '%s  %s\n' "$$1" "$$name"; \
	}; \
	{ \
		for binary in dist/pymesec-mcp-*; do \
			[ -f "$$binary" ] || continue; \
			[ -x "$$binary" ] || continue; \
			sha256_line "$$binary"; \
		done; \
		for archive in "$(RELEASE_DIR)"/*.tar.gz "$(RELEASE_DIR)"/*.zip; do \
			[ -f "$$archive" ] || continue; \
			sha256_line "$$archive"; \
		done; \
	} | LC_ALL=C sort -k2 > "$(RELEASE_DIR)/SHA256SUMS"
	@echo "Release archives written to $(RELEASE_DIR)"

smoke-app:
	$(DOCKER_COMPOSE) up -d --build app
	$(DOCKER_COMPOSE) exec app php artisan about

token-issue:
	$(DOCKER_COMPOSE) exec app php artisan api-tokens:issue "$(MCP_TOKEN_PRINCIPAL)" "$(MCP_TOKEN_LABEL)" --organization_id="$(MCP_TOKEN_ORG)" --scope_id="$(MCP_TOKEN_SCOPE)" --expires_in_days="$(MCP_TOKEN_DAYS)" --created_by="$(MCP_TOKEN_CREATED_BY)"

mcp-smoke:
	@if [ -z "$(MCP_SMOKE_TOKEN)" ]; then echo "MCP_SMOKE_TOKEN is required"; exit 1; fi
	@if [ ! -x "$(MCP_SMOKE_BIN)" ]; then echo "Missing MCP binary at $(MCP_SMOKE_BIN). Run 'make compile' or override MCP_SMOKE_BIN."; exit 1; fi
	@cd mcp && go run ./cmd/pymesec-mcp-smoke --mcp-bin="$(abspath $(MCP_SMOKE_BIN))" --api-base-url="$(MCP_SMOKE_API_BASE_URL)" --api-token="$(MCP_SMOKE_TOKEN)" --operation-id="$(MCP_SMOKE_OPERATION_ID)" --request-timeout="$(MCP_SMOKE_REQUEST_TIMEOUT)"

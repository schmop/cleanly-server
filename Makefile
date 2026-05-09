COMPOSE := docker compose --env-file .env $(if $(wildcard .env.local),--env-file .env.local)

.PHONY: up down restart logs ps test test-server test-hub phpstan

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) up -d --force-recreate

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

# `make test` runs the full server suite + the hub suite. Both expect
# the stack to be running (`make up`). Pass FILTER=<name> to scope phpunit:
# `make test-server FILTER=Json`.
test: test-server test-hub

test-server:
	$(COMPOSE) exec symfony php bin/phpunit $(if $(FILTER),--filter '$(FILTER)')

test-hub:
	$(COMPOSE) exec node_sse npm test

phpstan:
	$(COMPOSE) exec symfony php vendor/bin/phpstan analyse --memory-limit=2G

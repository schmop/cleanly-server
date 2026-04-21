COMPOSE := docker compose --env-file .env $(if $(wildcard .env.local),--env-file .env.local)

.PHONY: up down restart logs ps

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

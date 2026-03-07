GREEN := \033[0;32m
BLUE := \033[0;34m
YELLOW := \033[1;33m
RED := \033[0;31m
CYAN := \033[0;36m
MAGENTA := \033[0;35m
WHITE := \033[1;37m
GRAY := \033[0;90m
NC := \033[0m

# -------------------------------------------------
# Commandes Docker
# -------------------------------------------------

.PHONY: up down restart ps logs

up: ## Démarre les conteneurs Docker
	@echo "$(GREEN)Démarrage des conteneurs...$(NC)"
	@docker compose up -d
	@docker compose exec app bash

down: ## Arrête les conteneurs Docker
	@echo "$(RED)Arrêt des conteneurs...$(NC)"
	@docker compose down

rs: down up ## Redémarre les conteneurs Docker

ps: ## Liste les conteneurs en cours d'exécution
	@echo "$(CYAN)Conteneurs en cours d'exécution :$(NC)"
	@docker compose ps

# -------------------------------------------------
# Commandes Application (Exécutées dans Docker)
# -------------------------------------------------

.PHONY: artisan a composer c test pint rector phpstan tsc

artisan a: ## Exécute php artisan dans le conteneur (ex: make a migrate)
	@docker compose exec app php artisan $(filter-out $@,$(MAKECMDGOALS))

composer c: ## Exécute composer dans le conteneur (ex: make c install)
	@docker compose exec app composer $(filter-out $@,$(MAKECMDGOALS))

test: ## Exécute les tests Pest
	@docker compose exec app php artisan test $(filter-out $@,$(MAKECMDGOALS))

pint: ## Exécute Laravel Pint
	@docker compose exec app ./vendor/bin/pint $(filter-out $@,$(MAKECMDGOALS))

rector: ## Exécute Rector
	@docker compose exec app ./vendor/bin/rector process $(filter-out $@,$(MAKECMDGOALS))

phpstan: ## Exécute PHPStan (Larastan)
	@docker compose exec app ./vendor/bin/phpstan analyse $(filter-out $@,$(MAKECMDGOALS))

tsc: ## Exécute le check TypeScript
	@docker compose exec app ./vendor/bin/tsc $(filter-out $@,$(MAKECMDGOALS))

logs: ## Affiche les logs d'un service (ex: make logs app)
	@if [ -z "$(word 2,$(MAKECMDGOALS))" ]; then \
		echo "$(YELLOW)Usage: make logs <nom_du_service>$(NC)"; \
	else \
		echo "$(BLUE)Logs du service $(word 2,$(MAKECMDGOALS)) :$(NC)"; \
		docker compose logs -f $(word 2,$(MAKECMDGOALS)); \
	fi

%:
	@:
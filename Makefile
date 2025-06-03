# Makefile
#
# Этот Makefile предназначен для управления Docker Compose проектом.
# Все команды docker compose выполняются из папки docker/,
# поэтому используем 'docker compose -f docker/docker-compose.yml'

# Переменные
DOCKER_COMPOSE_FILE = docker/docker-compose.yml
DB_CONTAINER_NAME = password-cracker-db
PHP_CONTAINER_NAME = password-cracker-php

.PHONY: all up down build rebuild logs cli db-cli clean setup

all: help

up: ## Запускает все сервисы в фоновом режиме
	@echo "Запуск Docker сервисов..."
	@docker compose -f $(DOCKER_COMPOSE_FILE) up -d

down: ## Останавливает и удаляет все сервисы
	@echo "Остановка и удаление Docker сервисов..."
	@docker compose -f $(DOCKER_COMPOSE_FILE) down

build: ## Пересобирает все образы сервисов
	@echo "Пересборка Docker образов..."
	@docker compose -f $(DOCKER_COMPOSE_FILE) build

rebuild: down clean build up ## Пересобирает, очищает и перезапускает все сервисы с нуля
	@echo "Полная пересборка и перезапуск Docker сервисов..."

logs: ## Просматривает логи всех сервисов
	@echo "Просмотр логов Docker сервисов (Ctrl+C для выхода)..."
	@docker compose -f $(DOCKER_COMPOSE_FILE) logs -f

cli: ## Подключается к Bash оболочке PHP-контейнера
	@echo "Подключение к оболочке PHP-контейнера..."
	@docker exec -it $(PHP_CONTAINER_NAME) bash

db-cli: ## Подключается к MySQL оболочке DB-контейнера
	@echo "Подключение к MySQL оболочке DB-контейнера..."
	@docker exec -it $(DB_CONTAINER_NAME) mysql -uuser -ppassword cracker_db

clean: ## Удаляет все сервисы, образы и тома
	@echo "Очистка Docker среды (удаление контейнеров, образов и томов)..."
	@docker compose -f $(DOCKER_COMPOSE_FILE) down --rmi all -v

setup: clean rebuild ## Полная установка и инициализация проекта
	@echo "Полная установка проекта: очистка, пересборка, инициализация БД и запуск."

help: ## Отображает это справочное сообщение
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'
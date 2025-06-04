# Makefile
#
# This Makefile is designed to manage the Docker Compose project.
# All docker compose commands are executed from the docker/ folder,
# therefore we use 'docker compose -f docker/docker-compose.yml'

# Variables
DOCKER_COMPOSE_FILE = docker/docker-compose.yml
DB_CONTAINER_NAME = password-cracker-db
PHP_CONTAINER_NAME = password-cracker-php

.PHONY: all up down build rebuild logs cli db-cli clean setup

all: help

up: ## Starts all services in detached mode
	@echo "Starting Docker services..."
	@docker compose -f $(DOCKER_COMPOSE_FILE) up -d

down: ## Stops and removes all services
	@echo "Stopping and removing Docker services..."
	@docker compose -f $(DOCKER_COMPOSE_FILE) down

build: ## Rebuilds all service images
	@echo "Rebuilding Docker images..."
	@docker compose -f $(DOCKER_COMPOSE_FILE) build

rebuild: down clean build up ## Rebuilds, cleans, and restarts all services from scratch
	@echo "Full rebuild and restart of Docker services..."

logs: ## Views the logs of all services
	@echo "Viewing Docker service logs (Ctrl+C to exit)..."
	@docker compose -f $(DOCKER_COMPOSE_FILE) logs -f

cli: ## Connects to the Bash shell of the PHP container
	@echo "Connecting to PHP container shell..."
	@docker exec -it $(PHP_CONTAINER_NAME) bash

db-cli: ## Connects to the MySQL shell of the DB container
	@echo "Connecting to MySQL shell of DB container..."
	@docker exec -it $(DB_CONTAINER_NAME) mysql -uuser -ppassword cracker_db

clean: ## Removes all services, images, and volumes
	@echo "Cleaning Docker environment (removing containers, images, and volumes)..."
	@docker compose -f $(DOCKER_COMPOSE_FILE) down --rmi all -v

setup: clean rebuild ## Full project setup and initialisation
	@echo "Full project setup: cleaning, rebuilding, DB initialisation, and starting."

help: ## Displays this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'
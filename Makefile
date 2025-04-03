install: 
	docker compose up -d
	docker compose exec php composer install
	docker compose exec php bin/console doctrine:database:create
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
	docker compose exec php bin/console doctrine:fixtures:load --no-interaction

up: 
	docker compose up -d

down: 
	docker compose down

fixtures:
	docker compose exec php bin/console doctrine:fixtures:load

generate-keys:
	docker compose exec php php bin/console lexik:jwt:generate-keypair

update:
	docker compose up -d
	docker compose exec php composer install
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
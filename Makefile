install: 
	docker compose up -d
	docker compose exec php composer install
	docker compose exec php bin/console doctrine:database:create
	docker compose exec php bin/console doctrine:migrations:migrate
	docker compose exec php bin/console doctrine:fixtures:load

up: 
	docker compose up -d

down: 
	docker compose down

fixtures:
	docker compose exec php bin/console doctrine:fixtures:load
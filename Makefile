.PHONY: lint lint-ecs lint-phpstan test

lint: lint-ecs lint-phpstan

lint-ecs:
	./vendor/bin/ecs check

lint-phpstan:
	./vendor/bin/phpstan analyse

test:
	./vendor/bin/phpunit

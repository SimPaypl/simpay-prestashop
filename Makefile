fixer:
	vendor/bin/php-cs-fixer fix

phpstan:
	vendor/bin/phpstan analyse

sa: fixer phpstan
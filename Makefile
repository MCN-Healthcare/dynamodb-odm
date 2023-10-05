###
#
#

all:

local-db-up:
	@docker run -p 8000:8000 -dt --name dynamo_local amazon/dynamodb-local
	@bin/odm-console odm:schema-tool:create
	@aws dynamodb list-tables --endpoint-url http://localhost:8000

local-db-down:
	@docker kill dynamo_local
	@docker rm dynamo_local

test:
	- @vendor/bin/phpunit -c ./phpunit.xml --stop-on-error --stop-on-failure

full-test: local-db-up test local-db-down

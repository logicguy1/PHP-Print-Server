.PHONY: up down build restart logs shell status clean

PRINTER ?= $(PRINTER_NAME)

up:
	PRINTER_NAME=$(PRINTER) docker compose up -d

build:
	PRINTER_NAME=$(PRINTER) docker compose up -d --build

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f

shell:
	docker compose exec printserver bash

status:
	docker compose ps

clean:
	docker compose down -v --remove-orphans
	rm -f data/*.db data/*.sqlite

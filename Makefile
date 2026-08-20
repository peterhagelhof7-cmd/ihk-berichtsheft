# Berichtsheft — Build & Release
#
# WICHTIG: Der Release-Tarball darf NUR Produktions-Abhaengigkeiten enthalten.
# Wird `composer install` OHNE --no-dev ausgefuehrt, landet u.a. der
# nextcloud/ocp-Stub (dev-stableXX) im vendor/ und ueberschattet auf der
# Zielinstanz die echten OCP-Klassen -> Fatal Error ("ueber den Release gibt es
# einen Fehler, beim Selbstbau nicht"). Das `dist`-Target unten macht es korrekt.

APP_ID  := berichtsheft
VERSION := $(shell sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
DIST    := dist
PKG     := $(DIST)/$(APP_ID)-$(VERSION).tar.gz

# Im Release enthaltene Laufzeit-Dateien (KEIN node_modules/src/tests/.git/dist).
RUNTIME := appinfo composer.json css img js lib templates vendor

.PHONY: dist deps build clean

## dist: Produktions-Release-Tarball bauen (dist/berichtsheft-<version>.tar.gz)
dist: clean deps build
	@mkdir -p $(DIST)
	tar czf $(PKG) --transform 's,^,$(APP_ID)/,' $(RUNTIME)
	@echo ">> Release fertig: $(PKG) (Version $(VERSION))"

## deps: PHP-Abhaengigkeiten OHNE Dev (verhindert OCP-Stub im Release)
deps:
	composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

## build: Frontend (js/, css/) aus src/ bauen
build:
	npm ci
	npm run build

## clean: Build-Ausgaben entfernen
clean:
	rm -rf js css

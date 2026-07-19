<?php

declare(strict_types=1);

/**
 * Reine Unit-Test-Bootstrap - laedt NUR den Composer-Autoloader (App-Code
 * ueber PSR-4 "OCA\Berichtsheft\", die OCP-Interfaces ueber das
 * "nextcloud/ocp"-Stub-Paket aus require-dev). Bewusst OHNE vollen
 * Nextcloud-Server-Bootstrap (lib/base.php) - die Tests in diesem
 * Verzeichnis testen Service-Klassen isoliert gegen gemockte
 * Mapper/OCP-Abhaengigkeiten, nicht gegen eine echte Datenbank/Instanz.
 */
require_once __DIR__ . '/../vendor/autoload.php';

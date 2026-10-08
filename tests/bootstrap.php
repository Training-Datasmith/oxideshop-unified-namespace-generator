<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (class_exists(\OxidEsales\EshopCommunity\Internal\Framework\Edition\EditionDirectoriesLocator::class, false)) {
    throw new RuntimeException(
        'Shop edition classes are already loaded; standalone tests cannot run in the same process as oxideshop_ce.'
    );
}

require_once __DIR__ . '/Support/ShopCollaborators.php';

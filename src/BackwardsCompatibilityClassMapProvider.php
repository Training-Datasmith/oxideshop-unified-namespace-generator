<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\UnifiedNameSpaceGenerator;

use OxidEsales\EshopCommunity\Internal\Framework\Edition\Edition;
use OxidEsales\EshopCommunity\Internal\Framework\Edition\EditionDirectoriesLocator;
use OxidEsales\UnifiedNameSpaceGenerator\Exceptions\InvalidUnifiedNamespaceClassMapException;
use Symfony\Component\Filesystem\Path;

class BackwardsCompatibilityClassMapProvider
{
    public function getClassMap(): array
    {
        $filePath = Path::join(
            (new EditionDirectoriesLocator())->getEditionSourcePath(Edition::Community),
            'Core',
            'Autoload',
            'BackwardsCompatibilityClassMap.php'
        );

        if (!is_readable($filePath)) {
            return [];
        }

        $map = include $filePath;
        if (!is_array($map)) {
            throw new InvalidUnifiedNamespaceClassMapException(
                "The file $filePath can not be loaded into array.",
            );
        }

        return array_flip($map);
    }
}

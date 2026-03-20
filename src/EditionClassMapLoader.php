<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */
declare (strict_types=1);
namespace Oxid_Esales\Unified_Name_Space_Generator;

use function is_array;
use Oxid_Esales\Eshop_Community\Internal\Framework\Edition\Edition;
use Oxid_Esales\Eshop_Community\Internal\Framework\Edition\Edition_Directories_Locator;
use Oxid_Esales\Unified_Name_Space_Generator\Exceptions\Invalid_Unified_Namespace_Class_Map_Exception;
use Symfony\Component\Filesystem\Path;
readonly class Edition_Class_Map_Loader
{
    public function __construct(private Edition $edition)
    {
    }
    public function load(): array
    {
        $file_path = Path::join((new Edition_Directories_Locator())->get_edition_source_path($this->edition), 'Core', 'Autoload', 'UnifiedNameSpaceClassMap.php');
        if (!is_readable($file_path)) {
            throw new Invalid_Unified_Namespace_Class_Map_Exception("The file {$file_path} for {$this->edition->get_full_edition_name()} is not readable or does not exist");
        }
        $unified_namespace_class_map = include $file_path;
        if (!is_array($unified_namespace_class_map)) {
            throw new Invalid_Unified_Namespace_Class_Map_Exception("The file {$file_path} for {$this->edition->get_full_edition_name()} can not be loaded into array.");
        }
        return $unified_namespace_class_map;
    }
}
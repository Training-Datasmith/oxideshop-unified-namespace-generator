<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */
declare (strict_types=1);
namespace Oxid_Esales\Unified_Name_Space_Generator;

use Oxid_Esales\Eshop_Community\Internal\Framework\Edition\Edition;
use Oxid_Esales\Eshop_Community\Internal\Framework\Edition\Edition_Resolver;
class Unified_Name_Space_Class_Map_Provider
{
    public function get_class_map(): array
    {
        return match ((new Edition_Resolver())->get_edition()) {
            Edition::Community => (new Edition_Class_Map_Loader(Edition::Community))->load(),
            Edition::Professional => array_merge((new Edition_Class_Map_Loader(Edition::Community))->load(), (new Edition_Class_Map_Loader(Edition::Professional))->load()),
            Edition::Enterprise => array_merge((new Edition_Class_Map_Loader(Edition::Community))->load(), (new Edition_Class_Map_Loader(Edition::Professional))->load(), (new Edition_Class_Map_Loader(Edition::Enterprise))->load()),
        };
    }
}
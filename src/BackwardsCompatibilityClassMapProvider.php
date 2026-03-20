<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */
declare (strict_types=1);
namespace Oxid_Esales\Unified_Name_Space_Generator;

use Oxid_Esales\Eshop_Community\Internal\Transition\Utility\Basic_Context;
class Backwards_Compatibility_Class_Map_Provider
{
    public function get_class_map(): array
    {
        return array_flip((new Basic_Context())->get_backwards_compatibility_class_map());
    }
}
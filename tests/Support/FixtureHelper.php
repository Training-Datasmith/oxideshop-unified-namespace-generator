<?php

declare(strict_types=1);

namespace OxidEsales\UnifiedNameSpaceGenerator\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Path;

final class FixtureHelper
{
    public const DEFAULT_CE_MAP = [
        'OxidEsales\Eshop\Core\OnlyCe' => [
            'editionClassName' => 'OxidEsales\EshopCommunity\Core\OnlyCe',
            'isAbstract' => false,
            'isInterface' => false,
            'isDeprecated' => false,
        ],
        'OxidEsales\Eshop\Core\Shared' => [
            'editionClassName' => 'OxidEsales\EshopCommunity\Core\Shared',
            'isAbstract' => false,
            'isInterface' => false,
            'isDeprecated' => false,
        ],
    ];

    public const DEFAULT_PE_MAP = [
        'OxidEsales\Eshop\Core\Shared' => [
            'editionClassName' => 'OxidEsales\EshopProfessional\Core\Shared',
            'isAbstract' => false,
            'isInterface' => false,
            'isDeprecated' => false,
        ],
        'OxidEsales\Eshop\Core\OnlyPe' => [
            'editionClassName' => 'OxidEsales\EshopProfessional\Core\OnlyPe',
            'isAbstract' => false,
            'isInterface' => false,
            'isDeprecated' => false,
        ],
    ];

    public const DEFAULT_EE_MAP = [
        'OxidEsales\Eshop\Core\Shared' => [
            'editionClassName' => 'OxidEsales\EshopEnterprise\Core\Shared',
            'isAbstract' => false,
            'isInterface' => false,
            'isDeprecated' => false,
        ],
        'OxidEsales\Eshop\Core\OnlyEe' => [
            'editionClassName' => 'OxidEsales\EshopEnterprise\Core\OnlyEe',
            'isAbstract' => false,
            'isInterface' => false,
            'isDeprecated' => false,
        ],
    ];

    public const DEFAULT_BC_MAP = [
        'oxarticle' => 'OxidEsales\\Eshop\\Application\\Model\\Article',
        'oxiconfigurable' => 'OxidEsales\\Eshop\\Core\\Contract\\IConfigurable',
    ];

    public static function createFixtureRoot(): string
    {
        $root = sys_get_temp_dir() . '/ung-test-' . bin2hex(random_bytes(8));
        mkdir($root, 0777, true);
        putenv('UNG_FIXTURE_ROOT=' . $root);

        return $root;
    }

    public static function destroyFixtureRoot(string $root): void
    {
        if (is_dir($root)) {
            self::removeDirectory($root);
        }
        putenv('UNG_FIXTURE_ROOT');
    }

    public static function writeEditionMap(string $root, string $editionKey, array $map): void
    {
        $dir = Path::join($root, $editionKey, 'Core', 'Autoload');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $content = '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($map, true) . ';' . PHP_EOL;
        file_put_contents(Path::join($dir, 'UnifiedNameSpaceClassMap.php'), $content);
    }

    public static function writeBackwardsCompatibilityMap(string $root, array $map): void
    {
        $dir = Path::join($root, 'ce', 'Core', 'Autoload');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $content = '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($map, true) . ';' . PHP_EOL;
        file_put_contents(Path::join($dir, 'BackwardsCompatibilityClassMap.php'), $content);
    }

    public static function communityMapPath(string $root): string
    {
        return Path::join($root, 'ce', 'Core', 'Autoload', 'UnifiedNameSpaceClassMap.php');
    }

    public static function createOutputDirectory(): string
    {
        $dir = sys_get_temp_dir() . '/ung-out-' . bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);
        touch(Path::join($dir, '.gitkeep'));

        return $dir . DIRECTORY_SEPARATOR;
    }

    public static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            @chmod($item->getPathname(), 0777);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @chmod($item->getPathname(), 0777);
                rmdir($item->getPathname());
            } else {
                @chmod($item->getPathname(), 0666);
                unlink($item->getPathname());
            }
        }
        @chmod($path, 0777);
        rmdir($path);
    }

    public static function resetGeneratedDirectory(string $repoRoot): void
    {
        $generated = Path::join($repoRoot, 'generated');
        if (!is_dir($generated)) {
            mkdir($generated, 0777, true);
        }

        foreach (scandir($generated) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
                continue;
            }
            $full = Path::join($generated, $entry);
            if (is_dir($full)) {
                self::removeDirectory($full);
            } else {
                unlink($full);
            }
        }
    }
}

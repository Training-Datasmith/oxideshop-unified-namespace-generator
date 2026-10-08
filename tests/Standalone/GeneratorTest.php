<?php

declare(strict_types=1);

namespace OxidEsales\UnifiedNameSpaceGenerator\Tests\Standalone;

use Exception;
use OxidEsales\UnifiedNameSpaceGenerator\Exceptions\FileSystemCompatibilityException;
use OxidEsales\UnifiedNameSpaceGenerator\Exceptions\OutputDirectoryValidationException;
use OxidEsales\UnifiedNameSpaceGenerator\Generator;
use OxidEsales\UnifiedNameSpaceGenerator\Tests\Support\FixtureHelper;
use OxidEsales\UnifiedNameSpaceGenerator\UnifiedNameSpaceClassMapProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Path;

final class GeneratorTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureRoot = FixtureHelper::createFixtureRoot();
        FixtureHelper::writeEditionMap($this->fixtureRoot, 'ce', []);
    }

    protected function tearDown(): void
    {
        FixtureHelper::destroyFixtureRoot($this->fixtureRoot);
        parent::tearDown();
    }

    public function testConstructorRejectsMissingOutputDirectory(): void
    {
        $missing = sys_get_temp_dir() . '/ung-missing-' . bin2hex(random_bytes(4));
        $this->assertFalse(is_dir($missing));

        $this->expectException(OutputDirectoryValidationException::class);
        $this->expectExceptionMessage($missing);

        new Generator($this->stubProvider([]), $missing);
    }

    public function testConstructorRejectsUnwritableOutputDirectory(): void
    {
        $output = FixtureHelper::createOutputDirectory();
        chmod(rtrim($output, DIRECTORY_SEPARATOR), 0555);
        $real = realpath(rtrim($output, DIRECTORY_SEPARATOR));
        $this->assertNotFalse($real);

        try {
            $this->expectException(OutputDirectoryValidationException::class);
            $this->expectExceptionMessage('not writable');
            $this->expectExceptionMessage($real);

            new Generator($this->stubProvider([]), $output);
        } finally {
            chmod($real, 0777);
            FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
        }
    }

    public function testCleanupKeepsGitkeepAndDeletesOtherEntries(): void
    {
        $output = FixtureHelper::createOutputDirectory();
        file_put_contents(Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'Stale.php'), 'stale');
        mkdir(Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'Sub'), 0777, true);
        file_put_contents(Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'Sub', 'Child.php'), 'child');

        (new Generator($this->stubProvider([]), $output))->cleanupOutputDirectory();

        $this->assertFileExists(Path::join(rtrim($output, DIRECTORY_SEPARATOR), '.gitkeep'));
        $this->assertFileDoesNotExist(Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'Stale.php'));
        $this->assertFileDoesNotExist(Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'Sub', 'Child.php'));

        FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
    }

    public function testCleanupThrowsWhenEntryCannotBeRemoved(): void
    {
        $output = FixtureHelper::createOutputDirectory();
        $locked = Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'locked');
        mkdir($locked, 0777, true);
        file_put_contents(Path::join($locked, 'inner.txt'), 'x');
        chmod($locked, 0555);

        try {
            $this->expectException(IOException::class);
            (new Generator($this->stubProvider([]), $output))->cleanupOutputDirectory();
        } finally {
            if (is_dir($locked)) {
                chmod($locked, 0777);
            }
            FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
        }
    }

    public function testGenerateWritesClassInterfaceAndAbstractFiles(): void
    {
        $output = FixtureHelper::createOutputDirectory();
        $map = $this->shapeMap();

        (new Generator($this->stubProvider($map), $output))->generate();

        $article = Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'OxidEsales/Eshop/Application/Model/Article.php');
        $this->assertFileExists($article);
        $articleBody = file_get_contents($article);
        $this->assertIsString($articleBody);
        $this->assertStringContainsString('namespace OxidEsales\Eshop\Application\Model;', $articleBody);
        $this->assertStringContainsString(
            'class Article extends \OxidEsales\EshopCommunity\Application\Model\Article',
            $articleBody
        );
        $this->assertStringContainsString('@deprecated', $articleBody);

        $interface = Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'OxidEsales/Eshop/Core/Contract/ClassNameResolverInterface.php');
        $interfaceBody = file_get_contents($interface);
        $this->assertIsString($interfaceBody);
        $this->assertStringContainsString('interface ClassNameResolverInterface extends \OxidEsales\EshopCommunity\Core\Contract\ClassNameResolverInterface', $interfaceBody);
        $this->assertStringNotContainsString('@deprecated', $interfaceBody);

        $abstract = Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'OxidEsales/Eshop/Core/Contract/AbstractUpdatableFields.php');
        $abstractBody = file_get_contents($abstract);
        $this->assertIsString($abstractBody);
        $this->assertStringContainsString('abstract class AbstractUpdatableFields extends \OxidEsales\EshopCommunity\Core\Contract\AbstractUpdatableFields', $abstractBody);

        foreach ([$article, $interface, $abstract] as $file) {
            exec('php -l ' . escapeshellarg($file), $lintOutput, $lintCode);
            $this->assertSame(0, $lintCode, implode("\n", $lintOutput));
        }

        FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
    }

    public function testGenerateEmitsClassAliasOnlyForMappedLegacyNames(): void
    {
        FixtureHelper::writeBackwardsCompatibilityMap($this->fixtureRoot, FixtureHelper::DEFAULT_BC_MAP);
        $output = FixtureHelper::createOutputDirectory();
        $map = $this->shapeMap();
        $map['OxidEsales\Eshop\Core\FileSystem\FileSystem'] = [
            'editionClassName' => 'OxidEsales\EshopCommunity\Core\FileSystem\FileSystem',
            'isAbstract' => false,
            'isInterface' => false,
            'isDeprecated' => false,
        ];

        (new Generator($this->stubProvider($map), $output))->generate();

        $article = file_get_contents(Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'OxidEsales/Eshop/Application/Model/Article.php'));
        $this->assertIsString($article);
        $this->assertStringContainsString(
            "class_alias(\\OxidEsales\\Eshop\\Application\\Model\\Article::class, 'oxarticle');",
            $article
        );

        $iconfig = file_get_contents(Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'OxidEsales/Eshop/Core/Contract/IConfigurable.php'));
        $this->assertIsString($iconfig);
        $this->assertStringContainsString(
            "class_alias(\\OxidEsales\\Eshop\\Core\\Contract\\IConfigurable::class, 'oxiconfigurable');",
            $iconfig
        );

        $fs = file_get_contents(Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'OxidEsales/Eshop/Core/FileSystem/FileSystem.php'));
        $this->assertIsString($fs);
        $this->assertStringNotContainsString('class_alias', $fs);

        FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
    }

    public function testGenerateWithoutBackwardsCompatibilityFileOmitsAlias(): void
    {
        $output = FixtureHelper::createOutputDirectory();
        $map = [
            'OxidEsales\Eshop\Core\OnlyCe' => FixtureHelper::DEFAULT_CE_MAP['OxidEsales\Eshop\Core\OnlyCe'],
        ];

        (new Generator($this->stubProvider($map), $output))->generate();

        $file = Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'OxidEsales/Eshop/Core/OnlyCe.php');
        $this->assertFileExists($file);
        $body = file_get_contents($file);
        $this->assertIsString($body);
        $this->assertStringNotContainsString('class_alias', $body);

        FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
    }

    public function testGenerateOverwritesExistingFile(): void
    {
        $output = FixtureHelper::createOutputDirectory();
        $community = [
            'OxidEsales\Eshop\Application\Model\Article' => [
                'editionClassName' => 'OxidEsales\EshopCommunity\Application\Model\Article',
                'isAbstract' => false,
                'isInterface' => false,
                'isDeprecated' => false,
            ],
        ];
        $enterprise = [
            'OxidEsales\Eshop\Application\Model\Article' => [
                'editionClassName' => 'OxidEsales\EshopEnterprise\Application\Model\Article',
                'isAbstract' => false,
                'isInterface' => false,
                'isDeprecated' => false,
            ],
        ];

        (new Generator($this->stubProvider($community), $output))->generate();
        (new Generator($this->stubProvider($enterprise), $output))->generate();

        $body = file_get_contents(Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'OxidEsales/Eshop/Application/Model/Article.php'));
        $this->assertIsString($body);
        $this->assertStringContainsString('OxidEsales\EshopEnterprise\Application\Model\Article', $body);
        $this->assertStringNotContainsString('OxidEsales\EshopCommunity\Application\Model\Article', $body);

        FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
    }

    public function testGenerateLeavesUnrelatedFileUntilCleanup(): void
    {
        $output = FixtureHelper::createOutputDirectory();
        $unrelated = Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'Unrelated.php');
        file_put_contents($unrelated, '<?php');

        $map = [
            'OxidEsales\Eshop\Core\OnlyCe' => FixtureHelper::DEFAULT_CE_MAP['OxidEsales\Eshop\Core\OnlyCe'],
        ];
        $generator = new Generator($this->stubProvider($map), $output);
        $generator->generate();
        $this->assertFileExists($unrelated);
        $generator->cleanupOutputDirectory();
        $this->assertFileDoesNotExist($unrelated);

        FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
    }

    public function testGenerateRejectsEmptyClassMap(): void
    {
        $output = FixtureHelper::createOutputDirectory();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No unified namespace found');

        try {
            (new Generator($this->stubProvider([]), $output))->generate();
        } finally {
            FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
        }
    }

    public function testGenerateRejectsClassNameWithoutNamespace(): void
    {
        $output = FixtureHelper::createOutputDirectory();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not extract unified sub namespace');

        try {
            (new Generator($this->stubProvider(['oxarticle' => $this->shapeMap()['OxidEsales\Eshop\Application\Model\Article']]), $output))->generate();
        } finally {
            FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
        }
    }

    public function testGenerateRejectsEmptyShortClassName(): void
    {
        $output = FixtureHelper::createOutputDirectory();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not extract short unified a class name');

        try {
            (new Generator($this->stubProvider(['OxidEsales\Eshop\\' => $this->shapeMap()['OxidEsales\Eshop\Application\Model\Article']]), $output))->generate();
        } finally {
            FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
        }
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function wrongDescriptionShapeProvider(): array
    {
        $valid = [
            'editionClassName' => 'OxidEsales\EshopCommunity\Application\Model\Article',
            'isAbstract' => false,
            'isInterface' => false,
            'isDeprecated' => false,
        ];

        return [
            'missing_is_deprecated' => [[
                'editionClassName' => $valid['editionClassName'],
                'isAbstract' => false,
                'isInterface' => false,
            ]],
            'extra_key' => [[
                'aaa' => 'x',
                'editionClassName' => $valid['editionClassName'],
                'isAbstract' => false,
                'isInterface' => false,
                'isDeprecated' => false,
            ]],
        ];
    }

    #[DataProvider('wrongDescriptionShapeProvider')]
    public function testGenerateRejectsWrongDescriptionShape(array $description): void
    {
        $output = FixtureHelper::createOutputDirectory();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('wrong layout');

        try {
            (new Generator($this->stubProvider(['OxidEsales\Eshop\Application\Model\Article' => $description]), $output))->generate();
        } finally {
            FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
        }
    }

    public function testGenerateRejectsNonArrayDescription(): void
    {
        $output = FixtureHelper::createOutputDirectory();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('wrong layout');

        try {
            (new Generator($this->stubProvider(['OxidEsales\Eshop\Application\Model\Article' => 'this will not work']), $output))->generate();
        } finally {
            FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
        }
    }

    public function testGenerateRejectsUnwritableExistingFile(): void
    {
        $output = FixtureHelper::createOutputDirectory();
        $dir = Path::join(rtrim($output, DIRECTORY_SEPARATOR), 'OxidEsales', 'Eshop', 'Application', 'Model');
        mkdir($dir, 0777, true);
        $file = Path::join($dir, 'Article.php');
        file_put_contents($file, '<?php');
        chmod($file, 0444);
        $this->assertFalse(is_writable($file));

        $this->expectException(FileSystemCompatibilityException::class);
        $this->expectExceptionMessage($file);

        try {
            (new Generator($this->stubProvider($this->articleOnlyMap()), $output))->generate();
        } finally {
            chmod($file, 0644);
            FixtureHelper::removeDirectory(rtrim($output, DIRECTORY_SEPARATOR));
        }
    }

    /**
     * @param array<string, array<string, mixed>> $classMap
     */
    private function stubProvider(array $classMap): UnifiedNameSpaceClassMapProvider
    {
        $stub = $this->createStub(UnifiedNameSpaceClassMapProvider::class);
        $stub->method('getClassMap')->willReturn($classMap);

        return $stub;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function shapeMap(): array
    {
        return [
            'OxidEsales\Eshop\Application\Model\Article' => [
                'editionClassName' => 'OxidEsales\EshopCommunity\Application\Model\Article',
                'isAbstract' => false,
                'isInterface' => false,
                'isDeprecated' => true,
            ],
            'OxidEsales\Eshop\Core\Contract\ClassNameResolverInterface' => [
                'editionClassName' => 'OxidEsales\EshopCommunity\Core\Contract\ClassNameResolverInterface',
                'isAbstract' => false,
                'isInterface' => true,
                'isDeprecated' => false,
            ],
            'OxidEsales\Eshop\Core\Contract\AbstractUpdatableFields' => [
                'editionClassName' => 'OxidEsales\EshopCommunity\Core\Contract\AbstractUpdatableFields',
                'isAbstract' => true,
                'isInterface' => false,
                'isDeprecated' => false,
            ],
            'OxidEsales\Eshop\Core\Contract\IConfigurable' => [
                'editionClassName' => 'OxidEsales\EshopCommunity\Core\Contract\IConfigurable',
                'isAbstract' => false,
                'isInterface' => true,
                'isDeprecated' => false,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function articleOnlyMap(): array
    {
        return [
            'OxidEsales\Eshop\Application\Model\Article' => $this->shapeMap()['OxidEsales\Eshop\Application\Model\Article'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace OxidEsales\UnifiedNameSpaceGenerator\Tests\Standalone;

use OxidEsales\UnifiedNameSpaceGenerator\BackwardsCompatibilityClassMapProvider;
use OxidEsales\UnifiedNameSpaceGenerator\Exceptions\InvalidUnifiedNamespaceClassMapException;
use OxidEsales\UnifiedNameSpaceGenerator\Tests\Support\FixtureHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

final class BackwardsCompatibilityClassMapProviderTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureRoot = FixtureHelper::createFixtureRoot();
    }

    protected function tearDown(): void
    {
        FixtureHelper::destroyFixtureRoot($this->fixtureRoot);
        parent::tearDown();
    }

    public function testFlipsLegacyNameOntoUnifiedClass(): void
    {
        FixtureHelper::writeBackwardsCompatibilityMap($this->fixtureRoot, FixtureHelper::DEFAULT_BC_MAP);

        $map = (new BackwardsCompatibilityClassMapProvider())->getClassMap();

        $this->assertSame('oxarticle', $map['OxidEsales\Eshop\Application\Model\Article']);
        $this->assertSame('oxiconfigurable', $map['OxidEsales\Eshop\Core\Contract\IConfigurable']);
    }

    public function testMissingFileReturnsEmptyArray(): void
    {
        $map = (new BackwardsCompatibilityClassMapProvider())->getClassMap();

        $this->assertIsArray($map);
        $this->assertSame([], $map);
    }

    public function testNonArrayFileThrows(): void
    {
        $dir = Path::join($this->fixtureRoot, 'ce', 'Core', 'Autoload');
        mkdir($dir, 0777, true);
        file_put_contents(Path::join($dir, 'BackwardsCompatibilityClassMap.php'), "<?php return 'nope';");

        $this->expectException(InvalidUnifiedNamespaceClassMapException::class);
        $this->expectExceptionMessage('BackwardsCompatibilityClassMap.php');

        (new BackwardsCompatibilityClassMapProvider())->getClassMap();
    }
}

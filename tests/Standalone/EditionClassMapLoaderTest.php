<?php

declare(strict_types=1);

namespace OxidEsales\UnifiedNameSpaceGenerator\Tests\Standalone;

use OxidEsales\EshopCommunity\Internal\Framework\Edition\Edition;
use OxidEsales\UnifiedNameSpaceGenerator\EditionClassMapLoader;
use OxidEsales\UnifiedNameSpaceGenerator\Exceptions\InvalidUnifiedNamespaceClassMapException;
use OxidEsales\UnifiedNameSpaceGenerator\Tests\Support\FixtureHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

final class EditionClassMapLoaderTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureRoot = FixtureHelper::createFixtureRoot();
        FixtureHelper::writeEditionMap($this->fixtureRoot, 'ce', FixtureHelper::DEFAULT_CE_MAP);
    }

    protected function tearDown(): void
    {
        FixtureHelper::destroyFixtureRoot($this->fixtureRoot);
        parent::tearDown();
    }

    public function testLoadReadsCommunityMapEntries(): void
    {
        $map = (new EditionClassMapLoader(Edition::Community))->load();

        $this->assertArrayHasKey('OxidEsales\Eshop\Core\OnlyCe', $map);
        $this->assertSame('OxidEsales\EshopCommunity\Core\OnlyCe', $map['OxidEsales\Eshop\Core\OnlyCe']['editionClassName']);
        $this->assertFalse($map['OxidEsales\Eshop\Core\OnlyCe']['isAbstract']);
        $this->assertFalse($map['OxidEsales\Eshop\Core\OnlyCe']['isInterface']);
        $this->assertFalse($map['OxidEsales\Eshop\Core\OnlyCe']['isDeprecated']);
        $this->assertArrayNotHasKey('OxidEsales\Eshop\Core\OnlyPe', $map);
    }

    public function testLoadThrowsWhenMapFileMissing(): void
    {
        $path = FixtureHelper::communityMapPath($this->fixtureRoot);
        unlink($path);
        $this->assertFalse(is_readable($path));

        $this->expectException(InvalidUnifiedNamespaceClassMapException::class);
        $this->expectExceptionMessage($path);
        $this->expectExceptionMessage('Community Edition');

        (new EditionClassMapLoader(Edition::Community))->load();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonArrayFileProvider(): array
    {
        return [
            'string_return' => ["<?php return 'some-string';"],
            'no_return' => ['<?php "literal";'],
        ];
    }

    #[DataProvider('nonArrayFileProvider')]
    public function testLoadThrowsWhenFileReturnsNonArray(string $contents): void
    {
        file_put_contents(FixtureHelper::communityMapPath($this->fixtureRoot), $contents);

        $this->expectException(InvalidUnifiedNamespaceClassMapException::class);
        $this->expectExceptionMessage('can not be loaded into array');

        (new EditionClassMapLoader(Edition::Community))->load();
    }
}

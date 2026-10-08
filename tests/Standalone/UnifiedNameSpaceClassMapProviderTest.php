<?php

declare(strict_types=1);

namespace OxidEsales\UnifiedNameSpaceGenerator\Tests\Standalone;

use OxidEsales\UnifiedNameSpaceGenerator\Tests\Support\FixtureHelper;
use OxidEsales\UnifiedNameSpaceGenerator\UnifiedNameSpaceClassMapProvider;
use PHPUnit\Framework\TestCase;

final class UnifiedNameSpaceClassMapProviderTest extends TestCase
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

    public function testCommunityEditionUsesCommunityClassesOnly(): void
    {
        FixtureHelper::writeEditionMap($this->fixtureRoot, 'ce', FixtureHelper::DEFAULT_CE_MAP);

        $classMap = (new UnifiedNameSpaceClassMapProvider())->getClassMap();

        $this->assertSame(
            'OxidEsales\EshopCommunity\Core\OnlyCe',
            $classMap['OxidEsales\Eshop\Core\OnlyCe']['editionClassName']
        );
        $this->assertSame(
            'OxidEsales\EshopCommunity\Core\Shared',
            $classMap['OxidEsales\Eshop\Core\Shared']['editionClassName']
        );
        $this->assertArrayNotHasKey('OxidEsales\Eshop\Core\OnlyPe', $classMap);
        $this->assertArrayNotHasKey('OxidEsales\Eshop\Core\OnlyEe', $classMap);
    }

    public function testProfessionalEditionOverridesSharedCommunityClass(): void
    {
        FixtureHelper::writeEditionMap($this->fixtureRoot, 'ce', FixtureHelper::DEFAULT_CE_MAP);
        FixtureHelper::writeEditionMap($this->fixtureRoot, 'pe', FixtureHelper::DEFAULT_PE_MAP);

        $classMap = (new UnifiedNameSpaceClassMapProvider())->getClassMap();

        $this->assertSame(
            'OxidEsales\EshopProfessional\Core\Shared',
            $classMap['OxidEsales\Eshop\Core\Shared']['editionClassName']
        );
        $this->assertSame(
            'OxidEsales\EshopCommunity\Core\OnlyCe',
            $classMap['OxidEsales\Eshop\Core\OnlyCe']['editionClassName']
        );
        $this->assertArrayHasKey('OxidEsales\Eshop\Core\OnlyPe', $classMap);
        $this->assertArrayNotHasKey('OxidEsales\Eshop\Core\OnlyEe', $classMap);
    }

    public function testEnterpriseEditionOverridesLowerEditions(): void
    {
        FixtureHelper::writeEditionMap($this->fixtureRoot, 'ce', FixtureHelper::DEFAULT_CE_MAP);
        FixtureHelper::writeEditionMap($this->fixtureRoot, 'pe', FixtureHelper::DEFAULT_PE_MAP);
        FixtureHelper::writeEditionMap($this->fixtureRoot, 'ee', FixtureHelper::DEFAULT_EE_MAP);

        $classMap = (new UnifiedNameSpaceClassMapProvider())->getClassMap();

        $this->assertSame(
            'OxidEsales\EshopEnterprise\Core\Shared',
            $classMap['OxidEsales\Eshop\Core\Shared']['editionClassName']
        );
        $this->assertSame(
            'OxidEsales\EshopCommunity\Core\OnlyCe',
            $classMap['OxidEsales\Eshop\Core\OnlyCe']['editionClassName']
        );
        $this->assertSame(
            'OxidEsales\EshopProfessional\Core\OnlyPe',
            $classMap['OxidEsales\Eshop\Core\OnlyPe']['editionClassName']
        );
        $this->assertSame(
            'OxidEsales\EshopEnterprise\Core\OnlyEe',
            $classMap['OxidEsales\Eshop\Core\OnlyEe']['editionClassName']
        );
    }
}

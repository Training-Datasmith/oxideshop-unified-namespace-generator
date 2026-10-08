<?php

declare(strict_types=1);

namespace OxidEsales\UnifiedNameSpaceGenerator\Tests\Standalone;

use Composer\Composer;
use Composer\Config;
use Composer\IO\BufferIO;
use Composer\Script\ScriptEvents;
use OxidEsales\UnifiedNameSpaceGenerator\Plugin;
use OxidEsales\UnifiedNameSpaceGenerator\Tests\Support\FixtureHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

final class PluginTest extends TestCase
{
    private string $fixtureRoot;
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoRoot = dirname(__DIR__, 2);
        $this->fixtureRoot = FixtureHelper::createFixtureRoot();
        FixtureHelper::writeEditionMap($this->fixtureRoot, 'ce', FixtureHelper::DEFAULT_CE_MAP);
        FixtureHelper::resetGeneratedDirectory($this->repoRoot);
    }

    protected function tearDown(): void
    {
        FixtureHelper::resetGeneratedDirectory($this->repoRoot);
        FixtureHelper::destroyFixtureRoot($this->fixtureRoot);
        parent::tearDown();
    }

    public function testSubscribesToPostInstallAndPostUpdate(): void
    {
        $this->assertSame(
            [
                ScriptEvents::POST_INSTALL_CMD => 'callback',
                ScriptEvents::POST_UPDATE_CMD => 'callback',
            ],
            Plugin::getSubscribedEvents()
        );
    }

    public function testCallbackAnnouncesAndWritesClasses(): void
    {
        $vendorDir = sys_get_temp_dir() . '/ung-vendor-' . bin2hex(random_bytes(4));
        mkdir($vendorDir, 0777, true);
        file_put_contents(Path::join($vendorDir, 'autoload.php'), '<?php');

        $config = new Config();
        $config->merge(['config' => ['vendor-dir' => $vendorDir]]);

        $composer = new Composer();
        $composer->setConfig($config);

        $io = new BufferIO();
        $plugin = new Plugin();
        $plugin->activate($composer, $io);
        $plugin->callback();

        $output = $io->getOutput();
        $this->assertStringContainsString('Generating OXID eShop unified namespace classes', $output);

        $generated = Path::join($this->repoRoot, 'generated', 'OxidEsales', 'Eshop', 'Core', 'OnlyCe.php');
        $this->assertFileExists($generated);
        $body = file_get_contents($generated);
        $this->assertIsString($body);
        $this->assertStringContainsString('class OnlyCe extends \OxidEsales\EshopCommunity\Core\OnlyCe', $body);

        FixtureHelper::removeDirectory($vendorDir);
    }
}

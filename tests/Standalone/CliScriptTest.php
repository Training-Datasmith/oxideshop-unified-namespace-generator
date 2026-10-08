<?php

declare(strict_types=1);

namespace OxidEsales\UnifiedNameSpaceGenerator\Tests\Standalone;

use OxidEsales\UnifiedNameSpaceGenerator\Tests\Support\FixtureHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

final class CliScriptTest extends TestCase
{
    private string $fixtureRoot;
    private string $repoRoot;
    private string $installRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoRoot = dirname(__DIR__, 2);
        $this->fixtureRoot = FixtureHelper::createFixtureRoot();
        FixtureHelper::writeEditionMap($this->fixtureRoot, 'ce', FixtureHelper::DEFAULT_CE_MAP);
        FixtureHelper::resetGeneratedDirectory($this->repoRoot);

        $this->installRoot = sys_get_temp_dir() . '/ung-cli-' . bin2hex(random_bytes(4));
        $packageDir = Path::join(
            $this->installRoot,
            'vendor',
            'oxid-esales',
            'oxideshop-unified-namespace-generator'
        );
        if (!is_dir($packageDir)) {
            mkdir($packageDir, 0777, true);
        }
        copy(
            Path::join($this->repoRoot, 'oe-eshop-unified_namespace_generator'),
            Path::join($packageDir, 'oe-eshop-unified_namespace_generator')
        );
        chmod(Path::join($packageDir, 'oe-eshop-unified_namespace_generator'), 0755);

        $vendorAutoload = Path::join($this->installRoot, 'vendor', 'autoload.php');
        if (!is_dir(dirname($vendorAutoload))) {
            mkdir(dirname($vendorAutoload), 0777, true);
        }
        file_put_contents(
            $vendorAutoload,
            '<?php require_once ' . var_export(Path::join($this->repoRoot, 'tests', 'bootstrap.php'), true) . ';'
        );
    }

    protected function tearDown(): void
    {
        FixtureHelper::resetGeneratedDirectory($this->repoRoot);
        FixtureHelper::destroyFixtureRoot($this->fixtureRoot);
        if (is_dir($this->installRoot)) {
            FixtureHelper::removeDirectory($this->installRoot);
        }
        parent::tearDown();
    }

    public function testInstalledLayoutExitsZeroAndWritesClass(): void
    {
        [$exitCode, $stdout] = $this->runScript();

        $this->assertSame(0, $exitCode);
        $this->assertSame('', $stdout);

        $generated = Path::join($this->repoRoot, 'generated', 'OxidEsales', 'Eshop', 'Core', 'OnlyCe.php');
        $this->assertFileExists($generated);
        $body = file_get_contents($generated);
        $this->assertIsString($body);
        $this->assertStringContainsString('class OnlyCe extends \OxidEsales\EshopCommunity\Core\OnlyCe', $body);
        $this->assertStringNotContainsString('class_alias', $body);
    }

    public function testInvalidDescriptionExitsOneAndPrintsHandledError(): void
    {
        FixtureHelper::writeEditionMap($this->fixtureRoot, 'ce', [
            'OxidEsales\Eshop\Core\Broken' => 'this will not work',
        ]);

        [$exitCode, $stdout] = $this->runScript();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('wrong layout', $stdout);
        $this->assertStringContainsString('error code:', $stdout);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runScript(): array
    {
        $script = Path::join(
            $this->installRoot,
            'vendor',
            'oxid-esales',
            'oxideshop-unified-namespace-generator',
            'oe-eshop-unified_namespace_generator'
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = getenv();
        if ($env === false) {
            $env = [];
        }
        $env['UNG_FIXTURE_ROOT'] = $this->fixtureRoot;

        $process = proc_open([PHP_BINARY, $script], $descriptorSpec, $pipes, $this->installRoot, $env);
        $this->assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$exitCode, trim($stdout . $stderr)];
    }
}

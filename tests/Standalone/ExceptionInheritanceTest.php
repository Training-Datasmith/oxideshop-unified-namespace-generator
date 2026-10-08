<?php

declare(strict_types=1);

namespace OxidEsales\UnifiedNameSpaceGenerator\Tests\Standalone;

use OxidEsales\UnifiedNameSpaceGenerator\Exceptions\FileSystemCompatibilityException;
use OxidEsales\UnifiedNameSpaceGenerator\Exceptions\InvalidUnifiedNamespaceClassMapException;
use OxidEsales\UnifiedNameSpaceGenerator\Exceptions\OutputDirectoryValidationException;
use OxidEsales\UnifiedNameSpaceGenerator\Exceptions\PermissionException;
use PHPUnit\Framework\TestCase;

final class ExceptionInheritanceTest extends TestCase
{
    public function testPackageExceptionsExtendException(): void
    {
        $this->assertSame(\Exception::class, (new \ReflectionClass(PermissionException::class))->getParentClass()?->getName());
        $this->assertSame(\Exception::class, (new \ReflectionClass(FileSystemCompatibilityException::class))->getParentClass()?->getName());
        $this->assertSame(\Exception::class, (new \ReflectionClass(OutputDirectoryValidationException::class))->getParentClass()?->getName());
        $this->assertSame(\Exception::class, (new \ReflectionClass(InvalidUnifiedNamespaceClassMapException::class))->getParentClass()?->getName());
    }
}

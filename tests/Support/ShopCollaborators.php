<?php

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Internal\Framework\Edition;

enum Edition: string
{
    case Community = 'CE';
    case Professional = 'PE';
    case Enterprise = 'EE';

    public function isCommunityEdition(): bool
    {
        return $this === self::Community;
    }

    public function getFullEditionName(): string
    {
        return match ($this) {
            self::Community => 'Community Edition',
            self::Professional => 'Professional Edition',
            self::Enterprise => 'Enterprise Edition',
        };
    }
}

readonly class EditionDirectoriesLocator
{
    public function getEditionSourcePath(Edition $edition): string
    {
        $root = getenv('UNG_FIXTURE_ROOT');
        if ($root === false || $root === '') {
            throw new \RuntimeException('UNG_FIXTURE_ROOT is not set');
        }

        return match ($edition) {
            Edition::Community => $root . '/ce',
            Edition::Professional => $root . '/pe',
            Edition::Enterprise => $root . '/ee',
        };
    }
}

readonly class EditionResolver
{
    public function getEdition(): Edition
    {
        $root = getenv('UNG_FIXTURE_ROOT');
        if ($root === false || $root === '') {
            throw new \RuntimeException('UNG_FIXTURE_ROOT is not set');
        }

        if (is_dir($root . '/ee')) {
            return Edition::Enterprise;
        }
        if (is_dir($root . '/pe')) {
            return Edition::Professional;
        }

        return Edition::Community;
    }
}

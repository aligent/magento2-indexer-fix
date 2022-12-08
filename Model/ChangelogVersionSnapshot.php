<?php

declare(strict_types=1);
namespace Aligent\IndexerFix\Model;

class ChangelogVersionSnapshot
{
    /**
     * @var int[]
     */
    private array $changelogVersions = [];

    /**
     * Set changelog version
     *
     * @param string $changelogName
     * @param int $version
     * @return void
     */
    public function setChangelogVersion(string $changelogName, int $version): void
    {
        $this->changelogVersions[$changelogName] = $version;
    }

    /**
     * Get changelog version
     *
     * @param string $changelogName
     * @return int|null
     */
    public function getChangelogVersion(string $changelogName): ?int
    {
        return $this->changelogVersions[$changelogName] ?? null;
    }
}

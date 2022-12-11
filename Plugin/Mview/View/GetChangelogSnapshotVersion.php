<?php

declare(strict_types=1);
namespace Aligent\IndexerFix\Plugin\Mview\View;

use Aligent\IndexerFix\Model\ChangelogVersionSnapshot;
use Magento\Framework\Mview\View\ChangelogInterface;

class GetChangelogSnapshotVersion
{

    /**
     * @param ChangelogVersionSnapshot $changelogVersionSnapshot
     */
    public function __construct(
        private readonly ChangelogVersionSnapshot $changelogVersionSnapshot
    ) {
    }

    /**
     * Get version for changelog from snapshot instead of database
     *
     * @param ChangelogInterface $subject
     * @param callable $proceed
     * @return int
     */
    public function aroundGetVersion(ChangelogInterface $subject, callable $proceed): int
    {
        $changelogName = $subject->getName();
        $version = $this->changelogVersionSnapshot->getChangelogVersion($changelogName);
        if ($version !== null) {
            return $version;
        }
        // could not get snapshot version, so proceed as normal
        return $proceed();
    }
}

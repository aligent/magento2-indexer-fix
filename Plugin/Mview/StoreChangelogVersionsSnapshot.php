<?php

declare(strict_types=1);
namespace Aligent\IndexerFix\Plugin\Mview;

use Aligent\IndexerFix\Model\ChangelogVersionSnapshot;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\Mview\Processor;
use Magento\Framework\Mview\View\ChangelogInterface;
use Magento\Framework\Mview\View\CollectionFactory;
use Magento\Framework\Mview\ViewInterface;

class StoreChangelogVersionsSnapshot
{

    /**
     * @param CollectionFactory $collectionFactory
     * @param ChangelogVersionSnapshot $changelogVersionSnapshot
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ChangelogVersionSnapshot $changelogVersionSnapshot,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Store the current value of each changelog version before updating any views.
     *
     * This is done so that changes occurring during index processing are not processed until the following run
     *
     * @param Processor $subject
     * @param string $group
     * @return array
     * @throws \Zend_Db_Select_Exception
     */
    public function beforeUpdate(Processor $subject, string $group = ''): array
    {
        $this->storeSnapshots($group);
        return [$group];
    }

    /**
     * Store the current value of each changelog version before making changes
     *
     * This is done so that changes occurring during index processing are not processed until the following run
     *
     * @param string $group
     * @return void
     * @throws \Zend_Db_Select_Exception
     */
    private function storeSnapshots(string $group): void
    {
        $views = $this->getViewsByGroup($group);
        $changelogs = [];
        foreach ($views as $view) {
            $changelogs[] = $view->getChangelog();
        }
        $changelogVersions = $this->getChangelogVersions($changelogs);
        foreach ($changelogVersions as $changelogName => $version) {
            $this->changelogVersionSnapshot->setChangelogVersion($changelogName, $version);
        }
    }

    /**
     * Return list of views by group
     *
     * @param string $group
     * @return ViewInterface[]
     */
    private function getViewsByGroup(string $group): array
    {
        $collection = $this->collectionFactory->create();
        return $group ? $collection->getItemsByColumnValue('group', $group) : $collection->getItems();
    }

    /**
     * Get changelog versions in union select.
     *
     * Union is used so that all versions are retrieved at the same time.
     *
     * @param ChangelogInterface[] $changelogs
     * @return array
     * @throws \Zend_Db_Select_Exception
     */
    private function getChangelogVersions(array $changelogs): array
    {
        $connection = $this->resourceConnection->getConnection();
        $selects = [];
        foreach ($changelogs as $changelog) {
            $changelogName = $changelog->getName();
            // skip if table doesn't exist
            if (!$connection->isTableExists($connection->getTableName($changelogName))) {
                continue;
            }
            $select = $connection->select();
            $select->from(
                $connection->getTableName($changelogName),
                [
                    'changelog_name' => new \Zend_Db_Expr($connection->quote($changelogName)),
                    'version' => new \Zend_Db_expr('MAX(version_id)')
                ]
            );
            $selects[] = $select;
        }
        $unionSelect = $connection->select()->union($selects, Select::SQL_UNION_ALL);
        return $connection->fetchPairs($unionSelect);
    }
}

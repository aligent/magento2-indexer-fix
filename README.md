# Magento 2 Scheduled Indexing Fix
This module is designed to fix a common issue around the scheduled "mview" indexing within Magento 2.

## Background
When indexers are set to update by schedule, the scenario is as follows:
 - Each indexer has a corresponding changelog database table.
 - Each indexer subscribes to a number of database tables - when a change is made in a subscribed table, a record is added to the changelog table for the indexer.

Additionally, some indexers use the data created by another indexer. For example, the `catalogsearch_fulltext` indexer may use indexed category and price data.

Indexed data is updated via the `indexer_update_all_views` cron job. This job loops through all scheduled indexers and updates its data based on the latest changelog record for each one.

## Issue
When `indexer_update_all_views` runs, it will get the changelog information for a single indexer, process it, and then move on to the next one.
Consider the following scenario:
 1. The `mview_state` table has `version_id` of `100` for both `catalog_product_price` and `catalogsearch_fulltext` records.
 2. Both `catalog_product_price_cl` and `catalogsearch_fulltext_cl` changelog tables are also at version `100`. 
 3. The prices of 10 products are updated
    1. This adds 10 records to `catalog_product_price_cl` and `catalogsearch_fulltext_cl`
    2. Latest version in the changelog tables is now `110`
 4. `indexer_update_all_views` begins, and starts to process the `catalog_product_price` indexer.
    1. It determines that it needs to process changelog records `101-110`
 5. Before the price indexer has finished processing, 10 more product prices are updated.
    1. 10 more recrds are added to `catalog_product_price_cl` and `catalogsearch_fulltext_cl`
    2. Latest version in the changelog tables is now `120`
 6. `indexer_update_all_views` finishes processing the price indexer, and moves on to `catalogsearch_fulltext`
    1. It determines that it needs to process changelog records `101-120`
 7. `indexer_update_all_views` completes
 8. The next run of `indexer_update_all_views` processes records `111-120` for the price indexer.

After this, everything _seems_ to be up to date - this is not the case.
Since the `catalogsearch_fulltext` indexer uses indexed price data, when it processed records `101-120`, it did not have up-to-date data for `111-120`, as these had not yet been processed by the price indexer.

The products corresponding to these last 10 changelog records will not be updated by the `catalogsearch_fulltext` indexer again until either:
 - The index is invalidated and a full re-index is performed.
 - The product gets updated again in some way.

## Fix
In order to avoid timing issues relating to updates happening at the same time as index processing, this module does the following:
 - Before `indexer_update_all_views` begins processing, a snapshot of all changelog tables is taken, recording the (at the time) latest version number.
 - An around plugin is added to the `ChangelogInterface->getVersion` function, so that the snapshot version is returned
   - If a snapshot does not exist, it is looked up from the database as per normal functionality

By doing this, any new changelog records that are created while the job is running will be ignored, and instead processed by the next run.

## Installation
To install this module, the following commands can be run:
```shell
composer require aligent/magento2-indexer-fix
bin/magento module:enable Aligent_IndexerFix
bin/magento setup:upgrade
```

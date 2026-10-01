<?php

/*
 * Company Plugin

  NOTICE OF LICENSE

  This source file is subject to the Open Software License (OSL 3.0)

  DISCLAIMER

  Do not edit or add to this file if you wish to upgrade to newer
  versions in the future. If you wish to customize this extension for your
  needs please refer to http://www.company.com for more information.

  @author      Raman Basu <support@company.com>
  @package     Company/Plugin
  @copyright   Copyright (C) 2016 Company <support@company.com>
  @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Company\Plugin\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
                
        $table = $installer->getConnection()
            ->newTable($installer->getTable('job'))
            ->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_BIGINT,
                null,
                ['unsigned' => true,'identity' => true,'nullable' => false,
                'primary'  => true],
                'Entity id'
            )
            ->addColumn('submittedon', \Magento\Framework\DB\Ddl\Table::TYPE_DATE, null, [
                'nullable' => true,'default'  => null], 'submitted on')
            ->addColumn('processedon', \Magento\Framework\DB\Ddl\Table::TYPE_DATE, null, [
                'nullable' => true,'default'  => null], 'processed on')
           ->addColumn('status', \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER, null, [
                'nullable' => true,'default'  => 0], 'Content')
            ->addColumn(
                'local_file_source',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                \Magento\Framework\DB\Ddl\Table::MAX_TEXT_SIZE,
                [
                'nullable' => true,'default'  => null],
                'local_file_source'
            )
             ->addColumn(
                 'log_file_source',
                 \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                 \Magento\Framework\DB\Ddl\Table::MAX_TEXT_SIZE,
                 [
                    'nullable' => true,'default'  => null],
                 'log_file_source'
             )
            ->addColumn(
                'stack_trace',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                \Magento\Framework\DB\Ddl\Table::MAX_TEXT_SIZE,
                [
                    'nullable' => true,'default'  => null],
                'stack_trace'
            )
             ->addColumn('module', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 50, [
                'nullable' => true,'default'  => null], 'module name')
            ->addIndex($installer->getIdxName('job_index', ['submittedon']), ['submittedon'])
            ->setComment('Jobs item');

        $installer->getConnection()->createTable($table);
        $installer->endSetup();
    }
}

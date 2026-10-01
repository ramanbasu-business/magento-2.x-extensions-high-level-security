<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
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

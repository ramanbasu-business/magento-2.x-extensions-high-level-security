<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Company\Plugin\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Description of UpgradeSchema
 *
 * @author Company Plugin
 */
class UpgradeSchema implements \Magento\Framework\Setup\UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '6.0.1') < 0) {
            //echo "Running Company Plugin30 update";
            
            // Get module table
            $tableName = $installer->getTable('job');

            // Check if the table already exists
            /*if ($installer->getConnection()->isTableExists($tableName) == true) {
                $installer->getConnection()
                        ->addColumn(
                            $tableName,
                            'stack_trace',
                            [
                                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                                'length' => \Magento\Framework\DB\Ddl\Table::MAX_TEXT_SIZE,
                                'nullable' => true,
                                'comment' => 'stack_trace'
                            ]
                        );
            }*/
            
            //echo "Company update ... Done";
        }

        $installer->endSetup();
    }
}

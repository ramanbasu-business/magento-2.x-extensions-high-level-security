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


namespace Company\Plugin\Controller\Install;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;

/**
 * Description of Index
 *
 * @author raman
 */
class Index extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    protected $pageFactory;
    protected $database;
    protected $cacheTypeList;
    protected $cacheFrontendPool;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Lib\Database $database,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool
    ) {
        $this->pageFactory = $pageFactory;
        $this->database = $database;

        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;

        $this->rawResultFactory = $context->getResultFactory();
        parent::__construct($context);
    }
    
    public function execute()
    {
        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $resultResponse->setHeader('Content-Type', 'text/html');
        $output = "";

        $output .= "Creating job if it does not exist... <br>";
        $this->database->createJobTableIfNotExist();
        $output .= "Done....... <br>";
        
        $output .= "Checking stack_trace... <br>";
        if ($this->database->checkJobColumns("stack_trace") == true):
            $output .= "stack_trace has been created<br>";
        else:
            $output .= "stack_trace already exists<br>";
        endif;
        
        $output .= "Done....... <br>";
        
        /* Code to clean cache [ php bin/magento:cache:clean ] */
        try {
            //$_cacheTypeList = $this->_objectManager->create('Magento\Framework\App\Cache\TypeListInterface');
            //$_cacheFrontendPool = $this->_objectManager->create('Magento\Framework\App\Cache\Frontend\Pool');
            $types = ['config','layout','block_html','collections',
                'reflection','db_ddl','eav','config_integration',
                'config_integration_api','full_page','translate','config_webservice'];
            
            foreach ($types as $type) {
                $this->cacheTypeList->cleanType($type);
            }

            foreach ($this->cacheFrontendPool as $cacheFrontend) {
                $cacheFrontend->getBackend()->clean();
            }
            
            $output .= "Cache has been cleaned <br />";
        } catch (Exception $e) {
            $output .= $msg = 'Error during cache clean: ' . $e->getMessage();
        }

        $resultResponse->setContents($output);
        return $resultResponse;
    }
}

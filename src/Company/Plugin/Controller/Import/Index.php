<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */

namespace Company\Plugin\Controller\Import;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Company\Plugin\Lib\ImportAction;
use Company\Plugin\Helper\Data;

class Index extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    protected $database;
    protected $helper;
    
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\Database $database
    ) {
        $this->helper = $helper;
        $this->database = $database;
        $this->rawResultFactory = $context->getResultFactory();

        return parent::__construct($context);
    }

    public function execute()
    {
        $this->helper->preExecute();
        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);

        $resultResponse->setContents("");
        return $resultResponse;
    }
}

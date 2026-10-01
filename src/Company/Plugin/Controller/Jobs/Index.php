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


namespace Company\Plugin\Controller\Jobs;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Description of Jobs
 *
 * @author raman
 */
class Index extends \Magento\Framework\App\Action\Action
{
    protected $pageFactory;
    protected $coreRegistry;
    protected $encoder;
    
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Magento\Framework\Registry $coreRegistry,
        \Company\Plugin\Lib\Encoder $encoder
    ) {
        $this->pageFactory = $pageFactory;
        $this->coreRegistry = $coreRegistry;
        $this->encoder = $encoder;
        
        return parent::__construct($context);
    }
    
    public function execute()
    {
        $this->coreRegistry->unregister('isAuthenticated');
        
        $post = (array) $this->getRequest()->getPost();
        if (!empty($post)):
            $username   = $post['txtUserName'];
            $password = $post['txtPassword'];

            if ($username == null || $username == "" || $password == null || $password == ""):
                $this->coreRegistry->register('isAuthenticated', '0');
                $this->messageManager->addErrorMessage('Invalid login');
            else:
                if ($this->encoder->dbLogin($username, $password) == true):
                    $this->coreRegistry->register('isAuthenticated', '1');
                else:
                    $this->coreRegistry->register('isAuthenticated', '0');
                    $this->messageManager->addErrorMessage('Invalid login');
                endif;
            endif;
        endif;
        
        $resultPage = $this->pageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Jobs'));
        
        return $resultPage;
    }
}

<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */


namespace Company\Plugin\Controller\Jobs;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Description of Jobs
 *
 * @author Company Plugin
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
                if ($this->encoder->validateMagentoUserCredentials($username, $password) == true):
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

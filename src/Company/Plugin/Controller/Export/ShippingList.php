<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */

namespace Company\Plugin\Controller\Export;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;

class ShippingList extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    protected $pageFactory;
    protected $request;
    private $helper;
    private $encoder;
    protected $utilityService;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\Encoder $encoder,
        \Company\Plugin\Lib\UtilityService $utilityService
    ) {
        $this->pageFactory = $pageFactory;
        $this->request = $this->getRequest();
        $this->helper = $helper;
        $this->encoder = $encoder;
        $this->utilityService = $utilityService;
        $this->rawResultFactory = $context->getResultFactory();
        
        return parent::__construct($context);
    }

    public function execute()
    {
        $this->helper->preExecute();
        
        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $this->helper->httpHeader($resultResponse);

        /**
        * -----------------------------------------------
        * Authentication phase starts
        * ===============================================
        **/
        $qryStrArray = $this->encoder->authenticate();

        if ($qryStrArray["is_authenticated"] == 0) {
            $resultResponse->setContents($qryStrArray["message"]);
            return $resultResponse;
        }
        
        /**
        * -----------------------------------------------
        * Authentication phase ends
        * ===============================================
        **/

        $shippingMethods = $this->utilityService->getAllShippingMethods();
        
        $output = '<?xml version="1.0" encoding="UTF-8" ?>';
        $output .= '<carriers>';
        $output .= $this->helper->printModuleVersion();

        foreach ($shippingMethods as $key => $method) {
            if (trim($key) == "" || empty($method["label"])) {
                continue;
            }
            //echo "code: [" . $key . "] " . is_array($method["value"]) . "\r\n";
            $output .= '<carrier>';
            $output .= '<code>' . $key . '</code>';
            $output .= '<label><![CDATA[' . $method["label"] . ']]></label>';
            
            $output .= '<options>';
            
            if (is_array($method["value"])):
                foreach ($method["value"] as $option):
                    $output .= '<option>';
                    $output .= '<value>' . $option["value"] . '</value>';
                    $output .= '<title><![CDATA[' . $option["label"] . ']]></title>';
                    $output .= '</option>';
                endforeach;
            endif;

            $output .= '</options>';
            $output .= '</carrier>';
        }

        $output .= '</carriers>';
        $resultResponse->setContents($output);
        unset($shippingMethods);

        return $resultResponse;
    }
}

<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */

namespace Company\Plugin\Lib;

class UtilityService
{
    /**
     * @var \Magento\Shipping\Model\Config\Source\Allmethods
     */
    protected $shippingAllmethods;
    
    public function __construct(
        \Magento\Rule\Model\Condition\Context $context,
        \Magento\Shipping\Model\Config\Source\Allmethods $shippingAllmethods,
        array $data = []
    ) {
        //parent::__construct($context, $data);
        $this->shippingAllmethods = $shippingAllmethods;
    }
    
    public function getAllShippingMethods()
    {
        return $this->shippingAllmethods->toOptionArray();
    }
}

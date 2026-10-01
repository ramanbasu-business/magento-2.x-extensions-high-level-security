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

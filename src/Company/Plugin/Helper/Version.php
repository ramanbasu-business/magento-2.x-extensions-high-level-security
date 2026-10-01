<?php

/*
 * Company Plugin

  NOTICE OF LICENSE

  This source file is subject to the Open Software License (OSL 3.0)

  DISCLAIMER

  Do not edit or add to this file if you wish to upgrade to newer
  versions in the future. If you wish to customize this extension for your
  needs please refer to http://www.company.com for more information.

  @author Raman Basu <raman@company.com>
  @package     Plugin_Imports
  @copyright   Copyright (C) 2016 Company <support@company.com>
  @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


namespace Company\Plugin\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

/**
 * Description of Test
 *
 * @author raman
 */
class Version extends \Magento\Framework\App\Helper\AbstractHelper
{
    public function getModuleVersion()
    {
        return '7.11.3';
    }
}

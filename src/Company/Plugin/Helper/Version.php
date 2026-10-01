<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */


namespace Company\Plugin\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

/**
 * Description of Test
 *
 * @author Company Plugin
 */
class Version extends \Magento\Framework\App\Helper\AbstractHelper
{
    public function getModuleVersion()
    {
        return '7.11.3';
    }
}

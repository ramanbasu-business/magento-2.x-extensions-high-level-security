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

class Encoder
{
    protected $helper;
    
    // COMMON PROPERTIES
    public $isEncryption = false;
    private $encryptionKey = "";
    private $authorizationHeader = "";
    private $apiUser="";
    private $apiPassword = "";
    private $verbose = 0;
    
    private $request;
    private $userFactory;
    
    /** @var $collection \Magento\User\Model\ResourceModel\User\Collection */
    private $userCollection;
    
    // objects for encryption methods
    private $aesMethod;
    
    public function __construct(
        \Magento\Framework\HTTP\PhpEnvironment\Request $request,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\AES $aesMethod,
        \Magento\User\Model\UserFactory $userFactory,
        \Magento\User\Model\ResourceModel\User\Collection $userCollection
    ) {
        $this->request = $request;
        $this->aesMethod = $aesMethod; // new \Company\Plugin\Lib\AES();
        $this->helper = $helper; //$this->objectManager->create("\Company\Plugin\Helper\Data");
        $this->userFactory = $userFactory;
        $this->userCollection = $userCollection;
    }
    
    public function authenticate($enabled = false, $encryption = false)
    {
        //\Zend_Debug::dump($this->request->getServer('QUERY_STRING'));
        //echo "<br>\r\n HTTP_AUTHORIZATION " . $_SERVER['HTTP_AUTHORIZATION'];
        $qryStrArray = [];

        $q = $this->request->getServer('QUERY_STRING');
        $q = $this->helper->cleanString($q);
        
        parse_str($q, $qryStrArray);
        
        $qryStrArray["is_authenticated"] = 0;
        $qryStrArray["message"] = "";
        
        $qryStrArray = array_map(
            function ($val) {
                return urldecode($val);
            },
            $qryStrArray
        );
        
        $qryStrArray = $this->makeParamLower($qryStrArray);
        
        if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] != ""):
            // High level security is enabled from company
            $this->isEncryption = true;
            $this->encryptionKey = $_SERVER['HTTP_ORIGIN'];
            
            if($this->verbose == 1) echo "HTTP_ORIGIN = " . $_SERVER['HTTP_ORIGIN'] . '<br />\r\n';

            if (isset($_SERVER['HTTP_AUTHORIZATION']) && $_SERVER['HTTP_AUTHORIZATION'] != ""):
                if($this->verbose == 1) echo "HTTP_AUTHORIZATION = " . $_SERVER['HTTP_AUTHORIZATION'];

                $this->authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'];
                $this->authorizationHeader = str_replace("BASIC ", "", $this->authorizationHeader);
                
                $arrAuthValues = explode(":", $this->authorizationHeader);

                if($this->verbose==1):
                    var_dump($arrAuthValues);
                endif;

                if (is_array($arrAuthValues) && count($arrAuthValues) == 2):
                    $enc_user = $arrAuthValues[0];
                    $enc_password = $arrAuthValues[1];
                    
                    $this->apiUser = $this->aesMethod->decrypt($enc_user, $this->encryptionKey);
                    $this->apiPassword = $this->aesMethod->decrypt($enc_password, $this->encryptionKey);
                endif;
            else:
                if($this->verbose == 1) echo "HTTP_AUTHORIZATION NOT SET <br />\r\n";
            endif;
        else:
            if($this->verbose == 1) echo "HTTP_ORIGIN NOT SET <br />\r\n";
        endif;
        
        $this->apiUser = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', trim($this->apiUser));
        $this->apiPassword = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', trim($this->apiPassword));
        
        
         //echo "<br>\r\n User " . $this->apiUser;
         //echo "<br>\r\n Pwd " . $this->apiPassword;
        
        $qryStrArray = $this->validateLogin($qryStrArray);

        return $qryStrArray;
        /*      * ******************************************************** */
        //*     @ End of Query string validation
    }

    private function validateLogin($qryStrArray)
    {
        $msg = "<?xml version=\"1.0\"?>\n<response>".
                $this->helper->printModuleVersion()
                . "<status>failure</status><message>%s</message></response>";
        try {
            if ($this->encryptionKey == "") {
                $qryStrArray["message"] = sprintf($msg, "Invalid HTTP_ORIGIN");
                return $qryStrArray;
            }

            if ($this->authorizationHeader == "") {
                $qryStrArray["message"] = sprintf($msg, "Invalid HTTP_AUTHORIZATION");
                return $qryStrArray;
            }

            if ($this->apiUser === "") {
                $qryStrArray["message"] = sprintf($msg, "Invalid User");
                return $qryStrArray;
            }
            
            if ($this->apiPassword === "") {
                $qryStrArray["message"] = sprintf($msg, "Invalid Password");
                return $qryStrArray;
            }
            
            if ($this->dbLogin($this->apiUser, $this->apiPassword)) {
                $qryStrArray["is_authenticated"] = 1;
                $qryStrArray["message"] = "<?xml version=\"1.0\"  encoding=\"ISO-8859-7\" ?><response>" .
                    $this->helper->printModuleVersion() .
                    "<status>success</status><message>User has been authenticated successfully</message></response>";

                return $qryStrArray;
            } else {
                $qryStrArray["message"] = sprintf($msg, "Authentication failed");
            }
        } catch (\Exception $ex) {
            $qryStrArray["message"] = sprintf($msg, $ex->getMessage());
        }

        return $qryStrArray;
    }

    private function makeParamLower($qryStrArray)
    {
        return array_change_key_case($qryStrArray, CASE_LOWER);
    }
    
    public function dbLogin($myAPILogin, $myAPIKey)
    {
        $blah = false;
        
        try {
            $this->userCollection->addFieldToFilter('username', $myAPILogin);
            $this->userCollection->load(false);
            
            if ($this->userCollection->getSize() > 0):
                foreach ($this->userCollection as $item):
                    $user = $this->userFactory->create()->load($item->getId());
                    if ($user->getId()):
                        $blah =$user->verifyIdentity($myAPIKey);
                    endif;
                    break;
                endforeach;
            endif;
                        
            if ($blah) {
                return true;
            }

            return false;
        } catch (\Exception $ex) {
            return false;
        }
    }
}

<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */

namespace Company\Plugin\Lib;

class Encoder
{
    protected $helper;

    // COMMON PROPERTIES
    public $isEncryption = false;
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
        $qryStrArray = [];

        $q = $this->request->getServer('QUERY_STRING');
        $q = $this->helper->cleanString($q);

        parse_str($q, $qryStrArray);

        $this->authorizationHeader = "";
        $this->apiUser = "";
        $this->apiPassword = "";

        $qryStrArray["is_authenticated"] = 0;
        $qryStrArray["message"] = "";

        $qryStrArray = array_map(
            function ($val) {
                return is_scalar($val) ? urldecode((string)$val) : $val;
            },
            $qryStrArray
        );

        $qryStrArray = $this->normalizeRequestParameterKeys($qryStrArray);

        $authorizationHeader = trim((string) $this->request->getServer('HTTP_AUTHORIZATION'));
        if ($authorizationHeader !== "") {
            $this->authorizationHeader = $authorizationHeader;
            $this->authorizationHeader = preg_replace('/^Basic\s+/i', '', $this->authorizationHeader);

            $decodedCredentials = base64_decode($this->authorizationHeader, true);
            if ($decodedCredentials !== false) {
                $authValues = explode(':', $decodedCredentials, 2);
                if (count($authValues) === 2) {
                    $this->apiUser = trim((string) $authValues[0]);
                    $this->apiPassword = trim((string) $authValues[1]);
                }
            }
        }

        $this->apiUser = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', trim($this->apiUser));
        $this->apiPassword = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', trim($this->apiPassword));

        $qryStrArray = $this->validateRequestAuthentication($qryStrArray);

        return $qryStrArray;
        /*      * ******************************************************** */
        //*     @ End of Query string validation
    }

    private function validateRequestAuthentication($qryStrArray)
    {
        $msg = "<?xml version=\"1.0\"?>\n<response>".
                $this->helper->printModuleVersion()
                . "<status>failure</status><message>%s</message></response>";
        try {
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

            if ($this->validateMagentoUserCredentials($this->apiUser, $this->apiPassword)) {
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

    private function normalizeRequestParameterKeys($qryStrArray)
    {
        return array_change_key_case($qryStrArray, CASE_LOWER);
    }

    public function validateMagentoUserCredentials($myAPILogin, $myAPIKey)
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

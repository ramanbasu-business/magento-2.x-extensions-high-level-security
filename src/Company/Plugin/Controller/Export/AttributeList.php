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
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory;

class AttributeList extends \Magento\Framework\App\Action\Action
{

    protected $attributeSetCollectionFactory;
    protected $pageFactory;
    protected $request;
    protected $helper;
    private $verbose = 0;
    private $encoder;
    protected $collection;
    private $rawResultFactory;
    private $isUserDefined = 1;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\Encoder $encoder,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\Collection $collection
    ) {
        $this->pageFactory = $pageFactory;
        $this->request = $this->getRequest();
        $this->helper = $helper;
        $this->encoder = $encoder;
        $this->collection = $collection;
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

        if (isset($qryStrArray["verbose"]) && $qryStrArray["verbose"] == 1):
            $this->verbose = 1;
        endif;

        /**
        * is_user_defined = 1 by default. Passing 0 will display system defined attributes
        * is_user_defined = 2 or so will display all attributes
        **/
        if (isset($qryStrArray["is_user_defined"]) && $qryStrArray["is_user_defined"] != ""):
            $this->isUserDefined = $qryStrArray["is_user_defined"];
        endif;

        /**
        * $this->collection->setEntityTypeFilter(4)
                ->addFieldToSelect('attribute_set_id', 'attribute_set_id')
                ->addFieldToSelect('attribute_set_name', 'attribute_set_name')
                ->addFieldToSelect('entity_type_id', 'entity_type_id')
                ->setOrder(
                    'attribute_set_id',
                    \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\Collection::SORT_ORDER_ASC
                );
        **/

        $this->collection = $this->helper->getAttributeSetDataArray();

        $output = '<?xml version="1.0" encoding="UTF-8" ?>';
        $output .= '<attribute_sets>';
        
        $output .= $this->helper->printModuleVersion();
        
        /** Magento\Eav\Model\Config
        * $defaultAttributeSetId = $this->eavConfig
        *    ->getEntityType(\Magento\Catalog\Api\Data\CategoryAttributeInterface::ENTITY_TYPE_CODE)
        *    ->getDefaultAttributeSetId();
        * var_dump ($defaultAttributeSetId);
        * */
        foreach ($this->collection as $aSet) {
            /** $attributeSetId = $aSet["attribute_set_id"]; **/
            $attributeSetId = $aSet["value"];
            $name = $aSet["label"]; /** $aSet["attribute_set_name"] **/
            $entityTypeId = 4; /** $aSet["entity_type_id"] **/

            $output .= '<attribute_set default="' . ($name == "Default" ? "true" : "false") . '">'
            . '<attribute_set_id>' . $attributeSetId . '</attribute_set_id>'
            . '<attribute_set_name><![CDATA[' . $name . ']]></attribute_set_name>'
            . '<entity_type_id>' . $entityTypeId . '</entity_type_id>';
            
            $attributes = $this->helper->getAttributes($attributeSetId);
            //if($this->verbose==1) {var_dump($attributes); }
            $output .= '<attributes>';
            
            if ($attributes != null):
                foreach ($attributes as $attribute):
                    if ($this->isUserDefined == 1 && $attribute['is_user_defined'] == 0):
                        continue;
                    endif;

                    if ($this->isUserDefined == 0 && $attribute['is_user_defined'] == 1):
                        continue;
                    endif;

                    $attributeCode = $attribute['attribute_code'];
                    $output .= '<attribute>'
                    . '<attribute_id>' . $attribute['attribute_id'] . '</attribute_id>'
                    . '<attribute_code>' . $attribute['attribute_code'] . '</attribute_code>'
                    . '<frontend_label><![CDATA[' . $attribute['frontend_label'] . ']]></frontend_label>'
                    . '<frontend_input>' . $attribute['frontend_input'] . '</frontend_input>'
                    . '<backend_type>' . $attribute['backend_type'] . '</backend_type>'
                    . '<is_required>' . $attribute['is_required'] . '</is_required>'
                    . '<is_user_defined>' . $attribute['is_user_defined'] . '</is_user_defined>';
                    
                    if ($attribute['frontend_input'] == 'select' || $attribute['frontend_input'] == 'multiselect'):
                        $options = $attribute->getOptions($attributeCode);

                        $output .= $this->getOptionValues($options);
                    endif;

                    $output .= '</attribute>';
                endforeach;
            endif;

            $output .= '</attributes>';
            $output .= '</attribute_set>';

            /** echo $output; **/
        }

        $output .= '</attribute_sets>';
        $resultResponse->setContents($output);
        return $resultResponse;
    }

    private function getOptionValues($options)
    {
        $output = '<optionvalues>';

        if ($options):
            foreach ($options as $optionValue):
                $output .= '<optionvalue>'
                . '<value>' . $optionValue['value'] . '</value>'
                . '<label><![CDATA[' . $optionValue['label'] . ']]></label>'
                . '</optionvalue>';
            endforeach;
        endif;

        $output .= '</optionvalues>';

        return $output;
    }
}

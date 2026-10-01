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


namespace Company\Plugin\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Attribute extends AbstractHelper
{

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterfac
     */
    protected $scopeConfig;
    protected $productAttributeRepository;

    /**
     * @var \Magento\Eav\Api\AttributeRepositoryInterface
     */
    protected $eavAttributeRepository;

    /**
     * @var AttributeFactory
     */
    protected $attributeFactory;

    /**
     * @var SetFactory
     */
    protected $attributeSetFactory;

    /**
     * @var TypeFactory
     */
    protected $eavTypeFactory;
    protected $eavSetupFactory;

    /**
     * @var \Magento\Framework\Cache\FrontendInterface
     */
    protected $attributeLabelCache;

    /**
     * @var \Magento\Catalog\Helper\Product
     */
    protected $productHelper;
    protected $eavConfig;
    protected $attributeManagement;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Model\Product\Attribute\Repository $pproductAttributeRepository,
        \Magento\Eav\Api\AttributeRepositoryInterface $peavAttributeRepository,
        \Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory $pattributeFactory,
        \Magento\Eav\Model\Entity\Attribute\SetFactory $pattributeSetFactory,
        \Magento\Eav\Model\Entity\Type $ptypeFactory,
        \Magento\Eav\Api\AttributeManagementInterface $pattributeManagement,
        \Magento\Eav\Setup\EavSetupFactory $peavSetupFactory,
        \Magento\Catalog\Helper\Product $pproductHelper,
        \Magento\Eav\Model\Config $peavConfig
    ) {
        $this->productAttributeRepository = $pproductAttributeRepository;
        $this->eavAttributeRepository = $peavAttributeRepository;
        $this->attributeFactory = $pattributeFactory;
        $this->attributeSetFactory = $pattributeSetFactory;
        $this->eavTypeFactory = $ptypeFactory;
        $this->attributeManagement = $pattributeManagement;
        $this->eavSetupFactory = $peavSetupFactory;
        $this->productHelper = $pproductHelper;
        $this->eavConfig = $peavConfig;

        parent::__construct($context);
    }

    public function getAttribute($attributeCode)
    {
        /* return $this->_eavAttributeRepository->get(
          \Magento\Catalog\Api\Data\CategoryAttributeInterface::ENTITY_TYPE_CODE,
          $attributeCode );
         */
        /** @var Attribute $attribute */
        $entityType = $this->eavTypeFactory->loadByCode('catalog_product');
        $attribute = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
        if (!$attribute->getId()) {
            return null;
        }

        return $attribute;
    }

    public function getAttributes($attributeSetId)
    {
        try {
            return $this
                        ->attributeManagement
                        ->getAttributes(
                            \Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE,
                            $attributeSetId
                        );
        } catch (\Magento\Framework\Exception\NoSuchEntityException $ex) {
            return null;
        }
    }

    public function getAttributeOptions($attributeCode)
    {
        $manufacturerOptions = $this->productAttributeRepository->get($attributeCode)->getAllOptions();
        return $manufacturerOptions;
    }

    public function createMissingAttribute(
        $_code,
        $_label,
        $frontend_input,
        $_product_type,
        $_attribute_set_id,
        $_configurable = null
    ) {
        $attribute = $this->createOrUpdateAttribute2(
            $_attribute_set_id,
            $_code,
            $_label,
            $frontend_input,
            $_product_type
        );
        return $attribute;
    }

    public function createOrUpdateAttribute($attributeCode, $label, $frontend_input = "", $apply_to = "")
    {
        $mode = "";
        $source = "";
        $type = "varchar";

        if ($frontend_input == ""):
            $frontend_input = "text";
            $type = "varchar";
        elseif ($frontend_input == "select"):
            $type = "int";
        endif;

        if ($apply_to == ""):
            $apply_to = "simple,virtual,downloadable";
        endif;

        $attribute = $this->getAttribute($attributeCode);

        if ($attribute == null):
            $mode = "A";
            $array = [
                'group' => 'General',
                'type' => $type,
                'frontend_input' => $frontend_input,
                'backend' => '',
                'frontend' => '',
                'label' => $label,
                'input' => $frontend_input,
                'class' => '',
                'source' => '',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => '0',
                'user_defined' => '1',
                'default' => '0',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => $apply_to
            ];
        else:
            $mode = "M";
            return $attribute;
        endif;

        try {
            $eavSetup = $this->eavSetupFactory->create();
            $eavSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, $attributeCode, $array);
            //$this->_attributeLabelCache->clean();
            $attribute = $this->getAttribute($attributeCode);
            $attribute->cleanModelCache();

            if ($mode == "A"):
                $array['status'] = 1;
                $array['message'] = 'Attribute ' . $attributeCode . ' has been added';
            else:
                $array['status'] = 1;
                $array['message'] = 'Attribute ' . $attributeCode . ' has been updated';
            endif;
        } catch (Exception $e) {
            $attribute = null;
        }

        return $attribute;
    }

    public function createOrUpdateAttribute2($setId, $attributeCode, $label, $frontend_input = "", $apply_to = "")
    {
        // $frontend_input = text, textarea, date, boolean, multiselect, select, price, media_image
        $source = "";
        $type = "varchar";
        if ($frontend_input == ""):
            $frontend_input = "text";
            $type = "varchar";
        elseif ($frontend_input == "select" || $frontend_input == "multiselect"):
            $type = "int";
        endif;

        if ($apply_to == ""):
            $apply_to = "simple,virtual,downloadable";
        endif;

        $attribute = $this->getAttribute($attributeCode);
        $attributeId = $attribute == null ? null : $attribute->getAttributeId();

        unset($attribute);
        $dataSaveNeeded = 0;
        
        $data = [];
        $data['attribute_code'] = $attributeCode;
        $data['frontend_input'] = $frontend_input;
        $data['type'] = $type;

        /* @var $model \Magento\Catalog\Model\ResourceModel\Eav\Attribute */
        $model = $this->attributeFactory->create();

        if ($attributeId):
            $model->load($attributeId);
            if (!$model->getId()) {
                return null;
            }
            //echo "attribute exists." . $attributeCode;
            //var_dump($model->getData());
            $data['attribute_code'] = $model->getAttributeCode();
            $data['is_user_defined'] = $model->getIsUserDefined();
            $data['frontend_input'] = $model->getFrontendInput();
            //$data['label'] = $label;
        else:
            /**
             * @todo add to helper and specify all relations for properties
             */
            $data['source_model'] = $this->productHelper->getAttributeSourceModelByInputType($data['frontend_input']);
            $data['backend_model'] = $this->productHelper
                        ->getAttributeBackendModelByInputType($data['frontend_input']);
            $data["global"] = \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL;
            //$data['label'] = $label;
            $data += ['is_filterable' => 0, 'is_filterable_in_search' => 0, 'apply_to' => []];
            
            $dataSaveNeeded = 1;
        endif;

        // 4.5.1.0
        //$data += ['is_filterable' => 0, 'is_filterable_in_search' => 0, 'apply_to' => []];
        
        if ($model->getIsUserDefined() == null || $model->getIsUserDefined() != 0):
            /**
            *var_dump("-------------------------" . $model->getBackendType() . "-------------");
            *var_dump("-------------------------"
            *. $model->getBackendTypeByInput($data['frontend_input']) . "-------------");
            **/

            if ($model->getBackendType() !== $model->getBackendTypeByInput($data['frontend_input'])):
                $data['backend_type'] = $model->getBackendTypeByInput($data['frontend_input']);
                $dataSaveNeeded = 1;
                //var_dump("----bb-----". $attributeCode . " - " . $dataSaveNeeded . ">> ");
            endif;
        endif;

        $defaultValueField = $model->getDefaultValueByInput($data['frontend_input']);

        if (!$model->getIsUserDefined() && $model->getId()) {
            // Unset attribute field for system attributes
            unset($data['apply_to']);
            $dataSaveNeeded = 1;
            //var_dump("----cc-----". $attributeCode . " - " . $dataSaveNeeded . ">> ");
        }

        $model->addData($data);

        if (!$attributeId) {
            $model->setEntityTypeId(4);
            $model->setIsUserDefined(1);
            
            // 4.5.1.0
            $model->setFrontendLabel($label);
            $dataSaveNeeded = 1;
            //var_dump("----dd-----". $attributeCode . " - " . $dataSaveNeeded . ">> ");
        }

        /** @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\Collection $setCollection */
        $attributeSet = $this->attributeSetFactory->create()
                ->getCollection()
                ->addFieldToFilter('entity_type_id', 4) //4
                ->addFieldToFilter('attribute_set_id', $setId)
                ->getFirstItem();

        $groupId = $attributeSet->getDefaultGroupId();
        
        //var_dump($model->getAttributeSetId());echo "\r\n\r\n";
        //var_dump($setId);echo "\r\n";
        //var_dump((int)$model->getAttributeSetId() === (int)$setId);
        if ((int)$model->getAttributeSetId() != (int)$setId):
            $model->setAttributeSetId($setId);
            $dataSaveNeeded = 1;
            
            //var_dump("----ee-----". $attributeCode . " - " . $dataSaveNeeded
            //. ">> " . $model->getAttributeSetId() . " " . $setId);
        endif;
        
        //var_dump($model->getAttributeGroupId());echo "\r\n\r\n";
        //var_dump($groupId);echo "\r\n";
        if ($model->getAttributeGroupId() !== $groupId):
            $model->setAttributeGroupId($groupId);
            $dataSaveNeeded = 1;
            //var_dump("----ff-----". $attributeCode . " - " . $dataSaveNeeded . ">> ");
        endif;
        
        // 4.5.1.0
        //$model->setFrontendLabel($label);
        
        try {
            //var_dump("---------". $attributeCode . " - " . $dataSaveNeeded . ">> ");
            if ($dataSaveNeeded == 1):
                $model->save();
            endif;
        } catch (\Exception $e) {
            throw($e);
        }

        return $model;
    }
    
    public function assignAttributeToSet($attributeCode, $attribute_set_id)
    {

        /** @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\Collection $setCollection */
        $attributeSet = $this->attributeSetFactory->create()
                ->getCollection()
                ->addFieldToFilter('entity_type_id', 4) //4
                ->addFieldToFilter('attribute_set_id', $attribute_set_id)
                ->getFirstItem();

        $groupId = $attributeSet->getDefaultGroupId();

        if ($groupId > 0):
            // Assign:
            $this->attributeManagement->assign(
                'catalog_product',
                $attribute_set_id,
                $groupId,
                $attributeCode,
                $attribute_set_id * 100
            );
            return true;
        endif;

        return false;
    }

    public function getAttribueValueFromCaption($attribute, $p_OptionCaption)
    {
        //$options = $attributeObject->getOptions();
        $options = $attribute->getSource()->getAllOptions();

        if ($options):
            foreach ($options as $option):
                if ($option["label"] == $p_OptionCaption):
                    return $option["value"];
                endif;
            endforeach;
            return 0;
        endif;
    }

    public function createMissingAttributeValue($attributeCode, $arg_value)
    {
        $ret = 0;
        $attribute = $this->getAttribute($attributeCode);
        if ($attribute == null || $attribute['attribute_id'] == null) {
            return $ret;
        }

        if (!($attribute->getFrontendInput() !== 'select' || $attribute->getFrontendInput() !== 'multiselect')):
            return $ret;
        endif;

        if ($arg_value === ''):
            return -1;
        endif;

        $ret = $this->getAttribueValueFromCaption($attribute, $arg_value);
        if ($ret > 0) {
            /** $this->_print("attribute caption already exists"); **/
            return $ret;
        }

        $option = [];
        $option['attribute_id'] = $attribute->getAttributeId();
        $option['value'][$arg_value][0] = $arg_value;

        $eavSetup = $this->eavSetupFactory->create();
        $eavSetup->addAttributeOption($option);

        // Re-fetching attribute
        $attribute = $this->getAttribute($attributeCode);
        $ret = $this->getAttribueValueFromCaption($attribute, $arg_value);
        if ($ret <= 0) {
            $ret = -1;
            /** _createMissingAttributeValue called, new option value created but could not be reloaded **/
        }

        unset($attribute, $eavSetup);
        return $ret;
    }

    public function removeAttribute($attributeCode)
    {
        $eavSetup = $this->eavSetupFactory->create();
        $eavSetup->removeAttribute(4, $attributeCode);
    }

    public function labelsToValueArray($attributeCode, array $labelsArray)
    {
        $attribute = $this->getAttribute($attributeCode);

        //1.0.2
        if ($attribute == null || $attribute->getSource() == null) {
            return null;
        }

        $options = $attribute->getSource()->getAllOptions();

        $values = [];
        if ($options):
            foreach ($options as $option):
                $values[$option['label']] = $option['value'];
            endforeach;
        endif;

        $valuesArray = [];
        foreach ($labelsArray as $label):
            if (isset($values[$label]) && $values[$label] != ""):
                $valuesArray[] = (int) $values[$label];
            endif;
        endforeach;

        unset($attribute, $options);
        return $valuesArray;
    }

    public function getRequiredAttributes1($_defAttrSetId, $_productType)
    {
        $_reqdAttrs = [];
        $attributes = $this->getAttributes($_defAttrSetId);
        foreach ($attributes as $attr):
            if ($attr["attribute_code"] == "media_gallery") {
                continue;
            }

            //echo strpos($attr->getData("apply_to"), $_productType) . "<br>";
            if (strpos($attr["apply_to"], $_productType) !== false || $attr["apply_to"] == null) {
                if ($attr->getIsRequired() === "1") {
                    $_reqdAttrs[] = $attr->getAttributeCode();
                }
            }
        endforeach;
        return $_reqdAttrs;
    }

    public function getRequiredAttributes($_defAttrSetId, $_productType)
    {
        $_reqdAttrs = [];
        $attributes = $this->getAttributes($_defAttrSetId);
        foreach ($attributes as $attr):
            if ($attr["attribute_code"] == "media_gallery") {
                continue;
            }

            $eav_attribute = $this->getAttribute($attr["attribute_code"]);
            if ($eav_attribute !== null):
                if($eav_attribute["apply_to"] == null):
                    $eav_attribute["apply_to"] = "";
                endif;

                if (strpos($eav_attribute["apply_to"], $_productType) !== false || $eav_attribute["apply_to"] == null):
                    if ($eav_attribute->getIsRequired() === "1"):
                        $_reqdAttrs[] = $eav_attribute->getAttributeCode();
                    endif;
                endif;
            endif;
        endforeach;
        return $_reqdAttrs;
    }
}

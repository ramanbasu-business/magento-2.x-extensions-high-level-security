<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */


namespace Company\Plugin\Helper;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;

class Matrix extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
     * @var StockItemRepositoryInterface
     */
    protected $stockItemRepository;
    
    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $stockRegistry;
    protected $attributeHelper;
    protected $productHelper;
    protected $productRepository;
    protected $productFactory;
    protected $optionsFactory;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\ConfigurableProduct\Helper\Product\Options\Factory $optionsFactory,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\CatalogInventory\Api\StockItemRepositoryInterface $stockItemRepository,
        \Company\Plugin\Helper\Attribute $attHelper
    ) {
        parent::__construct($context);

        $this->productHelper = $productHelper;
        $this->attributeHelper = $attHelper;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->optionsFactory = $optionsFactory;
        $this->stockRegistry = $stockRegistry;
        $this->stockItemRepository = $stockItemRepository;
    }

    private function isInStock($configurable)
    {
        $is_in_stock = 0;
        $allProducts = $configurable->getTypeInstance()->getUsedProductIds($configurable, null);
        //$allProducts = $configurable->getUsedProductCollection($configurable);
        foreach ($allProducts as $productId):
            $stockItem = $this->stockRegistry->getStockItem($productId);
            //var_dump($stockItem->getIsInStock());
            if ($stockItem && $stockItem->getIsInStock() == "1"):
                $is_in_stock = 1;
            endif;
        endforeach;
        
        return $is_in_stock;
    }
    
    public function manage($matrixProductId, $childProductIds, $commaAttributes)
    {
        
        $product = $this->productFactory->create()->load($matrixProductId);
                
        $configurableAttributesData = [];
        $associatedProductIds = $childProductIds;
        
        $arrAttributes = array_map('trim', explode(",", $commaAttributes));
        
        foreach ($arrAttributes as $commaAttributes):
            $attribute = $this->attributeHelper->getAttribute($commaAttributes);
        
            /** @var AttributeOptionInterface[] $options */
            $options = $attribute->getOptions();

            /**
            * remove the first option which is empty
            * $optionArray = $attribute->getSource()->getAllOptions();
            * var_dump($optionArray);
            * if($optionArray && count($optionArray) >0 && empty($optionArray[0]["label"])):
            **/
            array_shift($options);
            /** endif;
            //unset($optionArray);
            **/

            $attributeValues = [];

            foreach ($options as $option):
                $matrixProductId = array_shift($childProductIds);
                
                $attributeValues[] = [
                    'label' => $option->getLabel(),
                    'attribute_id' => (int)$attribute->getId(),
                    'value_index' => (int)$option->getValue(),
                ];
                //$associatedProductIds[] = $productId; // $product->getId();
            endforeach;

            $configurableAttributesData[] = [
                //[
                    'attribute_id' => $attribute->getId(),
                    'code' => $attribute->getAttributeCode(),
                    'label' => $attribute->getStoreLabel(),
                    'position' => '0',
                    'values' => $attributeValues,
                //],
            ];
        endforeach;
        
        /** var_dump($configurableAttributesData); **/
        $configurableOptions = $this->optionsFactory->create($configurableAttributesData);

        $extensionConfigurableAttributes = $product->getExtensionAttributes();
        $extensionConfigurableAttributes->setConfigurableProductOptions($configurableOptions);
        $extensionConfigurableAttributes->setConfigurableProductLinks($associatedProductIds);

        $product->setExtensionAttributes($extensionConfigurableAttributes);
               
        // Saving the configurable product
        try {
            //$product->setStockData(['is_in_stock' => 1]);
            $product
                    //->setName('P2 Configurable Product')
                    ->setTypeId(Configurable::TYPE_CODE)
                    //->setAttributeSetId(4)
                    //->setSku('P2')
                    //->setUrlKey($product->getUrlKey() . "-1")
                    ->setVisibility(Visibility::VISIBILITY_BOTH)
                    ->setStatus(Status::STATUS_ENABLED);
            
            $this->productRepository->save($product);
        } catch (Exception $ex) {
            throw new \Magento\Framework\Exception\LocalizedException($ex->getMessage());
        }
        
        // Save Stock for matrix parent
        $inStock = $this->isInStock($product);
        //var_dump($inStock);
        $stockItem = $this->stockRegistry->getStockItem($product->getId());
        
        /**
        if ($stockItem->getItemId()):
            //$stockItem->setItemId(null);
        endif;
        **/

        $stockItem->setIsInStock($inStock);
        $stockItem->setProductId($product->getId());
        $this->stockItemRepository->save($stockItem);
        
        return true;
    } // EOF
}

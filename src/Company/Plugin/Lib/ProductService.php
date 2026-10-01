<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */

namespace Company\Plugin\Lib;

use Magento\Framework\App\ResourceConnection;


class ProductService extends \Company\Plugin\Lib\CommonService
{
    protected $productLinkInterfaceFactory;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;
    protected $resourceFactory;
    protected $resourceConnection;
    protected $productRepositoryInterface;
    protected $storeManager;

    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var StockItemRepositoryInterface
     */
    /** protected $stockItemRepository; **/

    /**
     * @var \Magento\CatalogInventory\Api\StockConfigurationInterface
     */
    protected $stockConfiguration;

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory
     */
    protected $setColFactory;

    /**
     * Pairs of attribute set name-to-ID.
     *
     * @var array
     */
    protected $attrSetNameToId = [];
    protected $websiteIds = [];
    protected $allStoreIds = [];

    /**
     * @var array
     */
    protected $defaultStockData = [
        'manage_stock' => 1,
        'use_config_manage_stock' => 1,
        'qty' => 0,
        'is_qty_decimal' => 0,
        'is_in_stock' => 1,
        'min_qty' => 0,
        'use_config_min_qty' => 1,
        'min_sale_qty' => 1,
        'use_config_min_sale_qty' => 1,
        'max_sale_qty' => 10000,
        'use_config_max_sale_qty' => 1,
        'backorders' => 0,
        'use_config_backorders' => 1,
        'notify_stock_qty' => 1,
        'use_config_notify_stock_qty' => 1,
        'enable_qty_increments' => 0,
        'use_config_enable_qty_inc' => 1,
        'qty_increments' => 0,
        'use_config_qty_increments' => 1,
        'low_stock_date' => null,
        /* 'stock_status_changed_auto' => 0, */
        'is_decimal_divided' => 0,
    ];

    /**
     * DB connection.
     *
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $connection;
    protected $attributeHelper;
    protected $attribute2Helper;
    protected $imgHelper;
    protected $myEncoding;
    /**
     * @var \Magento\Catalog\Helper\Product\Edit\Action\Attribute
     */
    protected $systemAttributeHelper;
    
    protected $matrixHelper;
    private $helper;
    protected $tierPriceManagement;
    protected $categoryLinkRepository;

    /**
     * @var DateTime
     */
    protected $dateTime;
    protected $dateTimeFactory;

    /** MSI SOURCE UPDATE **/
    protected $sourceItemsSaveInterface;
    protected $sourceItemFactory;

    /** bundle product */
    


    public function __construct(
        ResourceConnection $resourceConnection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepositoryInterface,
        \Magento\Catalog\Model\ResourceModel\Product $resourceFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $setColFactory,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        /** \Magento\CatalogInventory\Api\StockItemRepositoryInterface $stockItemRepository, **/
        \Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Company\Plugin\Helper\Attribute $attHelper,
        \Company\Plugin\Helper\Image $imgHelper,
        \Company\Plugin\Lib\MyEncoding $myEncoding,
        \Company\Plugin\Helper\Matrix $matrixHelper,
        \Magento\Catalog\Helper\Product\Edit\Action\Attribute $systemAttributeHelper,
        \Company\Plugin\Helper\Attribute2 $att2Helper,
        \Company\Plugin\Helper\XmlLog $xmlLog,
        \Company\Plugin\Lib\Database $database,
        \Company\Plugin\Helper\Data $helper,
        \Magento\Catalog\Model\Product\TierPriceManagement $tierPriceManagement,
        \Magento\Catalog\Api\CategoryLinkManagementInterface $categoryLinkRepository,
        \Magento\Framework\Stdlib\DateTime\DateTimeFactory $dateTimeFactory,
        \Magento\Catalog\Api\Data\productLinkInterface $productLinkInterfaceFactory
        //\Magento\InventoryApi\Api\SourceItemsSaveInterface $sourceItemsSaveInterface,
        //\Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory $sourceItemFactory,
    ) {
        $this->storeManager = $storeManager;
        $this->productFactory = $productFactory;
        $this->setColFactory = $setColFactory;
        $this->resourceConnection = $resourceConnection;
        $this->resourceFactory = $resourceFactory;
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->connection = $this->resourceFactory->getConnection();
        $this->stockRegistry = $stockRegistry;
        $this->stockConfiguration = $stockConfiguration;
        
        /**
         * 6.8.7
         * $this->stockItemRepository = $stockItemRepository;
         * **/
        
        $this->attributeHelper = $attHelper;
        $this->attribute2Helper = $att2Helper;
        
        $this->imgHelper = $imgHelper;
        $this->systemAttributeHelper = $systemAttributeHelper;
        $this->myEncoding = $myEncoding;
        $this->matrixHelper = $matrixHelper;
        
        $this->helper = $helper;
        $this->tierPriceManagement = $tierPriceManagement;
        
        /** 6.5.2.1 **/
        $this->categoryLinkRepository = $categoryLinkRepository;
        
        $this->dateTimeFactory = $dateTimeFactory;
        $this->dateTime = $this->dateTimeFactory->create();

        $this->productLinkInterfaceFactory = $productLinkInterfaceFactory;

        /** 6.10.7 bundle product **/
        

        parent::__construct($objectManager, $xmlLog, $database);

        /* 6.10.0 MSI may or may not be enabled */
        /** msi_sources **/
        //$this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
        //$this->sourceItemFactory = $sourceItemFactory;

        if($this->helper->IsMsiEnabled() == 1) {
            $this->sourceItemsSaveInterface = 
                $this->objectManager->create('Magento\InventoryApi\Api\SourceItemsSaveInterface');
            $this->sourceItemFactory = 
                $this->objectManager->create('Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory');
        }

        // Common method initializations
        $this->initAttributeSets();
        $this->initWebsites();
        // -----------------------------
    }

    public function setParameters($jobId, $verbose = 0)
    {
        parent::setParameters($jobId, $verbose);
        $this->allStoreIds = $this->helper->getAllStores($this->verbose);
    }

    public function __destruct()
    {
        $this->setDebugMessage('ProductService Service action is complete.');
        /** parent::__destruct(); **/
    }

    private function saveProductToStores($product)
    {
        // Rearranging so that array key contains the website_id
        $associatedWebsiteIds = $product->getWebsiteIds();
        foreach ($associatedWebsiteIds as $key => $value) {
            $associatedWebsiteIds[$value] = $value;
        }
        //var_dump($associatedWebsiteIds);

        $product->setStoreId(0)->save();

        foreach ($this->allStoreIds as $currentStoreId => $thisStore):
            $this->setDebugMessage("Iteration of allStoreIds: website id:"
                . $thisStore["website_id"] . " and store Id : " . $currentStoreId);

            if (array_key_exists($thisStore["website_id"], $associatedWebsiteIds)):
                $this->setDebugMessage("Saving product to website id:"
                    . $thisStore["website_id"] . " and store Id : " . $currentStoreId);
            
                // option 1
                $product->setStoreId($currentStoreId)->save();
            else:
                $this->setDebugMessage("product will not be updated to website id:"
                    . $thisStore["website_id"] . " and store Id : " . $currentStoreId);
            endif;
        endforeach;
    }

    public function importImages($feed_xml)
    {
        // Aug 24, 2018
        $store = $this->storeManager->getStore(\Magento\Store\Model\Store::ADMIN_CODE);
        $this->storeManager->setCurrentStore($store->getCode());
        
        $this->setDebugMessage('<b>Importing Images from XML----------</b>');
        $this->setDebugMessage('TIME :::: ' . date("H:i"));
        $this->setDebugMessage('------------------------------------------');

        //$this->_storeManager->setCurrentStore($store->getCode());
        $imagesToRemoveFromDisk = [];
        
        foreach ($feed_xml->product as $productXML):
            $sku = (string) $productXML->sku;
            $productId = $this->getIdBySku($sku);
            
            if ($productId == 0):
                $this->setDebugMessage($sku . ': does not exist... skipping image import');
                continue;
            endif;
            
            $this->setDebugMessage($sku . ": exists, updating images... ");
            $product = $this->productFactory->create()->load($productId);
            
            // raman 21 Nov, 2017. Need to have the same website ids
            $oldWebsiteIds = $product->getWebsiteIds();
            
            $this->setDebugMessage("Removing images for product id " . $productId);
            try {
                $this->imgHelper->removeImagesFromProduct($productId, $this->verbose);
                
                $this->setDebugMessage("Removed all images thru magento methods for product id " . $productId);
            } catch (\Exception $e) {
                $this->setDebugMessage("Error trying to remove all the existing images: " . $e->getMessage());
                
                // 6.5.2.2
                if ($this->helper->getEdition() !== "B2B"):
                    $this->setDebugMessage("Trying manually [removeMediaGalleryManually]");

                    try {
                        $this->database->removeMediaGalleryManually($productId, $this->verbose);
                        $product = $this->productFactory->create()->load($productId);
                    } catch (Exception $ex) {
                        $this->xmlLog->log($product['sku'], "error", $e->getMessage());
                    }
                else:
                    $this->setDebugMessage("Ignoring [removeMediaGalleryManually]. Magento Edition: "
                        . $this->helper->getEdition());
                endif;
            }
            
            $product->setMediaGallery(['images' =>  [], 'values' =>  []]);
            
            // raman 21 Nov, 2017. Need to have the same website ids
            $product->setWebsiteIds($oldWebsiteIds);
            
            /** //$product->save(); **/
            $this->saveProductToStores($product);

            $product = $this->productFactory->create()->load($productId);

            if (!(isset($productXML->thumbnail)
                || isset($productXML->small_image)
                || isset($productXML->image))):
                continue;
            endif;
            
            $this->setDebugMessage($sku . " Trying to prepare small, base and thumb images");
            $thumbnail = "";
            $small_image = "";
            $image = "";
            $exclude_small_image = $exclude_thumbnail = $exclude_image = false;
            
            if (isset($productXML->thumbnail) && isset($productXML->small_image) & isset($productXML->image)
                && (string) $productXML->thumbnail == (string) $productXML->small_image
                && (string) $productXML->small_image == (string) $productXML->image):
                $thumbnail = $this->imgHelper->importImage2((string) $productXML->thumbnail, $this->verbose);
                
                if ($thumbnail == "") {
                    continue;
                }

                $image = $small_image = $thumbnail;
                
                if (isset($productXML->thumbnail["exclude"])):
                    $exclude_thumbnail = (int) $productXML->thumbnail["exclude"] == 1 ? true : false;
                endif;

                if (isset($productXML->small_image["exclude"])):
                    $exclude_small_image = (int) $productXML->small_image["exclude"] == 1 ? true : false;
                endif;
                
                if (isset($productXML->image["exclude"])):
                    $exclude_image = (int) $productXML->image["exclude"] == 1 ? true : false;
                endif;
                
                $product->setImage($image) //'/test.jpg'
                    ->setSmallImage($image)
                    ->setThumbnail($image)
                    ->addImageToMediaGallery($image, ['image','thumbnail','small_image'], false, false);
                
                $this->saveProductToStores($product);

                $this->setDebugMessage("Setting images... done");
                
                $imagesToRemoveFromDisk[] = $image;
            else:
                if (isset($productXML->thumbnail)):
                    $thumbnail = $this->imgHelper->importImage2((string) $productXML->thumbnail, $this->verbose);
                    $exclude_thumbnail = false;
                    
                    if ($thumbnail == "") {
                        continue;
                    }

                    if (isset($productXML->thumbnail["exclude"])):
                        $exclude_thumbnail = (int) $productXML->thumbnail["exclude"] == 1 ? true : false;
                    endif;
                
                    $product->setThumbnail($thumbnail);
                    $product->addImageToMediaGallery($thumbnail, ['thumbnail'], false, $exclude_thumbnail);
                    $this->setDebugMessage("Thumbnail: " . (string) $productXML->thumbnail . " > " . $thumbnail);
                
                    $imagesToRemoveFromDisk[] = $thumbnail;
                endif;

                if (isset($productXML->small_image)):
                    $small_image = $this->imgHelper->importImage2((string) $productXML->small_image, $this->verbose);
                    $exclude_small_image = false;
                
                    if ($small_image == "") {
                        continue;
                    }

                    if (isset($productXML->small_image["exclude"])) {
                        $exclude_small_image = (int) $productXML->small_image["exclude"] == 1 ? true : false;
                    }

                    $this->setDebugMessage("Small Image: "
                        . (string) $productXML->small_image . " > " . $small_image);
                    
                    /** //$product->setSmallImage($small_image); **/
                    $product->setSmallImage($small_image);
                    $product->addImageToMediaGallery($small_image, ['small_image'], false, $exclude_small_image);
                
                    $imagesToRemoveFromDisk[] = $small_image;
                endif;
            
                if (isset($productXML->image)):
                    /**
                    *if ((string) $productXML->image == (string) $productXML->thumbnail)
                    *$image = $thumbnail;
                    *else if ((string) $productXML->image == (string) $productXML->small_image)
                    *$image = $small_image;
                    *else
                    **/

                    $image = $this->imgHelper->importImage2((string) $productXML->image, $this->verbose);
                    $exclude_image = false;
                
                    if ($image == "") {
                        continue;
                    }

                    if (isset($productXML->image["exclude"])) {
                        $exclude_image = (int) $productXML->image["exclude"] == 1 ? true : false;
                    }
                
                    /** $product->setImage($image); **/
                    $product->addImageToMediaGallery($image, ['image'], false, $exclude_image);
                
                    //$arr_imageLabels['image'] = isset($productXML->image["label"]) ? (string)
                    //$productXML->image["label"] : "";
                    $this->setDebugMessage("Image: " . (string) $productXML->image . " > " . $image);
                
                    /** //$product->save(); **/
                    $imagesToRemoveFromDisk[] = $image;
                endif;
            
                /** $product->save(); **/
                $this->saveProductToStores($product);
            endif;

            $this->setDebugMessage('TIME :::: ' . date("H:i"));
        endforeach;
        
        $this->setDebugMessage('TIME :::: ' . date("H:i"));

        /**
        * @param directory where import image resides
        * @param leave 'null' so that it isn't imported as thumbnail, base, or small
        * @param false = the image is copied, not moved from the import directory to it's new location
        * @param false = not excluded from the front end gallery
        */
        foreach ($feed_xml->product as $productXML):
            $product = null;
            $sku = (string) $productXML->sku;
            $productId = $this->getIdBySku($sku);
            
            if ($productId == 0):
                $this->setDebugMessage($sku . ': does not exist... skipping gallery image import');
                continue;
            else:
                $this->setDebugMessage($sku . ": exists, updating... ");
                $product = $this->productFactory->create()->load($productId);
            endif;
            
            if (!isset($productXML->gallery_image)):
                continue;
            endif;
            
            foreach ($productXML->gallery_image as $galleryimage):
                $galleryImageUrl = (string)$galleryimage;
                $exclude = false;
                if (isset($galleryimage["exclude"])) {
                    $exclude = (int) $galleryimage["exclude"] == 1 ? true : false;
                }
                
                try {
                    $gImage = $this->imgHelper->importImage2($galleryImageUrl, $this->verbose);
                    
                    if ($gImage != ""):
                        $product->addImageToMediaGallery($gImage, null, false, $exclude);
                        $imagesToRemoveFromDisk[] = $gImage;
                    endif;

                    $this->setDebugMessage("Gallery image : " . $galleryImageUrl . " > " . $gImage);
                    
                } catch (Exception $ex) {
                    $this->setDebugMessage("addImageToMediaGallery error: " . $ex->getMessage());
                }
            endforeach;
            
            /** // $product->save(); **/
            $this->saveProductToStores($product);
        endforeach;
        
        //version 13.8: remove images from /media/import
        try {
            foreach ($imagesToRemoveFromDisk as $img):
                if (!empty($img) && file_exists($img)) {
                    unlink($img);
                    $this->setDebugMessage('removing ' . $img);
                }
            endforeach;
        } catch (\Exception $exx) {
            $this->setDebugMessage("imagesToRemoveFromDisk error: " . $exx->getMessage());
        }
        unset($imagesToRemoveFromDisk);

        $this->setDebugMessage($this->imgHelper->getDebugMessage());
        $this->setDebugMessage('TIME :::: ' . date("H:i"));
        $this->setDebugMessage('------------------------------------------');
    }
    
    public function importDescriptions($feed_xml)
    {
        $this->setDebugMessage('Updating descriptions from XML');

        $store = $this->storeManager->getStore(\Magento\Store\Model\Store::ADMIN_CODE);
        $this->storeManager->setCurrentStore($store->getCode());
        
        foreach ($feed_xml->product as $productXML):
            $sku = (string) $productXML->sku;
            $this->setDebugMessage('processing Sku ' . $sku);
            
            $productId = $this->getIdBySku($sku);
            
            if ($productId == 0):
                $this->setDebugMessage("sku: " . $sku . " not found");
                $this->xmlLog->log($sku, "error", "sku: " . $sku . " not found");
                continue;
            endif;
            
            $product = $this->productFactory->create()->load($productId);

            try {
                
                if (isset($productXML->description)):
                    $description = preg_replace("/\\n/m", "", (string)$productXML->description);
                    //$arrDataToUpdate["description"] = $this->myEncoding->fix UTF8($arrDataToUpdate["description"]);
                    $this->setDebugMessage("description = " . $description);
                    $product->setDescription($description);
                endif;
                
                if (isset($productXML->short_description)):
                    $short_description =
                        preg_replace("/\\n/m", "", (string)$productXML->short_description);

                    /*
                    * 6.9.1.0 php 8 deprecates utf8 functions (MyEncoding)
                    */
                    if (version_compare(PHP_VERSION, "8.0.0") <= -1):
                        $short_description =
                            $this->myEncoding->fixUTF8($short_description);
                    endif;

                    $this->setDebugMessage("short_description = " . $short_description);
                    $product->setShortDescription($short_description);
                endif;

                $product->save();
                
                $this->xmlLog->log($sku, "success", "");
            } catch (\Exception $ex) {
                $this->setDebugMessage($ex->getMessage());
                $this->xmlLog->log($sku, "error", $ex->getMessage());
            }
        endforeach;
    }

    public function importConfigurable($feed_xml)
    {
        $store = $this->storeManager->getStore(\Magento\Store\Model\Store::ADMIN_CODE);
        $this->storeManager->setCurrentStore($store->getCode());
        
        $this->setDebugMessage('<b>Creating configurable products from XML</b>');
        foreach ($feed_xml->product as $productXML):
            $typeId = (string) $productXML->type;
            if ($typeId !== "configurable"):
                continue;
            endif;
            
            $sku = (string) $productXML->sku;
            $configAttr = strtolower((string) $productXML['__configattr']);
            
            $this->setDebugMessage($sku . ': Updating / Assigning configurable attributes');
            $this->setDebugMessage($sku . ': Child Skus: ' . (string) $productXML['__childsku']);
            $this->setDebugMessage($sku . ': Config Attributes: ' . $configAttr);
            
            $matrixProductId = $this->getIdBySku($sku);
            $childProductIds = [];
            $childSkuArray = array_map('trim', explode(",", (string) $productXML['__childsku']));
            foreach ($childSkuArray as $eachSku):
                $childProductIds[] = $this->getIdBySku($eachSku);
            endforeach;
            
            try {
                $this->matrixHelper->manage($matrixProductId, $childProductIds, $configAttr);
                $this->xmlLog->log($sku, "success", "");
            } catch (\Exception $ex) {
                $this->setDebugMessage($ex->getMessage());
                $this->xmlLog->log($sku, "error", $ex->getMessage());
            }
        endforeach;
    }

    public function importBundle($feed_xml)
    {
        $store = $this->storeManager->getStore(\Magento\Store\Model\Store::ADMIN_CODE);
        $this->storeManager->setCurrentStore($store->getCode());
        
        $this->setDebugMessage('<b>Creating bundle products from XML</b>');
        foreach ($feed_xml->product as $productXML):
            $typeId = (string) $productXML->type;
            if ($typeId !== "bundle"):
                continue;
            endif;
            
            try {
                $sku = (string) $productXML->sku;
                $productId = $this->getIdBySku($sku);

                $product = $this->productFactory->create()->load($productId);
                
                $this->setDebugMessage("Assigning bundle attributes to product for " . $productId);
                
                /** 6.10.7 Assigning data to product for bundle product **/
                //sku type (0 - dynamic, 1 - fixed) 
                $skuType = isset($productXML->sku_type)? (int) $productXML->sku_type : 0;
                $product->setSkuType($skuType);

                // weight type (0 - dynamic, 1 - fixed)
                $weightType = isset($productXML->weight_type)? (int) $productXML->weight_type : 0;
                $product->setWeightType($weightType);

                // shipment_type (0 - together, 1 - separately)
                $shipmentType = isset($productXML->shipment_type)? (int) $productXML->shipment_type : 0;
                $product->setShipmentType($shipmentType);

                // price_view (0 - price range, 1 - as low as)
                $priceView = isset($productXML->price_view)? (int) $productXML->price_view : 0;
                $product->setPriceView($priceView);

                // price_type (0 - dynamic, 1 - fixed)
                $priceType = isset($productXML->price_type)? (int) $productXML->price_type : 0;
                $product->setPriceType($priceType);
                

                /** following properties are only available if price type is 'fixed=1' 
                 * but if price type is 'dynamic=0':
                **/
                if($priceType == 0):
                    $product->setPrice('');
                    $product->setTaxClassId(null);
                    $product->setMsrpEnabled(null); //enable MAP
                    $product->setMsrp(null); //Manufacturer's Suggested Retail Price
                    $product->setMsrpDisplayActualPriceType('');
                endif;

                /* If weight_type is dynamic, unset weight */
                if($weightType == 0):
                    $product->setWeight(null);
                endif;

                // Handle bundle options and selections
                $this->setBundleOptions($product, $productXML);
            
                // We need to set is_in_stock = 1.
                /**
                $stockItem = $this->stockRegistry->getStockItem($product->getId());
                $stockItem->setIsInStock(1);
                $stockItem->setProductId($product->getId());
                $stockItem->save();
                $this->setDebugMessage("stockItem->save() method called");

                $this->stockRegistry->updateStockItemBySku($product->getSku(), $stockItem);
                $this->setDebugMessage("Called updateStockItemBySku() by stockRegistry");
                * **/
                $isInStock = isset($productXML->is_in_stock)? (int) $productXML->is_in_stock : 1;
                $manageStock = isset($productXML->manage_stock)? (int) $productXML->manage_stock : 1;

                $product->setStockData(array(
                        'use_config_manage_stock' => 1, //'Use config settings' checkbox
                        'manage_stock' => $manageStock, //manage stock
                        'is_in_stock' => $isInStock, //Stock Availability
                    )
                );

                $this->setDebugMessage("Saving bundle attributes to product for " . $productId);
                $this->productRepositoryInterface->save($product);
                $this->xmlLog->log($sku, "success", "");
            } catch (\Exception $ex) {
                $this->setDebugMessage($ex->getMessage());
                $this->xmlLog->log($sku, "error", $ex->getMessage());
            }
        endforeach;
    }
    

    protected function setBundleOptions(&$bundle_product, $productXml)
    {
        // Set Bundle Options Data
        /*
        $bundle_product->setBundleOptionsData(
            [
                [
                    'title' => 'RAM',
                    'default_title' => 'RAM',
                    'type' => 'radio',
                    'required' => 1,
                    'delete' => '0',
                ],
                [
                    'title' => 'Bundle title 2',
                    'default_title' => 'Bundle Product Items title 2',
                    'type' => 'checkbox',
                    'required' => 1,
                    'delete' => '0',
                ]
            ]
        )->setBundleSelectionsData(
            [
                [
                    ['product_id' => 14, 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '0'],
                    ['product_id' => 15, 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '0'],
                ],
                [
                    ['product_id' => 16, 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '0'],
                    ['product_id' => 17, 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '0'],
                ],
            ]
        );**/

        $this->setDebugMessage('<b>Creating array of bundle option from XML</b>');
        $bundleOptionsData = [];
        $bundleSelectionData = [];


        foreach ($productXml->bundle_options->option as $option) {
            
            $bundleOption = [
                "title" => (string)$option->title,
                "default_title" => (string)$option->title,
                "type" => (string)$option->type,
                "required" => (int)$option->required,
                "delete" => (int)$option->delete,
            ];

            $bundleOptionsData[] = $bundleOption;

            $productLinks = [];
            foreach ($option->selections->selection as $selection) {
                
                $productLink = [
                    "product_id" => $this->getIdBySku((string)$selection->sku),
                    "selection_qty" => (int)$selection->selection_qty,
                    "selection_can_change_qty" => (int)$selection->selection_can_change_qty,
                    //"is_default" => true,
                    "delete" => (int)$option->delete
                ];

                if($bundle_product->getPriceType() == '1'):
                    if(isset($selection->selection_price_value) && (float)$selection->selection_price_value >= 0.00):
                        $productLink["selection_price_value"] = (float)$selection->selection_price_value;
                    endif;

                    if(isset($selection->selection_price_type) && (int)$selection->selection_price_type >= 0):
                        $productLink["selection_price_type"] = (int)$selection->selection_price_type;
                    endif;
                endif;
                
                $productLinks[] = $productLink;
            }

            $bundleSelectionData[] = $productLinks;
        }
        //print_r($bundleSelectionData);
        //exit();

        $bundle_product->setBundleOptionsData($bundleOptionsData);
        $bundle_product->setBundleSelectionsData($bundleSelectionData);

        $this->setDebugMessage('<b>Done</b>');
        /** bundle_product_two_dropdown_options.php **/
        // setBundleSelectionsData set your Product Id
        if ($bundle_product->getBundleOptionsData()) {
            $options = [];

            foreach ($bundle_product->getBundleOptionsData() as $key => $option_data) {
                
                $this->setDebugMessage('Bundle option delete=' . (bool)$option_data['delete']);

                if (!(bool)$option_data['delete']) {
                    $this->setDebugMessage('Bundle option to be created =' . (bool)$option_data['delete']);

                    $option = $this->objectManager->create('Magento\Bundle\Api\Data\OptionInterface');
                    $option->setData($option_data);
                    $option->setSku($bundle_product->getSku());
                    $option->setOptionId(null);
                    
                    $links_array = [];
                    $bundle_links_data = $bundle_product->getBundleSelectionsData();
                    
                    if (!empty($bundle_links_data[$key])) {
                        foreach ($bundle_links_data[$key] as $linkdata) {
                            if (!(bool)$linkdata['delete']) {
                                $link = $this->objectManager->create('Magento\Bundle\Api\Data\LinkInterface');
                                $link->setData($linkdata);
                                $linkProduct = $this->productRepositoryInterface->getById($linkdata['product_id']);
                                $link->setSku($linkProduct->getSku());
                                $link->setQty($linkdata['selection_qty']);
                                
                                if (isset($linkdata['selection_can_change_qty'])) {
                                    $link->setCanChangeQuantity($linkdata['selection_can_change_qty']);
                                }

                                // fixed amount or percent (when price_type=fixed)
                                if (isset($linkdata['selection_price_value'])) {
                                    $link->setPrice($linkdata['selection_price_value']);
                                }

                                // 0-fixed,1-percent (when price_type=fixed)
                                if (isset($linkdata['selection_price_type'])) {
                                    $link->setPriceType($linkdata['selection_price_type']);
                                }

                                $links_array[] = $link;
                            }
                        }
                        $option->setProductLinks($links_array);
                        $options[] = $option;
                    }
                }
            }

            $extension_attribute = $bundle_product->getExtensionAttributes();
            $extension_attribute->setBundleProductOptions($options);
            $bundle_product->setExtensionAttributes($extension_attribute);
        }
    }


    public function importSimple($feed_xml)
    {
        $store = $this->storeManager->getStore(\Magento\Store\Model\Store::ADMIN_CODE);
        $this->storeManager->setCurrentStore($store->getCode());
        
        $this->setDebugMessage('<b>Importing simple and grouped products from XML</b>');
        $this->setDebugMessage('TIME :::: ' . date("H:i"));

        foreach ($feed_xml->product as $productXML):
            $this->setDebugMessage('TIME :::: ' . date("H:i"));
            $typeId = (string) $productXML->type; // simple or grouped
            
            if ($typeId == 'simple' || $typeId == "configurable" || $typeId == 'grouped' || $typeId == 'bundle'):
                $__addNew = true;
                $__action = (string) $productXML->action;
                $__validate_attribute = 'true';
                $__defaultAttributeSetName = "";
                $sku = (string) $productXML->sku;

                if (isset($productXML['__defaultattributesetname']) == true) {
                    $__defaultAttributeSetName = (string) $productXML['__defaultattributesetname'];
                    if ($__defaultAttributeSetName == '') {
                        $this->xmlLog->log($sku, "error", "__defaultattributesetname is missing");
                        continue;
                    }
                }
                
                // x.5.0.4
                if (!isset($this->attrSetNameToId[$__defaultAttributeSetName])):
                    $this->xmlLog->log($sku, "error", "Invalid Default Attribute Set: " . $__defaultAttributeSetName);
                    continue;
                endif;

                $__defaultAttributeSetId = $this->attrSetNameToId[$__defaultAttributeSetName];
                
                if (isset($productXML['__validate_attribute']) == true) {
                    $__validate_attribute = (string) $productXML['__validate_attribute'];
                }

                $arrDataToUpdate = [];
                foreach ($productXML->children() as $a => $b) {
                    if (substr($a, 0, 2) == '__'
                            || $a == 'additional_attributes'
                            || $a == 'categories'
                            || $a == 'price'
                            || $a == 'tierprices'
                            || $a == 'type'
                            || $a == 'websiteids'
                            || $a == 'msi_sources'
                            ) {
                        $this->setDebugMessage("Ignored attributes: " . $a);
                    } else {
                        $arrDataToUpdate[$a] = (string) $b;
                    }
                }

                $productId = $this->getIdBySku($sku);
                if ($productId == 0):
                    $mode = "A";
                    $this->setDebugMessage($sku . ': does not exist... creating new');
                    $product = $this->productFactory->create();
                else:
                    $mode = "M";
                    $this->setDebugMessage($sku . ": exists, updating... ");
                    $product = $this->productFactory->create()->load($productId);
                endif;

                //$product->setData( $arrDataToUpdate );
                $product->setSku($sku); // Set your sku here
                $product->setName((string) $productXML->name); // Name of Product
                $product->setWeight((double) $productXML->weight); // weight of product
                
                //$product->setDescription((string) $productXML->description);
                if (isset($arrDataToUpdate["description"])):
                    $arrDataToUpdate["description"] = preg_replace("/\\n/m", "", $arrDataToUpdate["description"]);
                    //$arrDataToUpdate["description"] = $this->myEncoding->fix UTF8($arrDataToUpdate["description"]);
                    $product->setDescription($arrDataToUpdate["description"]);
                endif;
                
                if (isset($arrDataToUpdate["short_description"])):
                    $arrDataToUpdate["short_description"] =
                        preg_replace("/\\n/m", "", $arrDataToUpdate["short_description"]);

                    
                    /*
                    * 6.9.1.0 php 8 deprecates utf8 functions (MyEncoding)
                    */
                    if (version_compare(PHP_VERSION, "8.0.0") <= -1):
                        $arrDataToUpdate["short_description"] =
                            $this->myEncoding->fixUTF8($arrDataToUpdate["short_description"]);
                    endif;
                    $product->setShortDescription($arrDataToUpdate["short_description"]);
                endif;
                
                // defaults
                $product->setAttributeSetId($__defaultAttributeSetId); // Attribute set id
                $product->setStatus((int) $productXML->status); // enabled/ disabled 1/0
                
                // visibilty of product (catalog / search / catalog, search / Not visible individually)
                $product->setVisibility((int) $productXML->visibility);
                $product->setTaxClassId((int) $productXML->tax_class_id); // Tax class id
                $product->setTypeId($typeId); // type of product


                if (isset($productXML->websiteids)) {
                    $this->setDebugMessage("Setting websiteIDs: " . (string) $productXML->websiteids);
                    $arrWebIds = explode(",", (string) $productXML->websiteids);
                    $validWebIdsArray = $this->getValidWebsiteIdsArray($sku, $arrWebIds);
                    
                    $product->setWebsiteIds($validWebIdsArray);
                } else {
                    $product->setWebsiteIds($this->websiteIds);
                }

                if (isset($productXML->price)):
                    $thisPrice = (string) $productXML->price;
                    $thisPrice = str_replace(',', '', $thisPrice);
                    $product->setPrice((float) $thisPrice);
                    $arrDataToUpdate["price"] = (float) $thisPrice;
                endif;

                if (isset($arrDataToUpdate["special_price"])):
                    if ($arrDataToUpdate["special_price"] == "" || (float) $arrDataToUpdate["special_price"] == 0.0) {
                        $arrDataToUpdate["special_price"] = null;
                        $arrDataToUpdate["special_from_date"] = null;
                        $arrDataToUpdate["special_to_date"] = null;
                    } else {
                        $thisPrice = str_replace(',', '', (string) $arrDataToUpdate["special_price"]);
                        $arrDataToUpdate["special_price"] = floatval($thisPrice);
                    }
                endif;

                //assign product to categories
                $arrCategoryIds = null;
                if (isset($productXML->categories)) {
                    $arrCategoryIds = [];
                    
                    foreach ($productXML->categories->categoryid as $p_categoryID) {
                        $arrCategoryIds[] = (int) $p_categoryID;
                    }
                    $product->setCategoryIds($arrCategoryIds);
                    //$arrDataToUpdate["category_ids"] = $arrCategoryIds;
                }

                //$product->addData(array('msrp_enabled' => 2));
                //$product->addData(array('msrp_display_actual_price_type' => 4));
                $arrDataToUpdate['msrp_enabled'] = 2;
                $arrDataToUpdate['msrp_display_actual_price_type'] = 0; // i.e. "Use Config". M17 had 4

                if ((isset($arrDataToUpdate["url_key"]) && $arrDataToUpdate["url_key"] == "")) {
                    unset($arrDataToUpdate["url_key"]);
                }

                try {
                    $this->setDebugMessage("Retrieve custom attributes to array");
                    $arrAttrData = $this->updateCustomAttributesToArray($sku, $productXML, $__defaultAttributeSetId);
                    $this->setDebugMessage("Done...");
                    
                    $product->addData($arrDataToUpdate);
                    $product->addData($arrAttrData);
                    $storeId = $this->storeManager->getStore()->getId();
                    
                    //$product->setCategoryIds($arrCategoryIds);
                    //var_dump($product->getCategoryIds());
                    
                    $this->setDebugMessage("Store Id from store manager: " . $storeId);
                    
                    // 6.5.2.4
                    $created_at = $product->getCreatedAt();
                    if (!$created_at || $created_at == null || empty($created_at)
                        || $created_at == "0000-00-00 00:00:00") {
                        $this->setDebugMessage("Setting up created_at datetime");
                        
                        /**6.8.7.13 php 8.1.x strftime() is deprecated.
                        $product->setCreatedAt(
                            strftime('%Y-%m-%d %H:%M:%S', $this->dateTime->gmtTimestamp())
                        ); // time() - 10
                        **/
                        $product->setCreatedAt(
                            date('Y-m-d h:m:s', $this->dateTime->gmtTimestamp())
                        ); // time() - 10
                    }

                    $this->setDebugMessage("get created_at " . $product->getCreatedAt());
                    
                    $product->setUpdatedAt(time() - 5);
                                        

                    /*
                    //$product->setStoreId(1)->save();
                    //$this->productRepositoryInterface->save($product);
                    $product->setStoreId(0)->save();
                    $associatedWebsiteIds = $product->getWebsiteIds();
                    //var_dump($associatedWebsiteIds);

                    foreach ($this->allStoreIds as $currentStoreId => $value):
                        if(array_key_exists($value["website_id"] , $associatedWebsiteIds)):
                            $this->setDebugMessage("Saving product to website id:"
                            . $value["website_id"] . " and store Id : " . $currentStoreId);

                            // option 1
                            $product->setStoreId($currentStoreId)->save();

                            // option 2
                            //$product->setStoreId($storeId);
                            //$this->productRepositoryInterface->save($product);
                        else:
                            $this->setDebugMessage("product will not be updated to website id:"
                            . $value["website_id"] . " and store Id : " . $currentStoreId);
                        endif;
                    endforeach;
                    */

                    $this->setDebugMessage("Calling saveProductToStores[]");
                    $this->saveProductToStores($product);
                    $this->setDebugMessage("saveProductToStores[].... Done");

                    $this->setDebugMessage("Updating categories....");
                    
                    if ($arrCategoryIds != null):
                        $this->categoryLinkRepository->assignProductToCategories($sku, $arrCategoryIds);
                        $this->setDebugMessage("categories.... Done");
                    endif;
                    
                    $productId = $product->getId();

                    if (isset($productXML->tierprices)):
                        $this->updateTierPrice($product, $productXML);
                    endif;
                    

                    $msi_source_data = [];
                    if (isset($productXML->msi_sources)):
                        foreach ($productXML->msi_sources->source as $source):
                            $msi_source_data[] = [
                                "code" => (string)$source->code,
                                "qty" => (int)$source->qty
                            ];
                        endforeach;
                    endif;

                    $this->setDebugMessage("Updating inventory, typeId=" . $typeId);
                    if (!($typeId == "configurable" || $typeId == "bundle")):
                        $this->updateStock($product, $arrDataToUpdate, $msi_source_data);
                    endif;
                    
                    if ($__validate_attribute == 'true'):
                        $this->validateRequiredAttributes($sku, $product, $__defaultAttributeSetId);
                    endif;
                    
                    if ($mode == "A"):
                        $this->setDebugMessage($sku . ': added ');
                    else:
                        $this->setDebugMessage($sku . ': updated ');
                    endif;
                    
                    $this->xmlLog->log($sku, "success", "");
                } catch (\Exception $ex) {
                    $this->setDebugMessage($ex->getMessage());
                    $this->xmlLog->log($sku, "error", $ex->getMessage());
                }
                
                unset($product, $arrDataToUpdate);
            endif;

            $this->setDebugMessage('TIME :::: ' . date("H:i"));
        endforeach;

        $this->setDebugMessage('<b>Simple and grouped products: Done</b>');
        $this->setDebugMessage('TIME :::: ' . date("H:i"));
    }

    private function validateRequiredAttributes($sku, $product, $__defaultAttributeSetId)
    {
        $reqdAttrs = $this->attributeHelper
            ->getRequiredAttributes($__defaultAttributeSetId, "simple");
        
        foreach ($reqdAttrs as $_reqd) {
            if ($_reqd == "links_purchased_separately"
                    || $_reqd == "samples_title"
                    || $_reqd == "links_title"):
                continue;
            endif;

            if ($product->getData($_reqd) == null || $product->getData($_reqd) == "") {
                $this->setDebugMessage("Required attribute '$_reqd' is missing");
                $this->xmlLog->log($sku, "error", "Required attribute '$_reqd' is missing");
            }
        }
    }

    public function importRelatedProducts($feed_xml)
    {
        //$feed_xml = simplexml_load_file($myFilename);
        foreach ($feed_xml->product as $productXML) {
            if (isset($productXML['__relatedskus'])) {
                try {
                    $sku = (string) $productXML->sku;
                    $this->setDebugMessage('importing related skus for ' . $sku);

                    $productId = $this->getIdBySku($sku);
                    $this->setDebugMessage("Saving related products for " . $productId);
                    
                    if ($productId > 0):
                        $product = $this->productFactory->create()->load($productId);
                        $this->saveRelatedProducts((string) $productXML['__relatedskus'], $product);
                        //$this->log->LogInfo("importRelatedProducts: sku:" . $sku);
                        $this->xmlLog->log($sku, "success", "");
                    else:
                        //$this->log->LogInfo("importRelatedProducts: sku:" . $sku . " not found");
                        $this->xmlLog->log($sku, "error", "[importRelatedProducts] sku not found");
                    endif;
                } catch (Exception $ex) {
                    $this->setDebugMessage($ex->getMessage());
                    $this->xmlLog->log($sku, "error", "[importRelatedProducts] " . $ex->getMessage());
                }
            }
        }
    }
    
    public function importMini($feed_xml)
    {
        $this->setDebugMessage('Mini-updating price/inventory from XML');
        
        foreach ($feed_xml->product as $productXML):
            // Assignments
            $storeId = null;
            $sku = (string) $productXML->sku;
            $this->setDebugMessage('processing SKU ' . $sku);
            
            $productId = $this->getIdBySku($sku);
            
            if ($productId == 0):
                $this->setDebugMessage("sku: " . $sku . " not found");
                $this->xmlLog->log($sku, "error", "sku: " . $sku . " not found");
                continue;
            endif;
            
            $product = $this->productFactory->create()->load($productId);
            $__defaultAttributeSetId = $product->getAttributeSetId();
            
            $arrDataToUpdate = [];
            foreach ($productXML->children() as $a => $b):
                if (substr($a, 0, 2) == '__'
                        || $a == 'additional_attributes'
                        || $a == 'categories'
                        || $a == 'price'
                        || $a == 'tierprices'
                        || $a == 'type'
                        || $a == 'msi_sources'
                    ) {
                    $this->setDebugMessage("Ignored attributes: " . $a);
                } else {
                    $arrDataToUpdate[$a] = (string) $b;
                }
            endforeach;
            
            if (isset($productXML->storeid)):
                $storeId = (int) $productXML->storeid;
                $product->setStoreId($storeId);
            endif;
            
            if (isset($productXML->price) && $product->getTypeId()!== 'bundle') :
                $thisPrice = (string) $productXML->price;
                $thisPrice = str_replace(',', '', $thisPrice);
                $product->setPrice((float) $thisPrice);
                $arrDataToUpdate["price"] = (float) $thisPrice;
            endif;

            if (isset($arrDataToUpdate["special_price"])):
                if ($arrDataToUpdate["special_price"] == "" || (float) $arrDataToUpdate["special_price"] == 0.0) {
                    $arrDataToUpdate["special_price"] = null;
                    $arrDataToUpdate["special_from_date"] = null;
                    $arrDataToUpdate["special_to_date"] = null;
                } else {
                    $thisPrice = str_replace(',', '', (string) $arrDataToUpdate["special_price"]);
                    $arrDataToUpdate["special_price"] = floatval($thisPrice);
                }
            endif;

            if (isset($productXML->tierprices)):
                $this->updateTierPrice($product, $productXML);
            endif;
            
            $msi_source_data = [];
            if (isset($productXML->msi_sources)):
                foreach ($productXML->msi_sources->source as $source):
                    $msi_source_data[] = [
                        "code" => (string)$source->code,
                        "qty" => (int)$source->qty
                    ];
                endforeach;
            endif;

            if (isset($productXML->websiteids)
                && (string)$productXML->websiteids != ""
                && (string)$productXML->websiteids != "0"):
                
                $this->setDebugMessage('Setting websiteIDs: ' . (string) $productXML->websiteids . '');
                $arrWebIds = explode(",", (string) $productXML->websiteids);
                
                /** $product->setWebsiteIds($arrWebIds); **/
                $validWebIdsArray = $this->getValidWebsiteIdsArray($sku, $arrWebIds);
                    
                $product->setWebsiteIds($validWebIdsArray);
            endif;
            
            try {
                $arrAttrData = $this->updateCustomAttributesToArray($sku, $productXML, $__defaultAttributeSetId);

                $product->addData($arrDataToUpdate);
                $product->addData($arrAttrData);
                $product->save();
                
                // Save product first and then update stock
                if (isset($productXML->qty) && $product->getTypeId()!== 'bundle'):
                    $arrDataToUpdate["qty"] = (int) $productXML->qty;
                    $arrDataToUpdate["is_in_stock"] = $arrDataToUpdate["qty"] > 0 ? 1 : 0;
                    
                    if (isset($productXML->is_in_stock) && (string)$productXML->is_in_stock != "") {
                        $arrDataToUpdate["is_in_stock"] = (int) $productXML->is_in_stock;
                    }

                    // FIRE UPDATE QTY
                    $this->UpdateStock($product, $arrDataToUpdate, $msi_source_data);
                endif;

                $this->xmlLog->log($sku, "success", "");
            } catch (\Exception $ex) {
                $this->setDebugMessage($ex->getMessage());
                $this->xmlLog->log($sku, "error", $ex->getMessage());
            }
        endforeach;
    }
    
    private function saveRelatedProducts($p_relatedSku, $product)
    {
        // \tests\integration\testsuite\Magento\Catalog\_files\products_related_multiple.php
        if (!isset($p_relatedSku)) {
            return;
        }
        
        $relatedSkuArray = array_map('trim', explode(",", $p_relatedSku));
        
        $productLinksArray = [];
        $position = 0;
        foreach ($relatedSkuArray as $individualRelatedSku):
            // 4.3.1 Check if related sku product actually exists or not.
            $productId = $this->getIdBySku($individualRelatedSku);
            $this->setDebugMessage("saveRelatedProducts[] product id:" . $productId);

            if ($productId > 0):
                //$productLink1 = $this->productLinkInterfaceFactory->create();
                $productLink1 = $this->productLinkInterfaceFactory;
                $productLink1   ->setSku($product->getSku())
                                ->setLinkedProductSku($individualRelatedSku)
                                ->setPosition($position)
                                ->setLinkType('related');

                $productLinksArray[] = $productLink1;
                $position++;
            endif;
        endforeach;
        
        $product->setProductLinks($productLinksArray)->save();
        unset($productLinksArray);
    }
    
    private function updateStock($product, $arrDataToUpdate, $msi_source_data)
    {
        $this->setDebugMessage("updateStock[]");
        $this->setDebugMessage("Qty : " . $arrDataToUpdate["qty"]);
        $this->setDebugMessage('TIME :::: ' . date("H:i"));

        $stockItem = $this->stockRegistry->getStockItem($product->getId());
        $existStockData = $stockItem->getData();
        if (!$stockItem->getItemId()):
            $this->setDebugMessage("For new product, stock object is empty");
        else:
            unset($existStockData['item_id']);
        endif;

        /**
        *if (isset($arrDataToUpdate['qty'])) {
        *    //$arrDataToUpdate['qty'] = (double) $arrDataToUpdate['qty'];
        *}
        **/

        $row = [];
        $row = array_merge(
            $this->defaultStockData,
            array_intersect_key($existStockData, $this->defaultStockData),
            array_intersect_key($arrDataToUpdate, $this->defaultStockData),
            $row
        );
        
        // Overwrite and adjust
        
        if (isset($arrDataToUpdate['manage_stock'])) {
            $row['manage_stock'] = (int) $arrDataToUpdate['manage_stock'];
            $row['use_config_manage_stock'] = 0;
        }

        if (isset($arrDataToUpdate['is_qty_decimal'])) {
            $row['is_qty_decimal'] = (bool) $arrDataToUpdate['is_qty_decimal'];
        }

        // ---------------------- START ------------------------------------
        // V 25.4.0  Qty for Item's Status to Become Out of Stock ----------
        // When min_qty is provided, overwrite use_config_min_qty and is_in_stock
        if (isset($arrDataToUpdate['min_qty'])) {
            $row['min_qty'] = $arrDataToUpdate['min_qty'];
            $row['use_config_min_qty'] = 0;

            if ($arrDataToUpdate["qty"] <= $arrDataToUpdate['min_qty']):
                 $row['is_in_stock'] = 0;
            else:
                 $row['is_in_stock'] = 1;
            endif;
        }
        // ---------------------- END --------------------------------------
        // =================================================================
        
        // ---------------------- START ------------------------------------
        // V 25.4.0
        // When backorders is provided, overwrite use_config_backorders
        if (isset($arrDataToUpdate['backorders'])) {
            $row['backorders'] = $arrDataToUpdate['backorders'];
            $row['use_config_backorders'] = 0;
            
            if ($arrDataToUpdate['backorders'] == 1):
                $row['is_in_stock'] = 1;
            endif;
        }
        // ---------------------- END --------------------------------------

        if (isset($arrDataToUpdate['min_sale_qty'])) {
            $row['min_sale_qty'] = $arrDataToUpdate['min_sale_qty'];
            $row["use_config_min_sale_qty"] = 0;
        }

        if (isset($arrDataToUpdate['max_sale_qty'])) {
            $row['max_sale_qty'] = $arrDataToUpdate['max_sale_qty'];
            $row["use_config_max_sale_qty"] = 0;
        }

        // --------- START: Notify for Quantity Below ----------------------
        if (isset($arrDataToUpdate['use_config_notify_stock_qty'])) {
            if ($arrDataToUpdate['use_config_notify_stock_qty'] == 'true') {
                $row['use_config_notify_stock_qty'] = 1;
            }

            if ($arrDataToUpdate['use_config_notify_stock_qty'] == 'false') {
                $row['use_config_notify_stock_qty'] = 0;
            }
        }

        if (isset($arrDataToUpdate['notify_stock_qty'])) {
            $row['notify_stock_qty'] = $arrDataToUpdate['notify_stock_qty'];
            $row['use_config_notify_stock_qty'] = 0;
        }
        // ------- END: Notify for Quantity Below --------------------------
        // =================================================================

        // --------- START: Enable Qty Increments --------------------------
        if (isset($arrDataToUpdate['qty_increments'])) {
            $row["qty_increments"] = $arrDataToUpdate['qty_increments'];
            $row["use_config_qty_increments"] = 0;
        }
        // ------- END: Enable Qty Increments ------------------------------
        // =================================================================

        // --------- START: Enable Qty Increments --------------------------
        if (isset($arrDataToUpdate['enable_qty_increments'])) {
            $row["enable_qty_increments"] = $arrDataToUpdate['enable_qty_increments'];
            $row['use_config_enable_qty_increments'] = 0;
        }
        // --------- END: Enable Qty Increments --------------------------
        
        $this->setDebugMessage("is_in_stock=" . $row['is_in_stock']);
        
        $stockItem->addData($row);
        
        $stockItem->setProductId($product->getId());
        
        // testing 6.10.3
        $stockItem->save();
        $this->setDebugMessage("stockItem->save() method called");

        //$this->stockItemRepository->save($stockItem);
        $this->stockRegistry->updateStockItemBySku($product->getSku(), $stockItem);
        $this->setDebugMessage("Called updateStockItemBySku() by stockRegistry");

        /**
         * MSI may be disabled in 2.4.x
         * */
        if (!empty($msi_source_data) && $this->helper->IsMsiEnabled()):
            $sourceItemArray = [];

            foreach ($msi_source_data as $msi_source_data_item):
                $sourceItem = $this->sourceItemFactory->create();
                
                $sourceItem->setSourceCode($msi_source_data_item["code"]);
                $sourceItem->setSku($product->getSku());
                $sourceItem->setQuantity($msi_source_data_item["qty"]);
                $sourceItem->setStatus(1);

                $sourceItemArray[] = $sourceItem;
            endforeach;

            $this->sourceItemsSaveInterface->execute($sourceItemArray);
        endif;

        $this->setDebugMessage('TIME :::: ' . date("H:i"));
    }

    private function updateCustomAttributesToArray($sku, $productXML, $__defaultAttributeSetId)
    {
        $this->setDebugMessage('TIME :::: ' . date("H:i"));
        $arrDataToUpdate = [];

        if (!isset($productXML->additional_attributes)):
            return $arrDataToUpdate;
        endif;

        //$sku = $product->getSku();
        foreach ($productXML->additional_attributes->associativeentity as $p_Custom):
            $attr_key = strtolower((string) $p_Custom->key);
            $attr_value = (string) $p_Custom->value;
            $attr_label = isset($p_Custom->label) ? (string) $p_Custom->label : $attr_key;
            $attr_type = isset($p_Custom->type) ? (string) $p_Custom->type : "text";
            
            try {
                if ($attr_key == "description") {
                    continue;
                }
                if ($attr_key == "msrp_display_actual_price_type") {
                    if (is_numeric($attr_value)):
                        $arrDataToUpdate[$attr_key] = (int) $attr_value;
                        $this->setDebugMessage("Attribute '" . $attr_key . "' for value '" . $attr_value . "'");
                        continue;
                    endif;
                }
                
                $this->setDebugMessage("Creating and getting attribute. Key= " . $attr_key
                        . " label= '" . $attr_label . "' type= '" . $attr_type . "' caption= '" . $attr_value . "'");
                $attribute = $this->attributeHelper
                        ->createMissingAttribute(
                            $attr_key,
                            $attr_label,
                            $attr_type,
                            "",
                            $__defaultAttributeSetId,
                            0
                        );
                //$attribute = $this->_attributeHelper->getAttribute($attr_key);
                //var_dump($attribute->getData());
                if ($attribute == null):
                    $this->setDebugMessage("Attribute: '" . $attr_key . " is null");
                    $this->xmlLog->log($sku, "error", "Attribute: '" . $attr_key . " is null");
                    continue;
                endif;

                switch ($attribute->getFrontendInput()):
                    case '':
                        /** attribute missing, not found **/
                        $this->setDebugMessage("Missing Attribute: '" . $attr_key . " in magento");
                        $this->xmlLog->log($sku, "error", "Missing Attribute: '" . $attr_key . " in magento");
                        break;

                    case 'select':
                        $this->setDebugMessage("Checking attribute[select]: '"
                            . $attr_key . "' for caption '" . $attr_value . "'");

                        /** 7.7.0.0 Moved code to updateSelectAttributeToArray to conform standards**/
                        $arrDataToUpdate = $this->updateSelectAttributeToArray(
                            $sku,
                            $attr_key,
                            $attr_value,
                            $arrDataToUpdate
                        );
                        break;

                    case 'multiselect':
                        $labelsArray = array_map('trim', explode(',', $attr_value));
                        $valuesArray = $this->attributeHelper->labelsToValueArray($attr_key, $labelsArray);

                        if (is_array($valuesArray)) {
                            $attr_value = implode(',', $valuesArray);
                        }

                        $arrDataToUpdate[$attr_key] = $attr_value;
                        break;

                    default:
                        $arrDataToUpdate[$attr_key] = $attr_value;
                        //$product->setCustomAttribute($attr_key, $attr_value);
                        break;
                endswitch;

                // If backend_type is decimal, remove ","
                if ($attribute->getBackendType() == "decimal"):
                    $arrDataToUpdate[$attr_key] = str_replace(',', '', $attr_value);
                endif;
            } catch (\Exception $ex) {
                $this->setDebugMessage(
                    "updateCustomAttributesToArray Attribute:" . $attr_key . " " . $ex->getMessage()
                );
            }
        endforeach;

        $this->setDebugMessage('TIME :::: ' . date("H:i"));

        return $arrDataToUpdate;
    }

    private function updateSelectAttributeToArray($sku, $attr_key, $attr_value, $arrDataToUpdate)
    {
        $attr_value_id = 0;
        try {
            /**
            *raman - 06.dec.2017 - attribute2
            *https://magento.stackexchange.com/
            *questions/103934/magento2-programmatically-add-product-attribute-options
            *$attr_value_id = $this->_attributeHelper
            *->_createMissingAttributeValue($attr_key, $attr_value);
            **/

            $attr_value_id = $this->attribute2Helper
                ->createOrGetId($attr_key, $attr_value, $this->verbose);
            
            if ($attr_value_id && $attr_value_id > 0) {
                $arrDataToUpdate[$attr_key] = (int) $attr_value_id;
                
                $this->setDebugMessage("Custom Attribute[select] assigned: '" . $attr_key
                        . "' caption '" . $attr_value . "' ID: " . $attr_value_id);
            } else {
                /*
                 * attribute is of type 'select', but matching ID not found in magento.
                 * We should still update the attribute. Set to blank value
                 */
                $arrDataToUpdate[$attr_key] = null;
                $this->setDebugMessage("Custom Attribute[select] Unassigned: '" . $attr_key
                        . "' caption '" . $attr_value . "' ID: " . $attr_value_id);
            }
        } catch (\Exception $e) {
            $this->setDebugMessage("_createMissingAttributeValue[select]: " . $e->getMessage());
            $this->xmlLog->log(
                $sku,
                "error",
                "_createMissingAttributeValue[select]: Attribute:" . $attr_key . " " . $e->getMessage()
            );
        }

        return $arrDataToUpdate;
    }

    public function getIdBySku($sku)
    {
        // Works too
        //$product = $this->_productRepositoryInterface->get($sku);

        $entityTable = $this->resourceFactory->getEntityTable();
        $binds = ['sku' => $sku];
        $pid = $this->connection->fetchOne("SELECT entity_id FROM " . $entityTable . " WHERE sku=:sku", $binds);
        if (!empty($pid)) {
            return (int) ($pid);
        }
        return 0;
    }

    private function initAttributeSets()
    {
        $collection = $this->setColFactory->create()->setEntityTypeFilter(4)
                ->addFieldToSelect('attribute_set_id', 'attribute_set_id')
                ->addFieldToSelect('attribute_set_name', 'attribute_set_name');

        foreach ($collection as $attributeSet):
            $this->attrSetNameToId[$attributeSet->getAttributeSetName()] = $attributeSet->getId();
        endforeach;
    }

    protected function initWebsites()
    {
        /** @var $website \Magento\Store\Model\Website */
        foreach ($this->storeManager->getWebsites() as $website) {
            $this->websiteIds[$website->getId()] = $website->getId();
        }
    }

    protected function getValidWebsiteIdsArray($sku, $arrWebIds)
    {
        $validArray = [];
        foreach ($arrWebIds as $arrWebId):
            if (isset($this->websiteIds[$arrWebId])):
                $validArray[$arrWebId] = $arrWebId;
            else:
                $this->xmlLog->log(
                    $sku,
                    "error",
                    "Invalid website Id: " . $arrWebId . ". Product will still be updated"
                );
                
                $this->setDebugMessage("[getValidWebsiteIdsArray] Invalid website Id:"
                    . $arrWebId . ". Product will still be updated");
            endif;
        endforeach;
        
        return $validArray;
    }
    
    public function deleteProducts($feed_xml)
    {

        $this->setDebugMessage('Start deleting products from XML');
        
        // Register a secure area to simulate 'admin'
        // Mage::register('isSecureArea', true);
        foreach ($feed_xml->product as $productXML):
            $sku = trim((string) $productXML->sku);
            
            try {
                $productId = $this->getIdBySku($sku);
                $product = $this->productFactory->create()->load($productId);
                
                if ($product == null):
                    $this->xmlLog->log($sku, "error", "product does not exist...");
                    $this->setDebugMessage($sku . 'product does not exist...');
                    continue;
                endif;

                $this->setDebugMessage('processing SKU ' . $sku);
                
                // ---------------------------------------------
                // Start actual product deletion here
                // ---------------------------------------------
                $product->delete();
                
                $this->setDebugMessage($sku . ': deleted ');
                $this->xmlLog->log($sku, "success", "");
            } catch (Exception $ex) {
                $this->setDebugMessage($ex->getMessage());
                $this->xmlLog->log($sku, "error", $ex->getMessage());
            }
        endforeach;
    }
    
    protected function updateTierPrice($product, $productXML)
    {
        if (isset($productXML->tierprices)):
            $this->setDebugMessage($product->getSku() . ' Setting up tier prices');
            
            $websiteId = $this->helper->getWebsiteForPriceScope();
            $allGroupsId = $this->helper->getAllCustomerGroupsId();
            $this->setDebugMessage('getWebsiteForPriceScope = ' . $websiteId);
            $this->setDebugMessage('getAllCustomerGroupsId = ' . $allGroupsId);
            
            // Remove existing tier prices
            $existingTierPrices = $product->getTierPrices();
            
            foreach ($existingTierPrices as $exisingTierPrice):
                //var_dump($exisingTierPrice->getData());
                $this->tierPriceManagement->remove(
                    $product->getSku(),
                    $exisingTierPrice->getCustomerGroupId(),
                    $exisingTierPrice->getQty()
                );
            endforeach;

            /** 6.8.7.6 **/
            $table = $this->resourceConnection->getTableName('catalog_product_entity').'_tier_price';
            //$table = 'catalog_product_entity_tier_price';
            $this->connection->query("DELETE FROM $table WHERE entity_id = " . $product->getId());
            

            $productSaveRequired = false;

            if (isset($productXML->tierprices->tier)):
                $productSaveRequired = true;
            endif;

            if ($productSaveRequired == true):
                $product = $this->productFactory->create()->load($product->getId());
            endif;

            /** $this->database->removeTierPrices($product->getId(), $this->verbose); **/
            $this->setDebugMessage('removeTierPrices called');
            
            /**
            * D:\xampp\htdocs\m211\vendor\magento\module-catalog\Model\Product\TierPriceManagement.php
            * D:\xampp\htdocs\m211\vendor\magento\module-catalog\Model\Product\Type\Price.php
            **/

            $tierPrices = [];
            foreach ($productXML->tierprices->tier as $tier):
                /**
                * $_website_id = $websiteId; //((int) $tier->website_id);
                * 6.8.7.6
                * */
                if(isset($tier->website_id)):
                    $_website_id = ((int) $tier->website_id);
                else:
                    $_website_id = $websiteId;
                endif;

                $_cust_group = (int) $tier->cust_group;
                $_price_qty = (double) $tier->price_qty;

                $thisPrice = (string)$tier->price;
                $thisPrice = str_replace(',', '', $thisPrice);
                $_price = (double) $thisPrice;
                
                /** *
                 * 6.8.7.7: discount data is now accepted
                 **/
                if(isset($tier->percentage_value)):
                    $_percentage_value = (double) $tier->percentage_value;
                else:
                    $_percentage_value = 0;
                endif;

                /**
                * 6.8.5 If custer group id is sent as -1, treat that as all group id
                **/
                if ($_cust_group == -1):
                    $_cust_group = $allGroupsId;
                endif;

                $tierPrices[] = [
                    'website_id' => $_website_id,
                    'cust_group' => $_cust_group,
                    'price_qty' => $_price_qty,
                    'price' => $_price,
                    //'all_groups' => ($_cust_group == $allGroupsId),
                    'delete' => '',
                    'percentage_value' => $_percentage_value
                ];
            endforeach;
            
            if ($productSaveRequired == true):
                $this->setDebugMessage('tier prices: ' . json_encode(array_values($tierPrices)));

                $product->setTierPrice($tierPrices);
                $product->save();

                $this->setDebugMessage($product->getSku() . ' tier prices: array set');
            endif;
            
            unset($tierPrices);
        endif;
    }
}

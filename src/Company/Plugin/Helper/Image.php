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
use Magento\Store\Model\Store;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DriverInterface;

class Image extends AbstractHelper
{
    /**
     * @var \Magento\Catalog\Model\ProductFactory
     **/
    protected $productFactory;
    protected $productRepositoryInterface;
    protected $config;
    protected $fileSystem;
    protected $driverFile;

    /**
     * @var \Magento\Framework\Filesystem\Directory\WriteInterface
     */
    protected $mediaDirectory;

    protected $curlClient;

    protected $debugMessage;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepositoryInterface,
        \Magento\Catalog\Model\Product\Media\Config $config,
        \Magento\Framework\Filesystem $fileSystem,
        \Magento\Framework\Filesystem\Driver\File $driverFile,
        \Magento\Framework\HTTP\Client\Curl $curl
    ) {
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->productFactory = $productFactory;
        $this->config = $config;
        $this->fileSystem = $fileSystem;
        $this->mediaDirectory = $fileSystem->getDirectoryWrite(DirectoryList::ROOT);

        $this->driverFile = $driverFile;
        $this->curlClient = $curl;
        $this->debugMessage = "";
    }
    
    public function getDebugMessage()
    {
        return $this->debugMessage;
    }

    public function removeImagesFromProduct($productId, $verbose = 0)
    {
        $product = $this->productFactory->create()->load($productId);
        $existingMediaGalleryEntries = $product->getMediaGalleryEntries();
        foreach ($existingMediaGalleryEntries as $key => $entry) {
            unset($existingMediaGalleryEntries[$key]);
        }
        
        $product->setMediaGalleryEntries($existingMediaGalleryEntries);
        
        // Saving the product, associates with all website ids, which is bad.
        $this->productRepositoryInterface->save($product);
        
        if ($verbose == 1):
            $images = $product->getMediaGalleryImages();
            /**foreach ($images as $child) {
            *    //$this->debugMessage .= $child->getFile() . "\n\r<br>";
            }**/
        endif;
        
        unset($product);
    }
    
    public function removeImagesFromProduct2($productId)
    {
        $product = $this->product->load($productId);
        $images = $product->getMediaGalleryImages();
        foreach ($images as $child) {
            /** var_dump($child->getData()); **/
            $this->galleryProcessor->removeImage($product, $child->getFile());
        }
        
        // Saving the product, associates with all website ids, which is bad.
        //$this->productRepositoryInterface->save($product);
        //$product->save();
    }
    
    public function importImage2($imageUrl, $debug = false)
    {
        $importDir = $this->getImportFolder();
        $importDir = str_replace("\\", "/", $importDir);

        $imageUrl = str_replace(" ", "%20", $imageUrl);
        $capturedExtension = $this->findExtension($imageUrl);
        
        if (!($capturedExtension == "jpg"
            || $capturedExtension == "gif"
            || $capturedExtension == "png")) {
            $capturedExtension = "jpg";
        }

        $capturedExtension = $capturedExtension == null ? "jpg" : $capturedExtension;
        $capturedExtension = $capturedExtension == "" ? "jpg" : $capturedExtension;

        $newFileName = uniqid(rand(), false) . "." . $capturedExtension;
        if ($debug) {
            $this->debugMessage .= '\r\n<br />New image path: ' . $importDir . " " . $newFileName;
        }
    
        $fp = $this->driverFile->fileOpen($importDir . $newFileName, "w+");
        
        //If $fp is FALSE, something went wrong.
        if ($fp === false) {
            $this->debugMessage .= '\r\n<br />Could not open: ' . $imageUrl;
            return "";
        }

        /**
        //Open file handler.
        $ch = curl_init($imageUrl);

        //Pass our file handle to cURL.
        curl_setopt($ch, CURLOPT_FILE, $fp);

        //Timeout if the file doesn't download after 20 seconds.
        curl_setopt($ch, CURLOPT_TIMEOUT, 420);

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

        // added Feb 17, 2017 in m17. here on 17th march 2017
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 420);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        //Execute the request.
        $rawdata = curl_exec($ch);
        //If there was an error, throw an Exception
        if (curl_errno($ch)) {
            //throw new Exception($ch);
            throw new \Exception($rawdata);
        }
        //Get the HTTP status code.
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        //Close the cURL handler.
        curl_close($ch);

        if ($statusCode == 200) {
            if ($debug) {
                $this->debugMessage .= ' Downloaded';
            }
        } else {
            if ($debug) {
                $this->debugMessage .= ' Not Downloaded Status Code: ' . $statusCode;
            }
        }

        fwrite($fp, $rawdata);
        fclose($fp);
        **/

        try {
            $this->curlClient->get($imageUrl);

            $this->curlClient->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curlClient->setOption(CURLOPT_BINARYTRANSFER, true);
            $this->curlClient->setOption(CURLOPT_TIMEOUT, 420);
            $this->curlClient->setOption(CURLOPT_HEADER, 0);
            $this->curlClient->setOption(
                CURLOPT_USERAGENT,
                'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0'
            );

            $response = $this->curlClient->getBody();
            $this->debugMessage .= '\r\n<br />' . $imageUrl . ' Downloaded';
        } catch (\Magento\Framework\Exception\LocalizedException $ex) {
            if ($debug) {
                $this->debugMessage .= '\r\n<br /> Not Downloaded Status Code: ' . $ex->getMessage()
                . " " . $ex->getTraceAsString();
            }
        }

        $this->driverFile->fileWrite($fp, $response);
        $this->driverFile->fileClose($fp);
        $this->debugMessage .= '\r\n<br /> File resource closed';
        return $importDir . $newFileName;
    }

    public function getImportFolder()
    {
        $dirConfig = DirectoryList::getDefaultConfig();
        $dirAddon = $dirConfig[DirectoryList::MEDIA][DirectoryList::PATH];
        
        //var_dump($dirConfig);
        //var_dump("DIR ADDON: " . $dirAddon);
        
        $tmpPath = $dirAddon . "/" . $this->mediaDirectory->getRelativePath('import');
        $tmpPath = $this->mediaDirectory->getAbsolutePath($tmpPath);
        
        return $tmpPath . "/";
    }
    
    private function findExtension($filename)
    {
        $filename = strtolower($filename);
        $exts = explode(".", $filename);
        $n = count($exts) - 1;
        $exts = $exts[$n];
        return $exts;
    }
}

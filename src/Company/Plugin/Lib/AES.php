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

class AES
{
    private $method = 'aes-128-cbc';
    
    public function encryptOld($text, $pkey)
    {
        $key = $this->getPaddedKey($pkey);

        $IV = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC), MCRYPT_RAND);

        // If we needed a separator to distinguish IV value
        // Regarding the pattern: I don't think it can cause any trouble because
        // "-[--IV-[-" is not encrypted - so it's not providing any hints to the attacker
        // (such as possible words in the plain text) - he will only know where the
        // IV is in that data block which won't help too much.
        // return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $text,
        // MCRYPT_MODE_CBC, $IV) . "-[--IV-[-" . $IV);
        return base64_encode(
            mcrypt_encrypt(
                MCRYPT_RIJNDAEL_128,
                $key,
                $text,
                MCRYPT_MODE_CBC,
                $IV
            ) . $IV
        );
    }

    public function encrypt($text, $pkey)
    {
        $key = $this->getPaddedKey($pkey);

        // IV must be exact 16 chars (128 bit)
        $IV = chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0)
                . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0);
        
        $encrypted = openssl_encrypt($text, $this->method, $key, OPENSSL_RAW_DATA, $IV);
        $enc_encoded = base64_encode($encrypted . $IV);
        
        return $enc_encoded;
    }
    
    public function decryptOld($text, $pkey)
    {
        $key = $this->getPaddedKey($pkey);

        $text = base64_decode($text);
        
        // If we needed a separator to distinguish IV value
        //$IV = substr($text, strrpos($text, "-[--IV-[-") + 9); //echo strlen($IV);
        //$text = str_replace("-[--IV-[-".$IV, "", $text);
        $IV = substr($text, -16);
        $text = str_replace($IV, "", $text);

        return rtrim(
            mcrypt_decrypt(
                MCRYPT_RIJNDAEL_128,
                $key,
                $text,
                MCRYPT_MODE_CBC,
                $IV
            ),
            "\0"
        );
    }

    public function decrypt($text, $pkey)
    {
        $key = $this->getPaddedKey($pkey);

        $text = base64_decode($text);
        
        // If we needed a separator to distinguish IV value
        //$IV = substr($text, strrpos($text, "-[--IV-[-") + 9); //echo strlen($IV);
        //$text = str_replace("-[--IV-[-".$IV, "", $text);
        $IV = substr($text, -16);
        $text = str_replace($IV, "", $text);
        
        $decrypted = openssl_decrypt($text, $this->method, $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $IV);
        return rtrim($decrypted, "\0");
    }
    
    private function getPaddedKey($pkey)
    {
        $key = "";

        if (strlen($pkey) > 16):
            $key = substr($pkey, 0, 16);
        else:
            $key = str_pad($pkey, 16, "0", STR_PAD_RIGHT);
        endif;

        return $key;
    }
}

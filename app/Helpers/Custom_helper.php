<?php

//use Exception;

function GenerateString($length, $strToUpper = true, $underBar = true) : string
{
    $characters  = "0123456789";
    $characters .= "abcdefghijklmnopqrstuvwxyz";
    if ($strToUpper) {
        $characters .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    }
    if ($underBar) {
        $characters .= "_";
    }
    $string_generated = "";
    $nmr_loops = $length;
    while ($nmr_loops--) {
        $string_generated .= $characters[mt_rand(0, strlen($characters) - 1)];
    }

    return $string_generated;
}

function opensslEncryptData($data): string
{
    try {
        $key = getenv('LOCATION_AES_KEY');
        $iv = openssl_random_pseudo_bytes(16);
        return base64_encode(@openssl_encrypt($data, "AES-128-ECB", $key, true, $iv));
    } catch (Exception $exception) {
        return '';
    }
}

function opensslDecryptData($data)
{
    try {
        $key = getenv('LOCATION_AES_KEY');
        $iv = openssl_random_pseudo_bytes(16);
        $data = base64_decode($data);
        return @openssl_decrypt($data, "AES-128-ECB", $key, OPENSSL_RAW_DATA, $iv);
    } catch (Exception $exception) {
        return '';
    }
}

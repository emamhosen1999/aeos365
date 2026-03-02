<?php
$secret = 'change-this-in-production';
$product = 'AES';
$modules = 'ALL';
$key = 'AEOS365PROD2026';
$domain = 'aeos365.com';

$data = $product . $modules . $key . $domain;
$checksum = strtoupper(substr(hash_hmac('sha256', $data, $secret), 0, 5));
$licenseKey = 'EP-' . $product . '-' . $modules . '-' . $key . '-' . $checksum;
echo $licenseKey . PHP_EOL;

// Also generate without domain (in case domain is not passed)
$data2 = $product . $modules . $key;
$checksum2 = strtoupper(substr(hash_hmac('sha256', $data2, $secret), 0, 5));
$licenseKey2 = 'EP-' . $product . '-' . $modules . '-' . $key . '-' . $checksum2;
echo "Without domain: " . $licenseKey2 . PHP_EOL;

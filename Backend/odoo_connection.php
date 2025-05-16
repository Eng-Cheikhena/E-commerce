<?php
set_time_limit(300); // Increase execution time to 5 minutes
// Configuration
$product_url = '********************';// Odoo URL
$product_db = '**********';// Odoo database name
$product_username = '***************@*******';// Odoo database username
$product_password = '*************';// Odoo password

// Helper function to perform the cURL request
function performCurlRequestProduct($url, $xml)
{
  $client = curl_init();
  curl_setopt($client, CURLOPT_URL, $url);
  curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($client, CURLOPT_POST, true);
  curl_setopt($client, CURLOPT_HTTPHEADER, ['Content-Type: text/xml']);
  curl_setopt($client, CURLOPT_POSTFIELDS, $xml);

  $response = curl_exec($client);
  if (curl_errno($client)) {
    die('cURL Error: ' . curl_error($client));
  }

  curl_close($client);
  return $response;
}
function authenticateProduct($product_url, $product_db, $product_username, $product_password)
{
  $xml = '<?xml version="1.0"?>
        <methodCall>
            <methodName>authenticate</methodName>
            <params>
                <param><value><string>' . htmlspecialchars($product_db) . '</string></value></param>
                <param><value><string>' . htmlspecialchars($product_username) . '</string></value></param>
                <param><value><string>' . htmlspecialchars($product_password) . '</string></value></param>
                <param><value><struct></struct></value></param>
            </params>
        </methodCall>';

  $response = performCurlRequestProduct($product_url . 'xmlrpc/2/common', $xml);
  $xml_response = simplexml_load_string($response);

  if (isset($xml_response->params->param->value->int)) {
    return (int)$xml_response->params->param->value->int;
  } else {
    die('Authentication failed: ' . print_r($xml_response, true));
  }
}
?>
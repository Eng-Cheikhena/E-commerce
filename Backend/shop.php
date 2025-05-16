<?php
set_time_limit(300); // Increase execution time to 5 minutes

require_once "./odoo_connection.php";

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

// Set the response content type
header('Content-Type: application/json');

// Function to fetch all products from Odoo
function fetchAllProducts($product_url, $product_db, $product_uid, $product_password, $chunkSize = 500) {
    $allProducts = [];
    $offset = 0;

    while (true) {
        // Fetch products in chunks of $chunkSize
        $chunk = fetchProducts($product_url, $product_db, $product_uid, $product_password, $offset, $chunkSize);
        if (empty($chunk)) {
            break; // Exit loop if no more products
        }

        $allProducts = array_merge($allProducts, $chunk);
        $offset += $chunkSize;
    }

    return $allProducts;
}

// Function to fetch products from Odoo with offset and limit
function fetchProducts($product_url, $product_db, $product_uid, $product_password, $offset = 0, $limit = 500) {
    $xml = <<<XML
<?xml version="1.0"?>
<methodCall>
  <methodName>execute_kw</methodName>
  <params>
      <param><value><string>{$product_db}</string></value></param>
      <param><value><int>{$product_uid}</int></value></param>
      <param><value><string>{$product_password}</string></value></param>
      <param><value><string>product.template</string></value></param>
      <param><value><string>search_read</string></value></param>
      <param><value><array><data></data></array></value></param>
      <param><value><struct>
          <member>
              <name>fields</name>
              <value><array><data>
                  <value><string>id</string></value>
                  <value><string>name</string></value>
                  <value><string>list_price</string></value>
                  <value><string>default_code</string></value>
                  <value><string>image_1024</string></value>
                  <value><string>categ_id</string></value>
                  <value><string>qty_available</string></value>
              </data></array></value>
          </member>
          <member>
              <name>offset</name>
              <value><int>{$offset}</int></value>
          </member>
          <member>
              <name>limit</name>
              <value><int>{$limit}</int></value>
          </member>
      </struct></value></param>
  </params>
</methodCall>
XML;

    // Make the XML-RPC request
    $response = performCurlRequestProduct($product_url . 'xmlrpc/2/object', $xml);
    $xmlResponse = simplexml_load_string($response);

    if (!isset($xmlResponse->params->param->value->array->data->value)) {
        return []; // Return empty array if no products are found
    }

    $products = [];
    foreach ($xmlResponse->params->param->value->array->data->value as $product) {
        $productStruct = $product->struct->member;
        $productInfo = [];
        foreach ($productStruct as $member) {
            $name = (string) $member->name;
            $value = $member->value;

            switch ($name) {
                case 'id':
                    $productInfo['id'] = (int) $value->int;
                    break;
                case 'name':
                    $productInfo['name'] = (string) $value->string;
                    break;
                case 'list_price':
                    $productInfo['list_price'] = (float) $value->double;
                    break;
                case 'default_code':
                    $productInfo['default_code'] = (string) $value->string;
                    break;
                case 'image_1024':
                    $productInfo['image'] = $value->string ? 'data:image/png;base64,' . $value->string : null;
                    break;
                case 'categ_id':
                    $productInfo['category_name'] = isset($value->array->data->value[1]->string) ? (string) $value->array->data->value[1]->string : 'Unknown';
                    break;
                case 'qty_available':
                    $productInfo['available_stock'] = (float) $value->double;
                    break;
            }
        }
        $products[] = $productInfo;
    }

    return $products;
}

// API Endpoint: /api/products
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Authenticate and fetch all product data
    $product_uid = authenticateProduct($product_url, $product_db, $product_username, $product_password);
    $allProducts = fetchAllProducts($product_url, $product_db, $product_uid, $product_password);

    // Output JSON response
    echo json_encode([
        'status' => 'success',
        'data' => $allProducts
    ]);
    exit;
}
?>

<?php
set_time_limit(300); // Increase execution time to 5 minutes

require_once "./odoo_connection.php";

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

// Set the response content type
header('Content-Type: application/json');

// Function to send JSON responses
function sendJsonResponse($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Fetch product by ID
function fetchProductById($product_url, $product_db, $product_uid, $product_password, $prod_id)
{
  $filter = '<value><array><data>
                   <value><array><data>
                       <value><string>id</string></value>
                       <value><string>=</string></value>
                       <value><int>' . intval($prod_id) . '</int></value>
                   </data></array></value>
               </data></array></value>';

  $xml = '<?xml version="1.0"?>
        <methodCall>
            <methodName>execute_kw</methodName>
            <params>
                <param><value><string>' . $product_db . '</string></value></param>
                <param><value><int>' . $product_uid . '</int></value></param>
                <param><value><string>' . $product_password . '</string></value></param>
                <param><value><string>product.template</string></value></param>
                <param><value><string>search_read</string></value></param>
                <param><value><array><data>' . $filter . '</data></array></value></param>
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
                </struct></value></param>
            </params>
        </methodCall>';

  $response = performCurlRequestProduct($product_url . 'xmlrpc/2/object', $xml);
  $xml_response = simplexml_load_string($response);

  if (!isset($xml_response->params->param->value->array->data->value[0])) {
    return null; // No product found
  }

  $productStruct = $xml_response->params->param->value->array->data->value[0]->struct->member;
  $productInfo = [];

  foreach ($productStruct as $member) {
    $name = (string)$member->name;
    $value = $member->value;

    switch ($name) {
      case 'id':
        $productInfo['id'] = (int)$value->int;
        break;
      case 'name':
        $productInfo['name'] = (string)$value->string;
        break;
      case 'list_price':
        $productInfo['list_price'] = (float)$value->double;
        break;
      case 'default_code':
        $productInfo['default_code'] = (string)$value->string;
        break;
      case 'image_1024':
        $productInfo['image'] = $value->string ? 'data:image/png;base64,' . $value->string : null;
        break;
      case 'categ_id':
        $productInfo['category_id'] = (int)$value->array->data->value[0]->int;
        $productInfo['category_name'] = isset($value->array->data->value[1]->string) ? (string)$value->array->data->value[1]->string : 'Unknown';
        break;
      case 'qty_available':
        $productInfo['available_stock'] = (float)$value->double;
        break;
    }
  }

  return $productInfo;
}

// Fetch related products by category
function fetchProductsByCategory($product_url, $product_db, $product_uid, $product_password, $category_id, $exclude_id, $limit = 5)
{
  $filter = '<value><array><data>
                   <value><array><data>
                       <value><string>categ_id</string></value>
                       <value><string>=</string></value>
                       <value><int>' . intval($category_id) . '</int></value>
                   </data></array></value>
                   <value><array><data>
                       <value><string>id</string></value>
                       <value><string>!=</string></value>
                       <value><int>' . intval($exclude_id) . '</int></value>
                   </data></array></value>
               </data></array></value>';

  $xml = '<?xml version="1.0"?>
        <methodCall>
            <methodName>execute_kw</methodName>
            <params>
                <param><value><string>' . $product_db . '</string></value></param>
                <param><value><int>' . $product_uid . '</int></value></param>
                <param><value><string>' . $product_password . '</string></value></param>
                <param><value><string>product.template</string></value></param>
                <param><value><string>search_read</string></value></param>
                <param><value><array><data>' . $filter . '</data></array></value></param>
                <param><value><struct>
                    <member>
                        <name>fields</name>
                        <value><array><data>
                            <value><string>id</string></value>
                            <value><string>name</string></value>
                            <value><string>list_price</string></value>
                            <value><string>image_1024</string></value>
                        </data></array></value>
                    </member>
                    <member>
                        <name>limit</name>
                        <value><int>' . $limit . '</int></value>
                    </member>
                </struct></value></param>
            </params>
        </methodCall>';

  $response = performCurlRequestProduct($product_url . 'xmlrpc/2/object', $xml);
  $xml_response = simplexml_load_string($response);

  if (!isset($xml_response->params->param->value->array->data->value)) {
    return []; // No related products found
  }

  $products = [];
  foreach ($xml_response->params->param->value->array->data->value as $value) {
    $productStruct = $value->struct->member;
    $product = [];
    foreach ($productStruct as $member) {
      $name = (string)$member->name;
      $value = $member->value;

      switch ($name) {
        case 'id':
          $product['id'] = (int)$value->int;
          break;
        case 'name':
          $product['name'] = (string)$value->string;
          break;
        case 'list_price':
          $product['list_price'] = (float)$value->double;
          break;
        case 'image_1024':
          $product['image'] = $value->string ? 'data:image/png;base64,' . $value->string : "default_image.png";
          break;
      }
    }
    $products[] = $product;
  }

  return $products;
}

// Main Execution
try {
    // Validate product ID
    if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
        sendJsonResponse(['error' => 'Invalid product ID.'], 400);
    }

    $prod_id = (int)trim($_GET['id']);
    $product_uid = authenticateProduct($product_url, $product_db, $product_username, $product_password);

    // Fetch product details
    $product = fetchProductById($product_url, $product_db, $product_uid, $product_password, $prod_id);
    if (!$product) {
        sendJsonResponse(['error' => 'No product found with the specified ID.'], 404);
    }

    // Fetch related products
    $related_products = fetchProductsByCategory(
        $product_url,
        $product_db,
        $product_uid,
        $product_password,
        $product['category_id'],
        $product['id']
    );

    // Response structure
    $response = [
        'product' => $product,
        'related_products' => $related_products,
    ];

    // Send the JSON response
    sendJsonResponse($response);

} catch (Exception $e) {
    sendJsonResponse(['error' => 'An error occurred: ' . $e->getMessage()], 500);
}
?>
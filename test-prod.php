<?php
set_time_limit(300); // Increase execution time to 5 minutes

require_once "./odoo_connection.php";

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

// Set the response content type
header('Content-Type: application/json');

// Function to fetch products from Odoo and return data as JSON
function fetchProducts($product_url, $product_db, $product_uid, $product_password) {
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
        </struct></value></param>
    </params>
</methodCall>
XML;

    // Make the XML-RPC request
    $response = performCurlRequestProduct($product_url . 'xmlrpc/2/object', $xml);
    $xmlResponse = simplexml_load_string($response);

    if (!isset($xmlResponse->params->param->value->array->data->value)) {
        return json_encode(['error' => 'Failed to fetch products.']);
    }

    // Process products into a structured array
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
    // Fetch the product data
    $product_uid = authenticateProduct($product_url, $product_db, $product_username, $product_password);
    $products = fetchProducts($product_url, $product_db, $product_uid, $product_password);

    // Output JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'data' => $products
    ]);
    exit;
}

?>

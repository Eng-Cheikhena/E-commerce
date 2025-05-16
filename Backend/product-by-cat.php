<?php
set_time_limit(300);
require_once "./odoo_connection.php";

// Set the content type to JSON
header('Content-Type: application/json');

// Fetch products by category
function fetchProductsByCategory($product_url, $product_db, $product_uid, $product_password, $category_id)
{
    $domain = '<value><array><data>
                    <value><array><data>
                        <value><string>categ_id</string></value>
                        <value><string>=</string></value>
                        <value><int>' . $category_id . '</int></value>
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
                <param><value><array><data>' . $domain . '</data></array></value></param>
                <param><value><struct>
                    <member><name>fields</name><value><array><data>
                        <value><string>id</string></value>
                        <value><string>name</string></value>
                        <value><string>list_price</string></value>
                        <value><string>image_1024</string></value>
                        <value><string>default_code</string></value>
                        <value><string>qty_available</string></value>
                    </data></array></value></member>
                </struct></value></param>
            </params>
        </methodCall>';

    $response = performCurlRequestProduct($product_url . 'xmlrpc/2/object', $xml);
    $xml_response = simplexml_load_string($response);

    $products = [];
    if (isset($xml_response->params->param->value->array->data->value)) {
        foreach ($xml_response->params->param->value->array->data->value as $product) {
            $productInfo = [];
            foreach ($product->struct->member as $member) {
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
                    case 'image_1024':
                        $productInfo['image'] = $value->string ? 'data:image/png;base64,' . $value->string : null;
                        break;
                    case 'default_code':
                        $productInfo['default_code'] = (string)$value->string;
                        break;
                    case 'qty_available':
                        $productInfo['qty_available'] = (int)$value->int;
                        break;
                }
            }
            $products[] = $productInfo;
        }
    }

    return $products;
}

// Main execution
try {
    $product_uid = authenticateProduct($product_url, $product_db, $product_username, $product_password);

    // Check if category ID is provided and valid
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or missing category ID.'
        ]);
        exit;
    }

    $category_id = (int)$_GET['id'];
    $products = fetchProductsByCategory($product_url, $product_db, $product_uid, $product_password, $category_id);

    // Output response
    echo json_encode([
        'status' => 'success',
        'data' => $products
    ]);
} catch (Exception $e) {
    // Handle errors gracefully
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>

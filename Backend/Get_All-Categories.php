<?php
set_time_limit(300);

require_once "./odoo_connection.php";

// Set the content type to JSON
header('Content-Type: application/json');

// Fetch categories
function fetchCategories($product_url, $product_db, $product_uid, $product_password)
{
    $xml = '<?xml version="1.0"?>
        <methodCall>
            <methodName>execute_kw</methodName>
            <params>
                <param><value><string>' . $product_db . '</string></value></param>
                <param><value><int>' . $product_uid . '</int></value></param>
                <param><value><string>' . $product_password . '</string></value></param>
                <param><value><string>product.category</string></value></param>
                <param><value><string>search_read</string></value></param>
                <param><value><array><data></data></array></value></param>
                <param><value><struct>
                    <field><name>fields</name><value><array><data>
                        <value><string>id</string></value>
                        <value><string>name</string></value>
                    </data></array></value></field>
                </struct></value></param>
            </params>
        </methodCall>';

    $response = performCurlRequestProduct($product_url . 'xmlrpc/2/object', $xml);
    $xml_response = simplexml_load_string($response);

    $categories = [];
    if (isset($xml_response->params->param->value->array->data->value)) {
        foreach ($xml_response->params->param->value->array->data->value as $category) {
            $id = null;
            $name = null;
            foreach ($category->struct->member as $member) {
                if ((string)$member->name === 'id') {
                    $id = (int)$member->value->int;
                }
                if ((string)$member->name === 'name') {
                    $name = (string)$member->value->string;
                }
            }
            if ($id && $name) {
                $categories[] = ['id' => $id, 'name' => $name];
            }
        }
    }

    return $categories;
}

// Main execution
try {
    $product_uid = authenticateProduct($product_url, $product_db, $product_username, $product_password);
    $categories = fetchCategories($product_url, $product_db, $product_uid, $product_password);

    // Prepare and output the JSON response
    $response = [
        'status' => 'success',
        'data' => $categories
    ];

    echo json_encode($response);
} catch (Exception $e) {
    // Handle errors gracefully and output them as JSON
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];

    echo json_encode($response);
}
?>

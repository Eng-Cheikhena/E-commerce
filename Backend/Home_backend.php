<?php
set_time_limit(300); // Increase to 5 minutes
require_once "./odoo_connection.php";

header('Content-Type: application/json');

// Function to fetch products from Odoo
function fetchProducts($product_url, $product_db, $product_uid, $product_password)
{
    $xml = '<?xml version="1.0"?>
        <methodCall>
            <methodName>execute_kw</methodName>
            <params>
                <param><value><string>' . $product_db . '</string></value></param>
                <param><value><int>' . $product_uid . '</int></value></param>
                <param><value><string>' . $product_password . '</string></value></param>
                <param><value><string>product.template</string></value></param>
                <param><value><string>search_read</string></value></param>
                <param><value><array><data></data></array></value></param>
                <param><value><struct>
                    <member><name>fields</name><value><array><data>
                        <value><string>id</string></value>
                        <value><string>name</string></value>
                        <value><string>list_price</string></value>
                        <value><string>default_code</string></value>
                        <value><string>image_1024</string></value>
                        <value><string>categ_id</string></value>
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
            $productStruct = $product->struct->member;
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
                        $productInfo['category_name'] = isset($value->array->data->value[1]->string) ? (string)$value->array->data->value[1]->string : 'Unknown';
                        break;
                    case 'qty_available':
                        $productInfo['available_stock'] = (float)$value->double;
                        break;
                }
            }
            $products[] = $productInfo;
        }
    }

    return $products;
}

// Function to filter and shuffle products by allowed categories
function getFilteredProducts($products, $allowedCategories, $limit = 6)
{
    $filteredProducts = array_filter($products, function ($product) use ($allowedCategories) {
        return isset($product['category_name'], $product['image'])
            && in_array($product['category_name'], $allowedCategories)
            && !empty($product['image']);
    });

    // Shuffle and limit
    $shuffledProducts = array_values($filteredProducts); // Re-index the array
    shuffle($shuffledProducts);
    return array_slice($shuffledProducts, 0, $limit);
}

// Main execution
try {
    $product_uid = authenticateProduct($product_url, $product_db, $product_username, $product_password);
    $allProducts = fetchProducts($product_url, $product_db, $product_uid, $product_password);

    // Define allowed categories
    $categories = [
        'ordinateurs' => [
            "Ordinateurs",
            "Ordinateurs/Laptop - ordinateur portable",
            "Ordinateurs/ Laptop - ordinateur portable / All",
            "Ordinateurs/ Laptop - ordinateur portable / All / Saleable / pos",
            "Ordinateurs / PC - ordinateur fixe",
        ],
        'home_appliances' => [
            "Home appliance/Climatiseurs",
            "Home appliance",
            "Home appliance/Téléviseurs",
        ],
        'imprimantes' => [
            "Imprimantes & consommables",
            "Imprimantes & consommables/ Consommables",
            "Imprimantes & consommables/ Imprimantes",
        ],
        'reseau' => [
            "Réseaux Telecoms/FAI",
            "Réseau & Telecom",
            "Réseau & Telecom / Antennes",
            "Réseau & Telecom / Phone systems & vidéos Conférences",
            "Réseau & Telecom / Routing & switching",
            "Réseau & Telecom / Système de surveillence",
            "Réseau & Télécoms / Accessoires",
            "FAI",
        ],
        'smart_devices' => [
            "Smartpones & Accessoires",
            "Smartpones & Accessoires / Accessoires",
            "Smartpones & Accessoires / Accessoires / Antichoc",
            "Smartpones & Accessoires / Accessoires / Chargeurs",
            "Smartpones & Accessoires / Accessoires / Divers",
            "Smartpones & Accessoires / Accessoires / Ecouteurs",
            "Smartpones & Accessoires / Accessoires / Pochette",
            "Smartpones & Accessoires / Accessoires / Smart watch",
            "Smartpones & Accessoires / Accessoires / Smartphones",
            "Tablettes et Accessoires",
        ],
        'accessoires' => [
            "Accessoires",
          
        ],
        'autres' => [
            "Fourniture Bureau",
        
        ],
    ];

    // Prepare response
    $response = [];
    foreach ($categories as $key => $allowedCategories) {
        $response[$key] = getFilteredProducts($allProducts, $allowedCategories);
    }

    echo json_encode([
        'status' => 'success',
        'data' => $response,
    ]);
} catch (Exception $e) {
    // Handle errors gracefully
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
?>

File "odoo_connection.php" in json :
<?php
set_time_limit(300); // Increase execution time to 5 minutes

// Configuration
$product_url = 'http://10.135.0.33:8069';
$product_db = 'oddo_db';
$product_username = 'commercial@a2ict.com';
$product_password = 'admin';

// Helper function to perform the cURL request
function performCurlRequestProduct($url, $payload)
{
    $client = curl_init();
    curl_setopt($client, CURLOPT_URL, $url);
    curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($client, CURLOPT_POST, true);
    curl_setopt($client, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($client, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($client);
    if (curl_errno($client)) {
        die('cURL Error: ' . curl_error($client));
    }

    curl_close($client);
    return json_decode($response, true);
}

// Function to authenticate using JSON-RPC
function authenticateProduct($product_url, $product_db, $product_username, $product_password)
{
    $url = $product_url . '/jsonrpc';

    $payload = [
        'jsonrpc' => '2.0',
        'method' => 'call',
        'params' => [
            'service' => 'common',
            'method' => 'authenticate',
            'args' => [
                $product_db,
                $product_username,
                $product_password,
                []
            ]
        ],
        'id' => 1
    ];

    $response = performCurlRequestProduct($url, $payload);

    if (isset($response['result'])) {
        return $response['result']; // User ID if authentication is successful
    } elseif (isset($response['error'])) {
        die('Authentication failed: ' . $response['error']['message']);
    } else {
        die('Unexpected response: ' . print_r($response, true));
    }
}

// Example usage
$user_id = authenticateProduct($product_url, $product_db, $product_username, $product_password);
if ($user_id) {
    echo "Authentication successful. User ID: $user_id";
} else {
    echo "Authentication failed.";
}
?>


File "Home_backend.php" in json :

<?php
set_time_limit(300); // Increase to 5 minutes
require_once './odoo_connection.php';

header('Content-Type: application/json');

// Function to fetch products from Odoo using JSON-RPC
function fetchProducts($product_url, $product_db, $product_uid, $product_password)
{
    $url = $product_url . '/jsonrpc';

    $payload = [
        'jsonrpc' => '2.0',
        'method' => 'call',
        'params' => [
            'service' => 'object',
            'method' => 'execute_kw',
            'args' => [
                $product_db,
                $product_uid,
                $product_password,
                'product.template',
                'search_read',
                [], // No specific filters
                [
                    'fields' => [
                        'id',
                        'name',
                        'list_price',
                        'default_code',
                        'image_1024',
                        'categ_id',
                        'qty_available'
                    ]
                ]
            ]
        ],
        'id' => 1
    ];

    $response = performCurlRequestProduct($url, $payload);

    if (isset($response['result'])) {
        return $response['result'];
    } elseif (isset($response['error'])) {
        die('Error fetching products: ' . $response['error']['message']);
    } else {
        die('Unexpected response: ' . print_r($response, true));
    }
}

// Function to filter and shuffle products by category
function getFilteredProducts($products, $allowedCategories, $limit = 6)
{
    $filteredProducts = array_filter($products, function ($product) use ($allowedCategories) {
        return isset($product['categ_id'][1], $product['image_1024'])
            && in_array($product['categ_id'][1], $allowedCategories)
            && !empty($product['image_1024']);
    });

    shuffle($filteredProducts); // Shuffle the products
    return array_slice($filteredProducts, 0, $limit); // Limit the results
}

// Main execution
try {
    $product_uid = authenticateProduct($product_url, $product_db, $product_username, $product_password);
    $allProducts = fetchProducts($product_url, $product_db, $product_uid, $product_password);

    // Define categories
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

    // Prepare filtered and shuffled products for each category
    $response = [];
    foreach ($categories as $key => $allowedCategories) {
        $response[$key] = getFilteredProducts($allProducts, $allowedCategories);
    }

    // Send response
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

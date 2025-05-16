<?php
// Define the XML URL
$xmlUrl = "https://www.getic.com/api/xml/a2ict-com/xml";

// Function to fetch and parse XML data
function fetchItemsFromXml($url)
{
    // Fetch the XML content
    $xmlContent = @file_get_contents($url);
    if ($xmlContent === false) {
        throw new Exception("Failed to fetch XML data from the URL.");
    }

    // Parse the XML content
    $xml = @simplexml_load_string($xmlContent);
    if ($xml === false) {
        throw new Exception("Failed to parse XML data. Ensure the XML format is correct.");
    }

    // Convert XML data to an array
    $items = [];
    foreach ($xml->item as $item) { // Update to match the actual XML structure
        $items[] = [
            'id' => (string)$item->id,
            'title' => isset($item->title) ? (string)$item->title : (string)$item->name,
            'price' => (float)$item->price,
            'link' => (string)$item->link,
            'image' => (string)$item->image,
            'brand' => isset($item->brand) ? (string)$item->brand : 'Unknown',
            'qty' => isset($item->qty) ? (int)$item->qty : 0,
            'mpn' => isset($item->mpn) ? (string)$item->mpn : 'N/A',
            'EAN' => isset($item->EAN) ? (int)$item->EAN : 0,
        ];
    }

    return $items;
}

// Main execution
try {
    $items = fetchItemsFromXml($xmlUrl);

    // Display items in the required format
    if (!empty($items)) {
      $count = 0; // Counter to track the number of displayed items
      foreach ($items as $item) {
          if ($count >= 100) {
              break; // Stop after displaying 30 items
          }
          $count++;
          ?>
            <div class="col-6 col-md-4 col-lg-3 col-xl-5col">
                <div class="item-default inner-quickview inner-icon">
                    <figure>
                        <a href="<?= htmlspecialchars($item['link']); ?>">
                            <img 
                                src="<?= htmlspecialchars($item['image']); ?>" 
                                style="width:205px; height:205px; object-fit:cover;" 
                                alt="<?= htmlspecialchars($item['title']); ?>"
                            >
                        </a>
                        <div class="btn-icon-group">
                            <a 
                                href="Single_item.php?id=<?= htmlspecialchars($item['id']); ?>" 
                                class="btn-icon btn-add-cart item-type-simple"
                            >
                                <i class="icon-shopping-cart"></i>
                            </a>
                        </div>
                        <a 
                            href="Single_item.php?id=<?= htmlspecialchars($item['id']); ?>" 
                            class="btn-quickview" 
                            title="Quick View"
                        >
                            Quick View
                        </a>
                    </figure>
                    <div class="item-details">
                        <div class="title-wrap">
                            <h6 class="item-title">
                                <a href="Single_item.php?id=<?= htmlspecialchars($item['id']); ?>">
                                    <?= htmlspecialchars($item['title']); ?>
                                </a>
                            </h6>
                            <a 
                                href="Single_item.php?id=<?= htmlspecialchars($item['id']); ?>" 
                                title="Wishlist" 
                                class="btn-icon-wish"
                            >
                                <i class="icon-heart"></i>
                            </a>
                        </div>
                        <div class="ratings-container">
                            <div class="item-ratings">
                                <span class="ratings" style="width:75%;"></span>
                                <span class="tooltiptext tooltip-top"></span>
                            </div>
                        </div>
                        <div class="price-box">
                            <span class="item-price">
                                MRU <?= number_format($item['price'], 2); ?>
                            </span>
                        </div>
                        <div class="brand-box">
                            <strong>Brand:</strong> <?= htmlspecialchars($item['brand']); ?>
                        </div>
                        
                    </div>
                </div>
            </div>
            <?php
        }
    } else {
        echo '<p>No items found in the XML data.</p>';
    }
} catch (Exception $e) {
    echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>

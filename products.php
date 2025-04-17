<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration and functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle Add to Cart POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $productId = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    
    try {
        // Verify product exists and is active
        $stmt = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Initialize cart if not exists
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            // Add or update item in cart
            if (isset($_SESSION['cart'][$productId])) {
                $newQuantity = $_SESSION['cart'][$productId]['quantity'] + $quantity;
                if ($newQuantity <= $product['stock']) {
                    $_SESSION['cart'][$productId]['quantity'] = $newQuantity;
                    $message = "Quantity updated in cart!";
                    $messageType = "success";
                } else {
                    $message = "Cannot add more than available stock!";
                    $messageType = "error";
                }
            } else {
                if ($quantity <= $product['stock']) {
                    $_SESSION['cart'][$productId] = [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'price' => $product['price'],
                        'quantity' => $quantity,
                        'max_stock' => $product['stock']
                    ];
                    $message = "Product added to cart!";
                    $messageType = "success";
                } else {
                    $message = "Quantity exceeds available stock!";
                    $messageType = "error";
                }
            }
            
            $_SESSION['cart_message'] = ['type' => $messageType, 'text' => $message];
        } else {
            $_SESSION['cart_message'] = ['type' => 'error', 'text' => "Product not available!"];
        }
        
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    } catch (PDOException $e) {
        $_SESSION['cart_message'] = ['type' => 'error', 'text' => "Error adding to cart. Please try again."];
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Helper function to get product image URL
function getProductImageUrl($imagePath) {
    // Check if imagePath is empty
    if (empty($imagePath)) {
        return BASE_URL . '/assets/images/default-product.jpg';
    }
    
    // Handle JSON encoded array (if you store multiple images as JSON)
    $images = json_decode($imagePath, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($images) && !empty($images)) {
        $imagePath = $images[0]; // Get first image
    }
    
    // Check if it's already a full URL
    if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
        return $imagePath;
    }
    
    // Remove any leading/trailing slashes
    $imagePath = ltrim($imagePath, '/');
    
    // Verify the file exists in uploads directory
    $localPath = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/products/' . $imagePath;
    if (!file_exists($localPath)) {
        error_log("Image not found: " . $localPath);
        return BASE_URL . '/assets/images/default-product.jpg';
    }
    
    // Return full URL
    return BASE_URL . '/farmer-platform/uploads/products/' . $imagePath;
}

// Get filter parameters
$searchQuery = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 12;

// Fetch available categories
try {
    $categories = $pdo->query(
        "SELECT DISTINCT category FROM products 
         WHERE status = 'active' AND category IS NOT NULL AND category != ''
         ORDER BY category"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

// Main products query
try {
    $sql = "SELECT id, name, description, price, stock, image_url, is_featured, category FROM products WHERE status = 'active'";
    $params = [];
    
    if (!empty($searchQuery)) {
        $sql .= " AND (name LIKE :search OR description LIKE :search)";
        $params[':search'] = "%$searchQuery%";
    }

    if (!empty($categoryFilter)) {
        $sql .= " AND category = :category";
        $params[':category'] = $categoryFilter;
    }

    // Count total items
    $countSql = "SELECT COUNT(*) FROM ($sql) AS total";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalItems = (int)$stmt->fetchColumn();
    $totalPages = max(1, ceil($totalItems / $itemsPerPage));

    // Get paginated results
    $sql .= " ORDER BY is_featured DESC, created_at DESC 
              LIMIT :offset, :limit";
    
    $params[':offset'] = ($page - 1) * $itemsPerPage;
    $params[':limit'] = $itemsPerPage;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $paramType);
    }
    $stmt->execute();
$products = $stmt->fetchAll();

// Debug output - remove after testing
echo '<pre>';
foreach ($products as $product) {
    echo "Product: " . $product['name'] . "\n";
    echo "Image URL from DB: " . $product['image_url'] . "\n";
    echo "Resolved URL: " . getProductImageUrl($product['image_url']) . "\n\n";
}
echo '</pre>';

} catch (PDOException $e) {
    $error = "Error loading products. Please try again.";
    $products = [];
    $totalPages = 1;
}

// Set page title
$pageTitle = $categoryFilter ? htmlspecialchars($categoryFilter) : "All Products";

// Include header
include 'includes/header.php';
?>

<!-- CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/products.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div class="container products-container">
    <!-- Display cart messages -->
    <?php if (isset($_SESSION['cart_message'])): ?>
        <div class="alert alert-<?= $_SESSION['cart_message']['type'] ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['cart_message']['text']) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['cart_message']); ?>
    <?php endif; ?>

    <!-- Category Navigation -->
    <div class="category-nav">
        <a href="products.php" class="category-item <?= empty($categoryFilter) ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All Products
        </a>
        <?php foreach ($categories as $category): ?>
            <a href="products.php?category=<?= urlencode($category) ?>" 
               class="category-item <?= $categoryFilter === $category ? 'active' : '' ?>">
                <i class="fas fa-<?= strtolower(str_replace(' ', '-', $category)) ?>"></i>
                <?= htmlspecialchars($category) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search and Title -->
    <div class="products-header">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <form class="product-search" method="get">
            <div class="input-group">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search products..." value="<?= htmlspecialchars($searchQuery) ?>">
                <?php if (!empty($categoryFilter)): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
                <?php endif; ?>
                <div class="input-group-append">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Products Grid -->
    <div class="products-grid">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open fa-3x"></i>
                <h3>No Products Found</h3>
                <p>Try adjusting your search or filter criteria</p>
                <a href="products.php" class="btn btn-primary">Browse All Products</a>
            </div>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <?php if ($product['is_featured']): ?>
                        <div class="featured-badge">Featured</div>
                    <?php endif; ?>
                    
                    <!-- Product Image -->
                    <div class="product-image">
                        <img src="<?= getProductImageUrl($product['image_url']) ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             loading="lazy">
                    </div>
                    
                    <!-- Product Info -->
                    <div class="product-info">
                        <h3><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="product-description">
                            <?= htmlspecialchars(substr($product['description'], 0, 100)) ?>
                            <?php if (strlen($product['description']) > 100): ?>...<?php endif; ?>
                        </p>
                        
                        <div class="price-stock">
                            <span class="price">KSh <?= number_format($product['price'], 2) ?></span>
                            <span class="stock <?= $product['stock'] > 0 ? 'in-stock' : 'out-stock' ?>">
                                <?= $product['stock'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Add to Cart Form -->
                    <form method="post" class="add-to-cart-form">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="hidden" name="add_to_cart" value="1">
                        
                        <div class="quantity-selector" <?= $product['stock'] <= 0 ? 'style="display:none;"' : '' ?>>
                            <button type="button" class="quantity-btn minus"><i class="fas fa-minus"></i></button>
                            <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock'] ?>" 
                                   class="quantity-input">
                            <button type="button" class="quantity-btn plus"><i class="fas fa-plus"></i></button>
                        </div>
                        
                        <button type="submit" class="btn btn-add-to-cart" <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                        
                        <a href="product.php?id=<?= $product['id'] ?>" class="btn btn-view-details">
                            <i class="fas fa-info-circle"></i> Details
                        </a>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav class="pagination-container">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Quantity selector functionality
    $('.quantity-btn.plus').click(function() {
        const input = $(this).siblings('.quantity-input');
        const max = parseInt(input.attr('max'));
        let value = parseInt(input.val());
        if (value < max) {
            input.val(value + 1);
        }
    });

    $('.quantity-btn.minus').click(function() {
        const input = $(this).siblings('.quantity-input');
        let value = parseInt(input.val());
        if (value > 1) {
            input.val(value - 1);
        }
    });

    // Prevent form submission if product is out of stock
    $('.add-to-cart-form').submit(function(e) {
        if ($(this).find('.btn-add-to-cart').is(':disabled')) {
            e.preventDefault();
            alert('This product is currently out of stock.');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
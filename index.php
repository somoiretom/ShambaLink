<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to get website content with caching
function getWebsiteContentWithCache($key, $default = '') {
    static $contentCache = [];
    
    if (!isset($contentCache[$key])) {
        $cacheFile = 'cache/content_' . md5($key) . '.cache';
        $cacheTime = 3600; // 1 hour cache
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
            $cachedData = file_get_contents($cacheFile);
            // Verify the cached data is not empty before unserializing
            $contentCache[$key] = (!empty($cachedData)) ? @unserialize($cachedData) : $default;
            if ($contentCache[$key] === false) {
                $contentCache[$key] = $default;
            }
        } else {
            global $pdo;
            try {
                $stmt = $pdo->prepare("SELECT content_value FROM website_content WHERE content_key = ?");
                $stmt->execute([$key]);
                $content = $stmt->fetchColumn();
                $contentCache[$key] = $content ?: $default;
                
                // Ensure cache directory exists
                if (!is_dir('cache')) {
                    mkdir('cache', 0755, true);
                }
                
                file_put_contents($cacheFile, serialize($contentCache[$key]));
            } catch (PDOException $e) {
                error_log("Content fetch error: " . $e->getMessage());
                $contentCache[$key] = $default;
            }
        }
    }
    
    return $contentCache[$key];
}

// Function to get data with caching
function getDataWithCache($query, $cacheKey, $default = []) {
    static $dataCache = [];
    global $pdo;
    
    if (!isset($dataCache[$cacheKey])) {
        $cacheFile = 'cache/data_' . md5($cacheKey) . '.cache';
        $cacheTime = 300; // 5 minutes cache
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
            $cachedData = file_get_contents($cacheFile);
            // Verify the cached data is not empty before unserializing
            $dataCache[$cacheKey] = (!empty($cachedData)) ? @unserialize($cachedData) : $default;
            if ($dataCache[$cacheKey] === false) {
                $dataCache[$cacheKey] = $default;
            }
        } else {
            try {
                $stmt = $pdo->query($query);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Ensure cache directory exists
                if (!is_dir('cache')) {
                    mkdir('cache', 0755, true);
                }
                
                file_put_contents($cacheFile, serialize($data));
                $dataCache[$cacheKey] = $data;
            } catch (PDOException $e) {
                error_log("Database error (" . $cacheKey . "): " . $e->getMessage());
                $dataCache[$cacheKey] = $default;
            }
        }
    }
    
    return $dataCache[$cacheKey];
}

// Clear cache if requested (admin only)
if (isset($_GET['clear_cache']) && $_GET['clear_cache'] === 'true') {
    if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
        array_map('unlink', glob("cache/*.cache"));
        $_SESSION['success'] = "Cache cleared successfully";
    }
    header("Location: index.php");
    exit();
}

// Fetch all content
$hero_content = getWebsiteContentWithCache('hero_text', 'Connecting Farmers Directly to Consumers');
$vision = getWebsiteContentWithCache('vision', 'To create a sustainable ecosystem where farmers and consumers connect directly, fostering fair trade and community growth.');
$mission = getWebsiteContentWithCache('mission', 'To empower smallholder farmers by providing them with a platform to showcase their products and share knowledge, while giving consumers access to fresh, locally-sourced produce.');

// Get dynamic content
$hero_media = getDataWithCache(
    "SELECT * FROM hero_media WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1",
    'hero_media'
);

$featured_products = getDataWithCache(
    "SELECT p.*, u.first_name, u.last_name 
     FROM products p 
     JOIN users u ON p.farmer_id = u.id 
     WHERE p.is_active = 1 AND p.is_featured = 1
     ORDER BY p.created_at DESC 
     LIMIT 6",
    'featured_products'
);

$recent_insights = getDataWithCache(
    "SELECT i.*, u.first_name, u.last_name 
     FROM insights i 
     JOIN users u ON i.farmer_id = u.id 
     WHERE i.is_published = 1 AND i.is_featured = 1
     ORDER BY i.created_at DESC 
     LIMIT 3",
    'recent_insights'
);

$testimonials = getDataWithCache(
    "SELECT * FROM testimonials WHERE is_active = 1 ORDER BY created_at DESC",
    'testimonials'
);

$partners = getDataWithCache(
    "SELECT * FROM partners WHERE is_active = 1 ORDER BY name",
    'partners'
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Platform - Connecting Farmers and Consumers</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/home.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/about.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/testimonials.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Display success/error messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero">
        <?php if (!empty($hero_media)): ?>
            <?php if ($hero_media[0]['media_type'] === 'video'): ?>
                <video class="hero-media" autoplay muted loop>
                    <source src="<?= BASE_URL ?>/uploads/hero/<?= htmlspecialchars($hero_media[0]['file_path']) ?>" type="video/mp4">
                </video>
            <?php else: ?>
                <img class="hero-media" src="<?= BASE_URL ?>/uploads/hero/<?= htmlspecialchars($hero_media[0]['file_path']) ?>" alt="Hero Image">
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="hero-content">
            <h1><?= nl2br(htmlspecialchars($hero_content)) ?></h1>
            
        </div>
    </section>

    <!-- Vision & Mission Section -->
    <section class="about-section">
        <div class="container">
            <div class="about-column">
                <h2>Our Vision</h2>
                <p><?= nl2br(htmlspecialchars($vision)) ?></p>
            </div>
            <div class="about-column">
                <h2>Our Mission</h2>
                <p><?= nl2br(htmlspecialchars($mission)) ?></p>
            </div>
        </div>
    </section>

    

    <!-- Testimonials Section -->
    <section class="testimonials-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">What Our Community Says</h2>
                <p class="section-subtitle">Hear from farmers and consumers who are part of our platform</p>
            </div>

            <?php if (!empty($testimonials)): ?>
            <div class="testimonials-carousel">
                <div class="testimonials-track">
                    <?php foreach ($testimonials as $testimonial): ?>
                    <div class="testimonial-card">
                        <div class="testimonial-content">
                            <div class="quote-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14.017 21V12.3C14.017 9.162 14.017 7.092 15.547 5.562C17.077 4.032 19.147 4.032 22.297 4.032H22.417V10.5H17.917V21H14.017ZM1.717 21V12.3C1.717 9.162 1.717 7.092 3.247 5.562C4.777 4.032 6.847 4.032 10.007 4.032H10.117V10.5H5.617V21H1.717Z" fill="currentColor"/>
                                </svg>
                            </div>
                            <p class="testimonial-text"><?= htmlspecialchars($testimonial['content']) ?></p>
                        </div>
                        <div class="testimonial-author">
                            <div class="author-info">
                                <span class="author-name"><?= htmlspecialchars($testimonial['author']) ?></span>
                                <?php if (!empty($testimonial['role'])): ?>
                                <span class="author-role"><?= htmlspecialchars($testimonial['role']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($testimonial['author_image'])): ?>
                            <div class="author-avatar">
                                <img src="<?= BASE_URL ?>/uploads/testimonials/<?= htmlspecialchars($testimonial['author_image']) ?>" alt="<?= htmlspecialchars($testimonial['author']) ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="carousel-controls">
                    <button class="carousel-prev" aria-label="Previous testimonial">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>
                    <div class="carousel-dots">
                        <?php foreach ($testimonials as $index => $t): ?>
                        <button class="carousel-dot <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-next" aria-label="Next testimonial">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-testimonials">
                <div class="empty-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>
                <h3>No testimonials yet</h3>
                <p>Be the first to share your experience with our platform</p>
                <a href="/share-testimonial" class="btn btn-primary">Share Your Story</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Partners Section -->
    <?php if (!empty($partners)): ?>
    <section class="partners-section">
        <div class="container">
            <h2>Our Partners</h2>
            <div class="partners-grid">
                <?php foreach ($partners as $partner): ?>
                    <div class="partner-logo">
                        <?php if (!empty($partner['website'])): ?>
                            <a href="<?= htmlspecialchars($partner['website']) ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?= BASE_URL ?>/uploads/partners/<?= htmlspecialchars($partner['logo_path']) ?>" 
                                     alt="<?= htmlspecialchars($partner['name']) ?>">
                            </a>
                        <?php else: ?>
                            <img src="<?= BASE_URL ?>/uploads/partners/<?= htmlspecialchars($partner['logo_path']) ?>" 
                                 alt="<?= htmlspecialchars($partner['name']) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/testimonials.js"></script>
</body>
</html>
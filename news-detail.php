<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/functions.php';

// Check if ID parameter exists and is valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: /farmer-platform/agricultural-news.php");
    exit();
}

$id = (int)$_GET['id'];

try {
    // Fetch the specific news article
    $stmt = $pdo->prepare("SELECT * FROM agricultural_news WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    $news = $stmt->fetch();
    
    if (!$news) {
        header("Location: /farmer-platform/agricultural-news.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    header("Location: /farmer-platform/agricultural-news.php");
    exit();
}

$page_title = htmlspecialchars($news['title']) . " | Agricultural News";
include 'includes/header.php';
?>

<div class="container news-detail-page">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <article class="news-article">
                <header class="article-header mb-4">
                    <h1><?php echo htmlspecialchars($news['title']); ?></h1>
                    <div class="article-meta">
                        <span class="publish-date">
                            <i class="far fa-calendar-alt"></i> 
                            <?php echo date('F j, Y', strtotime($news['published_date'])); ?>
                        </span>
                        <?php if (!empty($news['source'])): ?>
                            <span class="source ml-3">
                                <i class="fas fa-external-link-alt"></i> 
                                Source: <?php echo htmlspecialchars($news['source']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if (!empty($news['image_path'])): ?>
                    <div class="article-image mb-4">
                        <img src="<?php echo htmlspecialchars($news['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($news['title']); ?>" 
                             class="img-fluid rounded">
                    </div>
                <?php endif; ?>

                <div class="article-content">
                    <?php echo nl2br(htmlspecialchars($news['content'])); ?>
                </div>

                    <a href="/farmer-platform/agricultural-news.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to News
                    </a>
            </article>
        </div>
    </div>
</div>


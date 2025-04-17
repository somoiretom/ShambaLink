<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/functions.php';

// Database connection with error handling
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get unique resource categories
    $stmt = $pdo->query("SELECT DISTINCT content_value FROM website_content WHERE content_key = 'resources_categories'");
    $categories = $stmt->fetch(PDO::FETCH_ASSOC);
    $resourceCategories = $categories ? array_unique(array_map('trim', explode(',', $categories['content_value']))) : ['Books', 'Videos', 'Audios', 'Blogs'];

    // Get search and filter parameters
    $searchQuery = $_GET['search'] ?? '';
    $categoryFilter = $_GET['category'] ?? '';

    // Build query to get all active resources
    $sql = "SELECT * FROM resources WHERE is_active = 1";
    $params = [];

    if (!empty($searchQuery)) {
        $sql .= " AND (title LIKE ? OR description LIKE ?)";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
    }

    if (!empty($categoryFilter)) {
        $sql .= " AND type = ?";
        $params[] = $categoryFilter;
    }

    $sql .= " ORDER BY is_featured DESC, created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allResources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Separate featured and regular resources
    $featuredResources = array_filter($allResources, function($resource) {
        return $resource['is_featured'];
    });
    
    $regularResources = array_filter($allResources, function($resource) {
        return !$resource['is_featured'];
    });

    // Group regular resources by type only when not filtered
    $groupedResources = [];
    if (empty($categoryFilter)) {
        foreach ($regularResources as $resource) {
            if (!isset($groupedResources[$resource['type']])) {
                $groupedResources[$resource['type']] = [];
            }
            $groupedResources[$resource['type']][] = $resource;
        }
    }

    // Get page header content
    $stmt = $pdo->query("SELECT content_key, content_value FROM website_content WHERE content_key LIKE 'resources_%'");
    $pageContent = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pageContent[$row['content_key']] = $row['content_value'];
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Error loading resources. Please try again later.");
}

$page_title = "Agricultural Resources";
include 'includes/header.php';
?>

<!-- CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/resources.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.10.5/viewer.min.css">

<!-- Hero Section -->
<section class="resource-hero">
    <div class="hero-overlay" style="background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.4)), url('<?= BASE_URL ?>/uploads/resources/<?= htmlspecialchars($pageContent['resources_hero_image'] ?? 'default-resources-bg.jpg') ?>');">
        <div class="container">
            <div class="hero-content">
                <h1><?= htmlspecialchars($pageContent['resources_hero_title'] ?? 'Agricultural Resources') ?></h1>
                <p class="lead"><?= htmlspecialchars($pageContent['resources_hero_subtitle'] ?? 'Educational materials for farmers') ?></p>
                
                <!-- Search Bar -->
                <form class="resource-search" action="" method="get">
                    <div class="search-container">
                        <input type="text" name="search" placeholder="Search resources..." value="<?= htmlspecialchars($searchQuery) ?>">
                        <button type="submit" class="search-btn">
                            <i class="material-icons">search</i>
                        </button>
                    </div>
                    <?php if (!empty($searchQuery)): ?>
                        <a href="resources.php" class="clear-search">Clear search</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<div class="container resource-container py-5">
    <!-- Resource Filter and Stats -->
    <div class="resource-meta mb-5">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h2 class="section-title">Explore Resources</h2>
                <p class="text-muted"><?= count($allResources) ?> resources available (<?= count($featuredResources) ?> featured)</p>
            </div>
            <div class="col-md-6 text-md-right">
                <div class="dropdown category-filter">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" id="categoryDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <?= !empty($categoryFilter) ? htmlspecialchars($categoryFilter) : 'All Categories' ?>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="categoryDropdown">
                        <a class="dropdown-item" href="resources.php">All Categories</a>
                        <?php foreach ($resourceCategories as $category): ?>
                            <a class="dropdown-item" href="resources.php?category=<?= urlencode($category) ?>"><?= htmlspecialchars($category) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Resources -->
    <?php if (!empty($featuredResources)): ?>
    <div class="featured-resources mb-5">
        <h3 class="section-subtitle"><i class="material-icons">star</i> Featured Resources</h3>
        <div class="row">
            <?php foreach ($featuredResources as $resource): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <?= renderResourceCard($resource) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Resource Categories Navigation (only show when not filtered) -->
    <?php if (empty($categoryFilter)): ?>
    <div class="resource-categories-nav">
        <div class="container">
            <nav class="categories-menu">
                <ul class="categories-list">
                    <li class="category-item active">
                        <a href="#all" class="category-link" data-toggle="tab" role="tab">
                            <span class="category-icon"><i class="material-icons">view_module</i></span>
                            <span class="category-name">All Resources</span>
                        </a>
                    </li>
                    <?php foreach ($resourceCategories as $category): 
                        $tabId = strtolower(str_replace(' ', '-', $category));
                        $icon = getCategoryIcon($category);
                    ?>
                        <li class="category-item">
                            <a href="#<?= $tabId ?>" class="category-link" data-toggle="tab" role="tab">
                                <span class="category-icon"><i class="material-icons"><?= $icon ?></i></span>
                                <span class="category-name"><?= htmlspecialchars($category) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>

    <!-- Resource Tab Content -->
    <div class="tab-content pt-4" id="resourceTabsContent">
        <?php if (!empty($categoryFilter)): ?>
            <!-- When filtered by category - show all matching resources including featured -->
            <div class="tab-pane fade show active" id="<?= strtolower(str_replace(' ', '-', $categoryFilter)) ?>" role="tabpanel">
                <?php if (!empty($allResources)): ?>
                    <div class="row">
                        <?php foreach ($allResources as $resource): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <?= renderResourceCard($resource) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <img src="<?= BASE_URL ?>/assets/images/empty-resources.svg" alt="No resources found" class="empty-img">
                        <h4>No resources found</h4>
                        <p>We couldn't find any resources matching your criteria.</p>
                        <a href="resources.php" class="btn btn-primary">Reset filters</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- When showing all categories - show only non-featured resources -->
            <div class="tab-pane fade show active" id="all" role="tabpanel">
                <?php if (!empty($regularResources)): ?>
                    <div class="row">
                        <?php foreach ($regularResources as $resource): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <?= renderResourceCard($resource) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <img src="<?= BASE_URL ?>/assets/images/empty-resources.svg" alt="No resources found" class="empty-img">
                        <h4>No resources found</h4>
                        <p>We couldn't find any resources matching your criteria.</p>
                        <a href="resources.php" class="btn btn-primary">Reset filters</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Category tabs - show only non-featured resources for each category -->
            <?php foreach ($resourceCategories as $category): 
                $category = trim($category);
                $tabId = strtolower(str_replace(' ', '-', $category));
                $categoryResources = $groupedResources[$category] ?? [];
            ?>
                <div class="tab-pane fade" id="<?= $tabId ?>" role="tabpanel">
                    <?php if (!empty($categoryResources)): ?>
                        <div class="row">
                            <?php foreach ($categoryResources as $resource): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <?= renderResourceCard($resource) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <img src="<?= BASE_URL ?>/assets/images/empty-category.svg" alt="No resources in this category" class="empty-img">
                            <h4>No <?= htmlspecialchars($category) ?> resources</h4>
                            <p>We don't have any resources in this category yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript -->
<script src="<?= BASE_URL ?>/assets/js/jquery.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.10.5/viewer.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.12.313/pdf.min.js"></script>

<script>
// Track displayed resources to prevent duplicates
const displayedResources = new Set();

$(document).ready(function() {
    // Handle resource card clicks
    $(document).on('click', '.resource-card', function(e) {
        // Don't trigger if clicking on buttons or links inside the card
        if ($(e.target).closest('.btn').length === 0 && !$(e.target).hasClass('play-button')) {
            const resourceId = $(this).find('.view-resource').data('resource-id');
            
            // Prevent duplicate modals
            if (displayedResources.has(resourceId)) return;
            displayedResources.add(resourceId);
            
            const resourceType = $(this).find('.view-resource').data('resource-type');
            const resourceUrl = $(this).find('.view-resource').data('resource-url');
            const resourceTitle = $(this).find('.card-title').text();
            
            showResourceModal(resourceId, resourceType, resourceUrl, resourceTitle);
        }
    });

    // Handle direct play button clicks on video thumbnails
    $(document).on('click', '.play-button', function(e) {
        e.stopPropagation();
        const card = $(this).closest('.resource-card');
        const resourceId = card.find('.view-resource').data('resource-id');
        
        // Prevent duplicate modals
        if (displayedResources.has(resourceId)) return;
        displayedResources.add(resourceId);
        
        const resourceType = card.find('.view-resource').data('resource-type');
        const resourceUrl = card.find('.view-resource').data('resource-url');
        const resourceTitle = card.find('.card-title').text();
        
        showResourceModal(resourceId, resourceType, resourceUrl, resourceTitle, true);
    });

    // Function to show resource in modal with auto-play option
    function showResourceModal(resourceId, resourceType, resourceUrl, resourceTitle, autoplay = false) {
        $('#resourceModalTitle').text(resourceTitle);
        $('#resourceModalLink').attr('href', resourceUrl);
        
        // Show loading state
        $('#resourceModalBody').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-2">Loading resource...</p>
            </div>
        `);
        
        // Show modal immediately
        $('#resourceModal').modal('show');

        // Determine content based on resource type
        let modalContent = '';
        const videoTypes = ['mp4', 'webm', 'ogg'];
        const imageTypes = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (videoTypes.includes(resourceType)) {
            modalContent = `
                <div class="embed-responsive embed-responsive-16by9">
                    <video controls ${autoplay ? 'autoplay' : ''} class="embed-responsive-item">
                        <source src="${resourceUrl}" type="video/${resourceType}">
                        Your browser does not support the video tag.
                    </video>
                </div>
                <div class="mt-3">
                    <p class="text-muted"><small>Video resource: ${resourceTitle}</small></p>
                </div>
            `;
            $('#resourceModalLinkText').text('Open Video');
        } else if (resourceType === 'pdf') {
            modalContent = `
                <div class="pdf-viewer-container">
                    <iframe src="${resourceUrl}#view=fitH" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
                <div class="mt-3">
                    <p class="text-muted"><small>PDF document: ${resourceTitle}</small></p>
                </div>
            `;
            $('#resourceModalLinkText').text('Open PDF');
        } else if (imageTypes.includes(resourceType)) {
            modalContent = `
                <div class="text-center">
                    <img src="${resourceUrl}" class="img-fluid img-viewer" alt="${resourceTitle}" style="max-height: 70vh;">
                </div>
                <div class="mt-3">
                    <p class="text-muted"><small>Image: ${resourceTitle}</small></p>
                </div>
            `;
            $('#resourceModalLinkText').text('Open Image');
        } else {
            // For other file types or when we want to show details
            $.ajax({
                url: '<?= BASE_URL ?>/ajax/get_resource_details.php',
                method: 'POST',
                data: { resource_id: resourceId },
                success: function(response) {
                    let modalContent = '';
                    if (response.success) {
                        modalContent = `
                            <div class="resource-details">
                                <div class="row">
                                    <div class="col-md-6">
                                        <img src="${response.thumbnail_path ? '<?= BASE_URL ?>/uploads/resources/' + response.thumbnail_path : '<?= BASE_URL ?>/assets/images/default-resource-thumbnail.jpg'}" 
                                             class="img-fluid rounded mb-3" alt="${response.title}">
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Description</h5>
                                        <p>${response.description}</p>
                                        <div class="resource-meta mt-4">
                                            <p><i class="material-icons">category</i> ${response.type}</p>
                                            <p><i class="material-icons">event</i> ${new Date(response.created_at).toLocaleDateString()}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        modalContent = `
                            <div class="alert alert-info">
                                <p>Click "Open Resource" to view this content.</p>
                            </div>
                        `;
                    }
                    $('#resourceModalBody').html(modalContent);
                },
                error: function() {
                    $('#resourceModalBody').html(`
                        <div class="alert alert-warning">
                            <p>Could not load resource details. Please try again.</p>
                        </div>
                    `);
                }
            });
            return;
        }
        
        $('#resourceModalBody').html(modalContent);
    }

    // Initialize image viewer when modal is shown
    $('#resourceModal').on('shown.bs.modal', function() {
        const viewerElements = document.querySelectorAll('.img-viewer');
        if (viewerElements.length > 0) {
            new Viewer(viewerElements[0], {
                navbar: false,
                title: false,
                toolbar: {
                    zoomIn: true,
                    zoomOut: true,
                    rotateLeft: true,
                    rotateRight: true,
                    reset: true,
                }
            });
        }
    });

    // Clear displayed resources when modal is closed
    $('#resourceModal').on('hidden.bs.modal', function() {
        displayedResources.clear();
        const videos = $('#resourceModalBody').find('video');
        if (videos.length > 0) {
            videos.each(function() {
                this.pause();
            });
        }
        $('#resourceModalBody').html('');
    });

    // Handle category tab switching
    $('.category-link[data-toggle="tab"]').on('click', function() {
        $('.category-item').removeClass('active');
        $(this).closest('.category-item').addClass('active');
    });
});
</script>

<?php include 'includes/footer.php'; ?>

<?php
// Function to render resource cards
function renderResourceCard($resource) {
    $isExternal = filter_var($resource['file_path'], FILTER_VALIDATE_URL);
    $resourceUrl = $isExternal ? $resource['file_path'] : BASE_URL . '/uploads/resources/' . $resource['file_path'];
    $thumbnailUrl = !empty($resource['thumbnail_path']) ? 
        BASE_URL . '/uploads/resources/' . $resource['thumbnail_path'] : 
        BASE_URL . '/assets/images/default-resource-thumbnail.jpg';
    
    // Determine file type
    $fileExtension = strtolower(pathinfo($resource['file_path'], PATHINFO_EXTENSION));
    $isVideo = in_array($fileExtension, ['mp4', 'webm', 'ogg']);
    $isPDF = $fileExtension === 'pdf';
    $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif']);
    $isDocument = in_array($fileExtension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);
    
    ob_start();
    ?>
    <div class="card resource-card h-100 <?= $resource['is_featured'] ? 'featured-resource' : '' ?>">
        <?php if ($resource['is_featured']): ?>
            <div class="featured-badge">
                <i class="material-icons">star</i> Featured
            </div>
        <?php endif; ?>
        
        <div class="card-img-container">
            <?php if ($isVideo): ?>
                <div class="video-thumbnail">
                    <img src="<?= htmlspecialchars($thumbnailUrl) ?>" class="card-img-top" alt="<?= htmlspecialchars($resource['title']) ?>">
                    <div class="play-button">
                        <i class="material-icons">play_circle_filled</i>
                    </div>
                </div>
            <?php elseif ($isImage): ?>
                <img src="<?= htmlspecialchars($resourceUrl) ?>" class="card-img-top" alt="<?= htmlspecialchars($resource['title']) ?>">
            <?php else: ?>
                <img src="<?= htmlspecialchars($thumbnailUrl) ?>" class="card-img-top" alt="<?= htmlspecialchars($resource['title']) ?>">
            <?php endif; ?>
            <div class="card-img-overlay">
                <span class="badge badge-resource-type">
                    <i class="material-icons"><?= getCategoryIcon($resource['type']) ?></i> <?= htmlspecialchars($resource['type']) ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($resource['title']) ?></h5>
            <p class="card-text"><?= htmlspecialchars(shortenDescription($resource['description'], 100)) ?></p>
            
            <div class="resource-meta">
                <span class="resource-date">
                    <i class="material-icons">event</i> <?= date('M d, Y', strtotime($resource['created_at'])) ?>
                </span>
            </div>
        </div>
        <div class="card-footer">
            <button class="btn btn-sm btn-outline-primary view-resource" 
                    data-resource-id="<?= $resource['id'] ?>"
                    data-resource-type="<?= $fileExtension ?>"
                    data-resource-url="<?= htmlspecialchars($resourceUrl) ?>">
                <i class="material-icons">info</i> Details
            </button>
            <a href="<?= htmlspecialchars($resourceUrl) ?>" 
               class="btn btn-sm btn-primary" 
               <?= ($isPDF || $isDocument || !$isImage) ? 'target="_blank"' : '' ?>
               <?= $isExternal ? 'rel="noopener noreferrer"' : '' ?>
               <?= !$isExternal ? 'download' : '' ?>>
                <i class="material-icons"><?= $isExternal ? 'link' : 'file_download' ?></i> <?= $isExternal ? 'Visit' : 'Download' ?>
            </a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Helper functions
function shortenDescription($text, $length = 100) {
    return strlen($text) <= $length ? $text : substr($text, 0, $length) . '...';
}

function getCategoryIcon($category) {
    $icons = [
        'Books' => 'menu_book',
        'Videos' => 'ondemand_video',
        'Audios' => 'audiotrack',
        'Blogs' => 'article',
        'Guides' => 'description',
        'Research Papers' => 'school',
        'Webinars' => 'video_library',
        'Tools' => 'build',
        'Datasets' => 'storage',
        'Case Studies' => 'assignment'
    ];
    return $icons[trim($category)] ?? 'folder';
}
?>
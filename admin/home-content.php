<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/functions.php';

requireRole('admin');

// Debug mode - comment out in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = sanitize($_POST['csrf_token'] ?? '');
    if (!verifyCSRFToken($csrf_token)) {
        die("Invalid CSRF token");
    }

    try {
        // Handle content updates
        if (isset($_POST['content_key'])) {
            $contentKey = sanitize($_POST['content_key']);
            $contentValue = sanitize($_POST['content_value'] ?? '');
            
            if (in_array($contentKey, ['hero_text', 'vision', 'mission', 'about_content', 'about_hero_title', 'about_hero_subtitle', 
                'about_story_title', 'about_story_content', 'about_mission_title', 'about_mission_content', 
                'about_team_title', 'about_team_desc', 'about_impact_title', 'about_stat_1_value', 'about_stat_1_label',
                'about_stat_2_value', 'about_stat_2_label', 'about_stat_3_value', 'about_stat_3_label',
                'about_value_1', 'about_value_2', 'about_value_3', 'about_value_4', 'insights_intro'])) {
                
                $stmt = $pdo->prepare("INSERT INTO website_content (content_key, content_value) 
                                     VALUES (?, ?)
                                     ON DUPLICATE KEY UPDATE content_value = VALUES(content_value)");
                $stmt->execute([$contentKey, $contentValue]);
                
                // Clear cache
                $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/content_' . md5($contentKey) . '.cache';
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
                $_SESSION['success'] = "Content updated successfully";
            }
        }

        // Handle hero media updates
        if (isset($_POST['media_action'])) {
            $mediaAction = $_POST['media_action'] ?? '';
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/hero/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Upload new media
            if ($mediaAction === 'update' && !empty($_FILES['media_file']['name'])) {
                $mediaType = sanitize($_POST['media_type'] ?? 'image');
                $setActive = isset($_POST['set_active']) ? 1 : 0;
                
                // Validate file type
                $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $allowedVideoTypes = ['video/mp4', 'video/webm'];
                $fileType = mime_content_type($_FILES['media_file']['tmp_name']);
                
                if (($mediaType === 'image' && !in_array($fileType, $allowedImageTypes)) || 
                    ($mediaType === 'video' && !in_array($fileType, $allowedVideoTypes))) {
                    throw new Exception("Invalid file type for selected media type");
                }
                
                // Check file size (max 10MB)
                if ($_FILES['media_file']['size'] > 100 * 2040 * 2040) {
                    throw new Exception("File size exceeds 100MB limit");
                }
                
                // Generate unique filename
                $fileName = uniqid() . '_' . basename($_FILES['media_file']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['media_file']['tmp_name'], $targetPath)) {
                    // Deactivate all current media if setting this as active
                    if ($setActive) {
                        $pdo->query("UPDATE hero_media SET is_active = 0");
                    }
                    
                    // Insert new media
                    $stmt = $pdo->prepare("INSERT INTO hero_media (file_path, media_type, is_active) VALUES (?, ?, ?)");
                    $stmt->execute([$fileName, $mediaType, $setActive]);
                    
                    // Clear cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_hero_media.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    
                    $_SESSION['success'] = "Hero media uploaded successfully";
                } else {
                    throw new Exception("Failed to move uploaded file");
                }
            }
            
            // Delete media
            if ($mediaAction === 'delete' && isset($_POST['media_id'])) {
                $mediaId = (int)$_POST['media_id'];
                
                $stmt = $pdo->prepare("SELECT file_path FROM hero_media WHERE id = ?");
                $stmt->execute([$mediaId]);
                $media = $stmt->fetch();
                
                if ($media) {
                    $filePath = $uploadDir . $media['file_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM hero_media WHERE id = ?");
                    $stmt->execute([$mediaId]);
                    
                    // Clear cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_hero_media.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    
                    $_SESSION['success'] = "Hero media deleted successfully";
                }
            }
        }
        
        // Handle image uploads
        if (isset($_POST['image_action'])) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/' . sanitize($_POST['image_type']) . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Delete old image if exists
            if (!empty($_POST['current_image'])) {
                $oldImagePath = $uploadDir . sanitize($_POST['current_image']);
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            // Upload new image
            $fileName = uniqid() . '_' . basename($_FILES['image_file']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
                $contentKey = sanitize($_POST['content_key_for_image']);
                
                $stmt = $pdo->prepare("INSERT INTO website_content (content_key, content_value) 
                                     VALUES (?, ?)
                                     ON DUPLICATE KEY UPDATE content_value = VALUES(content_value)");
                $stmt->execute([$contentKey, $fileName]);
                
                // Clear cache
                $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/content_' . md5($contentKey) . '.cache';
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
                $_SESSION['success'] = "Image updated successfully";
            } else {
                throw new Exception("Failed to upload image");
            }
        }
        
        // Handle partner management
        if (isset($_POST['partner_action'])) {
            // Add new partner
            if ($_POST['partner_action'] === 'add' && !empty($_FILES['partner_logo'])) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/partners/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileName = uniqid() . '_' . basename($_FILES['partner_logo']['name']);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['partner_logo']['tmp_name'], $targetPath)) {
                    $stmt = $pdo->prepare("INSERT INTO partners (logo_path, name, website, is_active) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $fileName,
                        sanitize($_POST['partner_name'] ?? ''),
                        sanitize($_POST['partner_website'] ?? ''),
                        isset($_POST['partner_active']) ? 1 : 0
                    ]);
                    
                    // Clear partners cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_partners.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "Partner added successfully";
                } else {
                    throw new Exception("Failed to move uploaded file");
                }
            }
            
            // Delete partner
            if ($_POST['partner_action'] === 'delete' && isset($_POST['partner_id'])) {
                $partnerId = (int)$_POST['partner_id'];
                
                $stmt = $pdo->prepare("SELECT logo_path FROM partners WHERE id = ?");
                $stmt->execute([$partnerId]);
                $partner = $stmt->fetch();
                
                if ($partner) {
                    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/partners/' . $partner['logo_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ?");
                    $stmt->execute([$partnerId]);
                    
                    // Clear cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_partners.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "Partner deleted successfully";
                }
            }
        }

        // Handle resource management
        if (isset($_POST['resource_action'])) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/resources/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Add new resource
            if ($_POST['resource_action'] === 'add') {
                $filePath = '';
                $thumbnailPath = '';
                
                // Handle file upload if present
                if (!empty($_FILES['resource_file']['name'])) {
                    $fileName = uniqid() . '_' . basename($_FILES['resource_file']['name']);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $targetPath)) {
                        $filePath = $fileName;
                    } else {
                        throw new Exception("Failed to upload resource file");
                    }
                } elseif (!empty($_POST['resource_url'])) {
                    $filePath = sanitize($_POST['resource_url']);
                } else {
                    throw new Exception("Either a file or URL must be provided");
                }
                
                // Handle thumbnail upload if present
                if (!empty($_FILES['resource_thumbnail']['name'])) {
                    $thumbName = uniqid() . '_' . basename($_FILES['resource_thumbnail']['name']);
                    $thumbPath = $uploadDir . $thumbName;
                    
                    if (move_uploaded_file($_FILES['resource_thumbnail']['tmp_name'], $thumbPath)) {
                        $thumbnailPath = $thumbName;
                    }
                }
                
                $stmt = $pdo->prepare("INSERT INTO resources 
                    (title, type, description, file_path, thumbnail_path, is_featured, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    sanitize($_POST['resource_title'] ?? ''),
                    sanitize($_POST['resource_type'] ?? ''),
                    sanitize($_POST['resource_description'] ?? ''),
                    $filePath,
                    $thumbnailPath,
                    isset($_POST['resource_featured']) ? 1 : 0,
                    isset($_POST['resource_active']) ? 1 : 0
                ]);
                
                // Clear resources cache
                $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_resources.cache';
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
                $_SESSION['success'] = "Resource added successfully";
            }
            
            // Toggle resource status
            if ($_POST['resource_action'] === 'toggle' && isset($_POST['resource_id'])) {
                $resourceId = (int)$_POST['resource_id'];
                $stmt = $pdo->prepare("UPDATE resources SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$resourceId]);
                
                // Clear cache
                $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_resources.cache';
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
                $_SESSION['success'] = "Resource status updated";
            }
            
            // Delete resource
            if ($_POST['resource_action'] === 'delete' && isset($_POST['resource_id'])) {
                $resourceId = (int)$_POST['resource_id'];
                
                $stmt = $pdo->prepare("SELECT file_path, thumbnail_path FROM resources WHERE id = ?");
                $stmt->execute([$resourceId]);
                $resource = $stmt->fetch();
                
                if ($resource) {
                    // Delete associated files
                    if (!empty($resource['file_path']) && !filter_var($resource['file_path'], FILTER_VALIDATE_URL)) {
                        $filePath = $uploadDir . $resource['file_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                    
                    if (!empty($resource['thumbnail_path'])) {
                        $thumbPath = $uploadDir . $resource['thumbnail_path'];
                        if (file_exists($thumbPath)) {
                            unlink($thumbPath);
                        }
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ?");
                    $stmt->execute([$resourceId]);
                    
                    // Clear cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_resources.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "Resource deleted successfully";
                }
            }
        }

        // Handle product management
        if (isset($_POST['product_action'])) {
            $action = sanitize($_POST['product_action']);
            
            try {
                // Add new product
                if ($action === 'add') {
                    $category = sanitize($_POST['product_category']);

                    // Check if we're adding a new category
                    if (isset($_POST['new_category']) && !empty($_POST['new_category'])) {
                        $newCategory = sanitize($_POST['new_category']);
                        
                        // Validate the new category
                        if (empty($newCategory)) {
                            $_SESSION['error'] = "Please enter a category name";
                            header("Location: home-content.php");
                            exit();
                        }
                        
                        // Check if category already exists
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category = ?");
                        $stmt->execute([$newCategory]);
                        if ($stmt->fetchColumn() > 0) {
                            $_SESSION['error'] = "This category already exists";
                            header("Location: home-content.php");
                            exit();
                        }
                        
                        $category = $newCategory;
                    }

                    // Handle image uploads
                    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/products/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $imagePaths = [];
                    if (!empty($_FILES['product_images']['name'][0])) {
                        foreach ($_FILES['product_images']['tmp_name'] as $key => $tmpName) {
                            if ($_FILES['product_images']['size'][$key] > 1 * 1024 * 1024) {
                                throw new Exception("Image size exceeds 1MB limit");
                            }
                            
                            $fileType = mime_content_type($tmpName);
                            if (!in_array($fileType, ['image/jpeg', 'image/png', 'image/gif'])) {
                                throw new Exception("Only JPG, PNG and GIF images are allowed");
                            }
                            
                            $fileName = uniqid() . '_' . basename($_FILES['product_images']['name'][$key]);
                            $targetPath = $uploadDir . $fileName;
                            
                            if (move_uploaded_file($tmpName, $targetPath)) {
                                $imagePaths[] = $fileName;
                            } else {
                                throw new Exception("Failed to upload image");
                            }
                            
                            if (count($imagePaths) >= 5) break; // Limit to 5 images
                        }
                    }
                    
                    if (empty($imagePaths)) {
                        throw new Exception("At least one product image is required");
                    }
                    
                    // Insert product
                    $stmt = $pdo->prepare("INSERT INTO products 
                        (name, category, price, stock, status, description, image_url, is_featured, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                    $stmt->execute([
                        sanitize($_POST['product_name']),
                        $category,
                        (float)$_POST['product_price'],
                        (int)$_POST['product_stock'],
                        sanitize($_POST['product_status']),
                        sanitize($_POST['product_description']),
                        json_encode($imagePaths),
                        isset($_POST['product_featured']) ? 1 : 0
                    ]);
                    
                    $_SESSION['success'] = "Product added successfully";
                }
                
                // Update product
                elseif ($action === 'update' && isset($_POST['product_id'])) {
                    $productId = (int)$_POST['product_id'];
                    
                    // Get current product data
                    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch();
                    
                    if (!$product) {
                        throw new Exception("Product not found");
                    }
                    
                    // Handle category (existing or new)
                    $category = sanitize($_POST['product_category']);
                    if ($category === '_new_category') {
                        $category = sanitize($_POST['new_category']);
                        if (empty($category)) {
                            throw new Exception("Category name cannot be empty");
                        }
                    }
                    
                    // Handle existing images
                    $currentImages = json_decode($product['image_url'], true) ?: [$product['image_url']];
                    $imagePaths = $currentImages;
                    
                    // Handle new image uploads
                    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/products/';
                    if (!empty($_FILES['product_images']['name'][0])) {
                        foreach ($_FILES['product_images']['tmp_name'] as $key => $tmpName) {
                            if ($_FILES['product_images']['size'][$key] > 1 * 1024 * 1024) {
                                throw new Exception("Image size exceeds 1MB limit");
                            }
                            
                            $fileType = mime_content_type($tmpName);
                            if (!in_array($fileType, ['image/jpeg', 'image/png', 'image/gif'])) {
                                throw new Exception("Only JPG, PNG and GIF images are allowed");
                            }
                            
                            $fileName = uniqid() . '_' . basename($_FILES['product_images']['name'][$key]);
                            $targetPath = $uploadDir . $fileName;
                            
                            if (move_uploaded_file($tmpName, $targetPath)) {
                                $imagePaths[] = $fileName;
                            } else {
                                throw new Exception("Failed to upload image");
                            }
                            
                            if (count($imagePaths) >= 5) break; // Limit to 5 images
                        }
                    }
                    
                    // Update product
                    $stmt = $pdo->prepare("UPDATE products SET
                        name = ?,
                        category = ?,
                        price = ?,
                        stock = ?,
                        status = ?,
                        description = ?,
                        short_description = ?,
                        image_url = ?,
                        is_featured = ?,
                        updated_at = NOW()
                        WHERE id = ?");
                    
                    $stmt->execute([
                        sanitize($_POST['product_name']),
                        $category,
                        (float)$_POST['product_price'],
                        (int)$_POST['product_stock'],
                        sanitize($_POST['product_status']),
                        sanitize($_POST['product_description']),
                        sanitize($_POST['product_short_desc']),
                        json_encode($imagePaths),
                        isset($_POST['product_featured']) ? 1 : 0,
                        $productId
                    ]);
                    
                    $_SESSION['success'] = "Product updated successfully";
                }
                
                // Delete product
                elseif ($action === 'delete' && isset($_POST['product_id'])) {
                    $productId = (int)$_POST['product_id'];
                    
                    // Get product images
                    $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch();
                    
                    if ($product) {
                        // Delete associated images
                        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/products/';
                        $images = json_decode($product['image_url'], true) ?: [$product['image_url']];
                        
                        foreach ($images as $image) {
                            $filePath = $uploadDir . $image;
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                        }
                        
                        // Delete product
                        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                        $stmt->execute([$productId]);
                        
                        $_SESSION['success'] = "Product deleted successfully";
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Product Error: " . $e->getMessage();
            }
        }

        // Handle category management
        if (isset($_POST['category_action'])) {
            $action = sanitize($_POST['category_action']);
            
            try {
                // Add new category
                if ($action === 'add') {
                    $categoryName = sanitize($_POST['category_name']);
                    $categoryIcon = str_replace('fa-', '', sanitize($_POST['category_icon']));
                    
                    if (empty($categoryName)) {
                        throw new Exception("Category name cannot be empty");
                    }
                    
                    // Check if category already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category = ?");
                    $stmt->execute([$categoryName]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception("Category already exists");
                    }
                    
                    $_SESSION['success'] = "Category will be created when first product is added";
                }
                
                // Delete category
                elseif ($action === 'delete' && isset($_POST['category_name'])) {
                    $categoryName = sanitize($_POST['category_name']);
                    
                    // Move products to uncategorized
                    $stmt = $pdo->prepare("UPDATE products SET category = NULL WHERE category = ?");
                    $stmt->execute([$categoryName]);
                    
                    $_SESSION['success'] = "Category deleted. Products moved to Uncategorized.";
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Category Error: " . $e->getMessage();
            }
        }

        // Handle testimonial management
        if (isset($_POST['testimonial_action'])) {
            // Add testimonial
            if ($_POST['testimonial_action'] === 'add' && !empty($_POST['testimonial_author']) && !empty($_POST['testimonial_content'])) {
                $stmt = $pdo->prepare("INSERT INTO testimonials (author, content, is_active) VALUES (?, ?, 1)");
                $stmt->execute([
                    sanitize($_POST['testimonial_author']),
                    sanitize($_POST['testimonial_content'])
                ]);
                
                // Clear cache
                $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_testimonials.cache';
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
                $_SESSION['success'] = "Testimonial added successfully";
            }
            
            // Delete testimonial
            if ($_POST['testimonial_action'] === 'delete' && isset($_POST['testimonial_id'])) {
                $testimonialId = (int)$_POST['testimonial_id'];
                $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ?");
                $stmt->execute([$testimonialId]);
                
                // Clear cache
                $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_testimonials.cache';
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
                $_SESSION['success'] = "Testimonial deleted successfully";
            }
            
            // Toggle testimonial status
            if ($_POST['testimonial_action'] === 'toggle' && isset($_POST['testimonial_id'])) {
                $testimonialId = (int)$_POST['testimonial_id'];
                $stmt = $pdo->prepare("UPDATE testimonials SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$testimonialId]);
                
                // Clear cache
                $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_testimonials.cache';
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
                $_SESSION['success'] = "Testimonial status updated";
            }
        }
        
        // Handle team member management
        if (isset($_POST['team_action'])) {
            // Add new team member
            if ($_POST['team_action'] === 'add' && !empty($_FILES['team_photo'])) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/team/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileName = uniqid() . '_' . basename($_FILES['team_photo']['name']);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['team_photo']['tmp_name'], $targetPath)) {
                    $stmt = $pdo->prepare("INSERT INTO team_members (name, role, bio, image_path, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        sanitize($_POST['team_name'] ?? ''),
                        sanitize($_POST['team_role'] ?? ''),
                        sanitize($_POST['team_bio'] ?? ''),
                        $fileName,
                        (int)($_POST['team_order'] ?? 0),
                        isset($_POST['team_active']) ? 1 : 0
                    ]);
                    
                    // Clear team cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_team_members.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "Team member added successfully";
                } else {
                    throw new Exception("Failed to move uploaded file");
                }
            }
            
            // Delete team member
            if ($_POST['team_action'] === 'delete' && isset($_POST['team_id'])) {
                $teamId = (int)$_POST['team_id'];
                
                $stmt = $pdo->prepare("SELECT image_path FROM team_members WHERE id = ?");
                $stmt->execute([$teamId]);
                $member = $stmt->fetch();
                
                if ($member) {
                    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/team/' . $member['image_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM team_members WHERE id = ?");
                    $stmt->execute([$teamId]);
                    
                    // Clear cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_team_members.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "Team member deleted successfully";
                }
            }
            
            // Toggle team member status
            if ($_POST['team_action'] === 'toggle' && isset($_POST['team_id'])) {
                $teamId = (int)$_POST['team_id'];
                $stmt = $pdo->prepare("UPDATE team_members SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$teamId]);
                
                // Clear cache
                $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_team_members.cache';
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
                $_SESSION['success'] = "Team member status updated";
            }
        }
        
    } catch (Exception $e) {
        error_log("Admin Error: " . $e->getMessage());
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: home-content.php");
    exit();
}
        // Handle insight management
        if (isset($_POST['insight_action'])) {
            $action = sanitize($_POST['insight_action']);
            
            try {
                // Add new insight
                if ($action === 'add') {
                    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/insights/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $imagePath = '';
                    if (!empty($_FILES['insight_image']['name'])) {
                        $fileName = uniqid() . '_' . basename($_FILES['insight_image']['name']);
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['insight_image']['tmp_name'], $targetPath)) {
                            $imagePath = $fileName;
                        } else {
                            throw new Exception("Failed to upload image");
                        }
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO expert_insights 
                        (title, content, author, author_credentials, image_path, is_active, published_date)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        sanitize($_POST['insight_title'] ?? ''),
                        sanitize($_POST['insight_content'] ?? ''),
                        sanitize($_POST['insight_author'] ?? ''),
                        sanitize($_POST['insight_credentials'] ?? ''),
                        $imagePath,
                        isset($_POST['insight_active']) ? 1 : 0
                    ]);
                    
                    // Clear insights cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_insights.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "Insight added successfully";
                }
                
                // Update insight
                elseif ($action === 'update' && isset($_POST['insight_id'])) {
                    $insightId = (int)$_POST['insight_id'];
                    
                    // Get current insight data
                    $stmt = $pdo->prepare("SELECT image_path FROM expert_insights WHERE id = ?");
                    $stmt->execute([$insightId]);
                    $currentInsight = $stmt->fetch();
                    
                    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/insights/';
                    $imagePath = $currentInsight['image_path'] ?? '';
                    
                    // Handle new image upload
                    if (!empty($_FILES['insight_image']['name'])) {
                        // Delete old image if exists
                        if (!empty($imagePath) && file_exists($uploadDir . $imagePath)) {
                            unlink($uploadDir . $imagePath);
                        }
                        
                        $fileName = uniqid() . '_' . basename($_FILES['insight_image']['name']);
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['insight_image']['tmp_name'], $targetPath)) {
                            $imagePath = $fileName;
                        } else {
                            throw new Exception("Failed to upload image");
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE expert_insights SET
                        title = ?,
                        content = ?,
                        author = ?,
                        author_credentials = ?,
                        image_path = ?,
                        is_active = ?,
                        updated_at = NOW()
                        WHERE id = ?");
                    $stmt->execute([
                        sanitize($_POST['insight_title'] ?? ''),
                        sanitize($_POST['insight_content'] ?? ''),
                        sanitize($_POST['insight_author'] ?? ''),
                        sanitize($_POST['insight_credentials'] ?? ''),
                        $imagePath,
                        isset($_POST['insight_active']) ? 1 : 0,
                        $insightId
                    ]);
                    
                    // Clear cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_insights.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "Insight updated successfully";
                }
                
                // Delete insight
                elseif ($action === 'delete' && isset($_POST['insight_id'])) {
                    $insightId = (int)$_POST['insight_id'];
                    
                    $stmt = $pdo->prepare("SELECT image_path FROM expert_insights WHERE id = ?");
                    $stmt->execute([$insightId]);
                    $insight = $stmt->fetch();
                    
                    if ($insight) {
                        // Delete associated image
                        if (!empty($insight['image_path'])) {
                            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/insights/' . $insight['image_path'];
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                        }
                        
                        $stmt = $pdo->prepare("DELETE FROM expert_insights WHERE id = ?");
                        $stmt->execute([$insightId]);
                        
                        // Clear cache
                        $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_insights.cache';
                        if (file_exists($cacheFile)) {
                            unlink($cacheFile);
                        }
                        $_SESSION['success'] = "Insight deleted successfully";
                    }
                }
                
                // Toggle insight status
                elseif ($action === 'toggle' && isset($_POST['insight_id'])) {
                    $insightId = (int)$_POST['insight_id'];
                    $stmt = $pdo->prepare("UPDATE expert_insights SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$insightId]);
                    
                    // Clear cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_insights.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "Insight status updated";
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Insight Error: " . $e->getMessage();
            }
        }

        // Handle market price management
       // Handle market price management
if (isset($_POST['price_action'])) {
    $action = sanitize($_POST['price_action']);
    
    try {
        // Add new price
        if ($action === 'add') {
            // Validate required fields
            if (empty($_POST['price_commodity']) || empty($_POST['price_market']) || !isset($_POST['price_value'])) {
                throw new Exception("Required fields are missing");
            }
            
            // Prepare statement
            $stmt = $pdo->prepare("INSERT INTO market_prices 
                (commodity, market, price, unit, date_recorded, source)
                VALUES (:commodity, :market, :price, :unit, :date_recorded, :source)");
                
            // Bind parameters
            $stmt->bindParam(':commodity', $_POST['price_commodity']);
            $stmt->bindParam(':market', $_POST['price_market']);
            $stmt->bindParam(':price', $_POST['price_value'], PDO::PARAM_STR);
            $stmt->bindParam(':unit', $_POST['price_unit'] ?? 'kg');
            $stmt->bindParam(':date_recorded', $_POST['price_date'] ?? date('Y-m-d'));
            $stmt->bindParam(':source', $_POST['price_source'] ?? '');
            
            // Execute
            if (!$stmt->execute()) {
                $error = $stmt->errorInfo();
                throw new Exception("Database error: " . $error[2]);
            }
            
            // Clear cache
            $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_prices.cache';
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
            
            $_SESSION['success'] = "Market price added successfully";
        } 
        // Update price
        elseif ($action === 'update' && isset($_POST['price_id'])) {
            $priceId = (int)$_POST['price_id'];
            
            $stmt = $pdo->prepare("UPDATE market_prices SET
                commodity = ?,
                market = ?,
                price = ?,
                unit = ?,
                date_recorded = ?,
                source = ?,
                updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([
                sanitize($_POST['price_commodity'] ?? ''),
                sanitize($_POST['price_market'] ?? ''),
                (float)$_POST['price_value'],
                sanitize($_POST['price_unit'] ?? 'kg'),
                sanitize($_POST['price_date'] ?? date('Y-m-d')),
                sanitize($_POST['price_source'] ?? ''),
                $priceId
            ]);
            
            // Clear cache
            $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_prices.cache';
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
            $_SESSION['success'] = "Market price updated successfully";
        }
        // Delete price
        elseif ($action === 'delete' && isset($_POST['price_id'])) {
            $priceId = (int)$_POST['price_id'];
            
            $stmt = $pdo->prepare("DELETE FROM market_prices WHERE id = ?");
            $stmt->execute([$priceId]);
            
            // Clear cache
            $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_prices.cache';
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
            $_SESSION['success'] = "Market price deleted successfully";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Price Error: " . $e->getMessage();
    }
}

        // Handle agricultural news management
        // Handle agricultural news management
if (isset($_POST['news_action'])) {
    $action = sanitize($_POST['news_action']);
    
    try {
        // Add new news
        if ($action === 'add') {
            // Validate required fields
            if (empty($_POST['news_title']) || empty($_POST['news_content'])) {
                throw new Exception("Title and content are required");
            }

            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/news/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $imagePath = '';
            if (!empty($_FILES['news_image']['name'])) {
                // Validate image
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $fileType = mime_content_type($_FILES['news_image']['tmp_name']);
                
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception("Only JPG, PNG, and GIF images are allowed");
                }
                
                if ($_FILES['news_image']['size'] > 2 * 1024 * 1024) { // 2MB limit
                    throw new Exception("Image size exceeds 2MB limit");
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['news_image']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (!move_uploaded_file($_FILES['news_image']['tmp_name'], $targetPath)) {
                    throw new Exception("Failed to upload image");
                }
                
                $imagePath = $fileName;
            }
            
            $stmt = $pdo->prepare("INSERT INTO agricultural_news 
                (title, content, source, image_path, is_active, published_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                sanitize($_POST['news_title'] ?? ''),
                sanitize($_POST['news_content'] ?? ''),
                sanitize($_POST['news_source'] ?? ''),
                $imagePath,
                isset($_POST['news_active']) ? 1 : 0,
                sanitize($_POST['news_date'] ?? date('Y-m-d'))
            ]);
            
            // Clear news cache
            $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_news.cache';
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
            
            $_SESSION['success'] = "News article added successfully";
            header("Location: home-content.php");
            exit();
        }
                elseif ($action === 'update' && isset($_POST['news_id'])) {
                    $newsId = (int)$_POST['news_id'];
                    
                    // Get current news data
                    $stmt = $pdo->prepare("SELECT image_path FROM agricultural_news WHERE id = ?");
                    $stmt->execute([$newsId]);
                    $currentNews = $stmt->fetch();
                    
                    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/news/';
                    $imagePath = $currentNews['image_path'] ?? '';
                    
                    // Handle new image upload
                    if (!empty($_FILES['news_image']['name'])) {
                        // Delete old image if exists
                        if (!empty($imagePath) && file_exists($uploadDir . $imagePath)) {
                            unlink($uploadDir . $imagePath);
                        }
                        
                        $fileName = uniqid() . '_' . basename($_FILES['news_image']['name']);
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['news_image']['tmp_name'], $targetPath)) {
                            $imagePath = $fileName;
                        } else {
                            throw new Exception("Failed to upload image");
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE agricultural_news SET
                        title = ?,
                        content = ?,
                        source = ?,
                        image_path = ?,
                        is_active = ?,
                        published_date = ?,
                        updated_at = NOW()
                        WHERE id = ?");
                    $stmt->execute([
                        sanitize($_POST['news_title'] ?? ''),
                        sanitize($_POST['news_content'] ?? ''),
                        sanitize($_POST['news_source'] ?? ''),
                        $imagePath,
                        isset($_POST['news_active']) ? 1 : 0,
                        sanitize($_POST['news_date'] ?? date('Y-m-d')),
                        $newsId
                    ]);
                    
                    // Clear cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_news.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "News article updated successfully";
                }
                
                // Delete news
                elseif ($action === 'delete' && isset($_POST['news_id'])) {
                    $newsId = (int)$_POST['news_id'];
                    
                    $stmt = $pdo->prepare("SELECT image_path FROM agricultural_news WHERE id = ?");
                    $stmt->execute([$newsId]);
                    $news = $stmt->fetch();
                    
                    if ($news) {
                        // Delete associated image
                        if (!empty($news['image_path'])) {
                            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/uploads/news/' . $news['image_path'];
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                        }
                        
                        $stmt = $pdo->prepare("DELETE FROM agricultural_news WHERE id = ?");
                        $stmt->execute([$newsId]);
                        
                        // Clear cache
                        $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_news.cache';
                        if (file_exists($cacheFile)) {
                            unlink($cacheFile);
                        }
                        $_SESSION['success'] = "News article deleted successfully";
                    }
                }
                
                // Toggle news status
                elseif ($action === 'toggle' && isset($_POST['news_id'])) {
                    $newsId = (int)$_POST['news_id'];
                    $stmt = $pdo->prepare("UPDATE agricultural_news SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$newsId]);
                    
                    // Clear cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_news.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "News article status updated";
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "News Error: " . $e->getMessage();
            }
        }

        // Handle weather data management
        if (isset($_POST['weather_action'])) {
            $action = sanitize($_POST['weather_action']);
            
            try {
                // Add new weather data
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO weather_data 
                        (region, forecast, temperature, rainfall, wind_speed, wind_direction, humidity, date_recorded)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        sanitize($_POST['weather_region'] ?? ''),
                        sanitize($_POST['weather_forecast'] ?? ''),
                        (float)$_POST['weather_temp'],
                        (float)$_POST['weather_rain'],
                        (float)$_POST['weather_wind'],
                        sanitize($_POST['weather_wind_dir'] ?? ''),
                        (int)$_POST['weather_humidity'],
                        sanitize($_POST['weather_date'] ?? date('Y-m-d'))
                    ]);
                    
                    // Clear weather cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_weather.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "Weather data added successfully";
                }
                
                // Update weather data
                elseif ($action === 'update' && isset($_POST['weather_id'])) {
                    $weatherId = (int)$_POST['weather_id'];
                    
                    $stmt = $pdo->prepare("UPDATE weather_data SET
                        region = ?,
                        forecast = ?,
                        temperature = ?,
                        rainfall = ?,
                        wind_speed = ?,
                        wind_direction = ?,
                        humidity = ?,
                        date_recorded = ?,
                        updated_at = NOW()
                        WHERE id = ?");
                    $stmt->execute([
                        sanitize($_POST['weather_region'] ?? ''),
                        sanitize($_POST['weather_forecast'] ?? ''),
                        (float)$_POST['weather_temp'],
                        (float)$_POST['weather_rain'],
                        (float)$_POST['weather_wind'],
                        sanitize($_POST['weather_wind_dir'] ?? ''),
                        (int)$_POST['weather_humidity'],
                        sanitize($_POST['weather_date'] ?? date('Y-m-d')),
                        $weatherId
                    ]);
                    
                    // Clear cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_weather.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "Weather data updated successfully";
                }
                
                // Delete weather data
                elseif ($action === 'delete' && isset($_POST['weather_id'])) {
                    $weatherId = (int)$_POST['weather_id'];
                    
                    $stmt = $pdo->prepare("DELETE FROM weather_data WHERE id = ?");
                    $stmt->execute([$weatherId]);
                    
                    // Clear cache
                    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/cache/data_weather.cache';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $_SESSION['success'] = "Weather data deleted successfully";
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Weather Error: " . $e->getMessage();
            }
        }
  
   
// Fetch current content
try {
    // Website content
    $stmt = $pdo->query("SELECT content_key, content_value FROM website_content");
    $websiteContent = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $websiteContent[$row['content_key']] = $row['content_value'];
    }
    
    // Partners
    $stmt = $pdo->query("SELECT * FROM partners ORDER BY name");
    $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Testimonials
    $stmt = $pdo->query("SELECT * FROM testimonials ORDER BY created_at DESC");
    $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Team members
    $stmt = $pdo->query("SELECT * FROM team_members ORDER BY display_order, name");
    $teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Products
    $products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();

    // Insights
    $insights = $pdo->query("SELECT * FROM expert_insights ORDER BY published_date DESC")->fetchAll();

    // Market prices
    $prices = $pdo->query("SELECT * FROM market_prices ORDER BY date_recorded DESC LIMIT 50")->fetchAll();

    // Agricultural news
    $news = $pdo->query("SELECT * FROM agricultural_news ORDER BY published_date DESC LIMIT 10")->fetchAll();

    // Weather data
    $weather = $pdo->query("SELECT * FROM weather_data ORDER BY date_recorded DESC LIMIT 10")->fetchAll();

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $websiteContent = [];
    $partners = [];
    $testimonials = [];
    $teamMembers = [];
    $products = [];
    $insights = [];
    $prices = [];
    $news = [];
    $weather = [];
}

$page_title = "Manage Website Content";
include '../includes/header.php';
?>

<!-- CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin/home-content.css">

<div class="container admin-home-content">
    <h1><i class="fas fa-cogs"></i> Manage Website Content</h1>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column: Existing Content Management -->
        <div class="col-md-6">
            <!-- Hero Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-image"></i> Hero Section</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="content_key" value="hero_text">
                        
                        <div class="form-group">
                            <label>Hero Text</label>
                            <textarea name="content_value" class="form-control" rows="3" required><?= 
                                htmlspecialchars($websiteContent['hero_text'] ?? 'Default hero text here...') 
                            ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </form>
                </div>
            </div>

            <!-- Hero Media Management -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-photo-video"></i> Hero Media</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="media_action" value="update">
                        
                        <div class="form-group">
                            <label>Current Hero Media</label>
                            <?php 
                            try {
                                $stmt = $pdo->query("SELECT * FROM hero_media WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
                                $currentMedia = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($currentMedia): ?>
                                    <div class="current-media-preview mb-3">
                                        <?php if ($currentMedia['media_type'] === 'video'): ?>
                                            <video controls style="max-width: 100%; max-height: 200px;">
                                                <source src="<?= BASE_URL ?>/uploads/hero/<?= htmlspecialchars($currentMedia['file_path']) ?>" type="video/mp4">
                                            </video>
                                        <?php else: ?>
                                            <img src="<?= BASE_URL ?>/uploads/hero/<?= htmlspecialchars($currentMedia['file_path']) ?>" 
                                                 style="max-width: 100%; max-height: 200px;">
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <small>Type: <?= htmlspecialchars($currentMedia['media_type']) ?></small><br>
                                            <small>Uploaded: <?= date('M j, Y', strtotime($currentMedia['created_at'])) ?></small>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No active hero media set</p>
                                <?php endif;
                            } catch (PDOException $e) {
                                echo '<p class="text-danger">Error loading current media</p>';
                                error_log("Hero media error: " . $e->getMessage());
                            }
                            ?>
                        </div>
                        
                        <div class="form-group">
                            <label>Upload New Hero Media</label>
                            <div class="custom-file">
                                <input type="file" name="media_file" class="custom-file-input" id="heroMediaFile" required>
                                <label class="custom-file-label" for="heroMediaFile">Choose file...</label>
                            </div>
                            <small class="form-text text-muted">Accepted: Images (JPG, PNG) or Videos (MP4, WebM). Max 10MB.</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Media Type</label>
                            <select name="media_type" class="form-control" required>
                                <option value="image">Image</option>
                                <option value="video">Video</option>
                            </select>
                        </div>
                        
                        <div class="form-group form-check">
                            <input type="checkbox" name="set_active" class="form-check-input" id="setActive" checked>
                            <label class="form-check-label" for="setActive">Set as active hero media</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Media
                        </button>
                    </form>
                    
                    <?php if (!empty($currentMedia)): ?>
                    <hr>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="media_action" value="delete">
                        <input type="hidden" name="media_id" value="<?= $currentMedia['id'] ?>">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this hero media?')">
                            <i class="fas fa-trash"></i> Delete Current Media
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Vision & Mission -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-bullseye"></i> Vision & Mission</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="content_key" value="vision">
                        <div class="form-group">
                            <label>Vision Statement</label>
                            <textarea name="content_value" class="form-control" rows="3" required><?= 
                                htmlspecialchars($websiteContent['vision'] ?? 'Our vision statement...') 
                            ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Vision</button>
                    </form>
                    
                    <hr>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="content_key" value="mission">
                        <div class="form-group">
                            <label>Mission Statement</label>
                            <textarea name="content_value" class="form-control" rows="3" required><?= 
                                htmlspecialchars($websiteContent['mission'] ?? 'Our mission statement...') 
                            ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Mission</button>
                    </form>
                </div>
            </div>
            
            <!-- Partners Management -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-handshake"></i> Partners</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="partner_action" value="add">
                        
                        <div class="form-group">
                            <label>Partner Name</label>
                            <input type="text" name="partner_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Website URL</label>
                            <input type="url" name="partner_website" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Logo (300×150px recommended)</label>
                            <div class="custom-file">
                                <input type="file" name="partner_logo" class="custom-file-input" id="partnerLogo" required accept="image/*">
                                <label class="custom-file-label" for="partnerLogo">Choose file...</label>
                            </div>
                        </div>
                        
                        <div class="form-group form-check">
                            <input type="checkbox" name="partner_active" class="form-check-input" id="partnerActive" checked>
                            <label class="form-check-label" for="partnerActive">Active</label>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Partner
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h4>Current Partners</h4>
                    <?php if (!empty($partners)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Logo</th>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($partners as $partner): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($partner['logo_path'])): ?>
                                                <img src="<?= BASE_URL ?>/uploads/partners/<?= htmlspecialchars($partner['logo_path']) ?>" 
                                                     alt="<?= htmlspecialchars($partner['name']) ?>" style="max-height: 50px;">
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($partner['name']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $partner['is_active'] ? 'success' : 'secondary' ?>">
                                                <?= $partner['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="partner_action" value="delete">
                                                <input type="hidden" name="partner_id" value="<?= $partner['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Delete this partner?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No partners added yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
         <!-- About Page & Testimonials -->
         <div class="col-md-6">
            <!-- About Page Management -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> About Page Content</h3>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="aboutTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="hero-tab" data-toggle="tab" href="#about-hero" role="tab">Hero</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="story-tab" data-toggle="tab" href="#about-story" role="tab">Our Story</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="mission-tab" data-toggle="tab" href="#about-mission" role="tab">Mission</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="team-tab" data-toggle="tab" href="#about-team" role="tab">Our Team</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="impact-tab" data-toggle="tab" href="#about-impact" role="tab">Impact</a>
                        </li>
                    </ul>
                    
                    <div class="tab-content pt-3" id="aboutTabsContent">
                        <!-- Hero Section -->
                        <div class="tab-pane fade show active" id="about-hero" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="content_key" value="about_hero_title">
                                <div class="form-group">
                                    <label>Hero Title</label>
                                    <input type="text" name="content_value" class="form-control" required 
                                           value="<?= htmlspecialchars($websiteContent['about_hero_title'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-3">Update Title</button>
                            </form>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="content_key" value="about_hero_subtitle">
                                <div class="form-group">
                                    <label>Hero Subtitle</label>
                                    <input type="text" name="content_value" class="form-control" required 
                                           value="<?= htmlspecialchars($websiteContent['about_hero_subtitle'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-3">Update Subtitle</button>
                            </form>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="image_action" value="update">
                                <input type="hidden" name="image_type" value="about">
                                <input type="hidden" name="content_key_for_image" value="about_hero_image">
                                <input type="hidden" name="current_image" value="<?= htmlspecialchars($websiteContent['about_hero_image'] ?? '') ?>">
                                
                                <div class="form-group">
                                    <label>Hero Image</label>
                                    <?php if (!empty($websiteContent['about_hero_image'])): ?>
                                        <div class="mb-3">
                                            <img src="<?= BASE_URL ?>/uploads/about/<?= htmlspecialchars($websiteContent['about_hero_image']) ?>" 
                                                 alt="Current Hero Image" style="max-width: 100%; max-height: 200px;">
                                        </div>
                                    <?php endif; ?>
                                    <div class="custom-file">
                                        <input type="file" name="image_file" class="custom-file-input" id="heroImage" accept="image/*">
                                        <label class="custom-file-label" for="heroImage">Choose new image...</label>
                                    </div>
                                    <small class="form-text text-muted">Recommended size: 1920×1080px</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Upload Image</button>
                            </form>
                        </div>
                        
                        <!-- Our Story -->
                        <div class="tab-pane fade" id="about-story" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="content_key" value="about_story_title">
                                <div class="form-group">
                                    <label>Story Title</label>
                                    <input type="text" name="content_value" class="form-control" required 
                                           value="<?= htmlspecialchars($websiteContent['about_story_title'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-3">Update Title</button>
                            </form>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="content_key" value="about_story_content">
                                <div class="form-group">
                                    <label>Story Content</label>
                                    <textarea name="content_value" class="form-control" rows="5" required><?= 
                                        htmlspecialchars($websiteContent['about_story_content'] ?? '') 
                                    ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary mb-3">Update Content</button>
                            </form>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="image_action" value="update">
                                <input type="hidden" name="image_type" value="about">
                                <input type="hidden" name="content_key_for_image" value="about_story_image">
                                <input type="hidden" name="current_image" value="<?= htmlspecialchars($websiteContent['about_story_image'] ?? '') ?>">
                                
                                <div class="form-group">
                                    <label>Story Image</label>
                                    <?php if (!empty($websiteContent['about_story_image'])): ?>
                                        <div class="mb-3">
                                            <img src="<?= BASE_URL ?>/uploads/about/<?= htmlspecialchars($websiteContent['about_story_image']) ?>" 
                                                 alt="Current Story Image" style="max-width: 100%; max-height: 200px;">
                                        </div>
                                    <?php endif; ?>
                                    <div class="custom-file">
                                        <input type="file" name="image_file" class="custom-file-input" id="storyImage" accept="image/*">
                                        <label class="custom-file-label" for="storyImage">Choose new image...</label>
                                    </div>
                                    <small class="form-text text-muted">Recommended size: 800×600px</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Upload Image</button>
                            </form>
                        </div>
                        
                        <!-- Mission & Values -->
                        <div class="tab-pane fade" id="about-mission" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="content_key" value="about_mission_title">
                                <div class="form-group">
                                    <label>Mission Title</label>
                                    <input type="text" name="content_value" class="form-control" required 
                                           value="<?= htmlspecialchars($websiteContent['about_mission_title'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-3">Update Title</button>
                            </form>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="content_key" value="about_mission_content">
                                <div class="form-group">
                                    <label>Mission Content</label>
                                    <textarea name="content_value" class="form-control" rows="3" required><?= 
                                        htmlspecialchars($websiteContent['about_mission_content'] ?? '') 
                                    ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary mb-4">Update Content</button>
                            </form>
                            
                            <h4>Core Values</h4>
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="content_key" value="about_value_<?= $i ?>">
                                <div class="form-group">
                                    <label>Value #<?= $i ?></label>
                                    <input type="text" name="content_value" class="form-control" required 
                                           value="<?= htmlspecialchars($websiteContent["about_value_$i"] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-primary">Update Value</button>
                            </form>
                            <?php endfor; ?>
                        </div>
                        
                        <!-- Our Team -->
                        <div class="tab-pane fade" id="about-team" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="content_key" value="about_team_title">
                                <div class="form-group">
                                    <label>Team Section Title</label>
                                    <input type="text" name="content_value" class="form-control" required 
                                           value="<?= htmlspecialchars($websiteContent['about_team_title'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-3">Update Title</button>
                            </form>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="content_key" value="about_team_desc">
                                <div class="form-group">
                                    <label>Team Description</label>
                                    <textarea name="content_value" class="form-control" rows="2" required><?= 
                                        htmlspecialchars($websiteContent['about_team_desc'] ?? '') 
                                    ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary mb-4">Update Description</button>
                            </form>
                            
                            <h4>Add Team Member</h4>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="team_action" value="add">
                                
                                <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" name="team_name" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Role/Position</label>
                                    <input type="text" name="team_role" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Bio</label>
                                    <textarea name="team_bio" class="form-control" rows="3" required></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Display Order</label>
                                    <input type="number" name="team_order" class="form-control" min="0" value="0">
                                </div>
                                
                                <div class="form-group">
                                    <label>Photo (400×400px recommended)</label>
                                    <div class="custom-file">
                                        <input type="file" name="team_photo" class="custom-file-input" id="teamPhoto" required accept="image/*">
                                        <label class="custom-file-label" for="teamPhoto">Choose file...</label>
                                    </div>
                                </div>
                                
                                <div class="form-group form-check">
                                    <input type="checkbox" name="team_active" class="form-check-input" id="teamActive" checked>
                                    <label class="form-check-label" for="teamActive">Active</label>
                                </div>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Add Team Member
                                </button>
                            </form>
                            
                            <hr>
                            
                            <h4>Current Team Members</h4>
                            <?php if (!empty($teamMembers)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Photo</th>
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Order</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teamMembers as $member): ?>
                                        <tr>
                                            <td>
                                                <img src="<?= BASE_URL ?>/uploads/team/<?= htmlspecialchars($member['image_path']) ?>" 
                                                     alt="<?= htmlspecialchars($member['name']) ?>" style="max-height: 50px;">
                                            </td>
                                            <td><?= htmlspecialchars($member['name']) ?></td>
                                            <td><?= htmlspecialchars($member['role']) ?></td>
                                            <td><?= htmlspecialchars($member['display_order']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $member['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $member['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="team_action" value="toggle">
                                                    <input type="hidden" name="team_id" value="<?= $member['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-<?= $member['is_active'] ? 'warning' : 'success' ?>">
                                                        <i class="fas fa-<?= $member['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="team_action" value="delete">
                                                    <input type="hidden" name="team_id" value="<?= $member['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" 
                                                            onclick="return confirm('Delete this team member?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <p class="text-muted">No team members added yet</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Our Impact -->
                        <div class="tab-pane fade" id="about-impact" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="content_key" value="about_impact_title">
                                <div class="form-group">
                                    <label>Impact Section Title</label>
                                    <input type="text" name="content_value" class="form-control" required 
                                           value="<?= htmlspecialchars($websiteContent['about_impact_title'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-4">Update Title</button>
                            </form>
                            
                            <h4>Impact Statistics</h4>
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5>Statistic #<?= $i ?></h5>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="content_key" value="about_stat_<?= $i ?>_value">
                                        <div class="form-group">
                                            <label>Value</label>
                                            <input type="text" name="content_value" class="form-control" required 
                                                   value="<?= htmlspecialchars($websiteContent["about_stat_{$i}_value"] ?? '') ?>">
                                        </div>
                                        <button type="submit" class="btn btn-primary mb-3">Update Value</button>
                                    </form>
                                    
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="content_key" value="about_stat_<?= $i ?>_label">
                                        <div class="form-group">
                                            <label>Label</label>
                                            <input type="text" name="content_value" class="form-control" required 
                                                   value="<?= htmlspecialchars($websiteContent["about_stat_{$i}_label"] ?? '') ?>">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Update Label</button>
                                    </form>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resources Management -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-book-open"></i> Resources Page Content</h3>
                </div>
                <div class="card-body">
                    <!-- Resource Page Hero Section -->
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="content_key" value="resources_hero_title">
                        <div class="form-group">
                            <label>Resources Page Title</label>
                            <input type="text" name="content_value" class="form-control" required 
                                   value="<?= htmlspecialchars($websiteContent['resources_hero_title'] ?? 'Agricultural Resources') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary mb-3">Update Title</button>
                    </form>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="content_key" value="resources_hero_subtitle">
                        <div class="form-group">
                            <label>Resources Page Subtitle</label>
                            <input type="text" name="content_value" class="form-control" required 
                                   value="<?= htmlspecialchars($websiteContent['resources_hero_subtitle'] ?? 'Educational materials for farmers') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary mb-4">Update Subtitle</button>
                    </form>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="image_action" value="update">
                        <input type="hidden" name="image_type" value="resources">
                        <input type="hidden" name="content_key_for_image" value="resources_hero_image">
                        <input type="hidden" name="current_image" value="<?= htmlspecialchars($websiteContent['resources_hero_image'] ?? '') ?>">
                        
                        <div class="form-group">
                            <label>Hero Image</label>
                            <?php if (!empty($websiteContent['resources_hero_image'])): ?>
                                <div class="mb-3">
                                    <img src="<?= BASE_URL ?>/uploads/resources/<?= htmlspecialchars($websiteContent['resources_hero_image']) ?>" 
                                         alt="Current Resources Hero Image" style="max-width: 100%; max-height: 200px;">
                                </div>
                            <?php endif; ?>
                            <div class="custom-file">
                                <input type="file" name="image_file" class="custom-file-input" id="resourcesHeroImage" accept="image/*">
                                <label class="custom-file-label" for="resourcesHeroImage">Choose new image...</label>
                            </div>
                            <small class="form-text text-muted">Recommended size: 1920×1080px</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Upload Image</button>
                    </form>
                    
                    <hr>
                    
                    <h4>Resource Categories</h4>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="content_key" value="resources_categories">
                        <div class="form-group">
                            <label>Category List (comma separated)</label>
                            <input type="text" name="content_value" class="form-control" 
                                   value="<?= htmlspecialchars($websiteContent['resources_categories'] ?? 'Books, Videos, Audios, Blogs, Guides, Research Papers') ?>"
                                   placeholder="Enter categories separated by commas">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Categories</button>
                    </form>
                    
                    <hr>
                    
                    <h4>Add New Resource</h4>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="resource_action" value="add">
                        
                        <div class="form-group">
                            <label>Resource Title</label>
                            <input type="text" name="resource_title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Resource Type</label>
                            <select name="resource_type" class="form-control" required>
                                <?php 
                                $categories = isset($websiteContent['resources_categories']) ? 
                                    explode(',', $websiteContent['resources_categories']) : 
                                    ['Books', 'Videos', 'Audios', 'Blogs', 'Guides', 'Research Papers'];
                                foreach ($categories as $category): ?>
                                    <option value="<?= trim($category) ?>"><?= trim($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="resource_description" class="form-control" rows="3" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Resource File/URL</label>
                            <div class="custom-file mb-2">
                                <input type="file" name="resource_file" class="custom-file-input" id="resourceFile">
                                <label class="custom-file-label" for="resourceFile">Choose file (for uploads)...</label>
                            </div>
                            <small class="text-muted">OR</small>
                            <input type="url" name="resource_url" class="form-control mt-2" placeholder="Enter URL (for external links)">
                        </div>
                        
                        <div class="form-group">
                            <label>Thumbnail Image (optional)</label>
                            <div class="custom-file">
                                <input type="file" name="resource_thumbnail" class="custom-file-input" id="resourceThumbnail" accept="image/*">
                                <label class="custom-file-label" for="resourceThumbnail">Choose thumbnail image...</label>
                            </div>
                        </div>
                        
                        <div class="form-group form-check">
                            <input type="checkbox" name="resource_featured" class="form-check-input" id="resourceFeatured">
                            <label class="form-check-label" for="resourceFeatured">Featured Resource</label>
                        </div>
                        
                        <div class="form-group form-check">
                            <input type="checkbox" name="resource_active" class="form-check-input" id="resourceActive" checked>
                            <label class="form-check-label" for="resourceActive">Active</label>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Resource
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h4>Current Resources</h4>
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT * FROM resources ORDER BY created_at DESC");
                        $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $resources = [];
                        error_log("Database Error: " . $e->getMessage());
                    }
                    ?>
                    
                    <?php if (!empty($resources)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resources as $resource): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($resource['title']) ?></strong>
                                            <?php if ($resource['is_featured']): ?>
                                                <span class="badge badge-warning ml-2">Featured</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($resource['type']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $resource['is_active'] ? 'success' : 'secondary' ?>">
                                                <?= $resource['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="resource_action" value="toggle">
                                                <input type="hidden" name="resource_id" value="<?= $resource['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= $resource['is_active'] ? 'warning' : 'success' ?>">
                                                    <i class="fas fa-<?= $resource['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="resource_action" value="delete">
                                                <input type="hidden" name="resource_id" value="<?= $resource['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Delete this resource?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No resources added yet</p>
                    <?php endif; ?>
                </div>
            </div>
        
            <!-- Insights Management -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Farmers Insights</h3>
                </div>
                <div class="card-body">
                    <!-- Insights Intro Text -->
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="content_key" value="insights_intro">
                        <div class="form-group">
                            <label>Insights Page Introduction</label>
                            <textarea name="content_value" class="form-control" rows="3" required><?= 
                                htmlspecialchars($websiteContent['insights_intro'] ?? 'Get the latest market trends, weather updates, and expert advice to help you make informed farming decisions.') 
                            ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary mb-4">Update Introduction</button>
                    </form>

                    <!-- Expert Insights Management -->
                    <h4>Expert Insights</h4>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="insight_action" value="add">
                        
                        <div class="form-group">
                            <label>Insight Title</label>
                            <input type="text" name="insight_title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Content</label>
                            <textarea name="insight_content" class="form-control" rows="4" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Author Name</label>
                            <input type="text" name="insight_author" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Author Credentials</label>
                            <input type="text" name="insight_credentials" class="form-control" placeholder="e.g., Agricultural Expert, PhD">
                        </div>
                        
                        <div class="form-group">
                            <label>Featured Image (optional)</label>
                            <div class="custom-file">
                                <input type="file" name="insight_image" class="custom-file-input" id="insightImage" accept="image/*">
                                <label class="custom-file-label" for="insightImage">Choose image...</label>
                            </div>
                        </div>
                        
                        <div class="form-group form-check">
                            <input type="checkbox" name="insight_active" class="form-check-input" id="insightActive" checked>
                            <label class="form-check-label" for="insightActive">Active</label>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Insight
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h5>Current Insights</h5>
                    <?php if (!empty($insights)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($insights as $insight): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($insight['title']) ?></td>
                                        <td><?= htmlspecialchars($insight['author']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $insight['is_active'] ? 'success' : 'secondary' ?>">
                                                <?= $insight['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary edit-insight" data-id="<?= $insight['id'] ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="insight_action" value="toggle">
                                                <input type="hidden" name="insight_id" value="<?= $insight['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= $insight['is_active'] ? 'warning' : 'success' ?>">
                                                    <i class="fas fa-<?= $insight['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="insight_action" value="delete">
                                                <input type="hidden" name="insight_id" value="<?= $insight['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Delete this insight?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No insights added yet</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Edit Insight Modal -->
<div class="modal fade" id="editInsightModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Insight</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editInsightForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="insight_action" value="update">
                <input type="hidden" name="insight_id" id="editInsightId">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Insight Title</label>
                        <input type="text" name="insight_title" class="form-control" id="editInsightTitle" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Content</label>
                        <textarea name="insight_content" class="form-control" id="editInsightContent" rows="5" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Author Name</label>
                                <input type="text" name="insight_author" class="form-control" id="editInsightAuthor" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Author Credentials</label>
                                <input type="text" name="insight_credentials" class="form-control" id="editInsightCredentials">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Image</label>
                        <div id="editInsightImagePreview" class="mb-2"></div>
                        <div class="custom-file">
                            <input type="file" name="insight_image" class="custom-file-input" id="editInsightImage">
                            <label class="custom-file-label" for="editInsightImage">Change image...</label>
                        </div>
                    </div>
                    
                    <div class="form-group form-check">
                        <input type="checkbox" name="insight_active" class="form-check-input" id="editInsightActive">
                        <label class="form-check-label" for="editInsightActive">Active</label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Price Modal -->
<div class="modal fade" id="editPriceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Market Price</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="editPriceForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="price_action" value="update">
                <input type="hidden" name="price_id" id="editPriceId">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Commodity</label>
                        <input type="text" name="price_commodity" class="form-control" id="editPriceCommodity" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Market</label>
                                <input type="text" name="price_market" class="form-control" id="editPriceMarket" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Price (KSh)</label>
                                <input type="number" name="price_value" class="form-control" id="editPriceValue" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Unit</label>
                                <select name="price_unit" class="form-control" id="editPriceUnit" required>
                                    <option value="kg">per kg</option>
                                    <option value="liter">per liter</option>
                                    <option value="bag">per bag</option>
                                    <option value="piece">per piece</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="price_date" class="form-control" id="editPriceDate">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Source</label>
                        <input type="text" name="price_source" class="form-control" id="editPriceSource">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit News Modal -->
<div class="modal fade" id="editNewsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit News Article</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editNewsForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="news_action" value="update">
                <input type="hidden" name="news_id" id="editNewsId">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>News Title</label>
                        <input type="text" name="news_title" class="form-control" id="editNewsTitle" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Content</label>
                        <textarea name="news_content" class="form-control" id="editNewsContent" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Source</label>
                        <input type="text" name="news_source" class="form-control" id="editNewsSource">
                    </div>
                    
                    <div class="form-group">
                        <label>Current Image</label>
                        <div id="editNewsImagePreview" class="mb-2"></div>
                        <div class="custom-file">
                            <input type="file" name="news_image" class="custom-file-input" id="editNewsImage">
                            <label class="custom-file-label" for="editNewsImage">Change image...</label>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Publish Date</label>
                                <input type="date" name="news_date" class="form-control" id="editNewsDate">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group form-check">
                                <input type="checkbox" name="news_active" class="form-check-input" id="editNewsActive">
                                <label class="form-check-label" for="editNewsActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Weather Modal -->
<div class="modal fade" id="editWeatherModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Weather Data</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="editWeatherForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="weather_action" value="update">
                <input type="hidden" name="weather_id" id="editWeatherId">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Region</label>
                        <input type="text" name="weather_region" class="form-control" id="editWeatherRegion" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Forecast</label>
                        <textarea name="weather_forecast" class="form-control" id="editWeatherForecast" rows="2" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Temperature (°C)</label>
                                <input type="number" name="weather_temp" class="form-control" id="editWeatherTemp" step="0.1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Rainfall (mm)</label>
                                <input type="number" name="weather_rain" class="form-control" id="editWeatherRain" step="0.1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Humidity (%)</label>
                                <input type="number" name="weather_humidity" class="form-control" id="editWeatherHumidity" min="0" max="100" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Wind Speed (km/h)</label>
                                <input type="number" name="weather_wind" class="form-control" id="editWeatherWind" step="0.1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Wind Direction</label>
                                <select name="weather_wind_dir" class="form-control" id="editWeatherWindDir" required>
                                    <option value="N">North</option>
                                    <option value="NE">Northeast</option>
                                    <option value="E">East</option>
                                    <option value="SE">Southeast</option>
                                    <option value="S">South</option>
                                    <option value="SW">Southwest</option>
                                    <option value="W">West</option>
                                    <option value="NW">Northwest</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="weather_date" class="form-control" id="editWeatherDate">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>


            <!-- Market Prices Management -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-tags"></i> Market Prices</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="price_action" value="add">
                        
                        <div class="form-group">
                            <label>Commodity</label>
                            <input type="text" name="price_commodity" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Market</label>
                                    <input type="text" name="price_market" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Price (KSh)</label>
                                    <input type="number" name="price_value" class="form-control" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Unit</label>
                                    <select name="price_unit" class="form-control" required>
                                        <option value="kg">per kg</option>
                                        <option value="liter">per liter</option>
                                        <option value="bag">per bag</option>
                                        <option value="piece">per piece</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Date</label>
                                    <input type="date" name="price_date" class="form-control" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Source</label>
                            <input type="text" name="price_source" class="form-control" placeholder="e.g., Nairobi Commodity Exchange">
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Price
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h5>Recent Prices</h5>
                    <?php if (!empty($prices)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Commodity</th>
                                        <th>Market</th>
                                        <th>Price</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prices as $price): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($price['commodity']) ?></td>
                                        <td><?= htmlspecialchars($price['market']) ?></td>
                                        <td>KSh <?= number_format($price['price'], 2) ?>/<?= $price['unit'] ?></td>
                                        <td><?= date('M j', strtotime($price['date_recorded'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary edit-price" data-id="<?= $price['id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="price_action" value="delete">
                                                <input type="hidden" name="price_id" value="<?= $price['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Delete this price record?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No price records added yet</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Agricultural News Management -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-newspaper"></i> Agricultural News</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="news_action" value="add">
                        
                        <div class="form-group">
                            <label>News Title</label>
                            <input type="text" name="news_title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Content</label>
                            <textarea name="news_content" class="form-control" rows="4" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Source</label>
                            <input type="text" name="news_source" class="form-control" placeholder="e.g., Ministry of Agriculture">
                        </div>
                        
                        <div class="form-group">
                            <label>Featured Image (optional)</label>
                            <div class="custom-file">
                                <input type="file" name="news_image" class="custom-file-input" id="newsImage" accept="image/*">
                                <label class="custom-file-label" for="newsImage">Choose image...</label>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Publish Date</label>
                                    <input type="date" name="news_date" class="form-control" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group form-check">
                                    <input type="checkbox" name="news_active" class="form-check-input" id="newsActive" checked>
                                    <label class="form-check-label" for="newsActive">Active</label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add News
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h5>Recent News</h5>
                    <?php if (!empty($news)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Source</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($news as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['title']) ?></td>
                                        <td><?= htmlspecialchars($item['source']) ?></td>
                                        <td><?= date('M j', strtotime($item['published_date'])) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $item['is_active'] ? 'success' : 'secondary' ?>">
                                                <?= $item['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary edit-news" data-id="<?= $item['id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="news_action" value="toggle">
                                                <input type="hidden" name="news_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= $item['is_active'] ? 'warning' : 'success' ?>">
                                                    <i class="fas fa-<?= $item['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="news_action" value="delete">
                                                <input type="hidden" name="news_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Delete this news article?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No news articles added yet</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Weather Data Management -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-cloud-sun"></i> Weather Data</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="weather_action" value="add">
                        
                        <div class="form-group">
                            <label>Region</label>
                            <input type="text" name="weather_region" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Forecast</label>
                            <textarea name="weather_forecast" class="form-control" rows="2" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Temperature (°C)</label>
                                    <input type="number" name="weather_temp" class="form-control" step="0.1" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Rainfall (mm)</label>
                                    <input type="number" name="weather_rain" class="form-control" step="0.1" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Humidity (%)</label>
                                    <input type="number" name="weather_humidity" class="form-control" min="0" max="100" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Wind Speed (km/h)</label>
                                    <input type="number" name="weather_wind" class="form-control" step="0.1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Wind Direction</label>
                                    <select name="weather_wind_dir" class="form-control" required>
                                        <option value="N">North</option>
                                        <option value="NE">Northeast</option>
                                        <option value="E">East</option>
                                        <option value="SE">Southeast</option>
                                        <option value="S">South</option>
                                        <option value="SW">Southwest</option>
                                        <option value="W">West</option>
                                        <option value="NW">Northwest</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="weather_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Weather Data
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h5>Recent Weather Data</h5>
                    <?php if (!empty($weather)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Region</th>
                                        <th>Forecast</th>
                                        <th>Temp (°C)</th>
                                        <th>Rain (mm)</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weather as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['region']) ?></td>
                                        <td><?= truncateText(htmlspecialchars($record['forecast']), 30) ?></td>
                                        <td><?= $record['temperature'] ?></td>
                                        <td><?= $record['rainfall'] ?></td>
                                        <td><?= date('M j', strtotime($record['date_recorded'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary edit-weather" data-id="<?= $record['id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="weather_action" value="delete">
                                                <input type="hidden" name="weather_id" value="<?= $record['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Delete this weather record?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No weather records added yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="<?= BASE_URL ?>/assets/js/admin/home-content.js"></script>
<script>
$(document).ready(function() {
    // Edit Insight
    $('.edit-insight').click(function() {
        const insightId = $(this).data('id');
        
        $.ajax({
            url: 'get-insight.php',
            method: 'POST',
            data: { id: insightId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const insight = response.insight;
                    
                    $('#editInsightId').val(insight.id);
                    $('#editInsightTitle').val(insight.title);
                    $('#editInsightContent').val(insight.content);
                    $('#editInsightAuthor').val(insight.author);
                    $('#editInsightCredentials').val(insight.author_credentials);
                    $('#editInsightActive').prop('checked', insight.is_active == 1);
                    
                    // Set image preview
                    let imageHtml = '';
                    if (insight.image_path) {
                        imageHtml = `<img src="<?= BASE_URL ?>/uploads/insights/${insight.image_path}" 
                                     style="max-height: 100px;" class="img-thumbnail">`;
                    } else {
                        imageHtml = '<p class="text-muted">No image</p>';
                    }
                    $('#editInsightImagePreview').html(imageHtml);
                    
                    $('#editInsightModal').modal('show');
                } else {
                    alert('Error loading insight data');
                }
            },
            error: function() {
                alert('Error loading insight data');
            }
        });
    });
    
    // Edit Price
    $('.edit-price').click(function() {
        const priceId = $(this).data('id');
        
        $.ajax({
            url: 'get-price.php',
            method: 'POST',
            data: { id: priceId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const price = response.price;
                    
                    $('#editPriceId').val(price.id);
                    $('#editPriceCommodity').val(price.commodity);
                    $('#editPriceMarket').val(price.market);
                    $('#editPriceValue').val(price.price);
                    $('#editPriceUnit').val(price.unit);
                    $('#editPriceDate').val(price.date_recorded);
                    $('#editPriceSource').val(price.source);
                    
                    $('#editPriceModal').modal('show');
                } else {
                    alert('Error loading price data');
                }
            },
            error: function() {
                alert('Error loading price data');
            }
        });
    });
    
    // Edit News
    $('.edit-news').click(function() {
        const newsId = $(this).data('id');
        
        $.ajax({
            url: 'get-news.php',
            method: 'POST',
            data: { id: newsId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const news = response.news;
                    
                    $('#editNewsId').val(news.id);
                    $('#editNewsTitle').val(news.title);
                    $('#editNewsContent').val(news.content);
                    $('#editNewsSource').val(news.source);
                    $('#editNewsDate').val(news.published_date);
                    $('#editNewsActive').prop('checked', news.is_active == 1);
                    
                    // Set image preview
                    let imageHtml = '';
                    if (news.image_path) {
                        imageHtml = `<img src="<?= BASE_URL ?>/uploads/news/${news.image_path}" 
                                     style="max-height: 100px;" class="img-thumbnail">`;
                    } else {
                        imageHtml = '<p class="text-muted">No image</p>';
                    }
                    $('#editNewsImagePreview').html(imageHtml);
                    
                    $('#editNewsModal').modal('show');
                } else {
                    alert('Error loading news data');
                }
            },
            error: function() {
                alert('Error loading news data');
            }
        });
    });
    
    // Edit Weather
    $('.edit-weather').click(function() {
        const weatherId = $(this).data('id');
        
        $.ajax({
            url: 'get-weather.php',
            method: 'POST',
            data: { id: weatherId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const weather = response.weather;
                    
                    $('#editWeatherId').val(weather.id);
                    $('#editWeatherRegion').val(weather.region);
                    $('#editWeatherForecast').val(weather.forecast);
                    $('#editWeatherTemp').val(weather.temperature);
                    $('#editWeatherRain').val(weather.rainfall);
                    $('#editWeatherHumidity').val(weather.humidity);
                    $('#editWeatherWind').val(weather.wind_speed);
                    $('#editWeatherWindDir').val(weather.wind_direction);
                    $('#editWeatherDate').val(weather.date_recorded);
                    
                    $('#editWeatherModal').modal('show');
                } else {
                    alert('Error loading weather data');
                }
            },
            error: function() {
                alert('Error loading weather data');
            }
        });
    });
    
    // File input labels
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });
});
</script>

<?php include '../includes/footer.php'; ?>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/functions.php';

// Add this if you can't modify functions.php
if (!function_exists('truncateText')) {
    function truncateText($text, $length = 100, $suffix = '...') {
        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length) . $suffix;
        }
        return $text;
    }
}

// Rest of your existing code...
$page_title = "Farmers Insights";
include 'includes/header.php';

// Get market prices
try {
    $prices = $pdo->query("SELECT * FROM market_prices 
                          WHERE date_recorded >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                          ORDER BY date_recorded DESC, commodity")->fetchAll();
    
    $weather = $pdo->query("SELECT * FROM weather_data 
                           WHERE region = 'Your Default Region' 
                           ORDER BY date_recorded DESC LIMIT 1")->fetch();
    
    $news = $pdo->query("SELECT * FROM agricultural_news 
                        WHERE is_active = 1 
                        ORDER BY published_date DESC LIMIT 5")->fetchAll();
    
    $insights = $pdo->query("SELECT * FROM expert_insights 
                            WHERE is_active = 1 
                            ORDER BY published_date DESC LIMIT 3")->fetchAll();
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $prices = [];
    $weather = [];
    $news = [];
    $insights = [];
}
?>

<div class="container insights-page">
    <h1><i class="fas fa-chart-line"></i> Farmers Insights</h1>
    
    <div class="row">
        <!-- Market Prices -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-tags"></i> Market Prices</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($prices)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Commodity</th>
                                        <th>Market</th>
                                        <th>Price</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prices as $price): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($price['commodity']) ?></td>
                                        <td><?= htmlspecialchars($price['market']) ?></td>
                                        <td>KSh <?= number_format($price['price'], 2) ?></td>
                                        <td><?= date('M j', strtotime($price['date_recorded'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="market-prices.php" class="btn btn-sm btn-primary">View All Prices</a>
                    <?php else: ?>
                        <p class="text-muted">No recent price data available</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Weather -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-cloud-sun"></i> Weather Forecast</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($weather)): ?>
                        <div class="weather-widget">
                            <h4><?= htmlspecialchars($weather['region']) ?></h4>
                            <p><?= htmlspecialchars($weather['forecast']) ?></p>
                            <div class="weather-details">
                                <div>
                                    <i class="fas fa-temperature-high"></i>
                                    <span>Temp: <?= $weather['temperature'] ?? 'N/A' ?>°C</span>
                                </div>
                                <div>
                                    <i class="fas fa-tint"></i>
                                    <span>Rain: <?= $weather['rainfall'] ?? 'N/A' ?> mm</span>
                                </div>
                                <div>
                                    <i class="fas fa-wind"></i>
                                    <span>Wind: <?= $weather['wind_speed'] ?? 'N/A' ?> km/h</span>
                                </div>
                            </div>
                            <small>Updated: <?= date('M j, Y', strtotime($weather['date_recorded'])) ?></small>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No weather data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- News & Expert Insights -->
        <div class="col-md-6">
            <!-- Agricultural News -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-newspaper"></i> Agricultural News</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($news)): ?>
                        <div class="news-list">
                            <?php foreach ($news as $item): ?>
                            <div class="news-item mb-3">
                                <h5><?= htmlspecialchars($item['title']) ?></h5>
                                <p><?= truncateText(htmlspecialchars($item['content']), 150) ?></p>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">
                                        <?= !empty($item['source']) ? "Source: ".htmlspecialchars($item['source']) : '' ?>
                                    </small>
                                    <small class="text-muted">
                                        <?= date('M j, Y', strtotime($item['published_date'])) ?>
                                    </small>
                                </div>
                                <a href="news-detail.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-link">Read More</a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="agricultural-news.php" class="btn btn-sm btn-primary">View All News</a>
                    <?php else: ?>
                        <p class="text-muted">No recent news articles</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Expert Insights -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-lightbulb"></i> Expert Insights</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($insights)): ?>
                        <div class="insights-list">
                            <?php foreach ($insights as $insight): ?>
                            <div class="insight-item mb-3">
                                <h5><?= htmlspecialchars($insight['title']) ?></h5>
                                <p><?= truncateText(htmlspecialchars($insight['content']), 100) ?></p>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">
                                        By <?= htmlspecialchars($insight['author']) ?>
                                        <?= !empty($insight['author_credentials']) ? "(".htmlspecialchars($insight['author_credentials']).")" : '' ?>
                                    </small>
                                    <small class="text-muted">
                                        <?= date('M j, Y', strtotime($insight['published_date'])) ?>
                                    </small>
                                </div>
                                <a href="expert-insight.php?id=<?= $insight['id'] ?>" class="btn btn-sm btn-link">Read More</a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="expert-insights.php" class="btn btn-sm btn-primary">View All Insights</a>
                    <?php else: ?>
                        <p class="text-muted">No expert insights available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Data Sources Section -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-database"></i> Data Sources</h3>
        </div>
        <div class="card-body">
            <p>Our insights are gathered from reliable sources including:</p>
            <ul>
                <li>Ministry of Agriculture</li>
                <li>National Meteorological Services</li>
                <li>Commodity Exchange Markets</li>
                <li>Agricultural Research Institutions</li>
                <li>Verified Farmer Cooperatives</li>
            </ul>
            <p>For suggestions or corrections, please contact our support team.</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
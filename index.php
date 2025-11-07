<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';

// Get platform stats
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'approved'");
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total_views) FROM users");
$totalViews = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total_earnings) FROM users");
$totalEarnings = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - <?= SITE_TAGLINE ?></title>
    
    <meta name="description" content="Turn your video views into earnings. Create short links, share anywhere, and get paid for every view.">
    <meta name="keywords" content="video monetization, link shortener, earn money, video sharing">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dark-mode.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="fas fa-link"></i> <?= SITE_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">Pricing</a></li>
                    <li class="nav-item"><a class="nav-link" href="/user/login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link btn btn-light text-primary ms-2" href="/user/register.php">Sign Up</a></li>
                    <li class="nav-item ms-2">
                        <button class="theme-toggle" id="themeToggle">
                            <i class="fas fa-moon theme-toggle-icon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1 class="fade-in"><?= SITE_TAGLINE ?></h1>
            <p class="fade-in">Monetize your video content with our powerful link shortening platform. Share anywhere, earn everywhere.</p>
            <div class="fade-in">
                <a href="/user/register.php" class="btn btn-light btn-lg me-3">Get Started Free</a>
                <a href="#how-it-works" class="btn btn-outline-light btn-lg">Learn More</a>
            </div>
            
            <!-- Stats -->
            <div class="row mt-5">
                <div class="col-md-4">
                    <h2><?= number_format($totalUsers) ?>+</h2>
                    <p>Active Users</p>
                </div>
                <div class="col-md-4">
                    <h2><?= number_format($totalViews) ?>+</h2>
                    <p>Total Views</p>
                </div>
                <div class="col-md-4">
                    <h2>$<?= number_format($totalEarnings, 2) ?>+</h2>
                    <p>Earnings Paid</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <h2 class="text-center mb-5">Powerful Features</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="feature-box">
                        <i class="fas fa-dollar-sign"></i>
                        <h4>Earn Money</h4>
                        <p>Get paid for every unique view. Dynamic CPM rates based on traffic quality and location.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-box">
                        <i class="fas fa-chart-line"></i>
                        <h4>Detailed Analytics</h4>
                        <p>Track views by country, device, browser, and peak hours. Make data-driven decisions.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-box">
                        <i class="fas fa-link"></i>
                        <h4>Custom Links</h4>
                        <p>Create branded short links with custom aliases. Generate QR codes instantly.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-box">
                        <i class="fas fa-globe"></i>
                        <h4>Multi-Currency</h4>
                        <p>View earnings in your local currency. Support for USD, EUR, INR, and more.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-box">
                        <i class="fas fa-shield-alt"></i>
                        <h4>Fraud Protection</h4>
                        <p>Advanced bot detection and duplicate view prevention. Only genuine views count.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-box">
                        <i class="fas fa-users"></i>
                        <h4>Referral Program</h4>
                        <p>Earn commission from referred users. Passive income opportunity.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-5 bg-light" id="how-it-works">
        <div class="container">
            <h2 class="text-center mb-5">How It Works</h2>
            <div class="row">
                <div class="col-md-3 text-center mb-4">
                    <div class="display-4 text-primary mb-3">1</div>
                    <h5>Sign Up</h5>
                    <p>Create your free account and get approved by our team.</p>
                </div>
                <div class="col-md-3 text-center mb-4">
                    <div class="display-4 text-primary mb-3">2</div>
                    <h5>Shorten Links</h5>
                    <p>Convert your video URLs into monetized short links.</p>
                </div>
                <div class="col-md-3 text-center mb-4">
                    <div class="display-4 text-primary mb-3">3</div>
                    <h5>Share & Promote</h5>
                    <p>Share your links on social media, websites, or apps.</p>
                </div>
                <div class="col-md-3 text-center mb-4">
                    <div class="display-4 text-primary mb-3">4</div>
                    <h5>Earn Money</h5>
                    <p>Get paid for every valid view. Withdraw anytime.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white text-center">
        <div class="container">
            <h2 class="mb-4">Ready to Start Earning?</h2>
            <p class="lead mb-4">Join thousands of creators already monetizing their content</p>
            <a href="/user/register.php" class="btn btn-light btn-lg">Create Free Account</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><?= SITE_NAME ?></h5>
                    <p><?= SITE_TAGLINE ?></p>
                    <div class="mt-3">
                        <a href="#" class="me-3"><i class="fab fa-facebook fa-2x"></i></a>
                        <a href="#" class="me-3"><i class="fab fa-twitter fa-2x"></i></a>
                        <a href="#" class="me-3"><i class="fab fa-instagram fa-2x"></i></a>
                        <a href="#"><i class="fab fa-telegram fa-2x"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <h6>Company</h6>
                    <ul class="list-unstyled">
                        <li><a href="/about.php">About Us</a></li>
                        <li><a href="/contact.php">Contact</a></li>
                        <li><a href="/blog.php">Blog</a></li>
                    </ul>
                </div>
                <div class="col-md-2 mb-4">
                    <h6>Support</h6>
                    <ul class="list-unstyled">
                        <li><a href="/faq.php">FAQ</a></li>
                        <li><a href="/terms.php">Terms</a></li>
                        <li><a href="/privacy.php">Privacy</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h6>Newsletter</h6>
                    <p>Subscribe for updates and tips</p>
                    <form class="d-flex">
                        <input type="email" class="form-control me-2" placeholder="Your email">
                        <button class="btn btn-primary">Subscribe</button>
                    </form>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/theme-switcher.js"></script>
    <script src="/assets/js/main.js"></script>
</body>
</html>
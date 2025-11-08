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
    
    <meta name="description" content="Turn your video views into earnings. Create short links, share anywhere, and get paid for every view. Join thousands of content creators earning passive income.">
    <meta name="keywords" content="video monetization, link shortener, earn money, video sharing, passive income, content creator, CPM earnings">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dark-mode.css">
    
    <style>
        .feature-card {
            border: none;
            border-radius: 15px;
            padding: 30px;
            height: 100%;
            background: var(--light-color);
            transition: all 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(255, 59, 48, 0.2);
        }
        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        .platform-logo {
            width: 80px;
            height: 80px;
            border-radius: 15px;
            background: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2.5rem;
            transition: all 0.3s ease;
        }
        .platform-logo:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 20px rgba(255, 59, 48, 0.3);
        }
        .testimonial-card {
            background: var(--light-color);
            border-radius: 15px;
            padding: 30px;
            margin: 15px;
            position: relative;
        }
        .testimonial-quote {
            font-size: 3rem;
            color: var(--primary-color);
            opacity: 0.3;
            position: absolute;
            top: 10px;
            left: 20px;
        }
        .stat-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px 30px;
            border-radius: 15px;
            text-align: center;
            margin: 10px 0;
        }
        .stat-badge h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }
        .stat-badge p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
        .faq-item {
            background: var(--light-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .faq-item:hover {
            box-shadow: 0 5px 15px rgba(255, 59, 48, 0.2);
        }
        .pricing-badge {
            display: inline-block;
            background: var(--success-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        .trust-indicator {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 25px;
            margin: 5px;
        }
        .trust-indicator i {
            margin-right: 10px;
            color: var(--success-color);
        }
    </style>
</head>
<body class="dark-mode">
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
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="animate__animated animate__fadeInDown" style="font-size: 3.5rem; font-weight: 800; margin-bottom: 25px;">
                        <?= SITE_TAGLINE ?>
                    </h1>
                    <p class="animate__animated animate__fadeInUp" style="font-size: 1.4rem; margin-bottom: 30px;">
                        Monetize your video content with our powerful link shortening platform. 
                        Share anywhere, earn everywhere. Get paid for every unique view with industry-leading CPM rates.
                    </p>
                    
                    <!-- Trust Indicators -->
                    <div class="mb-4 animate__animated animate__fadeInUp animate__delay-1s">
                        <div class="trust-indicator">
                            <i class="fas fa-check-circle"></i>
                            <span>Instant Payouts</span>
                        </div>
                        <div class="trust-indicator">
                            <i class="fas fa-shield-alt"></i>
                            <span>100% Safe</span>
                        </div>
                        <div class="trust-indicator">
                            <i class="fas fa-globe"></i>
                            <span>Worldwide Support</span>
                        </div>
                    </div>
                    
                    <div class="animate__animated animate__fadeInUp animate__delay-1s">
                        <a href="/user/register.php" class="btn btn-light btn-lg me-3 px-4 py-3">
                            <i class="fas fa-rocket me-2"></i>Get Started Free
                        </a>
                        <a href="#how-it-works" class="btn btn-outline-light btn-lg px-4 py-3">
                            <i class="fas fa-play-circle me-2"></i>Learn More
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-5">
                    <!-- Stats Cards -->
                    <div class="row mt-5 mt-lg-0">
                        <div class="col-12 mb-3">
                            <div class="stat-badge animate__animated animate__fadeInRight">
                                <h3><?= number_format($totalUsers) ?>+</h3>
                                <p><i class="fas fa-users me-2"></i>Active Content Creators</p>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="stat-badge animate__animated animate__fadeInRight animate__delay-1s">
                                <h3><?= number_format($totalViews) ?>+</h3>
                                <p><i class="fas fa-eye me-2"></i>Total Views Processed</p>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="stat-badge animate__animated animate__fadeInRight animate__delay-2s">
                                <h3>$<?= number_format($totalEarnings, 2) ?>+</h3>
                                <p><i class="fas fa-dollar-sign me-2"></i>Earnings Paid Out</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold mb-3">Powerful Features for Content Creators</h2>
                <p class="lead text-muted">Everything you need to monetize and manage your video content effectively</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h4 class="mb-3">High CPM Rates</h4>
                        <p class="mb-3">Get paid for every unique view with industry-leading CPM rates. Dynamic pricing based on traffic quality and geographic location.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Up to $8 CPM for tier 1 countries</li>
                            <li><i class="fas fa-check text-success me-2"></i>Real-time earnings tracking</li>
                            <li><i class="fas fa-check text-success me-2"></i>Daily payment processing</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4 class="mb-3">Advanced Analytics</h4>
                        <p class="mb-3">Comprehensive analytics dashboard with detailed insights about your audience and performance metrics.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Country-wise view breakdown</li>
                            <li><i class="fas fa-check text-success me-2"></i>Device & browser statistics</li>
                            <li><i class="fas fa-check text-success me-2"></i>Peak hours heatmap</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-link"></i>
                        </div>
                        <h4 class="mb-3">Custom Branded Links</h4>
                        <p class="mb-3">Create professional short links with custom aliases and QR codes for enhanced brand recognition.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Custom URL aliases</li>
                            <li><i class="fas fa-check text-success me-2"></i>Instant QR code generation</li>
                            <li><i class="fas fa-check text-success me-2"></i>Bulk link creation</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4 class="mb-3">Fraud Protection</h4>
                        <p class="mb-3">Advanced security measures ensure only genuine views are counted and you get paid fairly.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Bot detection system</li>
                            <li><i class="fas fa-check text-success me-2"></i>Duplicate view prevention</li>
                            <li><i class="fas fa-check text-success me-2"></i>IP filtering & rate limiting</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <h4 class="mb-3">Multiple Payment Methods</h4>
                        <p class="mb-3">Withdraw your earnings through various payment gateways with low minimum payout thresholds.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>PayPal, UPI, Bank Transfer</li>
                            <li><i class="fas fa-check text-success me-2"></i>$5 minimum withdrawal</li>
                            <li><i class="fas fa-check text-success me-2"></i>24-48 hour processing</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4 class="mb-3">Mobile Optimized</h4>
                        <p class="mb-3">Fully responsive platform and video player optimized for all devices and screen sizes.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Responsive design</li>
                            <li><i class="fas fa-check text-success me-2"></i>Fast loading speed</li>
                            <li><i class="fas fa-check text-success me-2"></i>Mobile app available</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                        <h4 class="mb-3">Multi-Currency Support</h4>
                        <p class="mb-3">View your earnings in your preferred currency with real-time exchange rates.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>15+ currencies supported</li>
                            <li><i class="fas fa-check text-success me-2"></i>Auto-updated exchange rates</li>
                            <li><i class="fas fa-check text-success me-2"></i>Multi-language interface</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="mb-3">Referral Program</h4>
                        <p class="mb-3">Earn passive income by referring new users to our platform with lifetime commissions.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>10% lifetime commission</li>
                            <li><i class="fas fa-check text-success me-2"></i>No limit on referrals</li>
                            <li><i class="fas fa-check text-success me-2"></i>Dedicated referral dashboard</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h4 class="mb-3">24/7 Support</h4>
                        <p class="mb-3">Our dedicated support team is always ready to help you with any questions or issues.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Live chat support</li>
                            <li><i class="fas fa-check text-success me-2"></i>Email & ticket system</li>
                            <li><i class="fas fa-check text-success me-2"></i>Comprehensive FAQ</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-5 bg-light" id="how-it-works">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold mb-3">How It Works</h2>
                <p class="lead text-muted">Start earning in 4 simple steps</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3 text-center">
                    <div class="p-4">
                        <div class="display-3 text-primary mb-3 fw-bold" style="background: linear-gradient(135deg, #ff3b30, #ff6347); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">1</div>
                        <div class="feature-icon mx-auto mb-3">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Sign Up Free</h5>
                        <p class="text-muted">Create your free account in minutes. Quick approval process with email verification.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 text-center">
                    <div class="p-4">
                        <div class="display-3 text-primary mb-3 fw-bold" style="background: linear-gradient(135deg, #ff3b30, #ff6347); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">2</div>
                        <div class="feature-icon mx-auto mb-3">
                            <i class="fas fa-compress-alt"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Create Short Links</h5>
                        <p class="text-muted">Convert your video URLs into monetized short links with custom aliases and QR codes.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 text-center">
                    <div class="p-4">
                        <div class="display-3 text-primary mb-3 fw-bold" style="background: linear-gradient(135deg, #ff3b30, #ff6347); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">3</div>
                        <div class="feature-icon mx-auto mb-3">
                            <i class="fas fa-share-alt"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Share Everywhere</h5>
                        <p class="text-muted">Share your links on social media, websites, forums, or messaging apps. Promote your content.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 text-center">
                    <div class="p-4">
                        <div class="display-3 text-primary mb-3 fw-bold" style="background: linear-gradient(135deg, #ff3b30, #ff6347); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">4</div>
                        <div class="feature-icon mx-auto mb-3">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Get Paid</h5>
                        <p class="text-muted">Earn money for every genuine view. Withdraw your earnings anytime with low minimums.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Supported Platforms -->
    <section class="py-5" style="background: var(--bg-color);">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold mb-3">Supported Video Platforms</h2>
                <p class="lead text-muted">We support major video hosting platforms for seamless integration</p>
            </div>
            
            <div class="row g-4 justify-content-center">
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <div class="platform-logo" title="Terabox">
                        <i class="fas fa-cloud" style="color: #0089ff;"></i>
                    </div>
                    <h6>Terabox</h6>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <div class="platform-logo" title="StreamTape">
                        <i class="fas fa-video" style="color: #ff4444;"></i>
                    </div>
                    <h6>StreamTape</h6>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <div class="platform-logo" title="FileMoon">
                        <i class="fas fa-moon" style="color: #ffd700;"></i>
                    </div>
                    <h6>FileMoon</h6>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <div class="platform-logo" title="GoFile">
                        <i class="fas fa-folder" style="color: #00c851;"></i>
                    </div>
                    <h6>GoFile</h6>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <div class="platform-logo" title="StreamNet">
                        <i class="fas fa-broadcast-tower" style="color: #33b5e5;"></i>
                    </div>
                    <h6>StreamNet</h6>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <div class="platform-logo" title="Direct Videos">
                        <i class="fas fa-play-circle" style="color: #ff6347;"></i>
                    </div>
                    <h6>Direct Videos</h6>
                </div>
            </div>
            
            <div class="text-center mt-5">
                <p class="text-muted mb-3"><i class="fas fa-plus-circle me-2"></i>More platforms being added regularly</p>
                <a href="/user/register.php" class="btn btn-primary btn-lg px-5">Start Using Now</a>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold mb-3">What Our Users Say</h2>
                <p class="lead text-muted">Join thousands of satisfied content creators earning daily</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="testimonial-card">
                        <i class="fas fa-quote-left testimonial-quote"></i>
                        <div class="mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="mb-4">"Best platform for monetizing video content! I've been earning consistently for the past 6 months. The analytics are detailed and payouts are always on time."</p>
                        <div class="d-flex align-items-center">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0">Rajesh Kumar</h6>
                                <small class="text-muted">Content Creator</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="testimonial-card">
                        <i class="fas fa-quote-left testimonial-quote"></i>
                        <div class="mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="mb-4">"I love the user-friendly interface and detailed statistics. The referral program is an excellent bonus. Highly recommended for anyone looking to earn online!"</p>
                        <div class="d-flex align-items-center">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0">Sarah Johnson</h6>
                                <small class="text-muted">Digital Marketer</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="testimonial-card">
                        <i class="fas fa-quote-left testimonial-quote"></i>
                        <div class="mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="mb-4">"Excellent CPM rates and fast payouts. The support team is responsive and helpful. I've tried other platforms, but this one is definitely the best!"</p>
                        <div class="d-flex align-items-center">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0">Mohammed Ali</h6>
                                <small class="text-muted">Video Publisher</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Pricing/Rates Section -->
    <section class="py-5" style="background: var(--bg-color);" id="pricing">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold mb-3">CPM Rates by Country</h2>
                <p class="lead text-muted">Competitive rates based on geographic location</p>
                <span class="pricing-badge">Updated Daily</span>
            </div>
            
            <div class="row g-4 justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card text-center">
                        <div class="badge bg-success mb-3" style="font-size: 0.9rem;">Tier 1 Countries</div>
                        <h3 class="mb-3" style="color: var(--success-color); font-size: 2.5rem; font-weight: 700;">$6-8</h3>
                        <p class="text-muted mb-4">per 1000 views</p>
                        <ul class="list-unstyled text-start">
                            <li class="mb-2"><i class="fas fa-globe text-success me-2"></i>United States</li>
                            <li class="mb-2"><i class="fas fa-globe text-success me-2"></i>United Kingdom</li>
                            <li class="mb-2"><i class="fas fa-globe text-success me-2"></i>Canada</li>
                            <li class="mb-2"><i class="fas fa-globe text-success me-2"></i>Australia</li>
                            <li class="mb-2"><i class="fas fa-globe text-success me-2"></i>Germany</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card text-center">
                        <div class="badge bg-info mb-3" style="font-size: 0.9rem;">Tier 2 Countries</div>
                        <h3 class="mb-3" style="color: var(--info-color); font-size: 2.5rem; font-weight: 700;">$3-5</h3>
                        <p class="text-muted mb-4">per 1000 views</p>
                        <ul class="list-unstyled text-start">
                            <li class="mb-2"><i class="fas fa-globe text-info me-2"></i>India</li>
                            <li class="mb-2"><i class="fas fa-globe text-info me-2"></i>Brazil</li>
                            <li class="mb-2"><i class="fas fa-globe text-info me-2"></i>Mexico</li>
                            <li class="mb-2"><i class="fas fa-globe text-info me-2"></i>Spain</li>
                            <li class="mb-2"><i class="fas fa-globe text-info me-2"></i>Italy</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card text-center">
                        <div class="badge bg-warning mb-3" style="font-size: 0.9rem;">Tier 3 Countries</div>
                        <h3 class="mb-3" style="color: var(--warning-color); font-size: 2.5rem; font-weight: 700;">$1-2</h3>
                        <p class="text-muted mb-4">per 1000 views</p>
                        <ul class="list-unstyled text-start">
                            <li class="mb-2"><i class="fas fa-globe text-warning me-2"></i>Pakistan</li>
                            <li class="mb-2"><i class="fas fa-globe text-warning me-2"></i>Bangladesh</li>
                            <li class="mb-2"><i class="fas fa-globe text-warning me-2"></i>Indonesia</li>
                            <li class="mb-2"><i class="fas fa-globe text-warning me-2"></i>Philippines</li>
                            <li class="mb-2"><i class="fas fa-globe text-warning me-2"></i>Other countries</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5">
                <p class="text-muted mb-0"><i class="fas fa-info-circle me-2"></i>Rates updated daily based on market conditions and advertiser demand</p>
            </div>
        </div>
    </section>
    
    <!-- FAQ Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold mb-3">Frequently Asked Questions</h2>
                <p class="lead text-muted">Everything you need to know about our platform</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item mb-3" style="border: none; border-radius: 10px; overflow: hidden;">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" style="background: var(--light-color); color: var(--text-color); font-weight: 600;">
                                    <i class="fas fa-question-circle me-2 text-primary"></i>
                                    How much can I earn?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body" style="background: var(--light-color);">
                                    Your earnings depend on the number of views and the geographic location of your audience. With tier 1 countries (US, UK, Canada), you can earn $6-8 per 1000 views. Many users earn $100-500 monthly, while top publishers earn over $2000.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item mb-3" style="border: none; border-radius: 10px; overflow: hidden;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" style="background: var(--light-color); color: var(--text-color); font-weight: 600;">
                                    <i class="fas fa-question-circle me-2 text-primary"></i>
                                    What is the minimum withdrawal amount?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body" style="background: var(--light-color);">
                                    The minimum withdrawal amount is just $5. We support multiple payment methods including PayPal, UPI, bank transfer, and cryptocurrency. Withdrawals are processed within 24-48 hours.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item mb-3" style="border: none; border-radius: 10px; overflow: hidden;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" style="background: var(--light-color); color: var(--text-color); font-weight: 600;">
                                    <i class="fas fa-question-circle me-2 text-primary"></i>
                                    Which video platforms are supported?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body" style="background: var(--light-color);">
                                    We support Terabox, StreamTape, FileMoon, GoFile, StreamNet, DiskWala, VividCast, and direct video URLs. We're constantly adding support for more platforms based on user demand.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item mb-3" style="border: none; border-radius: 10px; overflow: hidden;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" style="background: var(--light-color); color: var(--text-color); font-weight: 600;">
                                    <i class="fas fa-question-circle me-2 text-primary"></i>
                                    Is there a referral program?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body" style="background: var(--light-color);">
                                    Yes! You earn 10% lifetime commission on all earnings of users you refer. There's no limit to how many people you can refer. This is a great way to build passive income.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item mb-3" style="border: none; border-radius: 10px; overflow: hidden;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5" style="background: var(--light-color); color: var(--text-color); font-weight: 600;">
                                    <i class="fas fa-question-circle me-2 text-primary"></i>
                                    How does fraud protection work?
                                </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body" style="background: var(--light-color);">
                                    Our advanced system detects and filters out bot traffic, duplicate views, and fraudulent clicks. We use IP filtering, device fingerprinting, and behavioral analysis to ensure only genuine views are counted.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item mb-3" style="border: none; border-radius: 10px; overflow: hidden;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6" style="background: var(--light-color); color: var(--text-color); font-weight: 600;">
                                    <i class="fas fa-question-circle me-2 text-primary"></i>
                                    Can I track my link performance?
                                </button>
                            </h2>
                            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body" style="background: var(--light-color);">
                                    Absolutely! We provide detailed analytics including total views, earnings, geographic distribution, device types, browsers used, and peak viewing hours. You can export reports in CSV format.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <p class="text-muted">Still have questions? <a href="/contact.php" class="text-primary fw-bold">Contact our support team</a></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white text-center">
        <div class="container">
            <h2 class="display-5 fw-bold mb-4">Ready to Start Earning?</h2>
            <p class="lead mb-4">Join thousands of content creators already monetizing their videos</p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="/user/register.php" class="btn btn-light btn-lg px-5">
                    <i class="fas fa-rocket me-2"></i>Create Free Account
                </a>
                <a href="#features" class="btn btn-outline-light btn-lg px-5">
                    <i class="fas fa-info-circle me-2"></i>Learn More
                </a>
            </div>
            <div class="mt-4">
                <small><i class="fas fa-check-circle me-2"></i>No credit card required • Free forever • Cancel anytime</small>
            </div>
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
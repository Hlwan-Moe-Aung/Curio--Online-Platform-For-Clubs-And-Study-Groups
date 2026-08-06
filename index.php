<?php
session_start();
include ('includes/db.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curio | Connect, Learn, Grow</title>    
    <link rel="stylesheet" href="assets/css/index.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/global.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>

<div class="landing-wrapper">
    <header class="main-header">
        <div class="header-container">
            <div class="brand">
                <a href="index.php" class="logo-link">
                    <div class="logo-box">
                        <img src="assets/img/logo.jpg" alt="Curio Logo" style="width: 24px;">
                    </div>  
                    <span class="site-name">Curio</span>
                </a>
            </div>

            <nav class="nav-links">
                <a href="#how-it-works">Process</a>
                <a href="#preview">Preview</a>
                <a href="#stats">Impact</a>
                <a href="public/rules.php" class="" title="View Community Rules">Rules</a>
            </nav>

            <div class="header-actions">
                <?php if(isset($_SESSION['user'])): ?>
                    <a href="views/clubs.php" class="btn-login">Enter</a>
                <?php else: ?>
                    <a href="public/login.php" class="btn-login">Login</a>
                    <a href="public/signup.php" class="btn-signup">Join Now</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1>Learn Together. <br><span class="text-gradient">Grow Together.</span></h1>
            <p>The all-in-one collaborative workspace designed specifically for student-led clubs and high-impact study groups.</p>
            <div class="hero-btns">
                <a href="public/signup.php" class="btn-primary">Get Started — It's Free</a>
                <a href="#preview" class="btn-secondary">Watch Preview</a>
            </div>
        </div>
        <div class="hero-visual">
             <div class="abstract-shape"></div>
        </div>
    </section>

    <section id="stats" class="stats">
        <div class="stat-card">
            <h2>120+</h2>
            <p>Active Clubs</p>
            <small>(Test Data)</small>
        </div>
        <div class="stat-card">
            <h2>350+</h2>
            <p>Study Groups</p>
                        <small>(Test Data)</small>

        </div>
        <div class="stat-card">
            <h2>5k+</h2>
            <p>Students</p>
                        <small>(Test Data)</small>

        </div>
    </section>

    <section id="how-it-works" class="how-it-works">
        <div class="section-title">
            <h2>Simplicity at its core</h2>
            <p>Three steps to a better academic social life.</p>
        </div>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-icon">01</div>
                <h3>Sign Up</h3>
                <p>Create your profile and select your academic interests.</p>
            </div>
            <div class="step-card">
                <div class="step-icon">02</div>
                <h3>Join or Create</h3>
                <p>Browse the directory or launch your own community in seconds.</p>
            </div>
            <div class="step-card">
                <div class="step-icon">03</div>
                <h3>Collaborate</h3>
                <p>Share notes, discuss topics, and manage events effortlessly.</p>
            </div>
        </div>
    </section>

    <section id="preview" class="preview-section">
        <div class="preview-container">
            <div class="preview-item">
                <div class="browser-mockup">
                    <div class="browser-header">
                        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
                    </div>
                    <img src="assets/img/preview-clubs.jpg" alt="Dashboard Preview" onclick="viewFullImage(this.src)">
                </div>
                <div class="preview-info">
                    <h3>Centralized Community</h3>
                    <p>Stop chasing links across different apps. See all your clubs and study groups in one unified, distraction-free glance.</p>
                </div>
            </div>

            <div class="preview-item reverse">
                <div class="browser-mockup">
                    <div class="browser-header">
                        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
                    </div>
                    <img src="assets/img/preview-discussion.jpg" alt="Club Management" onclick="viewFullImage(this.src)">
                </div>
                <div class="preview-info">
                    <h3>Rich Discussions</h3>
                    <p>Built-in forums designed for students. Share images, code snippets, and notes without the clutter of traditional social media.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="main-footer">
        <div class="footer-bottom">
            <p>© 2026 Curio Platform. Built for the future of education.</p>
        </div>
    </footer>
</div>

 <script src="assets/js/myscript.js?v=<?php echo time(); ?>"></script>

</body>
</html>
<?php
require_once '../config/database.php';
startSecureSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Alumni Relationship & Networking System" />
    <title>Privacy Policy - Alumni Relationship & Networking System</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&family=Roboto:wght@500;700&display=swap" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- AOS Animation CSS -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f5f7fa;
            color: #002147;
        }
        .hero {
            background: linear-gradient(to right, #002147, #0077c8);
            color: #fff;
            padding: 60px 20px;
            text-align: center;
            position: relative;
        }
        .hero h1 {
            font-weight: 700;
            font-size: 2.75rem;
        }
        h2.section-title {
            font-weight: 700;
            font-family: 'Roboto', sans-serif;
            font-size: 2rem;
            margin-bottom: 2rem;
        }
        .content-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background-color: #ffffff;
            padding: 24px;
            transition: all 0.3s ease;
        }
        .content-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
        }
        .footer {
            background-color: #002147;
            color: #fff;
            padding: 40px 0;
            font-size: 0.95rem;
        }
        .footer a {
            color: #aad4ff;
            text-decoration: none;
            margin: 0 10px;
            transition: color 0.3s;
        }
        .footer a:hover {
            color: #ffffff;
        }
        .social-icons img {
            margin: 0 6px;
            width: 24px;
            height: 24px;
            filter: grayscale(100%);
            transition: filter 0.3s;
        }
        .social-icons img:hover {
            filter: grayscale(0%);
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<!-- Hero Section -->
<section class="hero" data-aos="fade-up">
    <div class="container">
        <h1 class="display-4">Privacy Policy</h1>
    </div>
</section>

<!-- Privacy Section -->
<section class="py-5">
    <div class="container">
        <div class="content-card" data-aos="fade-up" data-aos-delay="100">
            <h2 class="section-title">Privacy Policy</h2>
            <p>
                We are committed to protecting your privacy. This privacy policy outlines how we collect, use, and safeguard your data.
                <br>
                Please contact us at <a href="mailto:info@abc.edu">info@abc.edu</a> for more details.
            </p>
            <ul>
                <li>Your personal information is kept confidential and only used for platform functionality.</li>
                <li>We do not share your data with third parties without your consent.</li>
                <li>We use security measures to protect your information.</li>
                <li>You can request deletion of your account and data at any time.</li>
            </ul>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({ duration: 1000, once: true });
</script>
</body>
</html>

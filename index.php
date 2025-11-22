<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Emotion Detection System</title>
    <link rel="icon" href="assets/images/logo.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Header with logo and title -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="assets/images/logo.png" alt="Logo" height="50" class="me-2">
                <span class="fs-4">Emotion Detection </span>
            </a>
        </div>
    </nav>

    <!-- Video Banner -->
<div class="video-banner position-relative">
    <video autoplay muted loop playsinline class="w-100 h-100 object-fit-cover">
        <source src="https://www.shutterstock.com/shutterstock/videos/1085676437/preview/stock-footage-futuristic-biometrical-analysis-emotions-close-up-woman-face-biometrics-research-innovate.webm" type="video/webm">
        Your browser does not support the video tag.
    </video>
    <div class="overlay position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center align-items-center text-white text-center">
       
        <h1 class="display-4 fw-bold">Welcome to Emotion Detection System</h1>
        <p class="lead">Biometrical analysis to understand emotional health</p> 
        
    </div>
</div>

    <!-- Action Buttons -->
    <div class="container text-center mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4 mb-3">
                <a href="login.php?role=patient" class="btn btn-outline-primary btn-lg w-100">Patient Login</a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="login.php?role=doctor" class="btn btn-outline-success btn-lg w-100">Doctor Login</a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="register_doctor.php" class="btn btn-outline-secondary btn-lg w-100">Register as Doctor</a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center text-muted py-4">
        &copy; <?= date('Y') ?> Emotion Detection. All Rights Reserved.
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
session_start();

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'guesthouse';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Helper functions
function getBookedRanges($conn) {
    $sql = "SELECT check_in, check_out FROM bookings WHERE booking_status = 'approved' ORDER BY check_in ASC";
    $result = $conn->query($sql);
    $ranges = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ranges[] = ['start' => $row['check_in'], 'end' => $row['check_out']];
        }
    }
    return $ranges;
}

// Calculate price based on booking type and nights
function calculatePrice($bookingType, $nights) {
    $pricing = [
        'individual' => ['1-3' => 600, '1-10' => 1500],
        'group3' => ['1-3' => 900, '1-10' => 2400],
        'group6' => ['1-3' => 1400, '1-10' => 4200],
        'honeymoon2' => ['per_night' => 250],
        'honeymoon3' => ['per_night' => 300],
        'honeymoon6' => ['per_night' => 480]
    ];
    if (strpos($bookingType, 'honeymoon') === 0) {
        return $nights * $pricing[$bookingType]['per_night'];
    } else {
        if ($nights <= 3) return $pricing[$bookingType]['1-3'];
        else return $pricing[$bookingType]['1-10'];
    }
}

// Process booking form submission (for mastercard)
$booking_message = '';
$booking_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_now'])) {
    $fullName   = trim($_POST['fullName'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $countryCode = trim($_POST['countryCode'] ?? '');
    $phoneNumber = trim($_POST['phoneNumber'] ?? '');
    $phone = $countryCode . $phoneNumber;
    $checkIn    = $_POST['checkIn'] ?? '';
    $checkOut   = $_POST['checkOut'] ?? '';
    $bookingType = $_POST['bookingType'] ?? 'individual';
    $paymentMethod = $_POST['paymentMethod'] ?? '';
    $paymentDetail = trim($_POST['paymentDetail'] ?? '');
    $totalAmount = floatval($_POST['totalAmount'] ?? 0);

    $errors = [];
    if (empty($fullName)) $errors[] = "Full name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($phoneNumber)) $errors[] = "Phone number is required.";
    if (empty($checkIn) || empty($checkOut)) $errors[] = "Check-in and check-out dates are required.";
    if (empty($paymentMethod)) $errors[] = "Payment method is required.";
    
    if (empty($errors)) {
        $from = new DateTime($checkIn);
        $to   = new DateTime($checkOut);
        $nights = $to->diff($from)->days;
        if ($from >= $to) {
            $errors[] = "Check-out must be after check-in.";
        } else {
            $stmt = $conn->prepare("SELECT id FROM bookings WHERE booking_status = 'approved' AND ((check_in <= ? AND check_out >= ?) OR (check_in BETWEEN ? AND ?) OR (check_out BETWEEN ? AND ?))");
            $stmt->bind_param("ssssss", $checkOut, $checkIn, $checkIn, $checkOut, $checkIn, $checkOut);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = "Selected dates are already booked. Please choose different dates.";
            } else {
                $insert = $conn->prepare("INSERT INTO bookings (full_name, email, phone, booking_type, check_in, check_out, nights, total_amount, payment_method, payment_detail, booking_status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid')");
                $insert->bind_param("ssssssidss", $fullName, $email, $phone, $bookingType, $checkIn, $checkOut, $nights, $totalAmount, $paymentMethod, $paymentDetail);
                if ($insert->execute()) {
                    $booking_success = true;
                    $booking_message = "✨ Thank you, $fullName! Your booking request has been received. Please wait for admin approval.";
                } else {
                    $errors[] = "Database error. Please try again later.";
                }
                $insert->close();
            }
            $stmt->close();
        }
    }
    if (!empty($errors)) {
        $booking_message = implode("<br>", $errors);
    }
}

// Process AJAX booking submission (for Zaad/Edahab)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_booking'])) {
    header('Content-Type: application/json');
    $fullName   = trim($_POST['fullName'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $countryCode = trim($_POST['countryCode'] ?? '');
    $phoneNumber = trim($_POST['phoneNumber'] ?? '');
    $phone = $countryCode . $phoneNumber;
    $checkIn    = $_POST['checkIn'] ?? '';
    $checkOut   = $_POST['checkOut'] ?? '';
    $bookingType = $_POST['bookingType'] ?? 'individual';
    $paymentMethod = $_POST['paymentMethod'] ?? '';
    $totalAmount = floatval($_POST['totalAmount'] ?? 0);
    $paymentNumber = trim($_POST['paymentNumber'] ?? '');

    $from = new DateTime($checkIn);
    $to   = new DateTime($checkOut);
    $nights = $to->diff($from)->days;
    
    $insert = $conn->prepare("INSERT INTO bookings (full_name, email, phone, booking_type, check_in, check_out, nights, total_amount, payment_method, payment_detail, booking_status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid')");
    $insert->bind_param("ssssssidss", $fullName, $email, $phone, $bookingType, $checkIn, $checkOut, $nights, $totalAmount, $paymentMethod, $paymentNumber);
    
    if ($insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Booking saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $insert->close();
    $conn->close();
    exit;
}

$bookedRanges = getBookedRanges($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sea View Batalaale | VIP Guest House</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:opsz,wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background-color: #FEFCF5; color: #2C2A29; line-height: 1.5; }
    h1, h2, h3, h4, .logo, .nav-links a, .btn, .section-tag { font-family: 'Cormorant Garamond', serif; }
    .container { max-width: 1280px; margin: 0 auto; padding: 0 32px; }
    .navbar { position: sticky; top: 0; background: rgba(255,252,245,0.96); backdrop-filter: blur(8px); z-index: 1000; padding: 16px 0; border-bottom: 1px solid #e4dcd0; }
    .nav-container { display: flex; justify-content: space-between; align-items: center; }
    .logo { font-size: 1.7rem; font-weight: 600; color: #2F5D5A; }
    .logo i { color: #C5A059; margin-right: 8px; }
    .nav-links { display: flex; gap: 2rem; list-style: none; }
    .nav-links a { text-decoration: none; font-size: 1.1rem; font-weight: 500; color: #3E3A35; transition: color 0.3s; }
    .nav-links a:hover { color: #C5A059; }
    .hamburger { display: none; background: none; border: none; font-size: 1.8rem; color: #2F5D5A; cursor: pointer; }
    .hero { min-height: 90vh; background: linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.2)), url('images/bedroom.jpg.jpeg') center/cover no-repeat; display: flex; align-items: center; text-align: center; }
    .hero-content { color: white; max-width: 800px; margin: 0 auto; }
    .hero-content h1 { font-size: 4.2rem; margin-bottom: 1rem; text-shadow: 0 2px 15px rgba(0,0,0,0.3); }
    .hero-content h1 .accent { color: #E8C67A; border-bottom: 2px solid #E8C67A; }
    .hero-content p { font-size: 1.25rem; margin-bottom: 2rem; }
    .btn { display: inline-block; padding: 14px 32px; border-radius: 40px; font-weight: 600; text-decoration: none; transition: all 0.3s ease; cursor: pointer; border: none; }
    .btn-primary { background: #C5A059; color: #2C2A29; box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
    .btn-primary:hover { background: #D9B46B; transform: translateY(-3px); }
    .btn-large { padding: 16px 40px; font-size: 1.2rem; }
    section { padding: 90px 0; }
    .section-tag { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 3px; color: #C5A059; display: inline-block; margin-bottom: 12px; }
    .section-header { text-align: center; margin-bottom: 56px; }
    .section-header h2 { font-size: 2.8rem; color: #2F5D5A; }
    .about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; }
    .about-text h2 { font-size: 2.5rem; color: #2F5D5A; }
    .accent-text { color: #C5A059; }
    .about-features { display: flex; gap: 24px; margin-top: 24px; flex-wrap: wrap; }
    .about-image img, .contact-image img { width: 100%; border-radius: 20px; box-shadow: 0 20px 35px -12px rgba(0,0,0,0.1); transition: transform 0.5s; }
    .policies-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 40px; margin-top: 20px; }
    .policy-card { background-color: transparent; perspective: 1000px; height: 320px; }
    .policy-inner { position: relative; width: 100%; height: 100%; text-align: center; transition: transform 0.6s; transform-style: preserve-3d; border-radius: 24px; box-shadow: 0 20px 30px -10px rgba(0,0,0,0.1); }
    .policy-card:hover .policy-inner { transform: rotateY(180deg); }
    .policy-front, .policy-back { position: absolute; width: 100%; height: 100%; backface-visibility: hidden; border-radius: 24px; padding: 30px 20px; display: flex; flex-direction: column; justify-content: center; align-items: center; }
    .policy-front { background: white; border: 1px solid #e4dcd0; }
    .policy-back { background: #2F5D5A; color: #F6F2EA; transform: rotateY(180deg); }
    .policy-front i, .policy-back i { font-size: 2.5rem; color: #C5A059; margin-bottom: 15px; }
    .policy-back i { color: #E8C67A; }
    .booking-section { background: #F6F2EA; }
    .booking-card { background: white; max-width: 1000px; margin: 0 auto; border-radius: 32px; padding: 48px; box-shadow: 0 20px 35px -8px rgba(0,0,0,0.08); }
    .booking-header { text-align: center; margin-bottom: 32px; }
    .form-row { display: flex; gap: 24px; margin-bottom: 20px; flex-wrap: wrap; }
    .form-group { flex: 1; min-width: 180px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
    .form-group input, .form-group select { width: 100%; padding: 12px 16px; border: 1px solid #ddd6cc; border-radius: 28px; background: #FEFCF5; }
    .phone-input-group { display: flex; gap: 10px; }
    .phone-input-group select { width: 120px; }
    .phone-input-group input { flex: 1; }
    .error-input { border-color: #c72a2a !important; background-color: #fff0f0 !important; }
    .availability-message { margin-top: 12px; padding: 12px; border-radius: 28px; text-align: center; display: none; }
    .availability-available { background: #e0f2e9; color: #1e6f3f; display: block; }
    .availability-booked { background: #ffe0e0; color: #a13e3e; display: block; }
    .payment-methods { display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap; }
    .payment-option { flex: 1; text-align: center; padding: 15px; border: 2px solid #ddd6cc; border-radius: 28px; cursor: pointer; transition: all 0.3s; }
    .payment-option.selected { border-color: #C5A059; background: #FFF8F0; }
    .payment-option i { font-size: 2rem; display: block; margin-bottom: 8px; }
    .booked-dates-list { background: #F9F6EF; border-radius: 28px; padding: 20px; margin-top: 24px; }
    .contact-wrapper { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: center; }
    .contact-details { list-style: none; margin: 28px 0; }
    .contact-details li { margin-bottom: 20px; display: flex; align-items: center; gap: 14px; }
    .contact-details i { width: 40px; height: 40px; background: #F0EBE2; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #C5A059; }
    .footer { background: #2F5D5A; color: #F6F2EA; padding: 48px 0 32px; margin-top: 30px; text-align: center; }
    .footer-social a { color: #F6F2EA; font-size: 1.5rem; margin: 0 16px; display: inline-block; transition: 0.2s; }
    .footer-social a:hover { color: #E8C67A; transform: translateY(-3px); }
    .admin-link { background: #2F5D5A; color: white; padding: 8px 20px; border-radius: 40px; text-decoration: none; margin-left: 20px; }
    .admin-link:hover { background: #C5A059; color: #2C2A29; }
    
    /* Payment Modal Styles - Compact with Scroll */
    .payment-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        z-index: 2000;
        justify-content: center;
        align-items: center;
    }
    .payment-modal-content {
        background: white;
        border-radius: 28px;
        max-width: 480px;
        width: 90%;
        padding: 25px;
        text-align: center;
        animation: modalFadeIn 0.3s ease;
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        max-height: 85vh;
        overflow-y: auto;
    }
    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    .payment-modal-content h3 {
        color: #2F5D5A;
        font-size: 1.5rem;
        margin-bottom: 12px;
        font-weight: 700;
    }
    .payment-number {
        background: linear-gradient(135deg, #2F5D5A 0%, #1a3a38 100%);
        color: white;
        font-size: 2rem;
        font-weight: bold;
        padding: 12px;
        border-radius: 18px;
        margin: 12px 0;
        letter-spacing: 4px;
        text-align: center;
    }
    .payment-amount {
        font-size: 1.3rem;
        color: #C5A059;
        font-weight: bold;
        margin: 10px 0;
        background: #FFF8F0;
        padding: 10px;
        border-radius: 14px;
    }
    .payment-message {
        color: #444;
        font-size: 0.85rem;
        margin-top: 10px;
        line-height: 1.5;
        text-align: center;
        background: #f9f6ef;
        padding: 12px;
        border-radius: 14px;
    }
    .payment-message strong {
        color: #2F5D5A;
    }
    .whatsapp-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: #25D366;
        color: white;
        padding: 12px 20px;
        border-radius: 50px;
        text-decoration: none;
        margin-top: 15px;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s;
        width: 100%;
    }
    .whatsapp-btn:hover {
        background: #128C7E;
        transform: scale(1.02);
    }
    .close-modal-btn {
        background: #a13e3e;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 50px;
        margin-top: 12px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 600;
        width: 100%;
        transition: all 0.3s;
    }
    .close-modal-btn:hover {
        background: #c72a2a;
        transform: scale(1.02);
    }
    hr {
        margin: 10px 0;
        border: none;
        height: 1px;
        background: #e4dcd0;
    }
    .send-number {
        font-size: 1.1rem;
        font-weight: bold;
        color: #C5A059;
        margin: 5px 0;
    }
    @media (max-width: 600px) {
        .payment-modal-content {
            padding: 20px;
        }
        .payment-number {
            font-size: 1.5rem;
            letter-spacing: 3px;
            padding: 10px;
        }
        .payment-amount {
            font-size: 1.1rem;
        }
        .payment-modal-content h3 {
            font-size: 1.3rem;
        }
    }
    @media (max-width: 900px) {
      .about-grid, .contact-wrapper { grid-template-columns: 1fr; }
      .hero-content h1 { font-size: 2.8rem; }
      .nav-links { position: fixed; top: 75px; left: -100%; width: 80%; background: #FFFCF5; flex-direction: column; align-items: center; padding: 40px 0; transition: left 0.3s; gap: 2rem; }
      .nav-links.active { left: 0; }
      .hamburger { display: block; }
      .payment-option { padding: 10px; font-size: 12px; }
      .phone-input-group { flex-direction: column; }
      .phone-input-group select { width: 100%; }
    }
    .fade-up { opacity: 0; transform: translateY(30px); transition: opacity 0.7s, transform 0.7s; }
    .fade-up.visible { opacity: 1; transform: translateY(0); }
    .message-area { margin-bottom: 20px; padding: 12px; border-radius: 28px; text-align: center; background: #e0f2e9; color: #1e6f3f; }
    .error-area { background: #ffe0e0; color: #a13e3e; }
  </style>
</head>
<body>
  <header class="navbar" id="navbar">
    <div class="container nav-container">
      <div class="logo"><i class="fas fa-feather-alt"></i> Sea View Batalaale</div>
      <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
      <ul class="nav-links" id="navLinks">
        <li><a href="#home">Home</a></li>
        <li><a href="#about">The Estate</a></li>
        <li><a href="#policies">Policies</a></li>
        <li><a href="#booking">Reserve</a></li>
        <li><a href="#contact">Visit</a></li>
      </ul>
    </div>
  </header>

  <main>
    <section id="home" class="hero fade-up">
      <div class="hero-content container">
        <h1>A legacy of <span class="accent">quiet luxury</span></h1>
        <a href="#booking" class="btn btn-primary btn-large">Book Now <i class="fas fa-chevron-right"></i></a>
      </div>
    </section>

    <section id="about" class="about-section fade-up">
      <div class="container">
        <div class="about-grid">
          <div class="about-text">
            <span class="section-tag">Berbera beach</span>
            <h2>Where <span class="accent-text">timeless comfort</span> meets heritage</h2>
            <p>Originally a private manor, Sea View Batalaale has been welcoming discerning travelers. Inside you'll find original hardwood floors, antique fireplaces, and curated artwork.</p>
            <p>Wake up to freshly baked scones, enjoy afternoon tea in the conservatory.</p>
            <div class="about-features">
              <span><i class="fas fa-champagne-glasses"></i> Welcome sherry</span>
              <span><i class="fas fa-fire"></i> Fireplaces</span>
              <span><i class="fas fa-soap"></i> Asprey amenities</span>
            </div>
          </div>
          <div class="about-image">
            <video width="100%" height="auto" autoplay loop muted playsinline style="border-radius: 20px; box-shadow: 0 20px 35px -12px rgba(0,0,0,0.1);">
              <source src="images/guesthouse-video.mp4" type="video/mp4">
              Your browser does not support the video tag.
            </video>
          </div>
        </div>
      </div>
    </section>

    <section id="policies" class="fade-up">
      <div class="container">
        <div class="section-header">
          <span class="section-tag">Xeerarka / Policies</span>
          <h2>Sea View Batalaale</h2>
          <p>Please read the cards below • Fadlan akhri</p>
        </div>
        <div class="policies-grid">
          <div class="policy-card"><div class="policy-inner"><div class="policy-front"><i class="fas fa-home"></i><h3>House Overview</h3><p>✔ VIP Guest House<br>✔ 6 bedrooms<br>✔ 2 kitchens, 5 toilets<br>✔ 2 balconies<br>✔ Shaded parking<br>✔ 2 large courtyards facing the sea</p></div><div class="policy-back"><i class="fas fa-home"></i><h3>Xogta Guud</h3><p>✔ 6 Qol oo hurdo ah<br>✔ 2 Kijo<br>✔ 5 Suuli<br>✔ 2 Balakoon<br>✔ Baarkin<br>✔ 2 barxadood oo waaweyn badana u jeeda</p></div></div></div>
          <div class="policy-card"><div class="policy-inner"><div class="policy-front"><i class="fas fa-water"></i><h3>Location</h3><p>🌊 Less than 30m from the sea<br>🍃 Cool breeze & ocean view</p></div><div class="policy-back"><i class="fas fa-water"></i><h3>Goobta</h3><p>🌊 Badda &lt; 30 Mitir<br>🍃 Hawo qabow</p></div></div></div>
          <div class="policy-card"><div class="policy-inner"><div class="policy-front"><i class="fas fa-users"></i><h3>Who Can Rent?</h3><p>✅ One person (entire house)<br>✅ Groups (family, friends, government, Organization)<br>✅ Honeymoon</p></div><div class="policy-back"><i class="fas fa-users"></i><h3>Yaa Kirayn Kara?</h3><p>✅ Hal shaqsi<br>✅ Group (family, Asxaab, dowlad, hay'ado)<br>✅ Reer aroos</p></div></div></div>
          <div class="policy-card"><div class="policy-inner"><div class="policy-front"><i class="fas fa-calendar-week"></i><h3>Rental Periods</h3><p>📅 1–3 days<br>📅 1–10 days<br>📅 1 day – 1 month</p></div><div class="policy-back"><i class="fas fa-calendar-week"></i><h3>Muddada</h3><p>📅 1-3 maalmood<br>📅 1-10 maalmood<br>📅 1 – 1 Bil</p></div></div></div>
          <div class="policy-card"><div class="policy-inner"><div class="policy-front"><i class="fas fa-phone-alt"></i><h3>Contact</h3><p>📞 +252 63-7980008<br>📞 +252 63-8858885</p></div><div class="policy-back"><i class="fas fa-phone-alt"></i><h3>Wixii Faahfaahin</h3><p>📞 +252 63-7980008<br>📞 +252 63-7980008</p></div></div></div>
        </div>
      </div>
    </section>

    <section id="booking" class="booking-section fade-up">
      <div class="container">
        <div class="booking-card">
          <div class="booking-header"><i class="fas fa-scroll"></i><h2>Reserve a classic experience</h2></div>
          <?php if (!empty($booking_message)): ?>
            <div class="message-area <?php if (!$booking_success) echo 'error-area'; ?>"><?php echo $booking_message; ?></div>
          <?php endif; ?>
          <form id="bookingForm" method="POST" action="#booking">
            <div class="form-row">
              <div class="form-group"><label>Full name *</label><input type="text" id="fullName" name="fullName" required></div>
              <div class="form-group"><label>Email *</label><input type="email" id="email" name="email" required></div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Phone Number *</label>
                <div class="phone-input-group">
                  <select id="countryCode" name="countryCode" required>
                    <option value="+252">🇸🇴 Somalia (+252)</option>
                    <option value="+254">🇰🇪 Kenya (+254)</option>
                    <option value="+251">🇪🇹 Ethiopia (+251)</option>
                    <option value="+971">🇦🇪 UAE (+971)</option>
                    <option value="+44">🇬🇧 UK (+44)</option>
                    <option value="+1">🇺🇸 US (+1)</option>
                    <option value="+49">🇩🇪 Germany (+49)</option>
                    <option value="+33">🇫🇷 France (+33)</option>
                    <option value="+61">🇦🇺 Australia (+61)</option>
                    <option value="+966">🇸🇦 Saudi Arabia (+966)</option>
                    <option value="+20">🇪🇬 Egypt (+20)</option>
                    <option value="+90">🇹🇷 Turkey (+90)</option>
                    <option value="+92">🇵🇰 Pakistan (+92)</option>
                    <option value="+91">🇮🇳 India (+91)</option>
                    <option value="+86">🇨🇳 China (+86)</option>
                    <option value="+81">🇯🇵 Japan (+81)</option>
                    <option value="+82">🇰🇷 South Korea (+82)</option>
                    <option value="+55">🇧🇷 Brazil (+55)</option>
                    <option value="+27">🇿🇦 South Africa (+27)</option>
                    <option value="+234">🇳🇬 Nigeria (+234)</option>
                  </select>
                  <input type="tel" id="phoneNumber" name="phoneNumber" placeholder="e.g., 61234567" required>
                </div>
              </div>
              <div class="form-group"><label>Booking Type *</label><select id="bookingType" name="bookingType">
                <option value="individual">💰 Individual (1 person)</option>
                <option value="group3">👥 Group (3 persons)</option>
                <option value="group6">👥 Group (6 persons)</option>
                <option value="honeymoon2">💍 Honeymoon (2 persons) - $250/night</option>
                <option value="honeymoon3">💍 Honeymoon (3 persons) - $300/night</option>
                <option value="honeymoon6">💍 Honeymoon (6 persons) - $480/night</option>
              </select></div>
            </div>
            <div class="form-row">
              <div class="form-group"><label>Check-in *</label><input type="date" id="checkIn" name="checkIn" required></div>
              <div class="form-group"><label>Check-out *</label><input type="date" id="checkOut" name="checkOut" required></div>
            </div>
            <div id="availabilityMsg" class="availability-message"></div>
            
            <div class="payment-methods">
              <div class="payment-option" data-method="zaad"><i class="fas fa-mobile-alt"></i><strong>Zaad (Telesom)</strong><small>444444</small></div>
              <div class="payment-option" data-method="edahab"><i class="fas fa-money-bill-wave"></i><strong>Edahab (Somtel)</strong><small>759666</small></div>
              <div class="payment-option" data-method="mastercard"><i class="fab fa-cc-mastercard"></i><strong>Mastercard / Visa</strong><small>16 digits</small></div>
            </div>
            <div id="mastercardField" style="display:none;">
              <div class="form-group"><label>Card Number</label><input type="text" id="cardNumber" placeholder="4242 4242 4242 4242"></div>
              <div class="form-row">
                <div class="form-group"><label>Expiry (MM/YY)</label><input type="text" id="cardExpiry" placeholder="12/28"></div>
                <div class="form-group"><label>CVC</label><input type="text" id="cardCvc" placeholder="123"></div>
              </div>
            </div>
            <input type="hidden" id="paymentMethodInput" name="paymentMethod">
            <input type="hidden" id="paymentDetail" name="paymentDetail">
            <input type="hidden" id="totalAmountInput" name="totalAmount">
            <button type="button" id="paymentBtn" class="btn btn-primary btn-block">Proceed to Payment</button>
          </form>
          
          
        </div>
      </div>
    </section>

    <section id="contact" class="contact-section fade-up">
      <div class="container">
        <div class="contact-wrapper">
          <div class="contact-info">
            <span class="section-tag">Visit us</span>
            <h2>In the heart of the country Berbera</h2>
            <ul class="contact-details">
              <li><i class="fas fa-phone-alt"></i><a href="tel:+252637980008">+252 63-7980008 / +252 63-8858885</a></li>
              <li><i class="fas fa-envelope"></i><a href="mailto:seaviewbatalaale@gmail.com">seaviewbatalaale@gmail.com</a></li>
              <li><i class="fas fa-map-marker-alt"></i>Berbera Beach, Somaliland</li>
            </ul>
          </div>
          <div class="contact-image"><img src="images/room.jpg.jpeg" alt="Bedroom" onerror="this.src='https://picsum.photos/600/400'"></div>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="footer-brand">Sea View Batalaale</div>
    <div class="footer-social">
      <a href="https://www.tiktok.com/@seaviewbatalaale?_r=1&_t=ZS-95qBhrROJ52"><i class="fab fa-tiktok"></i></a>
      <a href="https://www.instagram.com/seaviewguest.house?igsh=aW9haGVod25wZmQz"><i class="fab fa-instagram"></i></a>
      <a href="https://wa.me/message/XP7TM2XKQSY7P1"><i class="fab fa-whatsapp"></i></a>
      <a href="https://www.facebook.com/share/1ESYRGNJKk/?mibextid=wwXIfr"><i class="fab fa-facebook"></i></a>
    </div>
  </footer>

  <!-- Payment Modal -->
  <div id="paymentModal" class="payment-modal">
    <div class="payment-modal-content">
      <h3 id="modalTitle">💰 Payment</h3>
      <div id="modalNumber" class="payment-number"></div>
      <div id="modalAmount" class="payment-amount"></div>
      <div id="modalMessage" class="payment-message"></div>
      <a href="#" target="_blank" class="whatsapp-btn" id="whatsappLink">
        <i class="fab fa-whatsapp"></i> Contact Admin
      </a>
      <button class="close-modal-btn" onclick="closePaymentModal()">Close</button>
    </div>
  </div>

  <script>
    // Global variables
    let selectedMethod = null;
    let currentTotalPrice = 0;
    let currentBookingData = {};

    // Calculate total price function
    function calculateTotalPrice() {
        const bookingType = document.getElementById('bookingType').value;
        const checkIn = document.getElementById('checkIn').value;
        const checkOut = document.getElementById('checkOut').value;
        
        if (checkIn && checkOut) {
            const from = new Date(checkIn), to = new Date(checkOut);
            const nights = (to - from) / (1000 * 60 * 60 * 24);
            
            const pricing = {
                individual: {'1-3': 600, '1-10': 1500},
                group3: {'1-3': 900, '1-10': 2400},
                group6: {'1-3': 1400, '1-10': 4200},
                honeymoon2: {per_night: 250},
                honeymoon3: {per_night: 300},
                honeymoon6: {per_night: 480}
            };
            
            if (nights > 0) {
                if (bookingType.includes('honeymoon')) {
                    const perNight = pricing[bookingType].per_night;
                    currentTotalPrice = nights * perNight;
                } else {
                    if (nights <= 3) currentTotalPrice = pricing[bookingType]['1-3'];
                    else currentTotalPrice = pricing[bookingType]['1-10'];
                }
                document.getElementById('totalAmountInput').value = currentTotalPrice;
            }
        }
        return currentTotalPrice;
    }

    // Collect booking data
    function collectBookingData() {
        currentBookingData = {
            fullName: document.getElementById('fullName').value.trim(),
            email: document.getElementById('email').value.trim(),
            countryCode: document.getElementById('countryCode').value,
            phoneNumber: document.getElementById('phoneNumber').value.trim(),
            checkIn: document.getElementById('checkIn').value,
            checkOut: document.getElementById('checkOut').value,
            bookingType: document.getElementById('bookingType').value,
            paymentMethod: selectedMethod,
            totalAmount: currentTotalPrice
        };
        return currentBookingData;
    }

    // Save booking to database via AJAX
    async function saveBookingToDatabase(paymentNumber) {
        const data = {
            ajax_booking: true,
            fullName: currentBookingData.fullName,
            email: currentBookingData.email,
            countryCode: currentBookingData.countryCode,
            phoneNumber: currentBookingData.phoneNumber,
            checkIn: currentBookingData.checkIn,
            checkOut: currentBookingData.checkOut,
            bookingType: currentBookingData.bookingType,
            paymentMethod: currentBookingData.paymentMethod,
            totalAmount: currentBookingData.totalAmount,
            paymentNumber: paymentNumber
        };
        
        return fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data)
        }).then(response => response.json());
    }

    // Update price on booking type or date change
    document.getElementById('bookingType')?.addEventListener('change', calculateTotalPrice);
    document.getElementById('checkIn')?.addEventListener('change', calculateTotalPrice);
    document.getElementById('checkOut')?.addEventListener('change', calculateTotalPrice);

    // Payment method selection
    document.querySelectorAll('.payment-option').forEach(opt => {
      opt.addEventListener('click', () => {
        document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        selectedMethod = opt.getAttribute('data-method');
        document.getElementById('paymentMethodInput').value = selectedMethod;
        
        const mastercardField = document.getElementById('mastercardField');
        if (selectedMethod === 'mastercard') {
            mastercardField.style.display = 'block';
        } else {
            mastercardField.style.display = 'none';
        }
      });
    });

    // Payment button click handler
    document.getElementById('paymentBtn').addEventListener('click', async function() {
        // Collect and validate form data
        const fullName = document.getElementById('fullName').value.trim();
        const email = document.getElementById('email').value.trim();
        const phoneNumber = document.getElementById('phoneNumber').value.trim();
        const checkIn = document.getElementById('checkIn').value;
        const checkOut = document.getElementById('checkOut').value;
        
        if (!fullName) { alert("Please enter your full name."); return; }
        if (!email || !email.includes('@')) { alert("Please enter a valid email address."); return; }
        if (!phoneNumber) { alert("Please enter your phone number."); return; }
        if (!checkIn || !checkOut) { alert("Please select check-in and check-out dates."); return; }
        
        const fromDate = new Date(checkIn), toDate = new Date(checkOut);
        if (fromDate >= toDate) { alert("Check-out must be after check-in."); return; }
        
        // Check date overlap with booked dates from PHP
        const bookedRangesFromDB = <?php echo json_encode($bookedRanges); ?>;
        function rangesOverlap(s1,e1,s2,e2) { return (s1 <= e2 && e1 >= s2); }
        let isBooked = false;
        for (let range of bookedRangesFromDB) {
            let start = new Date(range.start), end = new Date(range.end);
            if (rangesOverlap(fromDate, toDate, start, end)) { isBooked = true; break; }
        }
        if (isBooked) { alert("These dates are already booked. Please choose different dates."); return; }
        
        if (!selectedMethod) { alert("Please select a payment method."); return; }
        
        calculateTotalPrice();
        collectBookingData();
        
        if (selectedMethod === 'mastercard') {
            const cardNum = document.getElementById('cardNumber').value.trim();
            const expiry = document.getElementById('cardExpiry').value.trim();
            const cvc = document.getElementById('cardCvc').value.trim();
            if (!cardNum) { alert("Please enter your card number."); return; }
            if (!expiry) { alert("Please enter expiry date."); return; }
            if (!cvc) { alert("Please enter CVC code."); return; }
            
            document.getElementById('paymentDetail').value = 'Card: ****' + cardNum.slice(-4);
            document.getElementById('bookingForm').submit();
        } 
        else if (selectedMethod === 'zaad') {
            showPaymentModal('Zaad', '444444', currentTotalPrice);
        } 
        else if (selectedMethod === 'edahab') {
            showPaymentModal('Edahab', '759666', currentTotalPrice);
        }
    });
    
    async function showPaymentModal(method, number, amount) {
        const modal = document.getElementById('paymentModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalNumber = document.getElementById('modalNumber');
        const modalAmount = document.getElementById('modalAmount');
        const modalMessage = document.getElementById('modalMessage');
        const whatsappLink = document.getElementById('whatsappLink');
        
        modalTitle.innerHTML = `💰 ${method}`;
        modalNumber.innerHTML = number;
        modalAmount.innerHTML = `$${amount} USD`;
        
        // Prepare WhatsApp message with booking details
        const bookingData = collectBookingData();
        const whatsappMessage = `🏨 *NEW BOOKING*%0A👤 ${bookingData.fullName}%0A📞 ${bookingData.countryCode}${bookingData.phoneNumber}%0A📅 ${bookingData.checkIn} to ${bookingData.checkOut}%0A💰 $${bookingData.totalAmount}%0A💳 ${method}: ${number}`;
        
        whatsappLink.href = `https://wa.me/252637980008?text=${whatsappMessage}`;
        
        modalMessage.innerHTML = `
            <strong>Send $${amount} to: ${number}</strong><br>
            <hr>
            📞 Contact us on WhatsApp after payment<br>
            ✉️ Send Transaction ID for confirmation
        `;
        
        modal.style.display = 'flex';
        
        // Save booking to database automatically when modal opens
        const result = await saveBookingToDatabase(number);
        if (result.success) {
            console.log('✅ Booking saved');
        }
    }
    
    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
        alert("✅ Booking request sent. Please complete payment and send transaction ID via WhatsApp.");
        document.getElementById('bookingForm').reset();
        document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
        selectedMethod = null;
        document.getElementById('mastercardField').style.display = 'none';
        document.getElementById('availabilityMsg').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('paymentModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }

    // Existing functions
    function isDateRangeBooked(checkInDate, checkOutDate) {
      for (let range of <?php echo json_encode($bookedRanges); ?>) {
        let start = new Date(range.start), end = new Date(range.end);
        if ((checkInDate <= end && checkOutDate >= start)) return true;
      }
      return false;
    }
    
    function validateAvailability() {
      const checkIn = document.getElementById('checkIn'), checkOut = document.getElementById('checkOut');
      const msgDiv = document.getElementById('availabilityMsg');
      const inVal = checkIn.value, outVal = checkOut.value;
      checkIn.classList.remove('error-input'); checkOut.classList.remove('error-input');
      msgDiv.style.display = 'none';
      if (!inVal || !outVal) return;
      const from = new Date(inVal), to = new Date(outVal);
      if (from >= to) {
        msgDiv.style.display = 'block'; msgDiv.className = 'availability-message availability-booked';
        msgDiv.innerHTML = '❌ Check-out must be after check-in.';
        checkIn.classList.add('error-input'); checkOut.classList.add('error-input');
        return;
      }
      if (isDateRangeBooked(from, to)) {
        checkIn.classList.add('error-input'); checkOut.classList.add('error-input');
        msgDiv.style.display = 'block'; msgDiv.className = 'availability-message availability-booked';
        msgDiv.innerHTML = '❌ These dates are already booked.';
      } else {
        msgDiv.style.display = 'block'; msgDiv.className = 'availability-message availability-available';
        msgDiv.innerHTML = '✅ These dates are available!';
      }
    }
    
    (function() {
      const hamburger = document.getElementById('hamburgerBtn'), navLinks = document.getElementById('navLinks');
      if (hamburger) hamburger.addEventListener('click', () => navLinks.classList.toggle('active'));
      document.querySelectorAll('.nav-link').forEach(link => link.addEventListener('click', () => navLinks.classList.remove('active')));
      const fadeElements = document.querySelectorAll('.fade-up');
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); } });
      }, { threshold: 0.15 });
      fadeElements.forEach(el => observer.observe(el));
      const todayStr = new Date().toISOString().split('T')[0];
      const checkInInput = document.getElementById('checkIn'), checkOutInput = document.getElementById('checkOut');
      if (checkInInput) checkInInput.min = todayStr;
      if (checkOutInput) checkOutInput.min = todayStr;
      checkInInput?.addEventListener('change', () => { checkOutInput.min = checkInInput.value; validateAvailability(); calculateTotalPrice(); });
      checkOutInput?.addEventListener('change', () => { validateAvailability(); calculateTotalPrice(); });
    })();
  </script>
</body>
</html>

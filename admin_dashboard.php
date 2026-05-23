<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'guesthouse';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ========== CRUD OPERATIONS ==========

// ADD NEW BOOKING (CREATE)
if (isset($_POST['add_booking'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $booking_type = $conn->real_escape_string($_POST['booking_type']);
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $nights = (strtotime($check_out) - strtotime($check_in)) / (60 * 60 * 24);
    $total_amount = $_POST['total_amount'];
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    $payment_detail = $conn->real_escape_string($_POST['payment_detail']);
    
    $insert = $conn->prepare("INSERT INTO bookings (full_name, email, phone, booking_type, check_in, check_out, nights, total_amount, payment_method, payment_detail, booking_status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid')");
    $insert->bind_param("ssssssidss", $full_name, $email, $phone, $booking_type, $check_in, $check_out, $nights, $total_amount, $payment_method, $payment_detail);
    $insert->execute();
    $insert->close();
    header("Location: admin_dashboard.php?msg=added");
    exit;
}

// EDIT BOOKING (UPDATE)
if (isset($_POST['edit_booking'])) {
    $id = intval($_POST['booking_id']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $booking_type = $conn->real_escape_string($_POST['booking_type']);
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $nights = (strtotime($check_out) - strtotime($check_in)) / (60 * 60 * 24);
    $total_amount = $_POST['total_amount'];
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    $payment_detail = $conn->real_escape_string($_POST['payment_detail']);
    
    $update = $conn->prepare("UPDATE bookings SET full_name=?, email=?, phone=?, booking_type=?, check_in=?, check_out=?, nights=?, total_amount=?, payment_method=?, payment_detail=? WHERE id=?");
    $update->bind_param("ssssssidssi", $full_name, $email, $phone, $booking_type, $check_in, $check_out, $nights, $total_amount, $payment_method, $payment_detail, $id);
    $update->execute();
    $update->close();
    header("Location: admin_dashboard.php?msg=updated");
    exit;
}

// DELETE BOOKING
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM bookings WHERE id = $id");
    header("Location: admin_dashboard.php?msg=deleted");
    exit;
}

// Admin actions (Approve, Cancel, Mark Paid)
if (isset($_POST['admin_action'])) {
    $booking_id = intval($_POST['booking_id']);
    $action = $_POST['admin_action'];
    if ($action === 'approve') {
        $conn->query("UPDATE bookings SET booking_status = 'approved', payment_status = 'paid' WHERE id = $booking_id");
    } elseif ($action === 'cancel') {
        $conn->query("UPDATE bookings SET booking_status = 'cancelled' WHERE id = $booking_id");
    } elseif ($action === 'mark_paid') {
        $conn->query("UPDATE bookings SET payment_status = 'paid' WHERE id = $booking_id");
    }
    header("Location: admin_dashboard.php?msg=updated");
    exit;
}

// Get statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];
$stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'pending'")->fetch_assoc()['count'];
$stats['approved'] = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'approved'")->fetch_assoc()['count'];
$stats['cancelled'] = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'cancelled'")->fetch_assoc()['count'];
$stats['total_revenue'] = $conn->query("SELECT SUM(total_amount) as total FROM bookings WHERE payment_status = 'paid'")->fetch_assoc()['total'] ?? 0;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$where = "";
if (!empty($search)) {
    $where = "WHERE full_name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%'";
}

$total_result = $conn->query("SELECT COUNT(*) as total FROM bookings $where");
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$bookings = $conn->query("SELECT * FROM bookings $where ORDER BY created_at DESC LIMIT $offset, $limit");

// Get single booking for edit modal
$edit_booking = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_result = $conn->query("SELECT * FROM bookings WHERE id = $edit_id");
    $edit_booking = $edit_result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Sea View Batalaale</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #f5f2ed 0%, #ede8e0 100%);
      color: #2C2A29;
    }
    .container {
      max-width: 1600px;
      margin: 0 auto;
      padding: 20px;
    }
    
    /* Admin Header */
    .admin-header {
      background: linear-gradient(135deg, #2F5D5A 0%, #1a3a38 100%);
      color: white;
      padding: 30px 35px;
      border-radius: 28px;
      margin-bottom: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .admin-header h1 {
      font-size: 1.8rem;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .header-buttons {
      display: flex;
      gap: 12px;
    }
    .btn {
      padding: 10px 22px;
      border-radius: 40px;
      text-decoration: none;
      font-weight: 500;
      cursor: pointer;
      border: none;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
    }
    .btn-danger {
      background: #a13e3e;
      color: white;
    }
    .btn-danger:hover {
      background: #c72a2a;
      transform: translateY(-2px);
    }
    .btn-primary {
      background: #C5A059;
      color: #2C2A29;
    }
    .btn-primary:hover {
      background: #D9B46B;
      transform: translateY(-2px);
    }
    .btn-success {
      background: #2F5D5A;
      color: white;
    }
    .btn-success:hover {
      background: #1e6f3f;
      transform: translateY(-2px);
    }
    .btn-outline {
      background: transparent;
      border: 1px solid #C5A059;
      color: #C5A059;
    }
    .btn-outline:hover {
      background: #C5A059;
      color: #2C2A29;
    }
    
    /* Stats Cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
      margin-bottom: 35px;
    }
    .stat-card {
      background: white;
      padding: 25px;
      border-radius: 24px;
      text-align: center;
      box-shadow: 0 5px 20px rgba(0,0,0,0.05);
      transition: all 0.3s;
      position: relative;
      overflow: hidden;
    }
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: #C5A059;
    }
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    }
    .stat-card h3 {
      font-size: 2.2rem;
      font-weight: 700;
      color: #C5A059;
    }
    .stat-card p {
      color: #666;
      margin-top: 8px;
      font-weight: 500;
    }
    .stat-card .stat-icon {
      font-size: 2rem;
      margin-bottom: 10px;
      color: #2F5D5A;
    }
    
    /* Search and Add Bar */
    .action-bar {
      background: white;
      border-radius: 28px;
      padding: 20px 25px;
      margin-bottom: 25px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    }
    .search-box {
      display: flex;
      gap: 10px;
      align-items: center;
    }
    .search-box input {
      padding: 12px 18px;
      border: 1px solid #e0d6c8;
      border-radius: 40px;
      width: 250px;
      font-size: 14px;
    }
    .search-box input:focus {
      outline: none;
      border-color: #C5A059;
    }
    
    /* Table */
    .table-container {
      background: white;
      border-radius: 28px;
      padding: 25px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.05);
      overflow-x: auto;
    }
    .table-container h2 {
      margin-bottom: 20px;
      color: #2F5D5A;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 1.4rem;
    }
    .booking-table {
      width: 100%;
      border-collapse: collapse;
    }
    .booking-table th,
    .booking-table td {
      padding: 16px 12px;
      text-align: left;
      border-bottom: 1px solid #e8e0d4;
    }
    .booking-table th {
      background: #f8f5f0;
      font-weight: 600;
      color: #2F5D5A;
      position: sticky;
      top: 0;
    }
    .booking-table tr:hover {
      background: #fefcf8;
    }
    
    /* Status Badges */
    .status-pending {
      background: #FFF3E0;
      color: #E67E22;
      padding: 5px 14px;
      border-radius: 40px;
      display: inline-block;
      font-size: 12px;
      font-weight: 600;
    }
    .status-approved {
      background: #E0F2E9;
      color: #1E6F3F;
      padding: 5px 14px;
      border-radius: 40px;
      display: inline-block;
      font-size: 12px;
      font-weight: 600;
    }
    .status-cancelled {
      background: #FFE0E0;
      color: #A13E3E;
      padding: 5px 14px;
      border-radius: 40px;
      display: inline-block;
      font-size: 12px;
      font-weight: 600;
    }
    .payment-paid {
      background: #E0F2E9;
      color: #1E6F3F;
      padding: 5px 14px;
      border-radius: 40px;
      display: inline-block;
      font-size: 12px;
      font-weight: 600;
    }
    .payment-unpaid {
      background: #FFE0E0;
      color: #A13E3E;
      padding: 5px 14px;
      border-radius: 40px;
      display: inline-block;
      font-size: 12px;
      font-weight: 600;
    }
    
    /* Action Buttons */
    .action-btns {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .action-btn {
      padding: 6px 14px;
      border-radius: 30px;
      font-size: 12px;
      font-weight: 500;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
    }
    .approve-btn { background: #2F5D5A; color: white; }
    .approve-btn:hover { background: #1e6f3f; }
    .cancel-btn { background: #a13e3e; color: white; }
    .cancel-btn:hover { background: #c72a2a; }
    .paid-btn { background: #C5A059; color: #2C2A29; }
    .paid-btn:hover { background: #D9B46B; }
    .edit-btn { background: #3498db; color: white; }
    .edit-btn:hover { background: #2980b9; }
    .delete-btn { background: #e74c3c; color: white; }
    .delete-btn:hover { background: #c0392b; }
    
    /* Pagination */
    .pagination {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-top: 25px;
      flex-wrap: wrap;
    }
    .pagination a, .pagination span {
      padding: 8px 16px;
      border-radius: 40px;
      text-decoration: none;
      color: #2F5D5A;
      background: #f0ebe2;
      transition: all 0.3s;
    }
    .pagination a:hover, .pagination .active {
      background: #C5A059;
      color: #2C2A29;
    }
    
    /* Modal */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background: white;
      border-radius: 32px;
      max-width: 600px;
      width: 90%;
      max-height: 85vh;
      overflow-y: auto;
      padding: 30px;
    }
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 1px solid #e8e0d4;
    }
    .modal-header h3 {
      color: #2F5D5A;
      font-size: 1.5rem;
    }
    .close-modal {
      font-size: 1.8rem;
      cursor: pointer;
      color: #999;
    }
    .close-modal:hover {
      color: #a13e3e;
    }
    .form-group {
      margin-bottom: 18px;
    }
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: #2C2A29;
    }
    .form-group input, .form-group select {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #e0d6c8;
      border-radius: 28px;
      font-size: 14px;
    }
    .form-group input:focus, .form-group select:focus {
      outline: none;
      border-color: #C5A059;
    }
    .form-row {
      display: flex;
      gap: 15px;
    }
    .form-row .form-group {
      flex: 1;
    }
    
    /* Alert Message */
    .alert {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 25px;
      border-radius: 40px;
      background: white;
      box-shadow: 0 5px 20px rgba(0,0,0,0.15);
      z-index: 1001;
      animation: slideIn 0.3s ease;
    }
    .alert-success { background: #e0f2e9; color: #1e6f3f; border-left: 4px solid #1e6f3f; }
    .alert-danger { background: #ffe0e0; color: #a13e3e; border-left: 4px solid #a13e3e; }
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    
    @media (max-width: 768px) {
      .container { padding: 15px; }
      .admin-header { flex-direction: column; text-align: center; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .booking-table th, .booking-table td { padding: 10px 8px; font-size: 11px; }
      .action-bar { flex-direction: column; }
      .search-box { width: 100%; }
      .search-box input { width: 100%; }
      .form-row { flex-direction: column; }
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Alert Messages -->
    <?php if (isset($_GET['msg'])): ?>
      <div class="alert alert-success" id="alertMsg">
        <?php 
          if ($_GET['msg'] == 'added') echo "✓ Booking added successfully!";
          if ($_GET['msg'] == 'updated') echo "✓ Booking updated successfully!";
          if ($_GET['msg'] == 'deleted') echo "✓ Booking deleted successfully!";
        ?>
      </div>
      <script>setTimeout(() => document.getElementById('alertMsg')?.remove(), 3000);</script>
    <?php endif; ?>

    <!-- Admin Header -->
    <div class="admin-header">
      <h1><i class="fas fa-chart-line"></i> Admin Dashboard</h1>
      <div class="header-buttons">
        <span style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 40px;">
          <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
        </span>
        <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Website</a>
        <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-alt"></i></div><h3><?php echo $stats['total']; ?></h3><p>Total Bookings</p></div>
      <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><h3 style="color: #E67E22;"><?php echo $stats['pending']; ?></h3><p>Pending Approval</p></div>
      <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><h3 style="color: #1E6F3F;"><?php echo $stats['approved']; ?></h3><p>Approved</p></div>
      <div class="stat-card"><div class="stat-icon"><i class="fas fa-times-circle"></i></div><h3 style="color: #A13E3E;"><?php echo $stats['cancelled']; ?></h3><p>Cancelled</p></div>
      <div class="stat-card"><div class="stat-icon"><i class="fas fa-dollar-sign"></i></div><h3 style="color: #C5A059;">$<?php echo number_format($stats['total_revenue'], 2); ?></h3><p>Total Revenue</p></div>
    </div>

    <!-- Action Bar: Search & Add Button -->
    <div class="action-bar">
      <div class="search-box">
        <form method="GET" action="" style="display: flex; gap: 10px;">
          <input type="text" name="search" placeholder="Search by name, email or phone..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
          <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
          <?php if (isset($_GET['search'])): ?>
            <a href="admin_dashboard.php" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
          <?php endif; ?>
        </form>
      </div>
      <button class="btn btn-success" onclick="openAddModal()"><i class="fas fa-plus"></i> Add New Booking</button>
    </div>

    <!-- Bookings Management Table -->
    <div class="table-container">
      <h2><i class="fas fa-list-ul"></i> All Bookings</h2>
      <table class="booking-table">
        <thead>
          <tr><th>ID</th><th>Guest Name</th><th>Email</th><th>Phone</th><th>Type</th><th>Check In</th><th>Check Out</th><th>Nights</th><th>Amount</th><th>Payment</th><th>Status</th><th>Pay Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if ($bookings && $bookings->num_rows > 0): ?>
            <?php while ($row = $bookings->fetch_assoc()): 
              $type_names = ['individual' => '👤 Individual', 'group3' => '👥 Group (3)', 'group6' => '👥 Group (6)', 'honeymoon2' => '💍 Honeymoon (2)', 'honeymoon3' => '💍 Honeymoon (3)', 'honeymoon6' => '💍 Honeymoon (6)'];
            ?>
            <tr>
              <td><?php echo $row['id']; ?></td>
              <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
              <td><?php echo htmlspecialchars($row['email']); ?></td>
              <td><?php echo htmlspecialchars($row['phone']); ?></td>
              <td><?php echo $type_names[$row['booking_type']] ?? $row['booking_type']; ?></td>
              <td><?php echo $row['check_in']; ?></td>
              <td><?php echo $row['check_out']; ?></td>
              <td><?php echo $row['nights']; ?></td>
              <td><strong>$<?php echo $row['total_amount']; ?></strong></td>
              <td><?php echo ucfirst($row['payment_method']); ?><br><small><?php echo htmlspecialchars($row['payment_detail']); ?></small></td>
              <td><span class="status-<?php echo $row['booking_status']; ?>"><?php echo ucfirst($row['booking_status']); ?></span></td>
              <td><span class="payment-<?php echo $row['payment_status']; ?>"><?php echo ucfirst($row['payment_status']); ?></span></td>
              <td class="action-btns">
                <?php if ($row['booking_status'] == 'pending'): ?>
                  <form method="POST" style="display:inline;"><input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>"><button type="submit" name="admin_action" value="approve" class="action-btn approve-btn"><i class="fas fa-check"></i></button></form>
                  <form method="POST" style="display:inline;"><input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>"><button type="submit" name="admin_action" value="cancel" class="action-btn cancel-btn"><i class="fas fa-times"></i></button></form>
                <?php elseif ($row['booking_status'] == 'approved' && $row['payment_status'] == 'unpaid'): ?>
                  <form method="POST" style="display:inline;"><input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>"><button type="submit" name="admin_action" value="mark_paid" class="action-btn paid-btn"><i class="fas fa-dollar-sign"></i></button></form>
                <?php endif; ?>
                <a href="?edit_id=<?php echo $row['id']; ?>" class="action-btn edit-btn" onclick="openEditModal(event, <?php echo $row['id']; ?>)"><i class="fas fa-edit"></i></a>
                <a href="?delete_id=<?php echo $row['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this booking?')"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="13" style="text-align:center;">No bookings found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      
      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?page=<?php echo $page-1; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>"><i class="fas fa-chevron-left"></i> Previous</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <a href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
          <a href="?page=<?php echo $page+1; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>">Next <i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Add Booking Modal -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-plus"></i> Add New Booking</h3>
        <span class="close-modal" onclick="closeAddModal()">&times;</span>
      </div>
      <form method="POST" action="">
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required></div>
          <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Phone *</label><input type="text" name="phone" required></div>
          <div class="form-group"><label>Booking Type *</label>
            <select name="booking_type" required>
              <option value="individual">Individual (1 person)</option>
              <option value="group3">Group (3 persons)</option>
              <option value="group6">Group (6 persons)</option>
              <option value="honeymoon2">Honeymoon (2 persons)</option>
              <option value="honeymoon3">Honeymoon (3 persons)</option>
              <option value="honeymoon6">Honeymoon (6 persons)</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Check-in Date *</label><input type="date" name="check_in" required></div>
          <div class="form-group"><label>Check-out Date *</label><input type="date" name="check_out" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Total Amount ($) *</label><input type="number" step="0.01" name="total_amount" required></div>
          <div class="form-group"><label>Payment Method *</label>
            <select name="payment_method" required>
              <option value="zaad">Zaad</option>
              <option value="edahab">Edahab</option>
              <option value="mastercard">Mastercard</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Payment Detail</label><input type="text" name="payment_detail" placeholder="Transaction ID or number"></div>
        <button type="submit" name="add_booking" class="btn btn-success" style="width:100%;"><i class="fas fa-save"></i> Save Booking</button>
      </form>
    </div>
  </div>

  <!-- Edit Booking Modal -->
  <?php if ($edit_booking): ?>
  <div id="editModal" class="modal" style="display: flex;">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-edit"></i> Edit Booking #<?php echo $edit_booking['id']; ?></h3>
        <a href="admin_dashboard.php" class="close-modal" style="text-decoration:none;">&times;</a>
      </div>
      <form method="POST" action="">
        <input type="hidden" name="booking_id" value="<?php echo $edit_booking['id']; ?>">
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($edit_booking['full_name']); ?>" required></div>
          <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?php echo htmlspecialchars($edit_booking['email']); ?>" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Phone *</label><input type="text" name="phone" value="<?php echo htmlspecialchars($edit_booking['phone']); ?>" required></div>
          <div class="form-group"><label>Booking Type *</label>
            <select name="booking_type" required>
              <option value="individual" <?php echo $edit_booking['booking_type']=='individual' ? 'selected' : ''; ?>>Individual (1 person)</option>
              <option value="group3" <?php echo $edit_booking['booking_type']=='group3' ? 'selected' : ''; ?>>Group (3 persons)</option>
              <option value="group6" <?php echo $edit_booking['booking_type']=='group6' ? 'selected' : ''; ?>>Group (6 persons)</option>
              <option value="honeymoon2" <?php echo $edit_booking['booking_type']=='honeymoon2' ? 'selected' : ''; ?>>Honeymoon (2 persons)</option>
              <option value="honeymoon3" <?php echo $edit_booking['booking_type']=='honeymoon3' ? 'selected' : ''; ?>>Honeymoon (3 persons)</option>
              <option value="honeymoon6" <?php echo $edit_booking['booking_type']=='honeymoon6' ? 'selected' : ''; ?>>Honeymoon (6 persons)</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Check-in Date *</label><input type="date" name="check_in" value="<?php echo $edit_booking['check_in']; ?>" required></div>
          <div class="form-group"><label>Check-out Date *</label><input type="date" name="check_out" value="<?php echo $edit_booking['check_out']; ?>" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Total Amount ($) *</label><input type="number" step="0.01" name="total_amount" value="<?php echo $edit_booking['total_amount']; ?>" required></div>
          <div class="form-group"><label>Payment Method *</label>
            <select name="payment_method" required>
              <option value="zaad" <?php echo $edit_booking['payment_method']=='zaad' ? 'selected' : ''; ?>>Zaad</option>
              <option value="edahab" <?php echo $edit_booking['payment_method']=='edahab' ? 'selected' : ''; ?>>Edahab</option>
              <option value="mastercard" <?php echo $edit_booking['payment_method']=='mastercard' ? 'selected' : ''; ?>>Mastercard</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Payment Detail</label><input type="text" name="payment_detail" value="<?php echo htmlspecialchars($edit_booking['payment_detail']); ?>" placeholder="Transaction ID or number"></div>
        <button type="submit" name="edit_booking" class="btn btn-primary" style="width:100%;"><i class="fas fa-save"></i> Update Booking</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <script>
    function openAddModal() {
      document.getElementById('addModal').style.display = 'flex';
    }
    function closeAddModal() {
      document.getElementById('addModal').style.display = 'none';
    }
    function openEditModal(event, id) {
      event.preventDefault();
      // Edit modal is handled via PHP redirect with edit_id
      window.location.href = '?edit_id=' + id;
    }
    // Close modal when clicking outside
    window.onclick = function(event) {
      if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
      }
    }
  </script>
</body>
</html>
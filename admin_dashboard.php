<?php
session_start();
include 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['name'] ?? 'Admin';

// Total Students
$student_result = $conn->query(
    "SELECT COUNT(*) AS total FROM users WHERE role = 'student'"
);
$total_students = $student_result->fetch_assoc()['total'] ?? 0;

// Total Active Services
$service_result = $conn->query(
    "SELECT COUNT(*) AS total FROM services WHERE status = 'active'"
);
$total_services = $service_result->fetch_assoc()['total'] ?? 0;

// Total Bookings
$booking_result = $conn->query(
    "SELECT COUNT(*) AS total FROM token_bookings"
);
$total_bookings = $booking_result->fetch_assoc()['total'] ?? 0;

// Pending Tokens
$pending_result = $conn->query(
    "SELECT COUNT(*) AS total FROM token_bookings WHERE status = 'pending'"
);
$pending_tokens = $pending_result->fetch_assoc()['total'] ?? 0;

// Approved Tokens
$approved_result = $conn->query(
    "SELECT COUNT(*) AS total FROM token_bookings WHERE status = 'approved'"
);
$approved_tokens = $approved_result->fetch_assoc()['total'] ?? 0;

// Completed Tokens
$completed_result = $conn->query(
    "SELECT COUNT(*) AS total FROM token_bookings WHERE status = 'completed'"
);
$completed_tokens = $completed_result->fetch_assoc()['total'] ?? 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Dashboard | Smart Campus</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: #F3F0E8;
    color: #292E32;
}

/* NAVBAR */

.navbar {
    background: #292E32;
    padding: 17px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 3px 15px rgba(41,46,50,0.12);
    position: sticky;
    top: 0;
    z-index: 100;
}

.logo {
    font-family: 'Outfit', sans-serif;
    font-size: 22px;
    font-weight: 800;
    color: #F3F0E8;
    display: flex;
    align-items: center;
    gap: 10px;
}

.logo-icon {
    width: 43px;
    height: 43px;
    background: #F3F0E8;
    border-radius: 12px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 23px;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 18px;
}

.admin-label {
    font-size: 13px;
    color: #D0CBC0;
    font-weight: 600;
}

.logout-btn {
    text-decoration: none;
    background: #F3F0E8;
    color: #292E32;
    padding: 10px 17px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 800;
    transition: 0.3s;
}

.logout-btn:hover {
    background: #DCD5C7;
    transform: translateY(-2px);
}

/* MAIN */

.container {
    max-width: 1200px;
    margin: 35px auto;
    padding: 0 23px;
}

/* WELCOME */

.welcome {
    background: linear-gradient(120deg, #292E32, #343B40, #454D52);
    padding: 38px;
    border-radius: 19px;
    color: #F3F0E8;
    position: relative;
    overflow: hidden;
    box-shadow: 0 12px 30px rgba(41,46,50,0.16);
}

.welcome::after {
    content: "⚙️";
    position: absolute;
    right: 60px;
    top: 15px;
    font-size: 115px;
    opacity: 0.18;
    transform: rotate(-15deg);
}

.welcome-tag {
    font-size: 12px;
    letter-spacing: 2px;
    font-weight: 800;
    color: #DCD5C7;
    margin-bottom: 12px;
}

.welcome h1 {
    font-family: 'Outfit', sans-serif;
    font-size: 33px;
    margin-bottom: 10px;
    position: relative;
    z-index: 1;
}

.welcome p {
    font-size: 14px;
    color: #E2DED5;
    line-height: 1.7;
    position: relative;
    z-index: 1;
}

/* SECTION HEADING */

.section-heading {
    margin: 35px 0 20px;
}

.section-heading h2 {
    font-family: 'Outfit', sans-serif;
    font-size: 23px;
    color: #292E32;
    margin-bottom: 6px;
}

.section-heading p {
    font-size: 13px;
    color: #817D74;
}

/* STATS */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 17px;
}

.stat-card {
    background: #FFFFFF;
    border: 1px solid #DCD8CF;
    border-radius: 15px;
    padding: 22px;
    box-shadow: 0 5px 18px rgba(41,46,50,0.045);
    transition: 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 25px rgba(41,46,50,0.09);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 23px;
    margin-bottom: 15px;
}

.blue-bg {
    background: #F3F0E8;
}

.green-bg {
    background: #E7E5DC;
}

.orange-bg {
    background: #EAE3D6;
}

.purple-bg {
    background: #E5E2D9;
}

.stat-card h2 {
    font-family: 'Outfit', sans-serif;
    font-size: 30px;
    color: #292E32;
    margin-bottom: 5px;
}

.stat-card p {
    font-size: 12px;
    color: #817D74;
    font-weight: 700;
}

/* MANAGEMENT CARDS */

.management-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.manage-card {
    background: #FFFFFF;
    border: 1px solid #DCD8CF;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 5px 18px rgba(41,46,50,0.04);
    transition: 0.3s;
}

.manage-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 13px 28px rgba(41,46,50,0.09);
    border-color: #BDB6A8;
}

.manage-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
    margin-bottom: 17px;
}

.manage-card h3 {
    font-family: 'Outfit', sans-serif;
    font-size: 19px;
    color: #292E32;
    margin-bottom: 9px;
}

.manage-card p {
    color: #817D74;
    font-size: 13px;
    line-height: 1.7;
    margin-bottom: 18px;
}

.manage-link {
    display: inline-block;
    text-decoration: none;
    color: #292E32;
    font-size: 13px;
    font-weight: 800;
    transition: 0.3s;
}

.manage-link:hover {
    transform: translateX(5px);
}

/* STATUS SECTION */

.status-panel {
    background: #FFFFFF;
    border: 1px solid #DCD8CF;
    border-radius: 16px;
    padding: 24px;
    margin-top: 28px;
}

.status-panel h3 {
    font-family: 'Outfit', sans-serif;
    font-size: 19px;
    color: #292E32;
    margin-bottom: 19px;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.status-item {
    background: #F3F0E8;
    padding: 17px;
    border-radius: 11px;
    border: 1px solid #DCD8CF;
}

.status-item h2 {
    font-size: 25px;
    font-family: 'Outfit', sans-serif;
    margin-bottom: 5px;
}

.status-item p {
    color: #817D74;
    font-size: 12px;
    font-weight: 700;
}

.pending-color {
    color: #9A702B;
}

.approved-color {
    color: #3E7C59;
}

.completed-color {
    color: #292E32;
}

/* FOOTER */

footer {
    text-align: center;
    padding: 27px 12px;
    color: #817D74;
    font-size: 12px;
}

/* MOBILE */

@media(max-width: 800px) {

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .management-grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 550px) {

    .navbar {
        padding: 14px 16px;
    }

    .logo {
        font-size: 17px;
    }

    .admin-label {
        display: none;
    }

    .container {
        padding: 0 14px;
        margin: 25px auto;
    }

    .welcome {
        padding: 29px 22px;
    }

    .welcome h1 {
        font-size: 27px;
    }

    .welcome::after {
        right: 8px;
        top: 40px;
        font-size: 75px;
    }

    .stats-grid {
        gap: 11px;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-card h2 {
        font-size: 25px;
    }

    .status-grid {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<!-- NAVBAR -->

<nav class="navbar">

    <div class="logo">
        <div class="logo-icon">🎓</div>
        Smart Campus
    </div>

    <div class="nav-right">

        <span class="admin-label">
            Administrator Portal
        </span>

        <a href="logout.php" class="logout-btn">
            ↪ Logout
        </a>

    </div>

</nav>


<div class="container">

    <!-- WELCOME -->

    <section class="welcome">

        <div class="welcome-tag">
            ADMINISTRATOR PORTAL
        </div>

        <h1>
            Welcome, <?php echo htmlspecialchars($admin_name); ?>! 👋
        </h1>

        <p>
            Manage your campus services, student bookings
            and token requests from one smart dashboard.
        </p>

    </section>


    <!-- STATISTICS -->

    <div class="section-heading">

        <h2>Dashboard Overview</h2>

        <p>Quick summary of your Smart Campus system.</p>

    </div>


    <div class="stats-grid">

        <div class="stat-card">

            <div class="stat-icon blue-bg">👨‍🎓</div>

            <h2><?php echo $total_students; ?></h2>

            <p>Total Students</p>

        </div>


        <div class="stat-card">

            <div class="stat-icon green-bg">🏢</div>

            <h2><?php echo $total_services; ?></h2>

            <p>Active Services</p>

        </div>


        <div class="stat-card">

            <div class="stat-icon orange-bg">🎟️</div>

            <h2><?php echo $total_bookings; ?></h2>

            <p>Total Bookings</p>

        </div>


        <div class="stat-card">

            <div class="stat-icon purple-bg">⏳</div>

            <h2><?php echo $pending_tokens; ?></h2>

            <p>Pending Tokens</p>

        </div>

    </div>


    <!-- MANAGEMENT -->

    <div class="section-heading">

        <h2>Manage Your Campus</h2>

        <p>Access all administration features in one place.</p>

    </div>


    <div class="management-grid">


        <!-- MANAGE SERVICES -->

        <div class="manage-card">

            <div class="manage-icon blue-bg">🏢</div>

            <h3>Manage Services</h3>

            <p>
                Add new main services and sub-services,
                and manage your college departments.
            </p>

            <a href="manage_services.php" class="manage-link">
                Manage Services →
            </a>

        </div>


        <!-- MANAGE TOKENS -->

        <div class="manage-card">

            <div class="manage-icon green-bg">📋</div>

            <h3>Manage Token Requests</h3>

            <p>
                View student bookings, approve pending
                requests and manage token statuses.
            </p>

            <a href="manage_tokens.php" class="manage-link">
                Manage Tokens →
            </a>

        </div>


        <!-- ADD SLOT -->

        <div class="manage-card">

            <div class="manage-icon orange-bg">🕒</div>

            <h3>Manage Time Slots</h3>

            <p>
                Access the time slot management page
                for college booking schedules.
            </p>

            <a href="add_slot.php" class="manage-link">
                Manage Time Slots →
            </a>

        </div>


        <!-- STUDENT INFORMATION -->

        <div class="manage-card">

            <div class="manage-icon purple-bg">👨‍🎓</div>

            <h3>Student & Booking Overview</h3>

            <p>
                View the total registered students
                and monitor booking activity.
            </p>

            <a href="manage_tokens.php" class="manage-link">
                View Bookings →
            </a>

        </div>

    </div>


    <!-- STATUS OVERVIEW -->

    <div class="status-panel">

        <h3>📊 Token Status Overview</h3>

        <div class="status-grid">

            <div class="status-item">

                <h2 class="pending-color">
                    <?php echo $pending_tokens; ?>
                </h2>

                <p>Pending Requests</p>

            </div>


            <div class="status-item">

                <h2 class="approved-color">
                    <?php echo $approved_tokens; ?>
                </h2>

                <p>Approved Tokens</p>

            </div>


            <div class="status-item">

                <h2 class="completed-color">
                    <?php echo $completed_tokens; ?>
                </h2>

                <p>Completed Tokens</p>

            </div>

        </div>

    </div>

</div>


<footer>
    © <?php echo date('Y'); ?> Smart Campus Token Booking System.
    All Rights Reserved.
</footer>


</body>
</html>
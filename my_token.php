<?php
session_start();
include 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

$student_id = (int)$_SESSION['user_id'];

// Fetch student's token bookings
$stmt = $conn->prepare(
    "SELECT
        tb.id,
        tb.token_number,
        tb.status,
        tb.created_at,
        tb.other_sub_service,
        s.service_name,
        ss.sub_service_name,
        COALESCE(tb.booking_date, ts.slot_date) AS booking_date,
        COALESCE(tb.start_time, ts.start_time) AS start_time,
        COALESCE(tb.end_time, ts.end_time) AS end_time

    FROM token_bookings tb

    INNER JOIN services s
        ON tb.service_id = s.id

    LEFT JOIN service_subcategories ss
        ON tb.sub_service_id = ss.id

    LEFT JOIN token_slots ts
        ON tb.slot_id = ts.id

    WHERE tb.student_id = ?

    ORDER BY
        COALESCE(tb.booking_date, ts.slot_date) DESC,
        COALESCE(tb.start_time, ts.start_time) DESC,
        tb.id DESC"
);

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

$total_tokens = $result->num_rows;

$bookings = [];

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();


// Status counts
$pending = 0;
$approved = 0;
$completed = 0;
$cancelled = 0;

foreach ($bookings as $booking) {

    $status = strtolower($booking['status']);

    if ($status == 'pending') {
        $pending++;
    } elseif ($status == 'approved') {
        $approved++;
    } elseif ($status == 'completed') {
        $completed++;
    } elseif ($status == 'cancelled') {
        $cancelled++;
    }
}


// Format date
function formatDate($date) {
    if (!$date) {
        return 'Not Available';
    }

    return date('d M Y', strtotime($date));
}


// Format time in 12-hour clock
function formatTime($time) {
    if (!$time) {
        return 'Not Available';
    }

    return date('h:i A', strtotime($time));
}


// Status class
function statusClass($status) {

    switch (strtolower($status)) {

        case 'pending':
            return 'pending';

        case 'approved':
            return 'approved';

        case 'completed':
            return 'completed';

        case 'cancelled':
            return 'cancelled';

        default:
            return 'pending';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Tokens | Smart Campus</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>

/* COLOR THEME */

:root {
    --primary: #292E32;
    --primary-dark: #1F2326;
    --primary-light: #E8E6DF;

    --ink: #292E32;
    --muted: #777873;

    --line: #E2E0D8;
    --surface: #F3F0E8;
    --page: #F3F0E8;

    --shadow: 0 8px 28px rgba(41, 46, 50, 0.08);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--page);
    color: var(--ink);
}

/* NAVBAR */

.navbar {
    background: var(--surface);
    padding: 17px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--shadow);
    border-bottom: 1px solid var(--line);
}

.logo {
    font-family: 'Outfit', sans-serif;
    font-size: 21px;
    font-weight: 800;
    color: var(--primary);
}

.back-link {
    text-decoration: none;
    color: var(--primary);
    font-size: 13px;
    font-weight: 700;
}

.back-link:hover {
    color: var(--primary-dark);
}

/* CONTAINER */

.container {
    max-width: 1150px;
    margin: 35px auto;
    padding: 0 22px;
}

/* HEADING */

.heading {
    margin-bottom: 28px;
}

.tag {
    color: var(--primary);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 2px;
    margin-bottom: 9px;
}

.heading h1 {
    font-family: 'Outfit', sans-serif;
    font-size: 32px;
    color: var(--primary);
    margin-bottom: 8px;
}

.heading p {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.6;
}

/* SUMMARY CARDS */

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 17px;
    margin-bottom: 30px;
}

.summary-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 20px;
    box-shadow: var(--shadow);
    transition: 0.3s;
}

.summary-card:hover {
    transform: translateY(-4px);
}

.summary-card .icon {
    font-size: 23px;
    margin-bottom: 12px;
}

.summary-card h2 {
    font-family: 'Outfit', sans-serif;
    font-size: 28px;
    margin-bottom: 4px;
}

.summary-card p {
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
}

.total h2 {
    color: var(--primary);
}

.pending-count h2 {
    color: #80632C;
}

.approved-count h2 {
    color: #3F5B49;
}

.completed-count h2 {
    color: #526B7B;
}

/* TOKEN SECTION */

.token-section {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 17px;
    padding: 25px;
    box-shadow: var(--shadow);
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 23px;
    gap: 10px;
}

.section-heading h2 {
    font-family: 'Outfit', sans-serif;
    font-size: 21px;
    color: var(--primary);
}

.token-count {
    background: #E5E2D9;
    color: var(--primary);
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 800;
}

/* TOKEN CARD */

.token-card {
    border: 1px solid var(--line);
    border-radius: 14px;
    margin-bottom: 18px;
    overflow: hidden;
    transition: 0.3s;
    background: var(--surface);
}

.token-card:hover {
    border-color: #BDB9AF;
    box-shadow: 0 8px 22px rgba(41, 46, 50, 0.08);
}

.token-top {
    padding: 17px 21px;
    background: #EAE7DE;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--line);
    gap: 10px;
}

.token-number {
    font-family: 'Outfit', sans-serif;
    font-size: 21px;
    font-weight: 800;
    color: var(--primary);
}

.status {
    padding: 7px 13px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.status.pending {
    background: #F1E7D2;
    color: #80632C;
}

.status.approved {
    background: #E3E9E4;
    color: #3F5B49;
}

.status.completed {
    background: #E1E7E9;
    color: #526B7B;
}

.status.cancelled {
    background: #EADDD9;
    color: #8A4C40;
}

.token-body {
    padding: 22px;
}

.token-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 21px;
}

.detail-label {
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 7px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    color: var(--primary);
    font-size: 14px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.token-footer {
    border-top: 1px solid var(--line);
    padding: 13px 21px;
    color: var(--muted);
    font-size: 11px;
    background: #F0EDE5;
}

/* EMPTY STATE */

.empty-state {
    text-align: center;
    padding: 55px 15px;
}

.empty-icon {
    font-size: 60px;
    margin-bottom: 17px;
}

.empty-state h3 {
    font-family: 'Outfit', sans-serif;
    color: var(--primary);
    font-size: 22px;
    margin-bottom: 9px;
}

.empty-state p {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 22px;
}

.book-btn {
    display: inline-block;
    text-decoration: none;
    background: var(--primary);
    color: var(--surface);
    padding: 13px 23px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 800;
    transition: 0.3s;
}

.book-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-3px);
    box-shadow: 0 7px 18px rgba(41, 46, 50, 0.15);
}

/* FOOTER */

footer {
    text-align: center;
    padding: 27px 12px;
    color: var(--muted);
    font-size: 12px;
}

/* MOBILE */

@media(max-width: 750px) {

    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .token-details {
        grid-template-columns: 1fr 1fr;
    }
}

@media(max-width: 500px) {

    .navbar {
        padding: 15px 17px;
    }

    .logo {
        font-size: 18px;
    }

    .container {
        padding: 0 13px;
        margin: 25px auto;
    }

    .heading h1 {
        font-size: 26px;
    }

    .summary-grid {
        gap: 10px;
    }

    .summary-card {
        padding: 15px;
    }

    .summary-card h2 {
        font-size: 24px;
    }

    .token-section {
        padding: 16px 12px;
    }

    .token-body {
        padding: 17px 13px;
    }

    .token-details {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .token-top {
        padding: 14px;
    }

    .token-number {
        font-size: 18px;
    }

    .status {
        font-size: 10px;
        padding: 6px 9px;
    }
}

</style>

</head>

<body>

<!-- NAVBAR -->

<nav class="navbar">

    <div class="logo">
        🎓 Smart Campus
    </div>

    <a href="student_dashboard.php" class="back-link">
        ← Back to Dashboard
    </a>

</nav>


<div class="container">

    <!-- PAGE HEADING -->

    <div class="heading">

        <div class="tag">STUDENT PORTAL</div>

        <h1>My Tokens 📋</h1>

        <p>
            Track all your college service bookings
            and check their latest status.
        </p>

    </div>


    <!-- SUMMARY -->

    <div class="summary-grid">

        <div class="summary-card total">
            <div class="icon">🎟️</div>
            <h2><?php echo $total_tokens; ?></h2>
            <p>Total Bookings</p>
        </div>

        <div class="summary-card pending-count">
            <div class="icon">⏳</div>
            <h2><?php echo $pending; ?></h2>
            <p>Pending</p>
        </div>

        <div class="summary-card approved-count">
            <div class="icon">✅</div>
            <h2><?php echo $approved; ?></h2>
            <p>Approved</p>
        </div>

        <div class="summary-card completed-count">
            <div class="icon">🏆</div>
            <h2><?php echo $completed; ?></h2>
            <p>Completed</p>
        </div>

    </div>


    <!-- TOKEN LIST -->

    <div class="token-section">

        <div class="section-heading">

            <h2>Your Booking History</h2>

            <span class="token-count">
                <?php echo $total_tokens; ?> Tokens
            </span>

        </div>


        <?php if ($total_tokens > 0): ?>

            <?php foreach ($bookings as $booking): ?>

                <?php
                    $sub_service = '';

                    if (!empty($booking['other_sub_service'])) {
                        $sub_service = 'Other: ' . $booking['other_sub_service'];
                    } elseif (!empty($booking['sub_service_name'])) {
                        $sub_service = $booking['sub_service_name'];
                    } else {
                        $sub_service = 'Not Specified';
                    }

                    $status = strtolower($booking['status']);
                ?>

                <div class="token-card">

                    <div class="token-top">

                        <div class="token-number">
                            Token #<?php echo htmlspecialchars($booking['token_number']); ?>
                        </div>

                        <span class="status <?php echo statusClass($status); ?>">
                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                        </span>

                    </div>


                    <div class="token-body">

                        <div class="token-details">

                            <div>
                                <div class="detail-label">
                                    Main Service
                                </div>

                                <div class="detail-value">
                                    🏢 <?php echo htmlspecialchars($booking['service_name']); ?>
                                </div>
                            </div>


                            <div>
                                <div class="detail-label">
                                    Sub Service
                                </div>

                                <div class="detail-value">
                                    📌 <?php echo htmlspecialchars($sub_service); ?>
                                </div>
                            </div>


                            <div>
                                <div class="detail-label">
                                    Booking Date
                                </div>

                                <div class="detail-value">
                                    📅 <?php echo formatDate($booking['booking_date']); ?>
                                </div>
                            </div>


                            <div>
                                <div class="detail-label">
                                    Time Slot
                                </div>

                                <div class="detail-value">
                                    🕒
                                    <?php
                                    echo formatTime($booking['start_time']);
                                    ?>
                                    -
                                    <?php
                                    echo formatTime($booking['end_time']);
                                    ?>
                                </div>
                            </div>

                        </div>

                    </div>


                    <div class="token-footer">

                        Booked On:
                        <?php
                        echo date('d M Y, h:i A', strtotime($booking['created_at']));
                        ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty-state">

                <div class="empty-icon">🎟️</div>

                <h3>No Tokens Yet!</h3>

                <p>
                    You have not booked any college service tokens.
                    Start by booking your first token.
                </p>

                <a href="book_token.php" class="book-btn">
                    + Book Your First Token
                </a>

            </div>

        <?php endif; ?>

    </div>

</div>


<footer>
    © <?php echo date('Y'); ?> Smart Campus Token Booking System.
    All Rights Reserved.
</footer>

</body>
</html>
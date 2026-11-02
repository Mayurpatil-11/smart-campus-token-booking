<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

date_default_timezone_set('Asia/Kolkata');

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$today = date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) ||
    strtotime($selectedDate) === false ||
    date('Y-m-d', strtotime($selectedDate)) !== $selectedDate ||
    $selectedDate < $today) {
    $selectedDate = $today;
}

$dayNumber = (int)date('N', strtotime($selectedDate));
$isWorkingDay = ($dayNumber >= 1 && $dayNumber <= 5);

$slots = [
    ['09:00:00', '10:00:00'],
    ['10:00:00', '11:00:00'],
    ['11:00:00', '12:00:00'],
    ['12:00:00', '13:00:00'],
    ['13:00:00', '14:00:00'],
    ['14:00:00', '15:00:00'],
    ['15:00:00', '16:00:00'],
    ['16:00:00', '17:00:00']
];

function displayTime($time) {
    return date('h:i A', strtotime($time));
}

$slotData = [];
$totalBookings = 0;

if ($isWorkingDay) {
    foreach ($slots as $slot) {
        $start = $slot[0];
        $end = $slot[1];

        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM token_bookings tb
            LEFT JOIN token_slots ts ON tb.slot_id = ts.id
            WHERE COALESCE(tb.booking_date, ts.slot_date) = ?
              AND COALESCE(tb.start_time, ts.start_time) = ?
              AND tb.status != 'cancelled'
        ");

        $stmt->bind_param("ss", $selectedDate, $start);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $totalBookings += $count;

        $slotData[] = [
            'start' => $start,
            'end' => $end,
            'count' => $count,
            'available' => max(0, 10 - $count)
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Slot Management | Smart Campus</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Arial, sans-serif;
}

body {
    background: #F3F0E8;
    color: #292E32;
}

.navbar {
    background: #292E32;
    padding: 17px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 3px 15px rgba(0,0,0,.12);
}

.logo {
    color: #F3F0E8;
    font-size: 22px;
    font-weight: 800;
}

.nav-links {
    display: flex;
    gap: 18px;
    align-items: center;
}

.nav-links a {
    color: #F3F0E8;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
}

.logout {
    background: #F3F0E8;
    color: #292E32 !important;
    padding: 9px 14px;
    border-radius: 8px;
}

.container {
    max-width: 1150px;
    margin: 35px auto;
    padding: 0 20px;
}

.heading {
    margin-bottom: 25px;
}

.heading h1 {
    font-size: 28px;
    color: #292E32;
}

.heading p {
    color: #777B7D;
    margin-top: 7px;
    font-size: 14px;
}

.info-banner {
    background: #E8E4DA;
    color: #292E32;
    padding: 17px 20px;
    border-radius: 12px;
    margin-bottom: 23px;
    line-height: 1.6;
    font-size: 13px;
    border: 1px solid #D8D3C8;
}

.date-card {
    background: #FFFFFF;
    padding: 22px;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(41,46,50,.06);
    margin-bottom: 23px;
}

.date-card label {
    display: block;
    color: #292E32;
    font-weight: 700;
    font-size: 13px;
    margin-bottom: 9px;
}

.date-row {
    display: flex;
    gap: 12px;
    align-items: center;
}

.date-row input {
    padding: 12px;
    border: 1px solid #D8D3C8;
    border-radius: 9px;
    outline: none;
    font-size: 14px;
    background: #F3F0E8;
    color: #292E32;
}

.date-row input:focus {
    border-color: #292E32;
}

.date-row button {
    background: #292E32;
    color: #F3F0E8;
    border: none;
    border-radius: 9px;
    padding: 12px 20px;
    font-weight: 700;
    cursor: pointer;
}

.date-row button:hover {
    background: #41484D;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: #FFFFFF;
    padding: 20px;
    border-radius: 13px;
    border: 1px solid #E5E1D8;
    box-shadow: 0 4px 16px rgba(41,46,50,.04);
}

.stat-card p {
    color: #777B7D;
    font-size: 13px;
    font-weight: 600;
}

.stat-card h2 {
    font-size: 26px;
    margin-top: 8px;
    color: #292E32;
}

.slot-section {
    background: #FFFFFF;
    padding: 23px;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(41,46,50,.06);
}

.section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    gap: 10px;
}

.section-title h3 {
    color: #292E32;
    font-size: 18px;
}

.section-title span {
    color: #777B7D;
    font-size: 12px;
}

.slot-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.slot-card {
    border: 1px solid #E5E1D8;
    border-radius: 12px;
    padding: 18px;
    background: #FFFFFF;
}

.slot-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    gap: 8px;
}

.slot-time {
    font-size: 15px;
    font-weight: 800;
    color: #292E32;
}

.slot-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 6px 9px;
    border-radius: 20px;
}

.available {
    background: #E5F0E6;
    color: #386B43;
}

.full {
    background: #F5E3DF;
    color: #9C4035;
}

.progress {
    width: 100%;
    height: 8px;
    background: #E8E4DA;
    border-radius: 20px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-bar {
    height: 100%;
    background: #292E32;
    border-radius: 20px;
}

.slot-bottom {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #777B7D;
}

.closed {
    text-align: center;
    padding: 45px 15px;
    background: #F3F0E8;
    border-radius: 12px;
    color: #6D6250;
    border: 1px solid #E5E1D8;
}

.closed .emoji {
    font-size: 42px;
    margin-bottom: 12px;
}

.footer {
    text-align: center;
    color: #777B7D;
    font-size: 12px;
    padding: 25px;
}

@media(max-width: 650px) {
    .navbar {
        padding: 14px;
    }

    .logo {
        font-size: 17px;
    }

    .nav-links {
        gap: 9px;
    }

    .nav-links a {
        font-size: 11px;
    }

    .container {
        padding: 0 12px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .slot-grid {
        grid-template-columns: 1fr;
    }

    .date-row {
        flex-wrap: wrap;
    }

    .date-row input {
        width: 100%;
    }

    .date-row button {
        width: 100%;
    }

    .heading h1 {
        font-size: 23px;
    }
}
</style>
</head>

<body>

<div class="navbar">
    <div class="logo">🎓 Smart Campus</div>

    <div class="nav-links">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="manage_tokens.php">Tokens</a>
        <a href="logout.php" class="logout">Logout</a>
    </div>
</div>

<div class="container">

    <div class="heading">
        <h1>Slot Management</h1>
        <p>View daily token slot availability and booking capacity.</p>
    </div>

    <div class="info-banner">
        <strong>Automatic Slot System</strong><br>
        Slots are generated automatically for Monday to Friday.
        Each slot allows a maximum of 10 bookings. Saturday and Sunday are closed.
        No manual slot creation is required.
    </div>

    <div class="date-card">
        <form method="GET">
            <label>Select Booking Date</label>

            <div class="date-row">
                <input type="date" name="date"
                       value="<?php echo htmlspecialchars($selectedDate); ?>"
                       min="<?php echo $today; ?>" required>

                <button type="submit">View Slots</button>
            </div>
        </form>
    </div>

    <?php if ($isWorkingDay): ?>

        <div class="stats">

            <div class="stat-card">
                <p>Total Daily Slots</p>
                <h2>8</h2>
            </div>

            <div class="stat-card">
                <p>Total Bookings</p>
                <h2><?php echo $totalBookings; ?></h2>
            </div>

            <div class="stat-card">
                <p>Remaining Capacity</p>
                <h2><?php echo max(0, 80 - $totalBookings); ?></h2>
            </div>

        </div>

        <div class="slot-section">

            <div class="section-title">
                <h3>Daily Available Slots</h3>
                <span><?php echo date('d M Y', strtotime($selectedDate)); ?></span>
            </div>

            <div class="slot-grid">

                <?php foreach ($slotData as $slot): ?>

                    <?php
                    $percentage = min(100, ($slot['count'] / 10) * 100);
                    $isFull = $slot['available'] <= 0;
                    ?>

                    <div class="slot-card">

                        <div class="slot-top">
                            <div class="slot-time">
                                <?php echo displayTime($slot['start']); ?>
                                -
                                <?php echo displayTime($slot['end']); ?>
                            </div>

                            <span class="slot-badge <?php echo $isFull ? 'full' : 'available'; ?>">
                                <?php echo $isFull ? 'FULL' : 'AVAILABLE'; ?>
                            </span>
                        </div>

                        <div class="slot-bottom">
                            <span>Bookings: <?php echo $slot['count']; ?>/10</span>
                            <span><?php echo $slot['available']; ?> seats left</span>
                        </div>

                        <div class="progress">
                            <div class="progress-bar"
                                 style="width: <?php echo $percentage; ?>%">
                            </div>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    <?php else: ?>

        <div class="slot-section">
            <div class="closed">
                <div class="emoji">🗓️</div>
                <h2>Booking Closed</h2>
                <p style="margin-top:8px;">
                    Saturday and Sunday are non-working days.
                    Please select a Monday to Friday date.
                </p>
            </div>
        </div>

    <?php endif; ?>

</div>

<div class="footer">
    Smart Campus Token Booking System © <?php echo date('Y'); ?>
</div>

</body>
</html>

<?php
$conn->close();
?>
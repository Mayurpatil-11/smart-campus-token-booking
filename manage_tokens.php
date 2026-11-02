<?php
session_start();
require_once "config/database.php";

date_default_timezone_set('Asia/Kolkata');

// --------------------------------------------------
// ADMIN LOGIN CHECK
// --------------------------------------------------

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

// Maximum number of active bookings allowed in one slot
const SLOT_CAPACITY = 10;

// --------------------------------------------------
// HELPER FUNCTIONS
// --------------------------------------------------

function formatDate($date) {
    return $date
        ? date("d M Y", strtotime($date))
        : "N/A";
}

function formatTime($time) {
    return $time
        ? date("h:i A", strtotime($time))
        : "N/A";
}

// True when the slot's end time has already passed
function slotHasEnded($date, $endTime) {
    if (empty($date) || empty($endTime)) {
        return false;
    }

    $timestamp = strtotime($date . ' ' . $endTime);

    return $timestamp !== false && $timestamp <= time();
}

// Readable service label, e.g. "Canteen — Lunch" or "Library — Other: Custom"
function serviceLabel($row) {
    $label = $row['service_name'] ?? '';

    if (!empty($row['other_sub_service'])) {
        $label .= ' — Other: ' . $row['other_sub_service'];
    } elseif (!empty($row['sub_service_name'])) {
        $label .= ' — ' . $row['sub_service_name'];
    }

    return $label;
}

// --------------------------------------------------
// FLASH MESSAGE (shown after redirect)
// --------------------------------------------------

$message = "";
$messageType = "";

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'error';

    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// --------------------------------------------------
// APPROVE / CANCEL / COMPLETE / INCOMPLETE ACTIONS
// --------------------------------------------------

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action'], $_POST['booking_id'])
) {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'];

    if (!$booking_id || $booking_id <= 0) {
        $message = "Invalid booking selected.";
        $messageType = "error";
    }

    // APPROVE
    elseif ($action === 'approve') {

        $stmt = $conn->prepare("
            UPDATE token_bookings
            SET status = 'approved'
            WHERE id = ?
              AND status = 'pending'
        ");

        $stmt->bind_param("i", $booking_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Token approved successfully!";
            $messageType = "success";
        } else {
            $message = "Token could not be approved. It may already be processed.";
            $messageType = "error";
        }

        $stmt->close();
    }

    // CANCEL
    elseif ($action === 'cancel') {

        $stmt = $conn->prepare("
            UPDATE token_bookings
            SET status = 'cancelled'
            WHERE id = ?
              AND status = 'pending'
        ");

        $stmt->bind_param("i", $booking_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Token cancelled successfully!";
            $messageType = "success";
        } else {
            $message = "Token could not be cancelled. It may already be processed.";
            $messageType = "error";
        }

        $stmt->close();
    }

    // COMPLETE / INCOMPLETE
    elseif ($action === 'complete' || $action === 'incomplete') {

        // Get booking date and end time
        $checkStmt = $conn->prepare("
            SELECT
                COALESCE(tb.booking_date, ts.slot_date) AS booking_date,
                COALESCE(tb.end_time, ts.end_time) AS end_time,
                tb.status
            FROM token_bookings tb
            LEFT JOIN token_slots ts
                ON tb.slot_id = ts.id
            WHERE tb.id = ?
        ");

        $checkStmt->bind_param("i", $booking_id);
        $checkStmt->execute();

        $bookingCheck = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($bookingCheck && $bookingCheck['status'] === 'approved') {

            // Admin can mark final status only after slot ends
            if (slotHasEnded($bookingCheck['booking_date'] ?? '', $bookingCheck['end_time'] ?? '')) {

                $newStatus = ($action === 'complete')
                    ? 'completed'
                    : 'incomplete';

                $stmt = $conn->prepare("
                    UPDATE token_bookings
                    SET status = ?
                    WHERE id = ?
                      AND status = 'approved'
                ");

                $stmt->bind_param("si", $newStatus, $booking_id);

                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = ($action === 'complete')
                        ? "Service marked as Completed!"
                        : "Service marked as Incomplete!";

                    $messageType = "success";
                } else {
                    $message = "Booking status could not be updated.";
                    $messageType = "error";
                }

                $stmt->close();

            } else {
                $message = "Time slot is not completed yet.";
                $messageType = "error";
            }

        } else {
            $message = "Only approved bookings can be completed.";
            $messageType = "error";
        }
    }

    // Post/Redirect/Get: prevents the action from being re-submitted on page refresh
    if ($message !== "") {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $messageType;
    }

    $redirectUrl = basename($_SERVER['PHP_SELF']);

    if (!empty($_SERVER['QUERY_STRING'])) {
        $redirectUrl .= '?' . $_SERVER['QUERY_STRING'];
    }

    header("Location: " . $redirectUrl);
    exit();
}

// --------------------------------------------------
// DASHBOARD COUNTS
// --------------------------------------------------

$counts = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'completed' => 0,
    'incomplete' => 0,
    'cancelled' => 0
];

$countResult = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM token_bookings
    GROUP BY status
");

if ($countResult) {
    while ($row = $countResult->fetch_assoc()) {

        $status = $row['status'];
        $total = (int)$row['total'];

        if (isset($counts[$status])) {
            $counts[$status] = $total;
        }

        $counts['total'] += $total;
    }
}

// --------------------------------------------------
// SEARCH AND STATUS FILTER
// --------------------------------------------------

$search = trim($_GET['search'] ?? '');
$filter = $_GET['status'] ?? 'all';

$allowedStatuses = [
    'all',
    'pending',
    'approved',
    'completed',
    'incomplete',
    'cancelled'
];

if (!in_array($filter, $allowedStatuses, true)) {
    $filter = 'all';
}

// --------------------------------------------------
// FETCH BOOKINGS
// --------------------------------------------------
// slot_booked = real number of NON-cancelled bookings in the same
// date + start time + end time slot. It is counted independently of the
// search/status filter, so the capacity is always accurate.

$sql = "
SELECT
    tb.id,
    tb.token_number,
    tb.status,
    tb.created_at,
    tb.other_sub_service,

    u.name,
    u.email,

    s.service_name,
    ss.sub_service_name,

    COALESCE(tb.booking_date, ts.slot_date) AS booking_date,
    COALESCE(tb.start_time, ts.start_time) AS start_time,
    COALESCE(tb.end_time, ts.end_time) AS end_time,

    (
        SELECT COUNT(*)
        FROM token_bookings b
        LEFT JOIN token_slots old_ts
            ON b.slot_id = old_ts.id
        WHERE
            COALESCE(b.booking_date, old_ts.slot_date) <=>
            COALESCE(tb.booking_date, ts.slot_date)

            AND COALESCE(b.start_time, old_ts.start_time) <=>
            COALESCE(tb.start_time, ts.start_time)

            AND COALESCE(b.end_time, old_ts.end_time) <=>
            COALESCE(tb.end_time, ts.end_time)

            AND b.status != 'cancelled'
    ) AS slot_booked

FROM token_bookings tb

JOIN users u
    ON tb.student_id = u.id

JOIN services s
    ON tb.service_id = s.id

LEFT JOIN service_subcategories ss
    ON tb.sub_service_id = ss.id

LEFT JOIN token_slots ts
    ON tb.slot_id = ts.id

WHERE 1 = 1
";

$params = [];
$types = "";

if ($filter !== 'all') {
    $sql .= " AND tb.status = ?";
    $types .= "s";
    $params[] = $filter;
}

if ($search !== '') {

    $sql .= "
        AND (
            u.name LIKE ?
            OR u.email LIKE ?
            OR s.service_name LIKE ?
            OR CAST(tb.token_number AS CHAR) LIKE ?
            OR tb.other_sub_service LIKE ?
        )
    ";

    $searchTerm = "%" . $search . "%";

    $types .= "sssss";

    for ($i = 0; $i < 5; $i++) {
        $params[] = $searchTerm;
    }
}

$sql .= "
ORDER BY
    CASE tb.status
        WHEN 'pending' THEN 1
        WHEN 'approved' THEN 2
        WHEN 'completed' THEN 3
        WHEN 'incomplete' THEN 4
        WHEN 'cancelled' THEN 5
        ELSE 6
    END,

    COALESCE(tb.booking_date, ts.slot_date) DESC,
    tb.id DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Fetch ONCE into an array; reused by the slot cards and the table
$bookings = [];

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();

// --------------------------------------------------
// GROUP BOOKINGS BY DATE + TIME SLOT
// --------------------------------------------------

$slotGroups = [];

foreach ($bookings as $row) {

    $bookingDate = $row['booking_date'] ?? '';
    $startTime = $row['start_time'] ?? '';
    $endTime = $row['end_time'] ?? '';

    // Same date + start + end time = same slot
    $slotKey = $bookingDate . '|' . $startTime . '|' . $endTime;

    if (!isset($slotGroups[$slotKey])) {

        $slotGroups[$slotKey] = [
            'date' => $bookingDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'booked_count' => (int)$row['slot_booked'], // non-cancelled, unfiltered
            'services' => [],                          // label => number of bookings
            'students' => []
        ];
    }

    $slotGroups[$slotKey]['students'][] = $row;

    // Cancelled bookings are not listed as booked services
    if (strtolower($row['status'] ?? '') !== 'cancelled') {

        $label = serviceLabel($row);

        if (!isset($slotGroups[$slotKey]['services'][$label])) {
            $slotGroups[$slotKey]['services'][$label] = 0;
        }

        $slotGroups[$slotKey]['services'][$label]++;
    }
}

// Sort slots: newest date first, earliest time first inside a date
uasort($slotGroups, function ($a, $b) {

    if ($a['date'] !== $b['date']) {
        return strcmp($b['date'], $a['date']);
    }

    return strcmp($a['start_time'], $b['start_time']);
});

$recordCount = count($bookings);
$slotCount = count($slotGroups);
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Manage Tokens | Smart Campus</title>

<style>

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
    font-family: 'Segoe UI', Arial, sans-serif;
}

body {
    background: var(--page);
    color: var(--ink);
}

/* Navbar */

.navbar {
    background: var(--surface);
    padding: 17px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--shadow);
    border-bottom: 1px solid var(--line);
}

.logo {
    color: var(--primary);
    font-size: 22px;
    font-weight: 800;
}

.nav-links {
    display: flex;
    gap: 18px;
    align-items: center;
}

.nav-links a {
    color: var(--muted);
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
}

.logout {
    background: #EADDD9;
    color: #8A4C40 !important;
    padding: 9px 14px;
    border-radius: 8px;
}

/* Main container */

.container {
    max-width: 1350px;
    margin: 35px auto;
    padding: 0 20px;
}

.heading {
    margin-bottom: 25px;
}

.heading h1 {
    font-size: 28px;
    color: var(--primary);
}

.heading p {
    color: var(--muted);
    margin-top: 6px;
    font-size: 14px;
}

/* Messages */

.message {
    padding: 13px 17px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: 600;
}

.success {
    background: #E3E9E4;
    color: #3F5B49;
}

.error {
    background: #EADDD9;
    color: #8A4C40;
}

/* Statistics */

.stats {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 15px;
    margin-bottom: 27px;
}

.stat-card {
    background: var(--surface);
    padding: 19px;
    border-radius: 13px;
    box-shadow: var(--shadow);
    border: 1px solid var(--line);
}

.stat-card p {
    color: var(--muted);
    font-size: 13px;
    font-weight: 600;
}

.stat-card h2 {
    margin-top: 9px;
    font-size: 27px;
    color: var(--primary);
}

.stat-card.pending h2 {
    color: #80632C;
}

.stat-card.approved h2 {
    color: #526B7B;
}

.stat-card.completed h2 {
    color: #3F5B49;
}

.stat-card.incomplete h2 {
    color: #8A4C40;
}

.stat-card.cancelled h2 {
    color: #8A4C40;
}

/* Search */

.toolbar {
    background: var(--surface);
    padding: 17px;
    border: 1px solid var(--line);
    border-radius: 13px;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
    box-shadow: var(--shadow);
}

.toolbar form {
    display: flex;
    gap: 10px;
    width: 100%;
}

.toolbar input,
.toolbar select {
    padding: 11px 13px;
    border: 1px solid var(--line);
    border-radius: 8px;
    outline: none;
    background: #F8F6F0;
    color: var(--ink);
    font-size: 13px;
}

.toolbar input {
    flex: 1;
}

.toolbar input:focus,
.toolbar select:focus {
    border-color: var(--primary);
}

.toolbar button {
    background: var(--primary);
    color: var(--surface);
    border: none;
    border-radius: 8px;
    padding: 10px 18px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.2s;
}

.toolbar button:hover {
    background: var(--primary-dark);
}

/* Section titles */

.section-title {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 10px;
    margin: 6px 0 15px;
}

.section-title h2 {
    font-size: 20px;
    color: var(--primary);
}

.section-title span {
    color: var(--muted);
    font-size: 12px;
}

/* Slot cards */

.slot-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 18px;
    align-items: start;
    margin-bottom: 35px;
}

.slot-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: var(--shadow);
    padding: 20px;
}

/* When students are shown, the card takes the full row width */
.slot-card.open {
    grid-column: 1 / -1;
}

.slot-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.slot-date {
    font-size: 16px;
    font-weight: 800;
    color: var(--primary);
}

.slot-time {
    margin-top: 5px;
    font-size: 13px;
    font-weight: 600;
    color: var(--muted);
}

.slot-count {
    text-align: right;
    flex-shrink: 0;
}

.slot-count strong {
    display: block;
    font-size: 24px;
    color: #52617A;
    line-height: 1;
}

.slot-count small {
    display: block;
    margin-top: 5px;
    font-size: 11px;
    color: var(--muted);
    font-weight: 600;
}

.slot-count.full strong {
    color: #8A4C40;
}

.slot-services {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--line);
}

.slot-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--muted);
    font-weight: 700;
    margin-bottom: 8px;
}

.service-chip {
    display: inline-block;
    background: #E5E2D9;
    color: var(--primary);
    border-radius: 20px;
    padding: 6px 11px;
    font-size: 12px;
    font-weight: 700;
    margin: 0 6px 6px 0;
    word-break: break-word;
}

.service-chip em {
    font-style: normal;
    color: var(--muted);
    margin-left: 4px;
}

/* Show more / Show less for services */

.service-extra {
    display: none;
}

.slot-services.expanded .service-extra {
    display: inline-block;
}

.more-btn {
    background: none;
    border: none;
    color: #52617A;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    padding: 6px 2px;
    text-decoration: underline;
}

.more-btn:hover {
    color: var(--primary);
}

.slot-empty {
    color: var(--muted);
    font-size: 12px;
}

.toggle-btn {
    width: 100%;
    margin-top: 12px;
    background: var(--primary);
    color: var(--surface);
    border: none;
    border-radius: 8px;
    padding: 11px 14px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.2s;
}

.toggle-btn:hover {
    background: var(--primary-dark);
}

/* Students list (expandable) - 2 students per row */

.student-list {
    display: none;
    margin-top: 14px;
}

.slot-card.open .student-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.student-item {
    background: #F8F6F0;
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 12px 14px;
}

.student-row-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
}

.student-info {
    min-width: 0;
}

.student-info .student-email {
    word-break: break-all;
}

.student-detail {
    margin-top: 9px;
    font-size: 12px;
    color: var(--ink);
    display: grid;
    gap: 4px;
}

.student-detail div {
    word-break: break-word;
}

.student-detail span {
    color: var(--muted);
    font-weight: 600;
}

/* Table */

.table-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

.table-header {
    padding: 19px 21px;
    border-bottom: 1px solid var(--line);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h3 {
    color: var(--primary);
    font-size: 17px;
}

.table-header span {
    color: var(--muted);
    font-size: 12px;
}

.table-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1100px;
    border-collapse: collapse;
    background: var(--surface);
    color: var(--ink);
}

th {
    background: var(--primary);
    color: var(--surface);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 15px 13px;
    text-align: left;
}

td {
    padding: 16px 13px;
    border-bottom: 1px solid var(--line);
    font-size: 13px;
    vertical-align: middle;
}

tr:hover td {
    background: #EDEAE2;
}

.student-name {
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 4px;
}

.student-email {
    color: var(--muted);
    font-size: 11px;
}

.token-number {
    background: #E5E2D9;
    color: var(--primary);
    display: inline-block;
    padding: 7px 11px;
    border-radius: 7px;
    font-weight: 800;
}

.service-name {
    font-weight: 700;
    color: var(--primary);
}

.sub-name {
    font-size: 11px;
    color: var(--muted);
    margin-top: 5px;
}

.other-service {
    color: #80632C;
    font-weight: 600;
    font-size: 12px;
    margin-top: 5px;
}

.slot-capacity {
    font-weight: 700;
    color: #52617A;
}

/* Status styles */

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    text-transform: capitalize;
    white-space: nowrap;
}

.status.pending {
    background: #F1E7D2;
    color: #80632C;
}

.status.approved {
    background: #E1E7E9;
    color: #526B7B;
}

.status.completed {
    background: #E3E9E4;
    color: #3F5B49;
}

.status.incomplete {
    background: #EADDD9;
    color: #8A4C40;
}

.status.cancelled {
    background: #EADDD9;
    color: #8A4C40;
}

/* Action buttons */

.actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.action-btn {
    border: none;
    padding: 8px 10px;
    border-radius: 7px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: 0.2s;
}

.approve-btn {
    background: #E3E9E4;
    color: #3F5B49;
}

.approve-btn:hover {
    background: #D3DED5;
}

.cancel-btn {
    background: #EADDD9;
    color: #8A4C40;
}

.cancel-btn:hover {
    background: #E0CEC9;
}

.complete-btn {
    background: #E3E9E4;
    color: #3F5B49;
}

.complete-btn:hover {
    background: #D3DED5;
}

.incomplete-btn {
    background: #EADDD9;
    color: #8A4C40;
}

.incomplete-btn:hover {
    background: #E0CEC9;
}

.no-action {
    color: var(--muted);
    font-size: 12px;
}

/* Empty state */

.empty {
    text-align: center;
    padding: 45px 15px;
    color: var(--muted);
}

.slot-grid-empty {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: var(--shadow);
    margin-bottom: 35px;
}

/* Footer */

.footer {
    text-align: center;
    padding: 25px;
    color: var(--muted);
    font-size: 12px;
}

/* Responsive */

@media(max-width: 1100px) {
    .stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Small screens: students shown one per row */
@media(max-width: 700px) {
    .slot-card.open .student-list {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 600px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

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
        margin-top: 25px;
    }

    .heading h1 {
        font-size: 23px;
    }

    .toolbar form {
        flex-wrap: wrap;
    }

    .toolbar input {
        flex-basis: 100%;
    }

    .slot-grid {
        grid-template-columns: 1fr;
    }

    .slot-card {
        padding: 16px;
    }

    .section-title {
        flex-direction: column;
        gap: 4px;
    }
}

</style>

</head>

<body>

<div class="navbar">

    <div class="logo">
        🎓 Smart Campus
    </div>

    <div class="nav-links">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="manage_services.php">Services</a>
        <a href="logout.php" class="logout">Logout</a>
    </div>

</div>

<div class="container">

    <div class="heading">
        <h1>Manage Token Requests</h1>
        <p>
            Review student bookings and manage token approvals.
            Mark services as completed or incomplete after the slot ends.
        </p>
    </div>

    <?php if ($message): ?>

        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <!-- Statistics -->

    <div class="stats">

        <div class="stat-card">
            <p>Total Tokens</p>
            <h2><?php echo $counts['total']; ?></h2>
        </div>

        <div class="stat-card pending">
            <p>Pending</p>
            <h2><?php echo $counts['pending']; ?></h2>
        </div>

        <div class="stat-card approved">
            <p>Approved</p>
            <h2><?php echo $counts['approved']; ?></h2>
        </div>

        <div class="stat-card completed">
            <p>Completed</p>
            <h2><?php echo $counts['completed']; ?></h2>
        </div>

        <div class="stat-card incomplete">
            <p>Incomplete</p>
            <h2><?php echo $counts['incomplete']; ?></h2>
        </div>

        <div class="stat-card cancelled">
            <p>Cancelled</p>
            <h2><?php echo $counts['cancelled']; ?></h2>
        </div>

    </div>

    <!-- Search and Filter -->

    <div class="toolbar">

        <form method="GET">

            <input
                type="text"
                name="search"
                placeholder="Search student, email, service or token..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <select name="status">

                <option value="all"
                    <?php if ($filter === 'all') echo 'selected'; ?>>
                    All Status
                </option>

                <option value="pending"
                    <?php if ($filter === 'pending') echo 'selected'; ?>>
                    Pending
                </option>

                <option value="approved"
                    <?php if ($filter === 'approved') echo 'selected'; ?>>
                    Approved
                </option>

                <option value="completed"
                    <?php if ($filter === 'completed') echo 'selected'; ?>>
                    Completed
                </option>

                <option value="incomplete"
                    <?php if ($filter === 'incomplete') echo 'selected'; ?>>
                    Incomplete
                </option>

                <option value="cancelled"
                    <?php if ($filter === 'cancelled') echo 'selected'; ?>>
                    Cancelled
                </option>

            </select>

            <button type="submit">
                Search
            </button>

        </form>

    </div>

    <!-- Slot-wise Booking Cards -->

    <div class="section-title">
        <h2>Slot-wise Bookings</h2>
        <span><?php echo $slotCount; ?> slots found</span>
    </div>

    <?php if ($slotCount > 0): ?>

        <div class="slot-grid">

            <?php $slotIndex = 0; ?>

            <?php foreach ($slotGroups as $slot): ?>

                <?php
                    $slotIndex++;
                    $panelId = 'students-' . $slotIndex;
                    $isFull = $slot['booked_count'] >= SLOT_CAPACITY;
                ?>

                <div class="slot-card">

                    <div class="slot-top">

                        <div>
                            <div class="slot-date">
                                📅 <?php echo htmlspecialchars(formatDate($slot['date'])); ?>
                            </div>

                            <div class="slot-time">
                                🕒
                                <?php echo htmlspecialchars(formatTime($slot['start_time'])); ?>
                                -
                                <?php echo htmlspecialchars(formatTime($slot['end_time'])); ?>
                            </div>
                        </div>

                        <div class="slot-count <?php echo $isFull ? 'full' : ''; ?>">
                            <strong>
                                <?php echo (int)$slot['booked_count']; ?> / <?php echo SLOT_CAPACITY; ?>
                            </strong>
                            <small><?php echo $isFull ? 'Slot Full' : 'Booked'; ?></small>
                        </div>

                    </div>

                    <div class="slot-services">

                        <div class="slot-label">Services Booked</div>

                        <?php if (!empty($slot['services'])): ?>

                            <?php
                                $serviceIndex = 0;
                                $extraServices = count($slot['services']) - 1;
                            ?>

                            <?php foreach ($slot['services'] as $label => $serviceTotal): ?>

                                <span class="service-chip <?php echo $serviceIndex > 0 ? 'service-extra' : ''; ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                    <?php if ($serviceTotal > 1): ?>
                                        <em>&times;<?php echo (int)$serviceTotal; ?></em>
                                    <?php endif; ?>
                                </span>

                                <?php $serviceIndex++; ?>

                            <?php endforeach; ?>

                            <?php if ($extraServices > 0): ?>
                                <div>
                                    <button
                                        type="button"
                                        class="more-btn"
                                        data-more="Show more (+<?php echo (int)$extraServices; ?>)"
                                        data-less="Show less"
                                    >
                                        Show more (+<?php echo (int)$extraServices; ?>)
                                    </button>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>

                            <div class="slot-empty">No active bookings</div>

                        <?php endif; ?>

                    </div>

                    <button
                        type="button"
                        class="toggle-btn"
                        data-target="<?php echo htmlspecialchars($panelId); ?>"
                        aria-expanded="false"
                        aria-controls="<?php echo htmlspecialchars($panelId); ?>"
                    >
                        View Students
                    </button>

                    <div class="student-list" id="<?php echo htmlspecialchars($panelId); ?>">

                        <?php foreach ($slot['students'] as $student): ?>

                            <?php $studentStatus = strtolower($student['status'] ?? 'pending'); ?>

                            <div class="student-item">

                                <div class="student-row-top">

                                    <div class="student-info">
                                        <div class="student-name">
                                            <?php echo htmlspecialchars($student['name']); ?>
                                        </div>
                                        <div class="student-email">
                                            <?php echo htmlspecialchars($student['email']); ?>
                                        </div>
                                    </div>

                                    <span class="status <?php echo htmlspecialchars($studentStatus); ?>">
                                        <?php echo htmlspecialchars(ucfirst($studentStatus)); ?>
                                    </span>

                                </div>

                                <div class="student-detail">

                                    <div>
                                        <span>Token:</span>
                                        #<?php echo (int)$student['token_number']; ?>
                                    </div>

                                    <div>
                                        <span>Service:</span>
                                        <?php echo htmlspecialchars($student['service_name']); ?>
                                    </div>

                                    <?php if (!empty($student['sub_service_name'])): ?>
                                        <div>
                                            <span>Sub-service:</span>
                                            <?php echo htmlspecialchars($student['sub_service_name']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($student['other_sub_service'])): ?>
                                        <div>
                                            <span>Other:</span>
                                            <?php echo htmlspecialchars($student['other_sub_service']); ?>
                                        </div>
                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="slot-grid-empty">
            <div class="empty">
                <div style="font-size:40px;">
                    📭
                </div>

                <h3 style="margin:12px 0;">
                    No Slots Found
                </h3>

                <p>
                    Try changing your search or status filter.
                </p>
            </div>
        </div>

    <?php endif; ?>

    <!-- Booking Table -->

    <div class="table-card">

        <div class="table-header">

            <h3>All Token Bookings</h3>

            <span>
                <?php echo $recordCount; ?> records found
            </span>

        </div>

        <div class="table-scroll">

        <?php if ($recordCount > 0): ?>

            <table>

                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Student</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Time Slot</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($bookings as $row): ?>

                    <?php
                        $rowStatus = strtolower($row['status'] ?? 'pending');

                        $slotFinished = slotHasEnded(
                            $row['booking_date'] ?? '',
                            $row['end_time'] ?? ''
                        );
                    ?>

                    <tr>

                        <!-- Token -->

                        <td>
                            <span class="token-number">
                                #<?php echo (int)$row['token_number']; ?>
                            </span>
                        </td>

                        <!-- Student -->

                        <td>

                            <div class="student-name">
                                <?php echo htmlspecialchars($row['name']); ?>
                            </div>

                            <div class="student-email">
                                <?php echo htmlspecialchars($row['email']); ?>
                            </div>

                        </td>

                        <!-- Service -->

                        <td>

                            <div class="service-name">
                                <?php echo htmlspecialchars($row['service_name']); ?>
                            </div>

                            <?php if (!empty($row['other_sub_service'])): ?>

                                <div class="other-service">
                                    Other:
                                    <?php echo htmlspecialchars($row['other_sub_service']); ?>
                                </div>

                            <?php elseif (!empty($row['sub_service_name'])): ?>

                                <div class="sub-name">
                                    <?php echo htmlspecialchars($row['sub_service_name']); ?>
                                </div>

                            <?php endif; ?>

                        </td>

                        <!-- Date -->

                        <td>
                            <?php echo htmlspecialchars(formatDate($row['booking_date'])); ?>
                        </td>

                        <!-- Time Slot -->

                        <td>

                            <?php
                                echo htmlspecialchars(formatTime($row['start_time']));
                                echo " - ";
                                echo htmlspecialchars(formatTime($row['end_time']));
                            ?>

                        </td>

                        <!-- Capacity -->

                        <td>

                            <span class="slot-capacity">
                                <?php echo (int)$row['slot_booked']; ?>/<?php echo SLOT_CAPACITY; ?>
                            </span>

                        </td>

                        <!-- Status -->

                        <td>

                            <span class="status <?php echo htmlspecialchars($rowStatus); ?>">
                                <?php echo htmlspecialchars(ucfirst($rowStatus)); ?>
                            </span>

                        </td>

                        <!-- Actions -->

                        <td>

                            <?php if ($rowStatus === 'pending'): ?>

                                <div class="actions">

                                    <!-- Approve -->

                                    <form method="POST"
                                          onsubmit="return confirm('Approve this token?');">

                                        <input
                                            type="hidden"
                                            name="booking_id"
                                            value="<?php echo (int)$row['id']; ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="action"
                                            value="approve"
                                            class="action-btn approve-btn"
                                        >
                                            ✓ Approve
                                        </button>

                                    </form>

                                    <!-- Cancel -->

                                    <form method="POST"
                                          onsubmit="return confirm('Cancel this token?');">

                                        <input
                                            type="hidden"
                                            name="booking_id"
                                            value="<?php echo (int)$row['id']; ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="action"
                                            value="cancel"
                                            class="action-btn cancel-btn"
                                        >
                                            ✕ Cancel
                                        </button>

                                    </form>

                                </div>

                            <?php elseif ($rowStatus === 'approved'): ?>

                                <?php if ($slotFinished): ?>

                                    <div class="actions">

                                        <!-- Complete -->

                                        <form method="POST"
                                              onsubmit="return confirm('Student has taken the service. Mark as Completed?');">

                                            <input
                                                type="hidden"
                                                name="booking_id"
                                                value="<?php echo (int)$row['id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="action"
                                                value="complete"
                                                class="action-btn complete-btn"
                                            >
                                                ✓ Complete
                                            </button>

                                        </form>

                                        <!-- Incomplete -->

                                        <form method="POST"
                                              onsubmit="return confirm('Student has not taken the service. Mark as Incomplete?');">

                                            <input
                                                type="hidden"
                                                name="booking_id"
                                                value="<?php echo (int)$row['id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="action"
                                                value="incomplete"
                                                class="action-btn incomplete-btn"
                                            >
                                                ✕ Incomplete
                                            </button>

                                        </form>

                                    </div>

                                <?php else: ?>

                                    <span class="no-action">
                                        Waiting for slot end
                                    </span>

                                <?php endif; ?>

                            <?php elseif ($rowStatus === 'completed'): ?>

                                <span class="no-action">
                                    ✓ Completed
                                </span>

                            <?php elseif ($rowStatus === 'incomplete'): ?>

                                <span class="no-action">
                                    ✕ Incomplete
                                </span>

                            <?php else: ?>

                                <span class="no-action">
                                    No action
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="empty">

                <div style="font-size:40px;">
                    📭
                </div>

                <h3 style="margin:12px 0;">
                    No Token Requests Found
                </h3>

                <p>
                    Try changing your search or status filter.
                </p>

            </div>

        <?php endif; ?>

        </div>

    </div>

</div>

<div class="footer">
    Smart Campus Token Booking System © <?php echo date("Y"); ?>
</div>

<script>
// View Students / Hide Students toggle for each slot card
document.querySelectorAll('.toggle-btn').forEach(function (button) {

    button.addEventListener('click', function () {

        var card = button.closest('.slot-card');
        var isOpen = card.classList.toggle('open');

        button.textContent = isOpen ? 'Hide Students' : 'View Students';
        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

});

// Show more / Show less for services in each slot card
document.querySelectorAll('.more-btn').forEach(function (button) {

    button.addEventListener('click', function () {

        var box = button.closest('.slot-services');
        var expanded = box.classList.toggle('expanded');

        button.textContent = expanded
            ? button.getAttribute('data-less')
            : button.getAttribute('data-more');
    });

});
</script>

</body>
</html>

<?php
$conn->close();
?>
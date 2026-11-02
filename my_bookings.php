<?php
session_start();
require_once "config/database.php";

// Student login check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = (int) $_SESSION['user_id'];

$message = "";
$message_type = "";

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Cancel booking
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['cancel_booking'])) {

    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        $message = "Invalid request. Please refresh the page and try again.";
        $message_type = "error";

    } elseif (!$booking_id || $booking_id <= 0) {
        $message = "Invalid booking selected.";
        $message_type = "error";

    } else {

        // Student can cancel only their own pending/approved booking
        $stmt = $conn->prepare("
            UPDATE token_bookings
            SET status = 'cancelled'
            WHERE id = ?
              AND student_id = ?
              AND status IN ('pending', 'approved')
        ");

        $stmt->bind_param("ii", $booking_id, $student_id);

        if ($stmt->execute()) {

            if ($stmt->affected_rows > 0) {
                $message = "Booking successfully cancelled!";
                $message_type = "success";
            } else {
                $message = "Booking could not be cancelled. It may already be cancelled or completed.";
                $message_type = "error";
            }

        } else {
            $message = "Something went wrong. Please try again.";
            $message_type = "error";
        }

        $stmt->close();
    }
}

// Fetch student's bookings with service names
$stmt = $conn->prepare("
    SELECT
        tb.id,
        tb.student_id,
        tb.service_id,
        tb.sub_service_id,
        tb.other_sub_service,
        tb.slot_id,
        tb.token_number,
        tb.status,
        tb.created_at,
        tb.booking_date,
        tb.start_time,
        tb.end_time,

        s.service_name,
        sc.sub_service_name

    FROM token_bookings AS tb

    LEFT JOIN services AS s
        ON tb.service_id = s.id

    LEFT JOIN service_subcategories AS sc
        ON tb.sub_service_id = sc.id

    WHERE tb.student_id = ?

    ORDER BY
        tb.booking_date DESC,
        tb.start_time DESC,
        tb.created_at DESC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Bookings | Smart Campus</title>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>

        :root {
            --charcoal: #292E32;
            --charcoal-light: #3B4146;
            --ivory: #F3F0E8;
            --ivory-light: #FBFAF6;
            --text: #292E32;
            --muted: #777A78;
            --border: #E2DED5;
            --white: #FFFFFF;

            --success-bg: #E7F1E8;
            --success-text: #3F704A;

            --warning-bg: #F8EED5;
            --warning-text: #99721F;

            --danger-bg: #F8E4E1;
            --danger-text: #A7463C;

            --info-bg: #E6EAE8;
            --info-text: #4F625B;

            --shadow: 0 8px 28px rgba(41, 46, 50, 0.07);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--ivory);
            color: var(--charcoal);
        }

        /* NAVBAR */

        .navbar {
            background: var(--ivory-light);
            padding: 17px 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 3px 15px rgba(41, 46, 50, 0.06);
            border-bottom: 1px solid var(--border);
        }

        .logo {
            font-family: 'Outfit', sans-serif;
            font-size: 21px;
            font-weight: 800;
            color: var(--charcoal);
        }

        .back-link {
            color: var(--charcoal);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            background: var(--ivory);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
            transition: 0.25s;
        }

        .back-link:hover {
            background: var(--charcoal);
            color: var(--ivory);
        }

        /* CONTAINER */

        .container {
            max-width: 1100px;
            margin: 35px auto;
            padding: 0 20px;
        }

        /* HEADING */

        .heading {
            margin-bottom: 25px;
        }

        .heading .tag {
            color: var(--charcoal-light);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 9px;
        }

        .heading h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 31px;
            color: var(--charcoal);
            margin-bottom: 8px;
        }

        .heading p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /* MESSAGE */

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.5;
            font-weight: 600;
        }

        .success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1px solid #D0E3D2;
        }

        .error {
            background: var(--danger-bg);
            color: var(--danger-text);
            border: 1px solid #EBD0CC;
        }

        /* BOOKING CARD */

        .booking-card {
            background: var(--ivory-light);
            padding: 25px;
            border-radius: 19px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 20px;
            transition: 0.3s;
        }

        .booking-card:hover {
            border-color: #C7C2B8;
            box-shadow: 0 10px 30px rgba(41, 46, 50, 0.10);
            transform: translateY(-2px);
        }

        .booking-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 17px;
            border-bottom: 1px solid var(--border);
        }

        .booking-top h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 21px;
            color: var(--charcoal);
        }

        /* STATUS */

        .status {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            text-transform: capitalize;
        }

        .status-pending {
            background: var(--warning-bg);
            color: var(--warning-text);
        }

        .status-approved {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .status-completed {
            background: var(--info-bg);
            color: var(--info-text);
        }

        .status-incomplete {
            background: var(--danger-bg);
            color: var(--danger-text);
        }

        .status-cancelled {
            background: var(--danger-bg);
            color: var(--danger-text);
        }

        /* BOOKING DETAILS */

        .booking-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .detail-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 7px;
        }

        .detail-value {
            color: var(--charcoal);
            font-size: 14px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        /* CANCEL BUTTON */

        .cancel-form {
            margin-top: 22px;
        }

        .cancel-btn {
            padding: 12px 18px;
            background: var(--charcoal);
            border: 1px solid var(--charcoal);
            border-radius: 9px;
            color: var(--ivory);
            font-family: inherit;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.25s;
        }

        .cancel-btn:hover {
            background: var(--charcoal-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(41, 46, 50, 0.15);
        }

        /* EMPTY MESSAGE */

        .empty-message {
            background: var(--ivory-light);
            text-align: center;
            padding: 45px 20px;
            border-radius: 19px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .empty-message h2 {
            color: var(--charcoal);
            margin-bottom: 10px;
            font-family: 'Outfit', sans-serif;
        }

        .empty-message p {
            color: var(--muted);
            font-size: 14px;
        }

        .book-now-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 20px;
            background: var(--charcoal);
            color: var(--ivory);
            text-decoration: none;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 800;
            transition: 0.25s;
        }

        .book-now-btn:hover {
            background: var(--charcoal-light);
            transform: translateY(-2px);
            box-shadow: 0 7px 18px rgba(41, 46, 50, 0.15);
        }

        /* FOOTER */

        footer {
            text-align: center;
            color: var(--muted);
            padding: 25px 10px;
            font-size: 12px;
        }

        /* TABLET */

        @media (max-width: 750px) {

            .booking-details {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        /* MOBILE */

        @media (max-width: 550px) {

            .navbar {
                padding: 15px 18px;
            }

            .logo {
                font-size: 18px;
            }

            .back-link {
                font-size: 11px;
                padding: 9px 10px;
            }

            .container {
                margin: 25px auto;
                padding: 0 14px;
            }

            .heading h1 {
                font-size: 26px;
            }

            .booking-card {
                padding: 20px 17px;
            }

            .booking-details {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .booking-top h2 {
                font-size: 19px;
            }

        }

    </style>

</head>

<body>

<nav class="navbar">

    <div class="logo">
        🎓 Smart Campus
    </div>

    <a href="student_dashboard.php" class="back-link">
        ← Back to Dashboard
    </a>

</nav>

<div class="container">

    <div class="heading">

        <div class="tag">STUDENT PORTAL</div>

        <h1>My Bookings 🎟️</h1>

        <p>
            View your token bookings, check booking status,
            and cancel pending or approved bookings.
        </p>

    </div>

    <?php if ($message !== ""): ?>

        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>

        <?php while ($booking = $result->fetch_assoc()): ?>

            <?php
                $status = strtolower($booking['status'] ?? 'pending');

                $allowed_statuses = [
                    'pending',
                    'approved',
                    'completed',
                    'incomplete',
                    'cancelled'
                ];

                if (!in_array($status, $allowed_statuses, true)) {
                    $status = 'pending';
                }

                $service_name = $booking['service_name']
                    ?? 'Service not found';

                if (!empty($booking['other_sub_service'])) {
                    $sub_service_name = $booking['other_sub_service'];

                } elseif (!empty($booking['sub_service_name'])) {
                    $sub_service_name = $booking['sub_service_name'];

                } else {
                    $sub_service_name = 'Not specified';
                }

                $booking_date = !empty($booking['booking_date'])
                    ? date("d M Y", strtotime($booking['booking_date']))
                    : "Not specified";

                $start_time = !empty($booking['start_time'])
                    ? date("h:i A", strtotime($booking['start_time']))
                    : "";

                $end_time = !empty($booking['end_time'])
                    ? date("h:i A", strtotime($booking['end_time']))
                    : "";

                $time_label = trim($start_time . " - " . $end_time);
            ?>

            <div class="booking-card">

                <div class="booking-top">

                    <h2>
                        🎟️ Token #
                        <?php
                            echo htmlspecialchars(
                                (string)($booking['token_number'] ?? 'N/A')
                            );
                        ?>
                    </h2>

                    <span class="status status-<?php echo htmlspecialchars($status); ?>">
                        <?php echo htmlspecialchars(ucfirst($status)); ?>
                    </span>

                </div>

                <div class="booking-details">

                    <div>
                        <div class="detail-label">Booking ID</div>
                        <div class="detail-value">
                            <?php echo (int)$booking['id']; ?>
                        </div>
                    </div>

                    <div>
                        <div class="detail-label">Main Service</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($service_name); ?>
                        </div>
                    </div>

                    <div>
                        <div class="detail-label">Sub Service</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($sub_service_name); ?>
                        </div>
                    </div>

                    <div>
                        <div class="detail-label">Booking Date</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($booking_date); ?>
                        </div>
                    </div>

                    <div>
                        <div class="detail-label">Time Slot</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($time_label ?: 'Not specified'); ?>
                        </div>
                    </div>

                    <div>
                        <div class="detail-label">Booked On</div>
                        <div class="detail-value">
                            <?php
                                echo !empty($booking['created_at'])
                                    ? htmlspecialchars(
                                        date(
                                            "d M Y, h:i A",
                                            strtotime($booking['created_at'])
                                        )
                                    )
                                    : "Not specified";
                            ?>
                        </div>
                    </div>

                </div>

                <?php if (in_array($status, ['pending', 'approved'], true)): ?>

                    <form method="POST"
                          class="cancel-form"
                          onsubmit="return confirm('Are you sure you want to cancel this booking?');">

                        <input type="hidden"
                               name="booking_id"
                               value="<?php echo (int)$booking['id']; ?>">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <button type="submit"
                                name="cancel_booking"
                                class="cancel-btn">
                            Cancel Booking
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty-message">

            <h2>No Bookings Found</h2>

            <p>
                You have not booked any campus service yet.
            </p>

            <a href="book_token.php" class="book-now-btn">
                Book a Token →
            </a>

        </div>

    <?php endif; ?>

</div>

<footer>
    © <?php echo date('Y'); ?> Smart Campus Token Booking System.
</footer>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
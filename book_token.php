
<?php
session_start();
include 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

$student_id = (int)$_SESSION['user_id'];

// Fixed college timings: database format is 24-hour TIME
$slots = [
    "09:00" => ["end" => "10:00", "label" => "09:00 AM - 10:00 AM"],
    "10:00" => ["end" => "11:00", "label" => "10:00 AM - 11:00 AM"],
    "11:00" => ["end" => "12:00", "label" => "11:00 AM - 12:00 PM"],
    "12:00" => ["end" => "13:00", "label" => "12:00 PM - 01:00 PM"],
    "13:00" => ["end" => "14:00", "label" => "01:00 PM - 02:00 PM"],
    "14:00" => ["end" => "15:00", "label" => "02:00 PM - 03:00 PM"],
    "15:00" => ["end" => "16:00", "label" => "03:00 PM - 04:00 PM"],
    "16:00" => ["end" => "17:00", "label" => "04:00 PM - 05:00 PM"]
];

$selected_service = "";
$selected_sub_service = "";
$selected_date = "";
$selected_time = "";

// BOOK TOKEN
if (isset($_POST['book_token'])) {

    $service_id = intval($_POST['service_id'] ?? 0);
    $sub_service_value = $_POST['sub_service_id'] ?? '';
    $booking_date = $_POST['booking_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $other_sub_service = trim($_POST['other_sub_service'] ?? '');

    $selected_service = (string)$service_id;
    $selected_sub_service = $sub_service_value;
    $selected_date = $booking_date;
    $selected_time = $start_time;

    $sub_service_id = null;

    // Validate main service
    $check = $conn->prepare(
        "SELECT id FROM services
         WHERE id = ? AND status = 'active'"
    );
    $check->bind_param("i", $service_id);
    $check->execute();

    $valid_service = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$valid_service) {
        $message = "Please select a valid Main Service.";
    }

    // Validate sub service
    if ($message == "") {

        if ($sub_service_value === "other") {

            if ($other_sub_service == "") {
                $message = "Please enter your required Sub Service.";
            } elseif (mb_strlen($other_sub_service) > 200) {
                $message = "Other Sub Service must be within 200 characters.";
            }

        } elseif (ctype_digit((string)$sub_service_value) && (int)$sub_service_value > 0) {

            $sub_service_id = (int)$sub_service_value;

            $check = $conn->prepare(
                "SELECT id FROM service_subcategories
                 WHERE id = ? AND service_id = ? AND status = 'active'"
            );
            $check->bind_param("ii", $sub_service_id, $service_id);
            $check->execute();

            $valid_sub = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$valid_sub) {
                $message = "Please select a valid Sub Service.";
            }

        } else {
            $message = "Please select a Sub Service.";
        }
    }

    // Validate date
    if ($message == "") {

        $date_object = DateTime::createFromFormat('!Y-m-d', $booking_date);

        if (!$date_object || $date_object->format('Y-m-d') !== $booking_date) {
            $message = "Please select a valid date.";
        } elseif ($booking_date < date('Y-m-d')) {
            $message = "Past dates are not allowed.";
        } elseif ((int)$date_object->format('N') >= 6) {
            $message = "College is closed on Saturday and Sunday. Please select a weekday.";
        }
    }

    // Validate time
    if ($message == "") {

        if (!isset($slots[$start_time])) {
            $message = "Please select a valid time slot.";
        } else {
            $end_time = $slots[$start_time]['end'];
        }
    }

    // Check duplicate booking
    if ($message == "") {

        $check = $conn->prepare(
            "SELECT id FROM token_bookings
             WHERE student_id = ?
             AND booking_date = ?
             AND start_time = ?
             AND status != 'cancelled'
             LIMIT 1"
        );

        $check->bind_param("iss", $student_id, $booking_date, $start_time);
        $check->execute();

        $already_booked = $check->get_result()->num_rows > 0;
        $check->close();

        if ($already_booked) {
            $message = "You have already booked a token for this time slot.";
        }
    }

    // Check slot capacity
    if ($message == "") {

        $check = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM token_bookings
             WHERE booking_date = ?
             AND start_time = ?
             AND status != 'cancelled'"
        );

        $check->bind_param("ss", $booking_date, $start_time);
        $check->execute();

        $count = $check->get_result()->fetch_assoc();
        $check->close();

        if ((int)$count['total'] >= 10) {
            $message = "Sorry! This time slot is full. Please select another slot.";
        }
    }

    // Generate token number and save booking
    if ($message == "") {

        $check = $conn->prepare(
            "SELECT COALESCE(MAX(token_number), 0) + 1 AS next_token
             FROM token_bookings
             WHERE booking_date = ?
             AND start_time = ?"
        );

        $check->bind_param("ss", $booking_date, $start_time);
        $check->execute();

        $token_data = $check->get_result()->fetch_assoc();
        $token_number = (int)$token_data['next_token'];
        $check->close();

        $other_value = ($sub_service_value === "other")
            ? $other_sub_service
            : null;

        $stmt = $conn->prepare(
            "INSERT INTO token_bookings
             (student_id, service_id, sub_service_id, slot_id,
              booking_date, start_time, end_time,
              token_number, other_sub_service, status)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, 'pending')"
        );

        $stmt->bind_param(
            "iiisssis",
            $student_id,
            $service_id,
            $sub_service_id,
            $booking_date,
            $start_time,
            $end_time,
            $token_number,
            $other_value
        );

        if ($stmt->execute()) {

            $message = "Token booked successfully! Your Token Number is #" . $token_number;
            $message_type = "success";

            $selected_service = "";
            $selected_sub_service = "";
            $selected_date = "";
            $selected_time = "";

        } else {
            $message = "Unable to book token. Please try again.";
        }

        $stmt->close();
    }

    if ($message_type != "success") {
        $message_type = "error";
    }
}

// Fetch main services
$services = $conn->query(
    "SELECT id, service_name
     FROM services
     WHERE status = 'active'
     ORDER BY service_name"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Book College Token | Smart Campus</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>

:root {
    --charcoal: #292E32;
    --charcoal-light: #3A4146;
    --ivory: #F3F0E8;
    --white: #FFFFFF;
    --border: #E2DED5;
    --muted: #77766F;
    --soft: #EAE6DD;
    --success: #47745D;
    --success-bg: #EDF5EF;
    --danger: #B54747;
    --danger-bg: #FFF1F0;
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
    min-height: 100vh;
}

/* NAVBAR */

.navbar {
    background: var(--charcoal);
    padding: 16px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 5px 20px rgba(41,46,50,0.10);
    position: sticky;
    top: 0;
    z-index: 100;
}

.logo {
    font-family: 'Outfit', sans-serif;
    font-size: 22px;
    font-weight: 800;
    color: var(--ivory);
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: -0.5px;
}

.logo-icon {
    width: 42px;
    height: 42px;
    background: var(--ivory);
    color: var(--charcoal);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.back-link {
    color: var(--charcoal);
    background: var(--ivory);
    text-decoration: none;
    font-weight: 800;
    font-size: 13px;
    padding: 11px 16px;
    border-radius: 10px;
    transition: 0.25s ease;
}

.back-link:hover {
    background: #E1DDD4;
    transform: translateY(-2px);
}

/* MAIN */

.container {
    max-width: 900px;
    margin: 38px auto;
    padding: 0 22px;
}

/* HEADING */

.heading {
    margin-bottom: 27px;
}

.heading .tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--muted);
    background: var(--soft);
    border: 1px solid var(--border);
    border-radius: 30px;
    padding: 8px 13px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.7px;
    margin-bottom: 14px;
}

.heading h1 {
    font-family: 'Outfit', sans-serif;
    font-size: clamp(27px, 4vw, 36px);
    color: var(--charcoal);
    margin-bottom: 10px;
    letter-spacing: -0.8px;
}

.heading p {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.8;
    max-width: 650px;
}

/* BOOKING CARD */

.booking-card {
    background: var(--white);
    padding: 35px;
    border-radius: 22px;
    box-shadow: 0 8px 30px rgba(41,46,50,0.055);
    border: 1px solid var(--border);
}

.form-group {
    margin-bottom: 25px;
}

label {
    display: block;
    color: var(--charcoal);
    font-weight: 700;
    font-size: 13px;
    margin-bottom: 10px;
}

select,
input {
    width: 100%;
    padding: 15px 16px;
    background: var(--ivory);
    border: 1px solid var(--border);
    border-radius: 12px;
    font-family: inherit;
    font-size: 14px;
    color: var(--charcoal);
    outline: none;
    transition: all 0.25s ease;
}

select:focus,
input:focus {
    border-color: var(--charcoal);
    background: var(--white);
    box-shadow: 0 0 0 4px rgba(41,46,50,0.07);
}

select:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

select option {
    color: var(--charcoal);
    background: var(--white);
}

input[type="date"] {
    cursor: pointer;
}

/* TIME SLOT */

.time-heading {
    margin-bottom: 14px;
}

.time-heading strong {
    color: var(--charcoal);
    font-size: 13px;
}

.time-note {
    color: var(--muted);
    font-size: 12px;
    margin-top: 7px;
    line-height: 1.7;
}

.time-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px;
}

.time-option {
    position: relative;
}

.time-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.time-option label {
    border: 1px solid var(--border);
    background: var(--ivory);
    border-radius: 12px;
    padding: 16px 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.25s ease;
    color: var(--charcoal);
    font-size: 13px;
    font-weight: 700;
    margin: 0;
    display: block;
}

.time-option label:hover {
    border-color: #A7A196;
    background: #ECE8DF;
}

.time-option input:checked + label {
    background: var(--charcoal);
    border: 1px solid var(--charcoal);
    color: var(--ivory);
    box-shadow: 0 5px 15px rgba(41,46,50,0.14);
}

.time-option input:focus-visible + label {
    outline: 3px solid #A7A196;
    outline-offset: 3px;
}

/* OTHER BOX */

#otherBox {
    display: none;
}

/* SUBMIT BUTTON */

.submit-btn {
    width: 100%;
    padding: 17px;
    background: var(--charcoal);
    border: 1px solid var(--charcoal);
    border-radius: 12px;
    color: var(--ivory);
    font-size: 15px;
    font-weight: 800;
    font-family: inherit;
    cursor: pointer;
    margin-top: 8px;
    transition: all 0.25s ease;
}

.submit-btn:hover {
    background: var(--charcoal-light);
    transform: translateY(-2px);
    box-shadow: 0 10px 22px rgba(41,46,50,0.16);
}

.submit-btn:active {
    transform: translateY(0);
}

/* MESSAGE */

.message {
    padding: 15px 17px;
    border-radius: 12px;
    margin-bottom: 23px;
    font-size: 14px;
    line-height: 1.6;
    font-weight: 600;
}

.success {
    background: var(--success-bg);
    color: var(--success);
    border: 1px solid #D3E6D8;
}

.error {
    background: var(--danger-bg);
    color: var(--danger);
    border: 1px solid #F0D0CC;
}

/* INFO BOX */

.info-box {
    background: var(--ivory);
    border: 1px solid var(--border);
    padding: 21px;
    border-radius: 15px;
    margin-top: 27px;
}

.info-box h3 {
    font-family: 'Outfit', sans-serif;
    font-size: 15px;
    color: var(--charcoal);
    margin-bottom: 12px;
}

.info-box p {
    font-size: 12px;
    color: var(--muted);
    line-height: 2;
}

/* FOOTER */

footer {
    text-align: center;
    color: #89867E;
    padding: 28px 15px;
    font-size: 12px;
    line-height: 1.7;
}

/* RESPONSIVE */

@media(max-width: 700px) {

    .navbar {
        padding: 14px 18px;
    }

    .logo {
        font-size: 18px;
    }

    .logo-icon {
        width: 38px;
        height: 38px;
        font-size: 20px;
    }

    .back-link {
        font-size: 12px;
        padding: 10px 12px;
    }

    .container {
        margin: 27px auto;
        padding: 0 15px;
    }

    .heading h1 {
        font-size: 28px;
    }

    .booking-card {
        padding: 25px 20px;
        border-radius: 18px;
    }

    .form-group {
        margin-bottom: 22px;
    }

    .time-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .time-option label {
        padding: 15px 12px;
    }

    .info-box {
        padding: 18px;
    }
}

@media(max-width: 400px) {

    .navbar {
        padding: 12px;
    }

    .logo {
        font-size: 16px;
        gap: 7px;
    }

    .back-link {
        font-size: 11px;
        padding: 9px 10px;
    }

    .container {
        padding: 0 12px;
    }

    .booking-card {
        padding: 21px 15px;
    }

    .heading h1 {
        font-size: 25px;
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

    <a href="student_dashboard.php" class="back-link">
        ← Back to Dashboard
    </a>

</nav>

<div class="container">

    <div class="heading">

        <div class="tag">
            ✦ STUDENT PORTAL
        </div>

        <h1>Book Your College Token 🎟️</h1>

        <p>
            Select your department, service, date and preferred time slot.
            Avoid waiting in long queues.
        </p>

    </div>

    <div class="booking-card">

        <?php if ($message != ""): ?>

            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>

        <form method="POST" id="bookingForm">

            <!-- MAIN SERVICE -->

            <div class="form-group">

                <label>🏢 Select Main Service</label>

                <select name="service_id" id="service_id" required>

                    <option value="">-- Select Main Service --</option>

                    <?php while ($service = $services->fetch_assoc()): ?>

                        <option value="<?php echo $service['id']; ?>"
                            <?php echo ($selected_service == $service['id']) ? 'selected' : ''; ?>>

                            <?php echo htmlspecialchars($service['service_name']); ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>

            <!-- SUB SERVICE -->

            <div class="form-group">

                <label>📌 Select Sub Service</label>

                <select name="sub_service_id" id="sub_service_id" required>

                    <option value="">-- First Select Main Service --</option>

                </select>

            </div>

            <!-- OTHER SUB SERVICE -->

            <div class="form-group" id="otherBox">

                <label>✏️ Enter Your Required Service</label>

                <input type="text"
                       name="other_sub_service"
                       id="other_sub_service"
                       maxlength="200"
                       placeholder="Write your required service here...">

            </div>

            <!-- DATE -->

            <div class="form-group">

                <label>📅 Select Booking Date</label>

                <input type="date"
                       name="booking_date"
                       id="booking_date"
                       min="<?php echo date('Y-m-d'); ?>"
                       value="<?php echo htmlspecialchars($selected_date); ?>"
                       required>

                <div class="time-note">
                    College working days: Monday to Friday.
                </div>

            </div>

            <!-- TIME SLOTS -->

            <div class="form-group">

                <div class="time-heading">

                    <strong>🕒 Select Preferred Time Slot</strong>

                    <div class="time-note">
                        Time is displayed in 12-hour format (AM / PM).
                        Maximum 10 bookings per slot.
                    </div>

                </div>

                <div class="time-grid">

                    <?php foreach ($slots as $start => $slot): ?>

                        <div class="time-option">

                            <input type="radio"
                                   name="start_time"
                                   id="slot_<?php echo str_replace(':', '', $start); ?>"
                                   value="<?php echo $start; ?>"
                                   required
                                   <?php echo ($selected_time == $start) ? 'checked' : ''; ?>>

                            <label for="slot_<?php echo str_replace(':', '', $start); ?>">
                                🕒 <?php echo $slot['label']; ?>
                            </label>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

            <button type="submit"
                    name="book_token"
                    class="submit-btn">

                Confirm & Book Token &nbsp; →

            </button>

        </form>

        <div class="info-box">

            <h3>ℹ️ Important Booking Information</h3>

            <p>
                • College working days are Monday to Friday.<br>
                • Saturday and Sunday are closed.<br>
                • Each time slot allows a maximum of 10 bookings.<br>
                • One student can book only one token per time slot.<br>
                • Your booking will initially have Pending status.
            </p>

        </div>

    </div>

</div>

<footer>
    © <?php echo date('Y'); ?> Smart Campus Token Booking System.
</footer>

<script>

// Sub-service dropdown
const serviceSelect = document.getElementById('service_id');
const subServiceSelect = document.getElementById('sub_service_id');
const otherBox = document.getElementById('otherBox');
const otherInput = document.getElementById('other_sub_service');

const previousSubService = <?php echo json_encode($selected_sub_service); ?>;

function updateOtherBox() {
    if (subServiceSelect.value === 'other') {
        otherBox.style.display = 'block';
        otherInput.required = true;
    } else {
        otherBox.style.display = 'none';
        otherInput.required = false;
        otherInput.value = '';
    }
}

function loadSubServices(serviceId, selectedValue = '') {

    subServiceSelect.innerHTML =
        '<option value="">Loading Sub Services...</option>';

    subServiceSelect.disabled = true;

    if (!serviceId) {
        subServiceSelect.innerHTML =
            '<option value="">-- First Select Main Service --</option>';
        subServiceSelect.disabled = false;
        updateOtherBox();
        return;
    }

    fetch('get_sub_services.php?service_id=' + encodeURIComponent(serviceId))
        .then(response => {
            if (!response.ok) {
                throw new Error('Unable to load services');
            }
            return response.json();
        })
        .then(data => {

            subServiceSelect.innerHTML =
                '<option value="">-- Select Sub Service --</option>';

            data.forEach(item => {

                const option = document.createElement('option');

                option.value = item.id;
                option.textContent = item.sub_service_name;

                if (String(item.id) === String(selectedValue)) {
                    option.selected = true;
                }

                subServiceSelect.appendChild(option);
            });

            // Other option is always available
            const otherOption = document.createElement('option');
            otherOption.value = 'other';
            otherOption.textContent = 'Other';
            subServiceSelect.appendChild(otherOption);

            if (selectedValue === 'other') {
                otherOption.selected = true;
            }

            subServiceSelect.disabled = false;
            updateOtherBox();

        })
        .catch(error => {

            subServiceSelect.innerHTML =
                '<option value="">Unable to load sub services</option>';

            const otherOption = document.createElement('option');
            otherOption.value = 'other';
            otherOption.textContent = 'Other';
            subServiceSelect.appendChild(otherOption);

            subServiceSelect.disabled = false;
            updateOtherBox();
        });
}

serviceSelect.addEventListener('change', function() {
    loadSubServices(this.value);
});

subServiceSelect.addEventListener('change', updateOtherBox);

// Restore selected sub service after validation error
if (serviceSelect.value) {
    loadSubServices(serviceSelect.value, previousSubService);
}

// Date validation: Monday to Friday only
const dateInput = document.getElementById('booking_date');

dateInput.addEventListener('change', function() {

    if (!this.value) return;

    const parts = this.value.split('-').map(Number);
    const date = new Date(parts[0], parts[1] - 1, parts[2]);
    const day = date.getDay();

    if (day === 0 || day === 6) {
        alert('College is closed on Saturday and Sunday. Please select Monday to Friday.');
        this.value = '';
    }
});

// Final browser validation
document.getElementById('bookingForm').addEventListener('submit', function(e) {

    if (subServiceSelect.value === 'other' &&
        otherInput.value.trim() === '') {

        e.preventDefault();
        alert('Please enter your required Sub Service.');
    }

});

</script>

</body>
</html>

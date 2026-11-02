<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";
$messageType = "";

// Add Main Service
if (isset($_POST['add_service'])) {
    $service_name = trim($_POST['service_name']);
    $description = trim($_POST['description']);

    if ($service_name === "") {
        $message = "Please enter service name.";
        $messageType = "error";
    } else {
        $check = $conn->prepare(
            "SELECT id FROM services WHERE service_name = ?"
        );
        $check->bind_param("s", $service_name);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            $message = "This service already exists!";
            $messageType = "error";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO services (service_name, description, status)
                 VALUES (?, ?, 'active')"
            );
            $stmt->bind_param("ss", $service_name, $description);

            if ($stmt->execute()) {
                $message = "Main service added successfully!";
                $messageType = "success";
            } else {
                $message = "Unable to add service.";
                $messageType = "error";
            }
            $stmt->close();
        }
    }
}

// Add Sub Service
if (isset($_POST['add_sub_service'])) {
    $service_id = (int)$_POST['service_id'];
    $sub_service_name = trim($_POST['sub_service_name']);

    if ($service_id <= 0 || $sub_service_name === "") {
        $message = "Please enter a valid sub-service name.";
        $messageType = "error";
    } else {
        $check = $conn->prepare(
            "SELECT id FROM service_subcategories
             WHERE service_id = ? AND sub_service_name = ?"
        );
        $check->bind_param("is", $service_id, $sub_service_name);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            $message = "This sub-service already exists!";
            $messageType = "error";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO service_subcategories
                 (service_id, sub_service_name, status)
                 VALUES (?, ?, 'active')"
            );
            $stmt->bind_param("is", $service_id, $sub_service_name);

            if ($stmt->execute()) {
                $message = "Sub-service added successfully!";
                $messageType = "success";
            } else {
                $message = "Unable to add sub-service.";
                $messageType = "error";
            }
            $stmt->close();
        }
    }
}

// Fetch Main Services
$services = $conn->query(
    "SELECT * FROM services WHERE status = 'active' ORDER BY service_name"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Services | Smart Campus</title>

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
    padding: 18px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 3px 15px rgba(41,46,50,0.12);
}

.logo {
    font-size: 23px;
    font-weight: 800;
    color: #F3F0E8;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 18px;
}

.nav-right a {
    text-decoration: none;
    color: #F3F0E8;
    font-weight: 600;
}

.logout {
    background: #F3F0E8;
    color: #292E32 !important;
    padding: 9px 16px;
    border-radius: 9px;
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
    font-size: 29px;
    color: #292E32;
}

.heading p {
    color: #817D74;
    margin-top: 7px;
}

.message {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 22px;
    font-weight: 600;
}

.success {
    background: #E7E5DC;
    color: #3E7C59;
}

.error {
    background: #EAE3D6;
    color: #9A4B40;
}

.top-actions {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 22px;
}

.btn {
    border: none;
    cursor: pointer;
    padding: 11px 17px;
    border-radius: 9px;
    font-weight: 700;
    font-size: 14px;
    transition: 0.2s;
}

.btn:hover {
    transform: translateY(-2px);
}

.btn-primary {
    background: #292E32;
    color: #F3F0E8;
}

.btn-light {
    background: #F3F0E8;
    color: #292E32;
}

.form-panel {
    display: none;
    background: #FFFFFF;
    padding: 24px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 5px 22px rgba(41,46,50,0.07);
}

.form-panel h3 {
    margin-bottom: 17px;
    color: #292E32;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group {
    margin-bottom: 14px;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 700;
    color: #5D625F;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #DCD8CF;
    border-radius: 9px;
    outline: none;
    background: #F3F0E8;
    font-size: 14px;
    color: #292E32;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    border-color: #777D80;
    box-shadow: 0 0 0 3px #E7E5DC;
}

.form-group textarea {
    resize: vertical;
    min-height: 75px;
}

.service-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.service-card {
    background: #FFFFFF;
    border-radius: 16px;
    padding: 23px;
    border: 1px solid #DCD8CF;
    box-shadow: 0 5px 20px rgba(41,46,50,0.05);
    transition: 0.25s;
}

.service-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 27px rgba(41,46,50,0.10);
}

.card-top {
    display: flex;
    align-items: flex-start;
    gap: 13px;
}

.service-icon {
    width: 46px;
    height: 46px;
    flex-shrink: 0;
    border-radius: 12px;
    background: #F3F0E8;
    display: grid;
    place-items: center;
    font-size: 22px;
}

.service-info h3 {
    color: #292E32;
    font-size: 18px;
    margin-bottom: 5px;
}

.service-info p {
    font-size: 13px;
    color: #817D74;
    line-height: 1.5;
}

.active-badge {
    margin-left: auto;
    background: #E7E5DC;
    color: #3E7C59;
    font-size: 11px;
    padding: 5px 9px;
    border-radius: 20px;
    font-weight: 700;
}

.sub-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 22px;
    margin-bottom: 12px;
}

.sub-heading strong {
    color: #5D625F;
    font-size: 13px;
}

.sub-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.sub-item {
    background: #F3F0E8;
    color: #5D625F;
    padding: 7px 11px;
    border-radius: 7px;
    font-size: 12px;
}

.sub-empty {
    color: #817D74;
    font-size: 13px;
}

.sub-form {
    display: none;
    margin-top: 17px;
    padding: 15px;
    border-radius: 11px;
    background: #F3F0E8;
}

.sub-form input {
    width: 100%;
    padding: 11px;
    border: 1px solid #DCD8CF;
    border-radius: 8px;
    margin: 10px 0;
    outline: none;
    background: #FFFFFF;
    color: #292E32;
}

.footer {
    text-align: center;
    color: #817D74;
    font-size: 12px;
    padding: 25px;
}

@media(max-width: 700px) {
    .service-grid, .form-grid {
        grid-template-columns: 1fr;
    }

    .navbar {
        padding: 15px 5%;
    }

    .logo {
        font-size: 18px;
    }

    .nav-right {
        gap: 10px;
        font-size: 12px;
    }

    .heading h1 {
        font-size: 24px;
    }
}
</style>
</head>

<body>

<div class="navbar">
    <div class="logo">🎓 Smart Campus</div>
    <div class="nav-right">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="logout.php" class="logout">Logout</a>
    </div>
</div>

<div class="container">

    <div class="heading">
        <h1>Manage Services</h1>
        <p>Manage campus departments and their services from one place.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="top-actions">
        <button class="btn btn-primary" onclick="toggleMainForm()">
            + Add Main Service
        </button>
    </div>

    <div class="form-panel" id="mainForm">
        <h3>＋ Add New Main Service</h3>

        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Service Name</label>
                    <input type="text" name="service_name"
                           placeholder="e.g. Library Department" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description"
                              placeholder="Enter service description"></textarea>
                </div>
            </div>

            <button type="submit" name="add_service"
                    class="btn btn-primary">Save Service</button>
        </form>
    </div>

    <div class="service-grid">

    <?php if ($services && $services->num_rows > 0): ?>
        <?php while ($service = $services->fetch_assoc()): ?>

            <div class="service-card">

                <div class="card-top">
                    <div class="service-icon">🏢</div>

                    <div class="service-info">
                        <h3>
                            <?php echo htmlspecialchars($service['service_name']); ?>
                        </h3>
                        <p>
                            <?php
                            echo htmlspecialchars(
                                $service['description'] ?? 'Campus service department'
                            );
                            ?>
                        </p>
                    </div>

                    <span class="active-badge">ACTIVE</span>
                </div>

                <?php
                $sid = (int)$service['id'];
                $subStmt = $conn->prepare(
                    "SELECT sub_service_name
                     FROM service_subcategories
                     WHERE service_id = ? AND status = 'active'
                     ORDER BY sub_service_name"
                );
                $subStmt->bind_param("i", $sid);
                $subStmt->execute();
                $subServices = $subStmt->get_result();
                ?>

                <div class="sub-heading">
                    <strong>Sub Services</strong>
                    <button type="button" class="btn btn-light"
                            onclick="toggleSubForm(<?php echo $sid; ?>)">
                        + Add
                    </button>
                </div>

                <div class="sub-list">
                    <?php if ($subServices->num_rows > 0): ?>
                        <?php while ($sub = $subServices->fetch_assoc()): ?>
                            <span class="sub-item">
                                <?php echo htmlspecialchars($sub['sub_service_name']); ?>
                            </span>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <span class="sub-empty">No sub-services added yet.</span>
                    <?php endif; ?>
                </div>

                <?php $subStmt->close(); ?>

                <div class="sub-form" id="subForm<?php echo $sid; ?>">
                    <form method="POST">
                        <input type="hidden" name="service_id"
                               value="<?php echo $sid; ?>">

                        <label><strong>New Sub Service Name</strong></label>

                        <input type="text" name="sub_service_name"
                               placeholder="Enter sub-service name" required>

                        <button type="submit" name="add_sub_service"
                                class="btn btn-primary">
                            Save Sub Service
                        </button>
                    </form>
                </div>  

            </div>

        <?php endwhile; ?>
    <?php else: ?>
        <p>No active services found. Add your first main service.</p>
    <?php endif; ?>

    </div>
</div>

<div class="footer">
    Smart Campus Token Booking System © <?php echo date("Y"); ?>
</div>

<script>
function toggleMainForm() {
    const form = document.getElementById("mainForm");
    form.style.display =
        form.style.display === "block" ? "none" : "block";

    if (form.style.display === "block") {
        form.scrollIntoView({behavior: "smooth", block: "center"});
    }
}

function toggleSubForm(id) {
    const form = document.getElementById("subForm" + id);
    form.style.display =
        form.style.display === "block" ? "none" : "block";

    if (form.style.display === "block") {
        form.scrollIntoView({behavior: "smooth", block: "center"});
    }
}
</script>

</body>
</html>
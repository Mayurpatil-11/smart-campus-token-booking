<?php
session_start();
include 'config/database.php';

// Admin login check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";

if (isset($_POST['add_sub_service'])) {
    $service_id = intval($_POST['service_id']);
    $sub_service_name = trim($_POST['sub_service_name']);

    if ($service_id > 0 && $sub_service_name != "") {
        $stmt = $conn->prepare(
            "INSERT INTO service_subcategories
            (service_id, sub_service_name)
            VALUES (?, ?)"
        );

        $stmt->bind_param("is", $service_id, $sub_service_name);

        if ($stmt->execute()) {
            $message = "Sub Service successfully added!";
        } else {
            $message = "Error: Sub Service already exists or could not be added.";
        }
        $stmt->close();
    } else {
        $message = "Please select service and enter sub service name.";
    }
}

// Main services
$services = $conn->query(
    "SELECT id, service_name FROM services
     WHERE status='active'
     ORDER BY service_name"
);

// Existing sub services
$sub_services = $conn->query(
    "SELECT ss.id, ss.sub_service_name, ss.status,
            s.service_name
     FROM service_subcategories ss
     JOIN services s ON ss.service_id = s.id
     ORDER BY s.service_name, ss.sub_service_name"
);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Sub Service</title>
    <style>
        body {
            font-family: Arial;
            background: #f1f4f8;
            padding: 30px;
        }
        .container {
            max-width: 650px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 15px #ccc;
        }
        input, select {
            width: 100%;
            padding: 12px;
            margin: 8px 0 18px;
            box-sizing: border-box;
        }
        button {
            background: #245477;
            color: white;
            padding: 12px 20px;
            border: 0;
            cursor: pointer;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #245477;
            color: white;
        }
        .message {
            background: #e5f5e9;
            padding: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Add Sub Service</h2>

    <?php if ($message != ""): ?>
        <p class="message">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form method="POST">
        <label>Select Main Service</label>
        <select name="service_id" required>
            <option value="">Choose Main Service</option>

            <?php while ($s = $services->fetch_assoc()): ?>
                <option value="<?php echo $s['id']; ?>">
                    <?php echo htmlspecialchars($s['service_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Sub Service Name</label>
        <input type="text"
               name="sub_service_name"
               placeholder="Example: Fee Enquiry"
               required>

        <button type="submit" name="add_sub_service">
            Add Sub Service
        </button>
    </form>

    <h3>Existing Sub Services</h3>

    <table>
        <tr>
            <th>Main Service</th>
            <th>Sub Service</th>
            <th>Status</th>
        </tr>

        <?php while ($ss = $sub_services->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($ss['service_name']); ?></td>
            <td><?php echo htmlspecialchars($ss['sub_service_name']); ?></td>
            <td><?php echo htmlspecialchars($ss['status']); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>

    <br>
    <a href="admin_dashboard.php">Back to Dashboard</a>
</div>

</body>
</html>
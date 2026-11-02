
<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $service_name = trim($_POST["service_name"]);
    $description = trim($_POST["description"]);

    $sql = "INSERT INTO services (service_name, description) VALUES (?, ?)";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $service_name, $description);

    if (mysqli_stmt_execute($stmt)) {
        $message = "Service Added Successfully!";
    } else {
        $message = "Error: Service not added.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add College Service</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .box {
            background: white;
            padding: 30px;
            width: 350px;
            border-radius: 12px;
            box-shadow: 0 4px 15px #ccc;
        }

        h2 {
            text-align: center;
            color: #234e70;
        }

        input, textarea, button {
            width: 100%;
            padding: 12px;
            margin-top: 12px;
            box-sizing: border-box;
        }

        textarea {
            height: 90px;
        }

        button {
            background: #234e70;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .message {
            text-align: center;
            color: green;
        }

        a {
            display: block;
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>

<body>

<div class="box">
    <h2>Add College Service</h2>

    <?php if ($message != "") { ?>
        <p class="message">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php } ?>

    <form method="POST">

        <input type="text" name="service_name"
               placeholder="Service Name" required>

        <textarea name="description"
                  placeholder="Service Description"></textarea>

        <button type="submit">Add Service</button>

    </form>

    <a href="admin_dashboard.php">Back to Dashboard</a>
</div>

</body>
</html>

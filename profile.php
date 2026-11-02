
<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"]) ||
    !in_array($_SESSION["role"] ?? "", ["admin", "student"], true)) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$role = $_SESSION["role"];

$stmt = $conn->prepare(
    "SELECT name, email, role FROM users WHERE id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$name = htmlspecialchars($user["name"]);
$email = htmlspecialchars($user["email"]);
$role_name = ucfirst(htmlspecialchars($user["role"]));

$dashboard = ($role === "admin")
    ? "admin_dashboard.php"
    : "student_dashboard.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | Smart Campus</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: #f4f8ff;
    color: #193858;
}

.navbar {
    background: white;
    padding: 18px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 3px 18px rgba(40,80,130,0.06);
}

.logo {
    font-family: 'Outfit', sans-serif;
    font-size: 22px;
    font-weight: 800;
    color: #2168c4;
}

.back-btn {
    text-decoration: none;
    color: #2878d7;
    font-weight: 700;
    font-size: 14px;
}

.container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}

.profile-card {
    background: white;
    border: 1px solid #e7eef7;
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 8px 25px rgba(37,75,120,0.06);
}

.profile-heading {
    font-family: 'Outfit', sans-serif;
    font-size: 27px;
    color: #1d426b;
    margin-bottom: 8px;
}

.subtitle {
    color: #8295aa;
    font-size: 14px;
    margin-bottom: 30px;
}

.profile-top {
    display: flex;
    align-items: center;
    gap: 22px;
    padding-bottom: 25px;
    border-bottom: 1px solid #edf2f8;
    margin-bottom: 25px;
}

.avatar {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    background: #e6f1ff;
    color: #2168c4;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 35px;
    border: 2px solid #cfe3ff;
}

.profile-name {
    font-family: 'Outfit', sans-serif;
    font-size: 23px;
    color: #24476d;
    margin-bottom: 8px;
}

.role {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    background: #e8f2ff;
    color: #2878d7;
    font-size: 12px;
    font-weight: 800;
}

.detail {
    margin: 20px 0;
}

.detail label {
    display: block;
    color: #8295aa;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 7px;
}

.detail p {
    font-size: 15px;
    color: #24476d;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 30px;
}

.btn {
    display: inline-block;
    padding: 12px 18px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
}

.primary {
    background: #2878d7;
    color: white;
}

.secondary {
    background: #eaf2fc;
    color: #2878d7;
}

.logout {
    background: #fff1f1;
    color: #d94b4b;
}

footer {
    text-align: center;
    padding: 25px;
    color: #99a9bb;
    font-size: 12px;
}

@media(max-width: 550px) {
    .profile-card {
        padding: 23px;
    }

    .profile-top {
        align-items: flex-start;
        flex-direction: column;
    }

    .profile-heading {
        font-size: 23px;
    }
}
</style>
</head>

<body>

<nav class="navbar">
    <div class="logo">🎓 Smart Campus</div>

    <a href="<?php echo $dashboard; ?>" class="back-btn">
        ← Back to Dashboard
    </a>
</nav>

<div class="container">
    <div class="profile-card">

        <h1 class="profile-heading">My Profile</h1>
        <p class="subtitle">
            View and manage your Smart Campus account.
        </p>

        <div class="profile-top">
            <div class="avatar">👤</div>

            <div>
                <h2 class="profile-name"><?php echo $name; ?></h2>
                <span class="role"><?php echo $role_name; ?></span>
            </div>
        </div>

        <div class="detail">
            <label>FULL NAME</label>
            <p><?php echo $name; ?></p>
        </div>

        <div class="detail">
            <label>EMAIL ADDRESS</label>
            <p><?php echo $email; ?></p>
        </div>

        <div class="detail">
            <label>ACCOUNT ROLE</label>
            <p><?php echo $role_name; ?></p>
        </div>

        <div class="actions">
            <a href="edit_profile.php" class="btn primary">
                ✏️ Edit Profile
            </a>

            <a href="change_password.php" class="btn secondary">
                🔒 Change Password
            </a>

            <a href="logout.php" class="btn logout">
                ↪ Logout
            </a>
        </div>

    </div>
</div>

<footer>
    © <?php echo date("Y"); ?> Smart Campus Token Booking System.
</footer>

</body>
</html>

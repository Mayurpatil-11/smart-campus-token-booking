
<?php
session_start();
include 'config/database.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    $stmt = $conn->prepare(
        "SELECT id, name, email, password, role
         FROM users
         WHERE email = ? AND role = ?
         LIMIT 1"
    );

    if ($stmt) {
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            $password_valid =
                password_verify($password, $user['password']) ||
                hash_equals(
                    (string)$user['password'],
                    (string)$password
                );

            if ($password_valid) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] == 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: student_dashboard.php");
                }

                exit();
            } else {
                $error = "Incorrect password. Please try again.";
            }
        } else {
            $error = "Account not found for the selected role.";
        }

        $stmt->close();
    } else {
        $error = "Something went wrong. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Smart Campus | Login</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --charcoal: #292E32;
    --ivory: #F3F0E8;
    --white: #FFFFFF;
    --border: #E2DED5;
    --muted: #77766F;
    --soft: #EAE6DD;
    --success: #47745D;
    --danger: #B54747;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    min-height: 100vh;
    font-family: 'DM Sans', sans-serif;
    background: var(--ivory);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    color: var(--charcoal);
}

/* MAIN CONTAINER */

.login-container {
    width: 100%;
    max-width: 1050px;
    min-height: 620px;
    display: grid;
    grid-template-columns: 1fr 0.9fr;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(41,46,50,0.10);
}

/* LEFT PANEL */

.brand-panel {
    position: relative;
    overflow: hidden;
    padding: 48px;
    background: var(--charcoal);
    color: var(--ivory);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.brand-panel::before {
    content: "";
    position: absolute;
    width: 420px;
    height: 420px;
    border: 1px solid rgba(243,240,232,0.10);
    border-radius: 50%;
    right: -180px;
    top: 80px;
}

.brand-panel::after {
    content: "";
    position: absolute;
    width: 300px;
    height: 300px;
    border: 1px solid rgba(243,240,232,0.10);
    border-radius: 50%;
    right: -120px;
    top: 140px;
}

.brand,
.hero-content,
.features,
.panel-footer {
    position: relative;
    z-index: 2;
}

/* BRAND */

.brand {
    display: flex;
    align-items: center;
    gap: 13px;
}

.brand-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    background: var(--ivory);
    color: var(--charcoal);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
}

.brand-name {
    font-family: 'Outfit', sans-serif;
    font-size: 23px;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: var(--ivory);
}

/* HERO */

.hero-content {
    margin: 45px 0;
}

.hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 13px;
    background: rgba(243,240,232,0.08);
    border: 1px solid rgba(243,240,232,0.15);
    border-radius: 30px;
    color: #D9D5CC;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    margin-bottom: 25px;
}

.hero-content h1 {
    font-family: 'Outfit', sans-serif;
    font-size: clamp(34px, 4vw, 48px);
    line-height: 1.16;
    letter-spacing: -1.5px;
    margin-bottom: 22px;
    color: var(--ivory);
}

.hero-content h1 span {
    color: #C9C3B7;
}

.hero-content p {
    color: #C6C3BC;
    font-size: 15px;
    line-height: 1.9;
    max-width: 380px;
}

/* FEATURES */

.features {
    display: grid;
    gap: 15px;
}

.feature {
    display: flex;
    align-items: center;
    gap: 13px;
    color: #E0DDD5;
    font-size: 14px;
    font-weight: 500;
}

.feature-icon {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: rgba(243,240,232,0.09);
    border: 1px solid rgba(243,240,232,0.10);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
}

.panel-footer {
    color: #A8A69F;
    font-size: 12px;
    margin-top: 30px;
}

/* RIGHT PANEL */

.form-panel {
    padding: 55px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: var(--white);
}

.welcome {
    margin-bottom: 32px;
}

.welcome .eyebrow {
    color: var(--muted);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2px;
    margin-bottom: 12px;
}

.welcome h2 {
    font-family: 'Outfit', sans-serif;
    font-size: 38px;
    font-weight: 800;
    color: var(--charcoal);
    letter-spacing: -1px;
    margin-bottom: 10px;
}

.welcome p {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.7;
}

/* FORM */

.form-group {
    margin-bottom: 21px;
}

label {
    display: block;
    color: var(--charcoal);
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 9px;
}

.input-wrapper {
    position: relative;
}

.input-wrapper input,
.input-wrapper select {
    width: 100%;
    height: 52px;
    padding: 0 45px;
    border: 1px solid var(--border);
    border-radius: 12px;
    outline: none;
    background: var(--ivory);
    color: var(--charcoal);
    font-family: inherit;
    font-size: 14px;
    transition: all 0.25s ease;
}

.input-wrapper input::placeholder {
    color: #929087;
}

.input-wrapper input:focus,
.input-wrapper select:focus {
    background: var(--white);
    border-color: var(--charcoal);
    box-shadow: 0 0 0 4px rgba(41,46,50,0.08);
}

.input-wrapper select option {
    background: var(--white);
    color: var(--charcoal);
}

.input-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--muted);
    font-size: 17px;
    pointer-events: none;
}

.password-input {
    padding-right: 48px !important;
}

.toggle-password {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: var(--muted);
    font-size: 18px;
    cursor: pointer;
    padding: 5px;
}

.toggle-password:hover {
    color: var(--charcoal);
}

/* ERROR */

.error-message {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 13px 15px;
    background: #FFF1F0;
    border: 1px solid #F0D0CC;
    color: var(--danger);
    border-radius: 12px;
    font-size: 13px;
    margin-bottom: 22px;
    line-height: 1.5;
}

/* LOGIN BUTTON */

.login-btn {
    width: 100%;
    height: 54px;
    margin-top: 5px;
    border: 1px solid var(--charcoal);
    border-radius: 12px;
    background: var(--charcoal);
    color: var(--ivory);
    font-family: inherit;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.25s ease;
}

.login-btn:hover {
    background: #3A4146;
    transform: translateY(-2px);
    box-shadow: 0 10px 22px rgba(41,46,50,0.16);
}

.login-btn:active {
    transform: translateY(0);
}

/* SECURITY */

.security-note {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 22px;
    color: var(--success);
    font-size: 12px;
    font-weight: 600;
}

.form-footer {
    margin-top: 35px;
    padding-top: 22px;
    border-top: 1px solid #EEECE6;
    text-align: center;
    color: #929087;
    font-size: 12px;
}

/* RESPONSIVE */

@media (max-width: 850px) {
    .login-container {
        max-width: 540px;
        grid-template-columns: 1fr;
    }

    .brand-panel {
        padding: 32px;
    }

    .hero-content {
        margin: 35px 0;
    }

    .hero-content h1 {
        font-size: 35px;
    }

    .features {
        display: none;
    }

    .form-panel {
        padding: 40px 35px;
    }
}

@media (max-width: 480px) {
    body {
        padding: 12px;
    }

    .login-container {
        border-radius: 20px;
    }

    .brand-panel {
        padding: 25px;
    }

    .brand-icon {
        width: 43px;
        height: 43px;
        font-size: 22px;
    }

    .brand-name {
        font-size: 20px;
    }

    .hero-content {
        margin: 28px 0;
    }

    .hero-content h1 {
        font-size: 31px;
    }

    .hero-content p {
        font-size: 13px;
    }

    .form-panel {
        padding: 32px 23px;
    }

    .welcome h2 {
        font-size: 32px;
    }
}
</style>
</head>

<body>

<div class="login-container">

    <!-- LEFT BRAND PANEL -->

    <section class="brand-panel">

        <div class="brand">

            <div class="brand-icon">🎓</div>

            <div class="brand-name">
                Smart Campus
            </div>

        </div>

        <div class="hero-content">

            <div class="hero-tag">
                ✦ SMART CAMPUS EXPERIENCE
            </div>

            <h1>
                Your Campus.<br>
                Your Time.<br>
                <span>Your Convenience.</span>
            </h1>

            <p>
                Smart token booking made simple.
                Skip the long queues, book your service
                and manage your campus visits easily.
            </p>

        </div>

        <div class="features">

            <div class="feature">
                <div class="feature-icon">⚡</div>
                Quick & Easy Token Booking
            </div>

            <div class="feature">
                <div class="feature-icon">📅</div>
                Convenient Time Slot Selection
            </div>

            <div class="feature">
                <div class="feature-icon">🔒</div>
                Secure Account Access
            </div>

        </div>

        <div class="panel-footer">
            Designed for a smarter campus.
        </div>

    </section>

    <!-- LOGIN PANEL -->

    <section class="form-panel">

        <div class="welcome">

            <div class="eyebrow">
                WELCOME BACK
            </div>

            <h2>Sign In</h2>

            <p>
                Enter your details to access your account.
            </p>

        </div>

        <?php if ($error != ""): ?>

            <div class="error-message">
                <span>⚠️</span>

                <span>
                    <?php echo htmlspecialchars($error); ?>
                </span>
            </div>

        <?php endif; ?>

        <form method="POST">

            <!-- ACCOUNT TYPE -->

            <div class="form-group">

                <label for="role">
                    Account Type
                </label>

                <div class="input-wrapper">

                    <span class="input-icon">👤</span>

                    <select name="role" id="role" required>

                        <option value="student"
                            <?php echo (($_POST['role'] ?? '') == 'student') ? 'selected' : ''; ?>>
                            Student Account
                        </option>

                        <option value="admin"
                            <?php echo (($_POST['role'] ?? '') == 'admin') ? 'selected' : ''; ?>>
                            Admin Account
                        </option>

                    </select>

                </div>

            </div>

            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <div class="input-wrapper">

                    <span class="input-icon">✉</span>

                    <input
                        type="email"
                        name="email"
                        id="email"
                        placeholder="Enter your email"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        autocomplete="username"
                        required
                    >

                </div>

            </div>

            <!-- PASSWORD -->

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="input-wrapper">

                    <span class="input-icon">🔑</span>

                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="password-input"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword()"
                        id="togglePassword"
                        aria-label="Show password"
                    >
                        👁
                    </button>

                </div>

            </div>

            <!-- SUBMIT -->

            <button type="submit" class="login-btn">
                Sign In &nbsp; →
            </button>

        </form>

        <div class="security-note">
            🔒 Your account is protected
        </div>

        <div class="form-footer">
            © <?php echo date('Y'); ?>
            Smart Campus Token Booking System
        </div>

    </section>

</div>

<script>
function togglePassword() {

    const password = document.getElementById("password");
    const button = document.getElementById("togglePassword");

    if (password.type === "password") {

        password.type = "text";
        button.textContent = "🙈";
        button.setAttribute("aria-label", "Hide password");

    } else {

        password.type = "password";
        button.textContent = "👁";
        button.setAttribute("aria-label", "Show password");

    }
}
</script>

</body>
</html>

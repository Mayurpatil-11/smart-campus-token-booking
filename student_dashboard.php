
<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "student") {
    header("Location: login.php");
    exit;
}

$student_name = $_SESSION["name"] ?? "Student";
$student_initial = strtoupper(substr($student_name, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Student Dashboard | Smart Campus</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --charcoal: #292E32;
    --ivory: #F3F0E8;
    --white: #FFFFFF;
    --border: #E2DED5;
    --muted: #77766F;
    --soft: #EAE6DD;
    --charcoal-light: #3A4146;
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
    width: 100%;
    padding: 16px 6%;
    background: var(--charcoal);
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 5px 20px rgba(41,46,50,0.10);
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-family: 'Outfit', sans-serif;
    font-size: 22px;
    font-weight: 800;
    color: var(--ivory);
    letter-spacing: -0.5px;
}

.logo-icon {
    width: 44px;
    height: 44px;
    border-radius: 13px;
    background: var(--ivory);
    color: var(--charcoal);
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 23px;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.student-label {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #D9D5CC;
    font-size: 13px;
    font-weight: 600;
}

.student-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--ivory);
    color: var(--charcoal);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 800;
}

.logout-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--ivory);
    color: var(--charcoal);
    padding: 11px 16px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
    transition: 0.25s ease;
}

.logout-btn:hover {
    background: #E1DDD4;
    transform: translateY(-2px);
}

/* MAIN */

.container {
    max-width: 1250px;
    margin: auto;
    padding: 38px 28px;
}

/* WELCOME BANNER */

.welcome-banner {
    background: var(--charcoal);
    padding: 48px;
    border-radius: 24px;
    color: var(--ivory);
    position: relative;
    overflow: hidden;
    box-shadow: 0 15px 35px rgba(41,46,50,0.12);
}

.welcome-banner::before {
    content: "";
    position: absolute;
    width: 400px;
    height: 400px;
    border: 1px solid rgba(243,240,232,0.10);
    border-radius: 50%;
    right: -140px;
    top: -150px;
}

.welcome-banner::after {
    content: "";
    position: absolute;
    width: 280px;
    height: 280px;
    border: 1px solid rgba(243,240,232,0.10);
    border-radius: 50%;
    right: -80px;
    top: -90px;
}

.welcome-content {
    position: relative;
    z-index: 2;
    max-width: 720px;
}

.welcome-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 13px;
    background: rgba(243,240,232,0.08);
    border: 1px solid rgba(243,240,232,0.13);
    border-radius: 30px;
    color: #D9D5CC;
    font-size: 11px;
    letter-spacing: 1.5px;
    font-weight: 800;
    margin-bottom: 22px;
}

.welcome-banner h1 {
    font-family: 'Outfit', sans-serif;
    font-size: clamp(28px, 4vw, 42px);
    line-height: 1.2;
    letter-spacing: -1px;
    margin-bottom: 15px;
}

.welcome-banner h1 span {
    color: #C9C3B7;
}

.welcome-banner p {
    color: #C6C3BC;
    font-size: 15px;
    line-height: 1.8;
    max-width: 600px;
}

/* SECTION HEADING */

.section-heading {
    margin: 42px 0 22px;
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 15px;
}

.section-heading h2 {
    font-family: 'Outfit', sans-serif;
    font-size: 26px;
    color: var(--charcoal);
    margin-bottom: 7px;
    letter-spacing: -0.5px;
}

.section-heading p {
    color: var(--muted);
    font-size: 13px;
}

.section-badge {
    background: var(--soft);
    color: var(--charcoal);
    padding: 8px 13px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

/* DASHBOARD CARDS */

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}

.card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 28px;
    min-height: 245px;
    box-shadow: 0 5px 18px rgba(41,46,50,0.035);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 32px rgba(41,46,50,0.10);
    border-color: #C7C1B5;
}

.card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 23px;
}

.card-icon {
    width: 56px;
    height: 56px;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 16px;
    font-size: 26px;
    background: var(--ivory);
    border: 1px solid var(--border);
}

.card-number {
    color: #B4B0A7;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 1px;
}

.card h3 {
    color: var(--charcoal);
    font-family: 'Outfit', sans-serif;
    font-size: 21px;
    margin-bottom: 10px;
}

.card p {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.8;
    margin-bottom: 22px;
    max-width: 430px;
}

.card-link {
    text-decoration: none;
    color: var(--charcoal);
    font-size: 13px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: 0.25s;
}

.card-link:hover {
    color: #555B5F;
    gap: 13px;
}

.card-link span {
    font-size: 17px;
}

/* QUICK INFORMATION */

.info-section {
    margin-top: 28px;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 25px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.info-content {
    display: flex;
    align-items: center;
    gap: 17px;
}

.info-icon {
    width: 50px;
    height: 50px;
    flex-shrink: 0;
    border-radius: 15px;
    background: var(--ivory);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
}

.info-section h3 {
    color: var(--charcoal);
    font-family: 'Outfit', sans-serif;
    font-size: 17px;
    margin-bottom: 7px;
}

.info-section p {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.7;
}

.info-label {
    color: var(--ivory);
    background: var(--charcoal);
    padding: 9px 14px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

/* FOOTER */

footer {
    text-align: center;
    padding: 30px 15px;
    color: #89867E;
    font-size: 12px;
}

/* RESPONSIVE */

@media(max-width: 850px) {
    .container {
        padding: 28px 20px;
    }

    .welcome-banner {
        padding: 38px;
    }

    .dashboard-grid {
        gap: 16px;
    }

    .card {
        padding: 24px;
    }
}

@media(max-width: 650px) {
    .navbar {
        padding: 13px 17px;
    }

    .logo {
        font-size: 18px;
        gap: 9px;
    }

    .logo-icon {
        width: 40px;
        height: 40px;
        font-size: 21px;
    }

    .nav-right {
        gap: 10px;
    }

    .student-label {
        display: none;
    }

    .logout-btn {
        padding: 10px 12px;
        font-size: 12px;
    }

    .container {
        padding: 22px 15px;
    }

    .welcome-banner {
        padding: 30px 24px;
        border-radius: 20px;
    }

    .welcome-banner h1 {
        font-size: 29px;
    }

    .welcome-banner p {
        font-size: 13px;
    }

    .section-heading {
        margin: 32px 0 18px;
        align-items: start;
    }

    .section-heading h2 {
        font-size: 23px;
    }

    .section-badge {
        font-size: 10px;
        padding: 7px 10px;
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .card {
        min-height: auto;
        padding: 24px;
        border-radius: 18px;
    }

    .card h3 {
        font-size: 20px;
    }

    .card p {
        margin-bottom: 18px;
    }

    .info-section {
        padding: 20px;
        align-items: flex-start;
    }

    .info-content {
        align-items: flex-start;
        gap: 12px;
    }

    .info-label {
        display: none;
    }

    .info-section h3 {
        font-size: 15px;
    }

    .info-section p {
        font-size: 12px;
    }

    footer {
        padding: 25px 15px;
        line-height: 1.7;
    }
}

@media(max-width: 380px) {
    .welcome-banner h1 {
        font-size: 25px;
    }

    .section-heading {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
</head>

<body>

<!-- NAVIGATION BAR -->

<nav class="navbar">

    <div class="logo">
        <div class="logo-icon">🎓</div>
        <span>Smart Campus</span>
    </div>

    <div class="nav-right">

        <div class="student-label">

            <div class="student-avatar">
                <?php echo htmlspecialchars($student_initial); ?>
            </div>

            Student Portal

        </div>

        <a href="logout.php" class="logout-btn">
            ↪ Logout
        </a>

    </div>

</nav>

<!-- MAIN CONTENT -->

<main class="container">

    <!-- WELCOME BANNER -->

    <section class="welcome-banner">

        <div class="welcome-content">

            <div class="welcome-tag">
                ✦ STUDENT PORTAL
            </div>

            <h1>
                Welcome,
                <span><?php echo htmlspecialchars($student_name); ?>!</span>
                👋
            </h1>

            <p>
                Your campus journey made smarter and easier.
                Book college services, manage your tokens and
                explore opportunities from one convenient place.
            </p>

        </div>

    </section>

    <!-- DASHBOARD HEADING -->

    <div class="section-heading">

        <div>
            <h2>Your Dashboard</h2>
            <p>Everything you need, all in one place.</p>
        </div>

        <div class="section-badge">
            Student Workspace
        </div>

    </div>

    <!-- DASHBOARD GRID -->

    <div class="dashboard-grid">

        <!-- BOOK TOKEN -->

        <article class="card">

            <div class="card-top">

                <div class="card-icon">
                    🎟️
                </div>

                <span class="card-number">01</span>

            </div>

            <h3>Book College Token</h3>

            <p>
                Select your college department, choose a
                sub-service and book your token at a
                convenient time.
            </p>

            <a href="book_token.php" class="card-link">
                Book Your Token <span>→</span>
            </a>

        </article>

        <!-- MY TOKENS -->

        <article class="card">

            <div class="card-top">

                <div class="card-icon">
                    📋
                </div>

                <span class="card-number">02</span>

            </div>

            <h3>My Tokens</h3>

            <p>
                View your token bookings, check booking
                details and track your token status.
            </p>

            <a href="my_token.php" class="card-link">
                View My Tokens <span>→</span>
            </a>

        </article>

        <!-- INTERNSHIP -->

        <article class="card">

            <div class="card-top">

                <div class="card-icon">
                    💼
                </div>

                <span class="card-number">03</span>

            </div>

            <h3>Internship Opportunities</h3>

            <p>
                Explore internship opportunities and
                discover new career possibilities.
            </p>

            <a href="#" class="card-link">
                Explore Opportunities <span>→</span>
            </a>

        </article>

        <!-- MY BOOKINGS -->

        <article class="card">

            <div class="card-top">

                <div class="card-icon">
                    📑
                </div>

                <span class="card-number">04</span>

            </div>

            <h3>My Bookings</h3>

            <p>
                Keep track of your token bookings
                and their progress.
            </p>

            <a href="my_bookings.php" class="card-link">
                View My Bookings <span>→</span>
            </a>

        </article>

    </div>

    <!-- QUICK INFORMATION -->

    <section class="info-section">

        <div class="info-content">

            <div class="info-icon">
                🏫
            </div>

            <div>

                <h3>
                    Smart Campus — Your Digital Campus Assistant
                </h3>

                <p>
                    Save time, avoid queues and manage
                    college services easily.
                </p>

            </div>

        </div>

        <div class="info-label">
            SMART & SIMPLE
        </div>

    </section>

</main>

<!-- FOOTER -->

<footer>
    © <?php echo date('Y'); ?>
    Smart Campus Token Booking System.
    All Rights Reserved.
</footer>

</body>
</html>

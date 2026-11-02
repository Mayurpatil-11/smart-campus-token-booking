
<?php
$mode = $animation_mode ?? 'login';
$redirect = $redirect_url ?? 'login.php';

$isLogin = ($mode === 'login');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $isLogin ? 'Welcome' : 'Logging Out'; ?></title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg,#edf5ff,#ffffff);
    overflow: hidden;
}

.animation-box {
    text-align: center;
    width: 100%;
    padding: 20px;
}

h2 {
    color: #205caa;
    font-size: 28px;
    margin-bottom: 10px;
}

p {
    color: #7b8da3;
    font-size: 15px;
}

.scene {
    position: relative;
    width: 280px;
    height: 230px;
    margin: 35px auto 20px;
    display: flex;
    justify-content: center;
    align-items: flex-end;
}

.door-frame {
    position: absolute;
    bottom: 0;
    width: 110px;
    height: 190px;
    background: #dceaff;
    border: 7px solid #225fae;
    border-bottom: 0;
    border-radius: 8px 8px 0 0;
    overflow: visible;
    perspective: 600px;
}

.door {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg,#3989ed,#1557ae);
    transform-origin: left center;
    border: 3px solid #174b91;
    border-radius: 3px;
    z-index: 3;
}

.door::after {
    content: "";
    position: absolute;
    right: 12px;
    top: 50%;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #fff;
}

.person {
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 55px;
    height: 125px;
    transform: translateX(-50%);
    z-index: 2;
}

.head {
    position: absolute;
    top: 0;
    left: 17px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #f0b88f;
    border: 2px solid #9c674b;
}

.body-man {
    position: absolute;
    top: 24px;
    left: 21px;
    width: 14px;
    height: 49px;
    border-radius: 7px;
    background: #246bc1;
}

.arm {
    position: absolute;
    top: 30px;
    width: 7px;
    height: 35px;
    background: #f0b88f;
    border-radius: 8px;
}

.arm.left {
    left: 13px;
    transform: rotate(25deg);
}

.arm.right {
    right: 10px;
    transform: rotate(-25deg);
}

.leg {
    position: absolute;
    top: 68px;
    width: 8px;
    height: 48px;
    background: #243d5c;
    border-radius: 8px;
    transform-origin: top center;
}

.leg.left {
    left: 20px;
}

.leg.right {
    left: 30px;
}

.ground {
    position: absolute;
    bottom: 0;
    width: 250px;
    height: 5px;
    background: #b9d3f2;
    border-radius: 10px;
}

/* LOGIN: Door opens and person walks outside */

.login .door {
    animation: openDoor 0.7s ease forwards;
    animation-delay: 0.3s;
}

.login .person {
    animation: walkOut 1.5s ease-in-out forwards;
    animation-delay: 0.6s;
}

.login .leg.left {
    animation: legWalk 0.3s infinite alternate;
}

.login .leg.right {
    animation: legWalk 0.3s infinite alternate-reverse;
}

/* LOGOUT: Person goes inside and door closes */

.logout .person {
    animation: walkIn 1.5s ease-in-out forwards;
}

.logout .door {
    animation: closeDoor 0.7s ease forwards;
    animation-delay: 1.2s;
}

.logout .leg.left {
    animation: legWalk 0.3s infinite alternate;
}

.logout .leg.right {
    animation: legWalk 0.3s infinite alternate-reverse;
}

@keyframes openDoor {
    to { transform: rotateY(-105deg); }
}

@keyframes closeDoor {
    from { transform: rotateY(-105deg); }
    to { transform: rotateY(0deg); }
}

@keyframes walkOut {
    0% { transform: translateX(-50%); }
    45% { transform: translateX(-50%); }
    100% { transform: translateX(170px); }
}

@keyframes walkIn {
    0% { transform: translateX(170px); }
    65% { transform: translateX(-50%); }
    100% { transform: translateX(-50%); }
}

@keyframes legWalk {
    from { transform: rotate(25deg); }
    to { transform: rotate(-25deg); }
}

@media(max-width:400px) {
    h2 { font-size: 23px; }
}
</style>
</head>

<body>

<div class="animation-box <?php echo $isLogin ? 'login' : 'logout'; ?>">

    <h2>
        <?php echo $isLogin ? 'Welcome to Smart Campus!' : 'See You Again!'; ?>
    </h2>

    <p>
        <?php echo $isLogin
            ? 'You are successfully logged in...'
            : 'You have successfully logged out...'; ?>
    </p>

    <div class="scene">

        <div class="ground"></div>

        <div class="person">
            <div class="head"></div>
            <div class="body-man"></div>
            <div class="arm left"></div>
            <div class="arm right"></div>
            <div class="leg left"></div>
            <div class="leg right"></div>
        </div>

        <div class="door-frame">
            <div class="door"></div>
        </div>

    </div>

    <p><?php echo $isLogin ? 'Entering your dashboard...' : 'Redirecting to login...'; ?></p>

</div>

<script>
setTimeout(function() {
    window.location.href = <?php echo json_encode($redirect); ?>;
}, 2500);
</script>

</body>
</html>

<?php
session_start();

// Language handling
if (isset($_GET['lang']) && ($_GET['lang'] == 'en' || $_GET['lang'] == 'hi')) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'] ?? 'en';

// Inline translations
$translations = [
    'en' => [
        'secure_login' => 'Secure Login',
        'waiting_text' => 'Verifying identity...',
        'waiting_subtext' => 'Please wait while we check your browser.',
        'login_btn' => 'Login',
        'select_role' => 'Select Role:',
        'admin_role' => 'Admin',
        'teacher_role' => 'Teacher',
        'student_role' => 'Student',
        'username' => 'Username:',
        'password' => 'Password:',
        'math_question' => 'Answer this',
        'how_to_login' => 'How to Login?',
        'use_creds' => 'Use the credentials provided by your school.',
        'admin_hint' => 'Ask the school head for login details.',
        'teacher_hint' => 'Your username is your E-Mail I\'D, password given by admin.',
        'student_hint' => 'Your username is your roll number & password is D.O.B.',
        'for_demo' => 'For demo, use:',
        'back_home' => 'Back to Home',
        'wrong_math' => 'Incorrect answer to security question.',
        'invalid_login' => 'Invalid username or password.',
        'too_fast' => 'You submitted too quickly. Please wait for the timer to finish.',
        'locked' => 'Too many failed attempts. Please try again after 15 minutes.',
        'session_expired' => 'Your session expired. Please wait again.',
        'show_hints' => 'Hints',
        'hide_hints' => 'Hide Hints'
    ],
    'hi' => [
        'secure_login' => 'सुरक्षित लॉगिन',
        'waiting_text' => 'पहचान सत्यापित हो रही है...',
        'waiting_subtext' => 'कृपया प्रतीक्षा करें जब तक हम आपका ब्राउज़र जांचते हैं।',
        'login_btn' => 'लॉगिन',
        'select_role' => 'भूमिका चुनें:',
        'admin_role' => 'एडमिन',
        'teacher_role' => 'शिक्षक',
        'student_role' => 'छात्र',
        'username' => 'उपयोगकर्ता नाम:',
        'password' => 'पासवर्ड:',
        'math_question' => 'सुरक्षा जाँच: क्या है',
        'how_to_login' => 'लॉगिन कैसे करें?',
        'use_creds' => 'अपने स्कूल द्वारा दिए गए क्रेडेंशियल्स का उपयोग करें।',
        'admin_hint' => 'लॉगिन विवरण के लिए स्कूल प्रमुख से पूछें।',
        'teacher_hint' => 'आपका उपयोगकर्ता नाम आपका ईमेल है, पासवर्ड एडमिन द्वारा दिया गया है।',
        'student_hint' => 'आपका उपयोगकर्ता नाम आपका रोल नंबर है और पासवर्ड आपकी जन्म तिथि है।',
        'for_demo' => 'डेमो के लिए उपयोग करें:',
        'back_home' => 'होम पेज पर वापस जाएं',
        'wrong_math' => 'सुरक्षा प्रश्न का गलत उत्तर।',
        'invalid_login' => 'अमान्य उपयोगकर्ता नाम या पासवर्ड।',
        'too_fast' => 'आपने बहुत जल्दी सबमिट किया। कृपया टाइमर पूरा होने तक प्रतीक्षा करें।',
        'locked' => 'बहुत अधिक असफल प्रयास। कृपया 15 मिनट बाद पुनः प्रयास करें।',
        'session_expired' => 'आपका सत्र समाप्त हो गया। कृपया फिर से प्रतीक्षा करें।',
        'show_hints' => 'संकेत',
        'hide_hints' => 'संकेत छुपाएं'
    ]
];
$t = $translations[$lang];

// ======================================================
//  SERVER-SIDE BOT PROTECTION
// ======================================================

// Determine wait time based on "returning user" cookie
$has_logged_in = isset($_COOKIE['has_logged_in']);
$wait_time     = $has_logged_in ? 5 : 30;

// If a failure happened OR this is a fresh visit, reset timer + generate new CSRF token
$error_param = $_GET['error'] ?? '';
$needs_reset = in_array($error_param, ['wrong_math', 'invalid', 'too_fast', 'locked', 'session_expired'], true);

if (!isset($_SESSION['login_start_time']) || $needs_reset) {
    $_SESSION['login_start_time'] = time();
    $_SESSION['csrf_token']       = bin2hex(random_bytes(32));
}

// If the browser doesn't have a session cookie at all, we still need one
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Compute the exact unlock timestamp (the server is the source of truth)
$unlock_time    = $_SESSION['login_start_time'] + $wait_time;
$remaining_time = max(0, $unlock_time - time());
$is_locked      = $remaining_time > 0;

// Generate fresh math challenge (store answer in session for backend verification)
$num1 = rand(1, 9);
$num2 = rand(1, 9);
$_SESSION['math_answer'] = $num1 + $num2;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['secure_login']; ?> - GSSS Maranga</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="secure-login-body">

<div class="secure-wrapper">
    <div class="secure-header">
        <h2><i class="fa-solid fa-lock"></i> <?php echo $t['secure_login']; ?></h2>
        <div class="header-actions">
            <div class="lang-switch">
                <a href="?lang=en">EN</a> | 
                <a href="?lang=hi">हिं</a>
            </div>
            <button class="hints-toggle-btn" onclick="toggleHints()" id="hintsBtn">
                <i class="fa-solid fa-circle-question"></i> <?php echo $t['show_hints']; ?>
            </button>
        </div>
    </div>

    <div class="secure-body">
        <!-- LEFT SIDE: TRANSPARENT COMPACT LOGIN CARD -->
        <div class="login-card">
            <?php if (!empty($error_param)): ?>
                <div class="error-message">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <?php
                        switch ($error_param) {
                            case 'wrong_math':     echo $t['wrong_math'];     break;
                            case 'too_fast':       echo $t['too_fast'];       break;
                            case 'locked':         echo $t['locked'];         break;
                            case 'session_expired':echo $t['session_expired'];break;
                            default:               echo $t['invalid_login'];
                        }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Waiting Screen -->
            <?php if ($is_locked): ?>
            <div id="waitingScreen">
                <div class="waiting-text"><?php echo $t['waiting_text']; ?></div>
                <div class="waiting-subtext"><?php echo $t['waiting_subtext']; ?></div>
                <div class="progress-bar-container">
                    <div class="progress-bar" id="progressBar"></div>
                </div>
                <div style="font-weight:bold; font-size: 24px; color:#0f172a; margin-top:10px;" id="countdownText">
                    <?php echo $remaining_time; ?> seconds
                </div>
            </div>
            <?php endif; ?>

            <!-- Login Screen -->
            <div id="loginScreen">
                <form action="backend/login.php" method="POST" autocomplete="off">
                    <!-- CSRF Token (server-verified) -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="form-group">
                        <label for="role"><?php echo $t['select_role']; ?></label>
                        <select name="role" id="role" required>
                            <option value="">-- Choose --</option>
                            <option value="admin"><?php echo $t['admin_role']; ?></option>
                            <option value="teacher"><?php echo $t['teacher_role']; ?></option>
                            <option value="student"><?php echo $t['student_role']; ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="username"><?php echo $t['username']; ?></label>
                        <input type="text" name="username" id="username" required>
                    </div>

                    <div class="form-group">
                        <label for="password"><?php echo $t['password']; ?></label>
                        <input type="password" name="password" id="password" required>
                    </div>

                    <div class="math-question">
                        <?php echo $t['math_question']; ?> <?php echo $num1; ?> + <?php echo $num2; ?> = ?
                    </div>
                    <div class="form-group">
                        <input type="number" name="math_answer" placeholder="Your answer" required>
                    </div>

                    <button type="submit" class="btn-login" id="loginBtn"><?php echo $t['login_btn']; ?></button>
                </form>
                <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> <?php echo $t['back_home']; ?></a>
            </div>
        </div>

        <!-- RIGHT SIDE: HINTS CARD (Hidden by default, expands when toggled) -->
        <div class="hint-card" id="hintCard">
            <h3><i class="fa-solid fa-circle-question"></i> <?php echo $t['how_to_login']; ?></h3>
            <p><?php echo $t['use_creds']; ?></p>
            <ul>
                <li><strong>Admin:</strong> <?php echo $t['admin_hint']; ?></li>
                <li><strong>Teacher:</strong> <?php echo $t['teacher_hint']; ?></li>
                <li><strong>Student:</strong> <?php echo $t['student_hint']; ?></li>
            </ul>
            <div class="demo-creds">
                <p><em><?php echo $t['for_demo']; ?></em></p>
                <p><strong>Admin:</strong> headmaster / admin123</p>
                <p><strong>Teacher:</strong> abhay@gmail.com / AB1994MB</p>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleHints() {
        var hintCard = document.getElementById('hintCard');
        var btn = document.getElementById('hintsBtn');
        hintCard.classList.toggle('show');
        
        if (hintCard.classList.contains('show')) {
            btn.innerHTML = '<i class="fa-solid fa-circle-question"></i> <?php echo $t['hide_hints']; ?>';
        } else {
            btn.innerHTML = '<i class="fa-solid fa-circle-question"></i> <?php echo $t['show_hints']; ?>';
        }
    }

    // Countdown driven by server-provided remaining time
    var remaining = <?php echo (int)$remaining_time; ?>;
    var total     = <?php echo (int)$wait_time; ?>;

    var progressBar   = document.getElementById('progressBar');
    var countdownText = document.getElementById('countdownText');
    var waitingScreen = document.getElementById('waitingScreen');
    var loginScreen   = document.getElementById('loginScreen');
    var loginBtn      = document.getElementById('loginBtn');

    if (remaining > 0) {
        // Disable the login button while locked
        if (loginBtn) loginBtn.disabled = true;

        progressBar.style.width = '0%';
        var interval = setInterval(function() {
            remaining--;
            var percentage = ((total - remaining) / total) * 100;
            progressBar.style.width = percentage + '%';
            countdownText.textContent = remaining + ' seconds';

            if (remaining <= 0) {
                clearInterval(interval);
                progressBar.style.width = '100%';
                waitingScreen.style.display = 'none';
                loginScreen.style.display = 'block';
                if (loginBtn) loginBtn.disabled = false;
            }
        }, 1000);
    } else {
        if (waitingScreen) waitingScreen.style.display = 'none';
        if (loginScreen)   loginScreen.style.display = 'block';
        if (loginBtn)      loginBtn.disabled = false;
    }
</script>

</body>
</html>
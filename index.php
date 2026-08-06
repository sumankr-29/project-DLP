<?php
session_start();

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

if (isset($_GET['lang']) && ($_GET['lang'] == 'en' || $_GET['lang'] == 'hi')) {
    $_SESSION['lang'] = $_GET['lang'];
}

require_once 'languages/' . $_SESSION['lang'] . '.php';

/** @var array $lang */

?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['school_name']; ?> - Digital Learning</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <div id="welcomePopup" class="popup-overlay">
        <div class="popup-content">
            <button class="close-popup" onclick="closePopup()">&times;</button>
            <h2 style="color: #0f172a; margin-top: 0;"><i class="fa-solid fa-graduation-cap" style="color: #f97316;"></i> <?php echo $lang['welcome_title']; ?></h2>
            <p style="color: #475569; line-height: 1.6;"><?php echo $lang['welcome_message']; ?></p>
            <p class="timer-text">Closing automatically in <span id="countdown">10</span> seconds...</p>
        </div>
    </div>

    <div class="main-wrapper">
        
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <div class="emblem-placeholder"><i class="fa-solid fa-building-columns"></i></div>
                    <div>
                        <h1><?php echo $lang['school_name']; ?></h1>
                        <p><?php echo $lang['tagline']; ?></p>
                    </div>
                </div>
                
                <div style="display:flex; gap:20px; align-items:center;">
                    
                    <div class="lang-dropdown">
                        <span class="lang-dropbtn"><i class="fa-solid fa-globe"></i> <?php echo $lang['language_menu']; ?> <i class="fa-solid fa-caret-down"></i></span>
                        <div class="lang-dropdown-content">
                            <a href="?lang=en" <?php if($_SESSION['lang']=='en') echo 'style="font-weight:bold; color:#3b82f6;"'; ?>>English</a>
                            <a href="?lang=hi" <?php if($_SESSION['lang']=='hi') echo 'style="font-weight:bold; color:#3b82f6;"'; ?>>हिंदी (Hindi)</a>
                        </div>
                    </div>

                    <a href="#login-section" class="header-login-btn"><i class="fa-solid fa-user"></i> <?php echo $lang['login_btn']; ?></a>
                </div>
            </div>
        </div>

        <div class="slider-area">
            <div class="slide fade">
                <img src="images/bus.png?q=80&w=2000&auto=format&fit=crop" alt="Students near bus">
            </div>
            <div class="slide fade">
                <img src="images/school_boy-outdoor.jpeg?q=80&w=2000&auto=format&fit=crop" alt="school boy outdoor">
            </div>
            <div class="slide fade">
                <img src="images/school_boy-group.jpeg?q=80&w=2000&auto=format&fit=crop" alt="School boy group">
            </div>
            <div class="slide fade">
                <img src="images/school_boy-room.jpeg?q=80&w=2000&auto=format&fit=crop" alt="School boy room">
            </div>
        </div>

        <div id="login-section" class="login-hint-section">
            <div class="login-box">
                <h2><i class="fa-solid fa-right-to-bracket"></i> <?php echo $lang['login_btn']; ?></h2>
                <form action="backend/login.php" method="POST">
                    <label for="role"><?php echo $lang['select_role']; ?></label>
                    <select name="role" id="role" required>
                        <option value="">-- Choose --</option>
                        <option value="admin"><?php echo $lang['admin_role']; ?></option>
                        <option value="teacher"><?php echo $lang['teacher_role']; ?></option>
                        <option value="student"><?php echo $lang['student_role']; ?></option>
                    </select>

                    <label for="username"><?php echo $lang['username']; ?></label>
                    <input type="text" name="username" id="username" required>

                    <label for="password"><?php echo $lang['password']; ?></label>
                    <input type="password" name="password" id="password" required>

                    <button type="submit"><?php echo $lang['login_btn']; ?></button>
                </form>
            </div>

            <div class="hint-box">
                <h3><i class="fa-solid fa-circle-question"></i> <?php echo $lang['how_to_login']; ?></h3>
                <p><?php echo $lang['use_creds']; ?></p>
                <ul>
                    <li><strong>Admin:</strong> <?php echo $lang['admin_hint']; ?></li>
                    <li><strong>Teacher:</strong> <?php echo $lang['teacher_hint']; ?></li>
                    <li><strong>Student:</strong> <?php echo $lang['student_hint']; ?></li>
                </ul>
                <div class="demo-creds">
                    <p><em><?php echo $lang['for_demo']; ?></em></p>
                    <p><strong>Admin:</strong> headmaster / admin123</p>
                    <p><strong>Teacher:</strong> abhay@gmail.com / AB1994MB</p>
                </div>
            </div>
        </div>
    </div> 
    
    <div class="footer">
        <div class="footer-container">
            <div class="footer-col">
                <h4><i class="fa-solid fa-location-dot"></i> <?php echo $lang['about_us']; ?></h4>
                <p><?php echo $lang['address_line1']; ?></p>
                <p style="margin-top: 10px;"><?php echo $lang['address_line2']; ?></p>
            </div>
            
            <div class="footer-col">
                <h4><i class="fa-solid fa-phone"></i> <?php echo $lang['contact_info']; ?></h4>
                <p>Phone: 01765-220XXX</p>
                <p>Email: maranga@ssc.gov.in</p>
            </div>

            <div class="footer-col">
                <h4><i class="fa-solid fa-graduation-cap"></i> <?php echo $lang['academics']; ?></h4>
                <p><?php echo $lang['classes']; ?></p>
                <p><?php echo $lang['streams']; ?></p>
                <p><?php echo $lang['staff']; ?></p>
            </div>

            <div class="footer-col">
                <h4><i class="fa-solid fa-computer"></i> <?php echo $lang['facilities']; ?></h4>
                <p><?php echo $lang['fac_1']; ?></p>
                <p><?php echo $lang['fac_2']; ?></p>
                <p><?php echo $lang['fac_3']; ?></p>
            </div>
        </div>
        <div class="footer-bottom">
            <p><?php echo $lang['footer_copy']; ?></p>
        </div>
    </div>

    <script>
        // --- Image Slider Logic ---
        var slideIndex = 1;
        showSlide(slideIndex);
        setInterval(function() {
            slideIndex++;
            showSlide(slideIndex);
        }, 4000);

        function showSlide(n) {
            var slides = document.getElementsByClassName("slide");
            if (n > slides.length) { slideIndex = 1; }
            if (n < 1) { slideIndex = slides.length; }
            for (var i = 0; i < slides.length; i++) {
                slides[i].style.display = "none";
            }
            slides[slideIndex - 1].style.display = "block";
        }

        // --- First Visit Popup Logic (10 Seconds) ---
        document.addEventListener("DOMContentLoaded", function() {
            // Check if the user has visited before
            if (!localStorage.getItem("hasVisitedMarangaDLP")) {
                let popup = document.getElementById("welcomePopup");
                popup.style.display = "flex"; // Show popup
                
                // Mark as visited so it doesn't show on refresh
                localStorage.setItem("hasVisitedMarangaDLP", "true");

                let timeLeft = 10;
                let timerSpan = document.getElementById("countdown");
                
                let timerInterval = setInterval(function() {
                    timeLeft--;
                    timerSpan.textContent = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(timerInterval);
                        closePopup();
                    }
                }, 1000); // 1000ms = 1 second
            }
        });

        function closePopup() {
            document.getElementById("welcomePopup").style.display = "none";
        }
    </script>
</body>
</html>
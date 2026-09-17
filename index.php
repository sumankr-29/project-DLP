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

    <!-- First Popup: Welcome Message -->
    <div id="welcomePopup" class="popup-overlay">
        <div class="popup-content">
            <button class="close-popup" onclick="closePopup()">&times;</button>
            <h2 style="color: #0f172a; margin-top: 0;"><i class="fa-solid fa-graduation-cap" style="color: #f97316;"></i> <?php echo $lang['welcome_title']; ?></h2>
            <p style="color: #475569; line-height: 1.6;"><?php echo $lang['welcome_message']; ?></p>
            <p class="timer-text">Closing automatically in <span id="countdown">10</span> seconds...</p>
        </div>
    </div>

    <!-- Second Popup: Disclaimer -->
    <div id="disclaimerPopup" class="popup-overlay" style="display: none;">
        <div class="popup-content">
            <button class="close-popup" onclick="closeDisclaimer()">&times;</button>
            <h2 style="color: #dc2626; margin-top: 0;"><i class="fa-solid fa-triangle-exclamation"></i> Disclaimer</h2>
            <p style="color: #b91c1c; font-weight: bold; line-height: 1.6;">
                This website is underwork Project and nothing to do with reality.
            </p>
            <p style="color: #b91c1c; font-weight: bold; line-height: 1.6;">
                यह वेबसाइट एक अधूरा प्रोजेक्ट है और वास्तविकता से इसका कोई लेना-देना नहीं है।
            </p>
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

                    <!-- Changed: Header login button now routes to secure_login.php -->
                    <a href="secure_login.php" class="header-login-btn"><i class="fa-solid fa-lock"></i> <?php echo $lang['login_btn']; ?></a>
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

        <!-- Login section removed entirely, header button now takes you to secure_login.php -->
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

        // --- Popup Logic (Welcome + Disclaimer) ---
        document.addEventListener("DOMContentLoaded", function() {
            if (!localStorage.getItem("hasVisitedMarangaDLP")) {
                let popup = document.getElementById("welcomePopup");
                popup.style.display = "flex";
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
                }, 1000);
            } else {
                showDisclaimer();
            }
        });

        function closePopup() {
            document.getElementById("welcomePopup").style.display = "none";
            showDisclaimer();
        }

        function showDisclaimer() {
            document.getElementById("disclaimerPopup").style.display = "flex";
        }

        function closeDisclaimer() {
            document.getElementById("disclaimerPopup").style.display = "none";
        }
    </script>
</body>
</html>
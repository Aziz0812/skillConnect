<?php
session_start();
require 'db.php';
// ✅ If user is already logged in (session active), redirect to dashboard immediately
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'provider') {
        header("Location: provider.php");
        exit();
    } elseif ($_SESSION['role'] === 'client') {
        header("Location: client.php");
        exit();
    }
}


// ✅ Auto-login using remember_token (same logic as provider.php)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT ID, Role, FName, LName, remember_expiry FROM users WHERE remember_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if ((int)$user['remember_expiry'] > time()) {
            // ✅ Restore session
            $_SESSION['user_id'] = $user['ID'];
            $_SESSION['role'] = $user['Role'];
            $_SESSION['FName'] = $user['FName'];
            $_SESSION['LName'] = $user['LName'];
            $_SESSION['name'] = $user['FName'] . ' ' . $user['LName'];

            // ✅ Redirect user automatically to their dashboard
            if ($user['Role'] === 'provider') {
                header("Location: provider.php");
                exit();
            } elseif ($user['Role'] === 'client') {
                header("Location: client.php");
                exit();
            }
        } else {
            // ❌ Token expired
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SkillConnect | Find Trusted Local Help</title>
  <link rel="stylesheet" href="styles/style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>

<header class="navbar">
    <div class="logo">
        <img src="imge/logo-.png" alt="SkillConnect Logo" class="main-log"> SkillConnect
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="profile-dropdown">
            <span class="user-name">Hi, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="logout.php" style="margin-left:10px; color:red;">Logout</a>
        </div>
    <?php else: ?>
        <a href="login.php" class="signin">Already a member? <span>Sign in</span></a>
    <?php endif; ?>
</header>


  <section class="hero" id="hero" tabindex="-1">
    <div class="hero-content">
      <div class="hero-text">
        <h1>Your Trusted Local Experts for Home Repairs & Services</h1>
        <p>From home repairs to tech setups and deep cleaning, SkillConnect helps you find skilled locals you can count on to get things done right.</p>
        <button class="primary-btn" onclick="document.getElementById('role-section').scrollIntoView({ behavior: 'smooth' });">Get Started</button>
        <p class="learn-more"><a href="#how-it-works">Learn how it works ↓</a></p>
      </div>
      <div class="hero-image">
        <img src="imge/png-transparent-plumbing-maintenance-handyman-plumber-miscellaneous-hand-home-repair-thumbnail_prev_ui.png" alt="Service Worker" />
      </div>
    </div>
  </section>

  <!-- Role Selection Section -->
  <section class="role-select" id="role-section">
    <h2>Choose Your Role</h2>
    <p>Let us guide your journey — whether you need help or want to offer your expertise.</p>
    <div class="role-options">
      <div class="role-card" onclick="selectRole('client')" id="client">
        <h3>🙋 I Need a Service</h3>
        <p>Search and connect with local experts in home repairs, maintenance, and more.</p>
      </div>
      <div class="role-card" onclick="selectRole('provider')" id="provider">
        <h3>👷 I Want to Offer My Skills</h3>
        <p>Sign up as a provider and gain access to job listings and local client requests.</p>
      </div>
    </div>
    <button class="primary-btn" id="continue-btn" style="display:none; margin-top: 2rem;">Continue</button>
  </section>

  

  <!-- How It Works Section -->
  <section class="how-it-works" id="how-it-works">
    <h2>How SkillConnect Works</h2>
    <div class="steps">
      <div class="step">
        <div class="icon">📝</div>
        <h3>Create an Account</h3>
        <p>Sign up as a customer or a verified service provider in just a few steps.</p>
      </div>
      <div class="step">
        <div class="icon">📋</div>
        <h3>Post a Job or Offer a Skill</h3>
        <p>Submit service requests or advertise your skills based on your location.</p>
      </div>
      <div class="step">
        <div class="icon">🤝</div>
        <h3>Connect, Chat & Complete</h3>
        <p>Communicate securely and complete services with transparency and ease.</p>
      </div>
    </div>
  </section>



    <!-- Preview of Providers -->
  <section class="provider-preview">
    <h2>Explore Service Providers in Your Area</h2>
    <p>Browse a few local experts. To book, please sign in.</p>
    <div class="provider-cards">
      <div class="provider-card">
        <img src="imge/sample-avatar1.png" alt="John D.">
        <h4>John D. - Electrician</h4>
        <p class="blurred">📍 Quezon City | 💰 PHP 600/hr</p>
        <p class="blurred">Contact: john.electrician@email.com</p>
        <div class="overlay">Login to view details</div>
      </div>
      <div class="provider-card">
        <img src="imge/sample-avatar2.png" alt="Maria P.">
        <h4>Maria P. - House Cleaning</h4>
        <p class="blurred">📍 Makati | 💰 PHP 450/hr</p>
        <p class="blurred">Contact: maria.clean@email.com</p>
        <div class="overlay">Login to view details</div>
      </div>
      <div class="provider-card">
        <img src="imge/sample-avatar3.png" alt="Jake R.">
        <h4>Jake R. - Plumbing</h4>
        <p class="blurred">📍 Pasig | 💰 PHP 700/hr</p>
        <p class="blurred">Contact: jakepipes@email.com</p>
        <div class="overlay">Login to view details</div>
      </div>
    </div>
  </section>



  <!-- Popular Categories -->
  <section class="categories">
    <h2>Popular Categories</h2>
    <div class="category-grid">
      <div class="category-item">🔧 Plumbing</div>
      <div class="category-item">💡 Electrical</div>
      <div class="category-item">🧹 House Cleaning</div>
      <div class="category-item">🖥️ Tech Support</div>
      <div class="category-item">🛠️ General Repairs</div>
      <div class="category-item">🛍️ Errands</div>
      <div class="category-item">📦 Moving Help</div>
      <div class="category-item">🐾 Pet Care</div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <p>&copy; 2025 SkillConnect. All rights reserved.</p>
    <div class="footer-links">
      <a href="#">About</a>
      <a href="#">Contact</a>
      <a href="#">Terms</a>
      <a href="#">Privacy</a>
    </div>
  </footer>

  <script src="js/script.js"></script>

</body>
</html>
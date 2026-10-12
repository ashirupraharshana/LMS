<?php

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax'
    ]);

    session_start();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$oldEmail = '';
$pdo = null;

// Connect to the database
try {
    require_once __DIR__ . '/config/database.php';
} catch (Throwable $e) {
    error_log('LMS database connection error: ' . $e->getMessage());
    $error = 'The login service is temporarily unavailable.';
}

// Redirect users who have already logged in
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: pages/admin/dashboard.php');
        exit;
    }

    if (($_SESSION['role'] ?? '') === 'student') {
        header('Location: pages/student/dashboard.php');
        exit;
    }
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $oldEmail = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $token = $_POST['csrf_token'] ?? '';

    // Validate CSRF token
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } elseif (!filter_var($oldEmail, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please enter a valid email address and password.';
    } elseif (!$pdo instanceof PDO) {
        if ($error === '') {
            $error = 'Unable to connect to the login service. Please try again later.';
        }
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, full_name, email, password_hash, role, status
                 FROM users
                 WHERE email = :email
                 LIMIT 1'
            );

            $stmt->execute(['email' => $oldEmail]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify the stored password hash
            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Invalid email address or password.';
            } elseif ($user['status'] !== 'active') {
                if ($user['status'] === 'pending') {
                    $error = 'Your account is awaiting administrator approval.';
                } else {
                    $error = 'Your account is inactive. Please contact the administrator.';
                }
            } elseif (!in_array($user['role'], ['admin', 'student'], true)) {
                $error = 'This account does not have a valid access role.';
            } else {
                // Prevent session fixation
                session_regenerate_id(true);

                // Store authenticated user information
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Generate a fresh CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // Redirect based on user role
                if ($user['role'] === 'admin') {
                    header('Location: pages/admin/dashboard.php');
                    exit;
                }

                header('Location: pages/student/dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('LMS login query error: ' . $e->getMessage());
            $error = 'Something went wrong. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#111827">

    <title>Login | Student Management System</title>

    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="public/css/login.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <main class="auth-layout">

        <!-- Premium brand panel -->
        <aside class="auth-showcase">

            <div class="showcase-glow glow-one"></div>
            <div class="showcase-glow glow-two"></div>
            <div class="showcase-grid"></div>

            <a href="index.php" class="brand brand-light">
                <span class="brand-icon">
                    <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
                </span>

                <span class="brand-text">
                    <strong>Student</strong>
                    <span>Management System</span>
                </span>
            </a>

            <div class="showcase-content">

                <div class="showcase-kicker">
                    <span class="online-dot"></span>
                    YOUR LEARNING PLATFORM
                </div>

                <h2>
                    Your journey.<br>
                    Your ambition.<br>
                    <span>Your future.</span>
                </h2>

                <p class="showcase-description">
                    Everything you need to stay connected with your
                    academic journey, manage your account, and move
                    forward with confidence.
                </p>

                <div class="feature-list">

                    <div class="feature-item">
                        <span class="feature-icon">
                            <i class="fa-solid fa-book-open" aria-hidden="true"></i>
                        </span>

                        <div>
                            <strong>Your learning, organized</strong>
                            <span>Keep your academic experience connected.</span>
                        </div>
                    </div>

                    <div class="feature-item">
                        <span class="feature-icon">
                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        </span>

                        <div>
                            <strong>Secure account access</strong>
                            <span>Your account starts with secure authentication.</span>
                        </div>
                    </div>

                    <div class="feature-item">
                        <span class="feature-icon">
                            <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                        </span>

                        <div>
                            <strong>Move forward with confidence</strong>
                            <span>Access the tools available to your account.</span>
                        </div>
                    </div>

                </div>

                <div class="showcase-quote">
                    <span class="quote-line"></span>
                    <p>
                        Every great achievement begins with a single step.
                    </p>
                </div>

            </div>

            <div class="showcase-bottom">
                <span>
                    <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
                    A dedicated space for your academic journey
                </span>

                <span class="showcase-version">STUDENT PORTAL</span>
            </div>

            <!-- Decorative floating card -->
            <div class="floating-card" aria-hidden="true">
                <span class="floating-icon">
                    <i class="fa-solid fa-lock"></i>
                </span>

                <span class="floating-copy">
                    <strong>Secure access</strong>
                    <small>Your account, protected</small>
                </span>

                <i class="fa-solid fa-circle-check floating-check"></i>
            </div>

        </aside>


        <!-- Login section -->
        <section class="auth-main">

            <div class="auth-topbar">
                <span class="topbar-label">
                    <span class="topbar-dot"></span>
                    STUDENT PORTAL
                </span>

                <a href="register.php" class="create-account-link">
                    New here?
                    <strong>Create account</strong>
                    <i class="fa-solid fa-arrow-up-right-from-square"
                        aria-hidden="true"></i>
                </a>
            </div>


            <div class="auth-content">

                <div class="login-heading">

                    <div class="login-symbol">
                        <i class="fa-solid fa-user-graduate" aria-hidden="true"></i>
                    </div>

                    <div class="heading-eyebrow">
                        WELCOME BACK
                    </div>

                    <h1>Sign in to your account</h1>

                    <p>
                        Enter your credentials below to continue to your portal.
                    </p>

                </div>

                <!-- Existing login form and CSRF protection -->
                <form action="login.php" method="POST" class="login-form">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                                    $_SESSION['csrf_token'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>">

                    <!-- Email -->
                    <div class="form-group">

                        <label for="email">Email address</label>

                        <div class="input-wrapper">

                            <i class="fa-regular fa-envelope input-icon"
                                aria-hidden="true"></i>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="field-input"
                                placeholder="you@example.com"
                                value="<?= htmlspecialchars(
                                            $oldEmail,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                autocomplete="username"
                                maxlength="190"
                                required>

                        </div>

                    </div>


                    <!-- Password -->
                    <div class="form-group">

                        <div class="password-header">
                            <label for="password">Password</label>

                            <a href="forgot-password.php" class="forgot-link">
                                Forgot password?
                            </a>
                        </div>

                        <div class="input-wrapper">

                            <i class="fa-solid fa-lock input-icon"
                                aria-hidden="true"></i>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="field-input"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required>

                            <button
                                type="button"
                                id="togglePassword"
                                class="password-toggle"
                                aria-label="Show password"
                                aria-pressed="false">

                                <i class="fa-regular fa-eye"
                                    aria-hidden="true"></i>

                            </button>

                        </div>

                    </div>


                    <!-- Submit -->
                    <button type="submit" class="login-button">

                        <span>Sign in to your account</span>

                        <span class="button-icon">
                            <i class="fa-solid fa-arrow-right"
                                aria-hidden="true"></i>
                        </span>

                    </button>

                </form>


                <!-- Security information -->
                <div class="security-card">

                    <span class="security-icon">
                        <i class="fa-solid fa-shield-halved"
                            aria-hidden="true"></i>
                    </span>

                    <div>
                        <strong>Secure sign-in</strong>
                        <p>
                            Your account is protected by secure authentication.
                        </p>
                    </div>

                    <i class="fa-solid fa-check security-check"
                        aria-hidden="true"></i>

                </div>


                <div class="login-divider">
                    <span>YOUR ACADEMIC JOURNEY STARTS HERE</span>
                </div>


                <a href="index.php" class="back-home">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Back to home
                </a>

            </div>


            <footer class="auth-footer">
                <span>
                    &copy; <?= date('Y') ?> Student Management System
                </span>

                <span class="footer-separator">&bull;</span>

                <span>All rights reserved.</span>
            </footer>

        </section>

    </main>




    <script>
        // Existing password visibility functionality
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');
        const passwordIcon = togglePassword.querySelector('i');

        togglePassword.addEventListener('click', function() {
            const showPassword = passwordInput.type === 'password';

            passwordInput.type = showPassword ? 'text' : 'password';

            passwordIcon.classList.toggle('fa-eye', !showPassword);
            passwordIcon.classList.toggle('fa-eye-slash', showPassword);

            this.setAttribute('aria-pressed', String(showPassword));
            this.setAttribute(
                'aria-label',
                showPassword ? 'Hide password' : 'Show password'
            );
        });
    </script>

    <?php if (isset($error) && $error !== ''): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Login unsuccessful',
                text: <?= json_encode(
                            $error,
                            JSON_HEX_TAG |
                                JSON_HEX_APOS |
                                JSON_HEX_QUOT |
                                JSON_HEX_AMP
                        ) ?>,
                confirmButtonText: 'Try again',
                confirmButtonColor: '#5065e9',
                allowOutsideClick: false,
                customClass: {
                    popup: 'login-swal-popup',
                    confirmButton: 'login-swal-confirm'
                }
            });
        </script>
    <?php endif; ?>

    <?php if (
        isset($_GET['logged_out']) &&
        $_GET['logged_out'] === '1'
    ): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Successfully signed out!',
                text: 'You have been logged out securely.',
                confirmButtonText: 'Continue',
                confirmButtonColor: '#5065e9',
                timer: 3000,
                timerProgressBar: true,
                customClass: {
                    popup: 'login-swal-popup',
                    confirmButton: 'login-swal-confirm'
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>
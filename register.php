<?php
session_start();

require_once __DIR__ . '/config/database.php';

// Create a CSRF token for the registration form.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $token = $_POST['csrf_token'] ?? '';

    // Validate CSRF token.
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } elseif ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $error = 'Please complete all required fields.';
    } elseif (strlen($fullName) > 120) {
        $error = 'Full name must not exceed 120 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Check whether this email is already registered.
            $checkEmail = $pdo->prepare(
                'SELECT id FROM users WHERE email = :email LIMIT 1'
            );
            $checkEmail->execute(['email' => $email]);

            if ($checkEmail->fetch(PDO::FETCH_ASSOC)) {
                $error = 'This email address is already registered.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Save only columns that exist in the users table.
                // New student accounts remain pending until admin approval.
                $insertUser = $pdo->prepare(
                    "INSERT INTO users (full_name, email, password_hash, role, status)
                     VALUES (:full_name, :email, :password_hash, 'student', 'pending')"
                );
                $insertUser->execute([
                    'full_name' => $fullName,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                ]);

                // Rotate the CSRF token after successful registration.
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                $fullName = '';
                $email = '';
                $success = 'Registration submitted successfully! Your account is awaiting administrator approval.';
            }
        } catch (PDOException $e) {
            error_log('LMS registration error: ' . $e->getMessage());

            if ($e->getCode() === '23000') {
                $error = 'This email address is already registered.';
            } else {
                $error = 'Registration failed. Please try again later.';
            }
        }
    }
}

function escapeOutput($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2563eb">
    <title>Student Registration | LMS</title>
    <link rel="stylesheet" href="public/css/register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <main class="register-page">
        <section class="register-container">
            <a href="index.php" class="brand">
                <span class="brand-icon"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></span>
                <span class="brand-text">
                    <strong>Student</strong>
                    <span>Management System</span>
                </span>
            </a>

            <div class="register-card">
                <div class="register-header">
                    <div class="register-icon"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></div>
                    <h1>Create Account</h1>
                    <p>Register as a student to get started.</p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="message error-message" role="alert">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <span><?= escapeOutput($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success !== ''): ?>
                    <div class="message success-message" role="status">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <div>
                            <span><?= escapeOutput($success) ?></span>
                            <p>Once your account is activated, you can <a href="login.php">log in here</a>.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST" class="register-form">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapeOutput($_SESSION['csrf_token']) ?>">

                    <div class="form-group">
                        <label for="full_name">Full Name <span>*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-user field-icon" aria-hidden="true"></i>
                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                placeholder="Enter your full name"
                                value="<?= escapeOutput($fullName) ?>"
                                maxlength="120"
                                autocomplete="name"
                                required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span>*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope field-icon" aria-hidden="true"></i>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Enter your email address"
                                value="<?= escapeOutput($email) ?>"
                                maxlength="190"
                                autocomplete="email"
                                required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password <span>*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock field-icon" aria-hidden="true"></i>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Create a password"
                                minlength="8"
                                autocomplete="new-password"
                                required>
                            <button
                                type="button"
                                class="password-toggle"
                                data-target="password"
                                aria-label="Show password"
                                aria-pressed="false">
                                <i class="fa-regular fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <small class="field-hint">Use at least 8 characters.</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span>*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock field-icon" aria-hidden="true"></i>
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Re-enter your password"
                                minlength="8"
                                autocomplete="new-password"
                                required>
                            <button
                                type="button"
                                class="password-toggle"
                                data-target="confirm_password"
                                aria-label="Show password"
                                aria-pressed="false">
                                <i class="fa-regular fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="register-button">
                        <span>Create Student Account</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                <div class="login-link">Already have an account? <a href="login.php">Login</a></div>
            </div>

            <a href="index.php" class="back-home">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Back to Home
            </a>

            <p class="copyright">
                &copy; <?= date('Y') ?> Student Management System. All rights reserved.
            </p>
        </section>
    </main>

    <script>
        document.querySelectorAll('.password-toggle').forEach((button) => {
            button.addEventListener('click', function () {
                const input = document.getElementById(this.dataset.target);
                const icon = this.querySelector('i');
                const showPassword = input.type === 'password';

                input.type = showPassword ? 'text' : 'password';
                icon.classList.toggle('fa-eye', !showPassword);
                icon.classList.toggle('fa-eye-slash', showPassword);
                this.setAttribute('aria-pressed', String(showPassword));
                this.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
            });
        });

        const registrationForm = document.querySelector('.register-form');
        registrationForm.addEventListener('submit', function (event) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Passwords do not match.');
                document.getElementById('confirm_password').focus();
            }
        });
    </script>
</body>
</html>

```php
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Student Management System</title>

    <!-- Main CSS -->
    <link rel="stylesheet" href="css/login.css">

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

</head>

<body>

    <main class="login-page">

        <div class="login-container">

            <!-- Login Header -->
            <div class="login-header">

                <div class="login-icon">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>

                <h1>Welcome Back</h1>

                <p>
                    Login to your account to continue
                </p>

            </div>


            <!-- Login Form -->
            <form action="" method="POST" class="login-form">

                <!-- Email -->
                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <div class="input-wrapper">

                        <i class="fa-solid fa-envelope input-left-icon"></i>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            autocomplete="email"
                            required>

                    </div>

                </div>


                <!-- Password -->
                <div class="form-group">

                    <div class="password-header">

                        <label for="password">
                            Password
                        </label>

                        <a
                            href="forgot-password.php"
                            class="forgot-link">

                            <i class="fa-solid fa-key"></i>
                            <span>Forgot Password?</span>

                        </a>

                    </div>


                    <div class="input-wrapper">

                        <i class="fa-solid fa-lock input-left-icon"></i>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required>

                        <button
                            type="button"
                            id="togglePassword"
                            class="password-toggle"
                            aria-label="Show password">

                            <i class="fa-solid fa-eye"></i>

                        </button>

                    </div>

                </div>


                <!-- Login Button -->
                <button
                    type="submit"
                    class="login-button">

                    <i class="fa-solid fa-right-to-bracket"></i>

                    <span>Login</span>

                </button>

            </form>


            <!-- Back to Home -->
            <div class="back-home">

                <a href="index.php">

                    <i class="fa-solid fa-arrow-left"></i>

                    <span>Back to Home</span>

                </a>

            </div>

        </div>

    </main>


    <!-- Password Visibility -->
    <script>

        const passwordInput = document.getElementById("password");
        const togglePassword = document.getElementById("togglePassword");

        togglePassword.addEventListener("click", function () {

            const icon = this.querySelector("i");

            if (passwordInput.type === "password") {

                passwordInput.type = "text";

                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");

                this.setAttribute("aria-label", "Hide password");

            } else {

                passwordInput.type = "password";

                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");

                this.setAttribute("aria-label", "Show password");

            }

        });

    </script>

</body>

</html>
```

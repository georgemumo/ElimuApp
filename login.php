<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once "db.php";
require_once __DIR__ . "/message_helpers.php";

$message = "";

/*
|--------------------------------------------------------------------------
| LOGIN PROCESS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $login = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($login) || empty($password)) {

        $message = "Please enter your email/phone and password.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | HARD-CODED ADMIN LOGIN
        |--------------------------------------------------------------------------
        | These credentials are checked before the database login.
        |--------------------------------------------------------------------------
        */

        $adminLogin = "admin@school.com";
        $adminPassword = "Admin@123";

        if (
            strtolower($login) === strtolower($adminLogin) &&
            $password === $adminPassword
        ) {

            $adminStatement = $conn->prepare(
                "SELECT user_id FROM users WHERE email = ? LIMIT 1"
            );
            $adminStatement->execute([$adminLogin]);
            $adminUserId = (int)$adminStatement->fetchColumn();

            if (!$adminUserId) {
                $adminStatement = $conn->prepare(
                    "INSERT INTO users (email, password_hash, role, is_verified, status)
                     VALUES (?, ?, 'SCHOOL_ADMIN', 1, 'ACTIVE')"
                );
                $adminStatement->execute([
                    $adminLogin,
                    password_hash($adminPassword, PASSWORD_DEFAULT)
                ]);
                $adminUserId = (int)$conn->lastInsertId();
            }

            /*
            |--------------------------------------------------------------------------
            | SECURITY
            |--------------------------------------------------------------------------
            */

            session_regenerate_id(true);

            /*
            |--------------------------------------------------------------------------
            | STORE ADMIN SESSION
            |--------------------------------------------------------------------------
            */

            $_SESSION["user_id"] = $adminUserId;
            $_SESSION["role"] = "SCHOOL_ADMIN";
            $_SESSION["email"] = $adminLogin;
            $_SESSION["phone"] = "";
            $_SESSION["logged_in"] = true;

            if (isset($user["role"], $user["user_id"])
                && in_array($user["role"], ["PARENT", "STUDENT", "TEACHER"], true)) {
                try {
                    syncUserClassGroups($conn, (int) $user["user_id"], (string) $user["role"]);
                } catch (Throwable $exception) {
                    error_log("Class message group sync failed: " . $exception->getMessage());
                }
            }
            $_SESSION["admin"] = true;

            /*
            |--------------------------------------------------------------------------
            | GO TO ADMIN DASHBOARD
            |--------------------------------------------------------------------------
            */

            header("Location: admin.php");
            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | NORMAL DATABASE LOGIN
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT
                user_id,
                phone_number,
                email,
                password_hash,
                role,
                status,
                is_verified
            FROM users
            WHERE email = ?
               OR phone_number = ?
            LIMIT 1
        ");

        $stmt->execute([
            $login,
            $login
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$user) {

            $message = "Invalid login details.";

        } elseif (
            !password_verify(
                $password,
                $user["password_hash"]
            )
        ) {

            $message = "Invalid login details.";

        } elseif ($user["status"] !== "ACTIVE") {

            $message = "Your account is not active.";

        } elseif ((int)$user["is_verified"] !== 1) {

            $_SESSION["otp_user_id"] = $user["user_id"];

            $message = "Please verify your email before logging in.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | SECURITY
            |--------------------------------------------------------------------------
            */

            session_regenerate_id(true);


            /*
            |--------------------------------------------------------------------------
            | STORE USER SESSION
            |--------------------------------------------------------------------------
            */

            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["phone"] = $user["phone_number"];
            $_SESSION["logged_in"] = true;


            /*
            |--------------------------------------------------------------------------
            | REDIRECT ACCORDING TO ROLE
            |--------------------------------------------------------------------------
            */

            switch ($user["role"]) {

                case "SUPER_ADMIN":

                    header("Location: admin.php");
                    exit;

                case "SCHOOL_ADMIN":

                    header("Location: admin.php");
                    exit;

                case "TEACHER":

                    header("Location: teacher.php");
                    exit;

                case "PARENT":

                    header("Location: parent.php");
                    exit;

                case "STUDENT":

                    header("Location: student.php");
                    exit;

                default:

                    $message = "Your account has an invalid role.";

                    session_unset();
                    session_destroy();

                    break;
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

```
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Login - MySchool</title>


<style>

    /* =========================================================
       RESET
    ========================================================= */

    * {
        box-sizing: border-box;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        width: 100%;
        min-height: 100%;
    }


    /* =========================================================
       PAGE
    ========================================================= */

    body {

        min-height: 100vh;

        font-family:
            "Segoe UI",
            Arial,
            Helvetica,
            sans-serif;

        color: #123452;

        background-image:
            linear-gradient(
                rgba(8, 43, 70, 0.78),
                rgba(8, 43, 70, 0.78)
            ),
            url("https://images.unsplash.com/photo-1580582932707-520aed937b7b?auto=format&fit=crop&w=2000&q=85");

        background-size: cover;
        background-position: center;
        background-attachment: fixed;

        display: flex;
        align-items: center;
        justify-content: center;

        padding: 30px 20px;
    }


    /* =========================================================
       MAIN LOGIN CARD
    ========================================================= */

    .auth-container {
        width: 100%;
        max-width: 620px;
    }


    .auth-box {

        width: 100%;

        background: rgba(255, 255, 255, 0.97);

        border-radius: 24px;

        padding: 34px 42px 30px;

        box-shadow:
            0 30px 80px rgba(0, 0, 0, 0.30),
            0 8px 25px rgba(0, 0, 0, 0.10);

        border: 1px solid rgba(255, 255, 255, 0.85);
    }


    /* =========================================================
       LOGO
    ========================================================= */

    .logo-area {

        text-align: center;

        margin-bottom: 26px;
    }


    .logo-icon {

        width: 56px;
        height: 56px;

        margin: 0 auto 11px;

        border-radius: 17px;

        background:
            linear-gradient(
                135deg,
                #2876f0,
                #0798c8
            );

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 26px;

        box-shadow:
            0 8px 20px rgba(
                25,
                119,
                224,
                0.25
            );
    }


    .logo-title {

        margin: 0;

        color: #102f4c;

        font-size: 27px;

        line-height: 1.1;

        font-weight: 800;

        letter-spacing: -0.8px;
    }


    .logo-subtitle {

        margin: 6px 0 0;

        color: #71869a;

        font-size: 12px;
    }


    /* =========================================================
       WELCOME
    ========================================================= */

    .login-heading {

        text-align: center;

        margin-bottom: 24px;
    }


    .login-heading h1 {

        margin: 0 0 6px;

        color: #153a5c;

        font-size: 24px;

        font-weight: 800;

        letter-spacing: -0.5px;
    }


    .login-heading p {

        margin: 0;

        color: #71869a;

        font-size: 12px;
    }


    /* =========================================================
       ERROR MESSAGE
    ========================================================= */

    .message {

        padding: 12px 14px;

        margin-bottom: 18px;

        border-radius: 10px;

        font-size: 12px;

        line-height: 1.5;
    }


    .message.error {

        background: #fff1f1;

        border: 1px solid #ffd2d2;

        color: #b42323;
    }


    /* =========================================================
       FORM
    ========================================================= */

    .login-form {

        width: 100%;

        max-width: 500px;

        margin: 0 auto;
    }


    /* =========================================================
       FIELD
    ========================================================= */

    .field {

        margin-bottom: 19px;
    }


    .field-label-row {

        display: flex;

        align-items: center;

        justify-content: space-between;

        margin-bottom: 7px;
    }


    .field label {

        display: block;

        margin: 0;

        color: #173b5d;

        font-size: 12px;

        font-weight: 700;
    }


    .required {

        color: #e55353;
    }


    /* =========================================================
       FORGOT PASSWORD
    ========================================================= */

    .forgot-link {

        color: #1874d1;

        font-size: 11px;

        font-weight: 700;

        text-decoration: none;
    }


    .forgot-link:hover {

        text-decoration: underline;
    }


    /* =========================================================
       INPUT WRAPPER
    ========================================================= */

    .input-wrap {

        position: relative;

        width: 100%;
    }


    /* =========================================================
       INPUT ICON
    ========================================================= */

    .input-icon {

        position: absolute;

        left: 15px;

        top: 50%;

        transform: translateY(-50%);

        width: 18px;
        height: 18px;

        display: flex;

        align-items: center;

        justify-content: center;

        color: #7d91a5;

        pointer-events: none;

        z-index: 2;
    }


    .input-icon svg {

        width: 16px;

        height: 16px;
    }


    /* =========================================================
       INPUT
    ========================================================= */

    .field input {

        width: 100%;

        height: 48px;

        border: 1px solid #cbd9e7;

        border-radius: 11px;

        background: #ffffff;

        color: #183a59;

        padding: 0 15px 0 43px;

        font-size: 13px;

        outline: none;

        transition:
            border-color 0.2s ease,
            box-shadow 0.2s ease,
            background 0.2s ease;
    }


    .field input::placeholder {

        color: #9aaabd;
    }


    .field input:focus {

        border-color: #2878ed;

        background: #ffffff;

        box-shadow:
            0 0 0 3px rgba(
                40,
                120,
                237,
                0.12
            );
    }


    /* =========================================================
       PASSWORD INPUT
    ========================================================= */

    .password-input {

        padding-right: 48px !important;
    }


    /* =========================================================
       EYE BUTTON
    ========================================================= */

    .password-toggle {

        position: absolute;

        right: 8px;

        top: 50%;

        transform: translateY(-50%);

        width: 35px;
        height: 35px;

        border: none;

        background: transparent;

        border-radius: 8px;

        display: flex;

        align-items: center;

        justify-content: center;

        cursor: pointer;

        color: #70869b;

        transition:
            background 0.2s ease,
            color 0.2s ease;

        z-index: 5;
    }


    .password-toggle:hover {

        background: #edf4fb;

        color: #176fd0;
    }


    .password-toggle svg {

        width: 19px;

        height: 19px;

        pointer-events: none;
    }


    /* =========================================================
       LOGIN BUTTON
    ========================================================= */

    .login-button {

        position: relative;

        overflow: hidden;

        width: 100%;

        height: 50px;

        margin-top: 5px;

        border: none;

        border-radius: 12px;

        background:
            linear-gradient(
                100deg,
                #2869ee 0%,
                #167fe0 45%,
                #079bc5 100%
            );

        color: #ffffff;

        font-size: 13px;

        font-weight: 800;

        letter-spacing: 0.1px;

        cursor: pointer;

        box-shadow:
            0 8px 18px rgba(
                31,
                111,
                224,
                0.25
            );

        transition:
            transform 0.2s ease,
            box-shadow 0.2s ease;
    }


    .login-button::before {

        content: "";

        position: absolute;

        top: 0;

        left: -100%;

        width: 60%;

        height: 100%;

        background:
            linear-gradient(
                90deg,
                transparent,
                rgba(255,255,255,0.20),
                transparent
            );

        transform: skewX(-20deg);

        transition: left 0.6s ease;
    }


    .login-button:hover::before {

        left: 140%;
    }


    .login-button:hover {

        transform: translateY(-2px);

        box-shadow:
            0 12px 25px rgba(
                31,
                111,
                224,
                0.32
            );
    }


    .login-button:active {

        transform: translateY(0);

        box-shadow:
            0 6px 14px rgba(
                31,
                111,
                224,
                0.22
            );
    }


    .button-content {

        position: relative;

        z-index: 2;

        display: flex;

        align-items: center;

        justify-content: center;

        gap: 8px;
    }


    .button-icon {

        width: 17px;

        height: 17px;
    }


    /* =========================================================
       CREATE ACCOUNT
    ========================================================= */

    .switch-link {

        text-align: center;

        margin: 22px 0 0;

        color: #71869a;

        font-size: 12px;
    }


    .switch-link a {

        color: #176fd0;

        font-weight: 800;

        text-decoration: none;
    }


    .switch-link a:hover {

        text-decoration: underline;
    }


    /* =========================================================
       SECURITY NOTE
    ========================================================= */

    .security-note {

        display: flex;

        align-items: center;

        justify-content: center;

        gap: 6px;

        margin-top: 15px;

        color: #8798a8;

        font-size: 10px;
    }


    .security-note svg {

        width: 13px;

        height: 13px;
    }


    /* =========================================================
       AUTOFILL
    ========================================================= */

    input:-webkit-autofill,
    input:-webkit-autofill:hover,
    input:-webkit-autofill:focus {

        -webkit-text-fill-color: #183a59;

        -webkit-box-shadow:
            0 0 0 1000px #ffffff inset;

        box-shadow:
            0 0 0 1000px #ffffff inset;

        transition:
            background-color 5000s ease-in-out 0s;
    }


    /* =========================================================
       MOBILE
    ========================================================= */

    @media (max-width: 650px) {

        body {

            padding: 20px 12px;

            background-attachment: scroll;
        }


        .auth-container {

            max-width: 100%;
        }


        .auth-box {

            padding: 28px 22px 26px;

            border-radius: 20px;
        }


        .login-form {

            max-width: 100%;
        }


        .logo-title {

            font-size: 25px;
        }


        .login-heading h1 {

            font-size: 22px;
        }
    }


    @media (max-width: 400px) {

        .auth-box {

            padding:
                25px 17px 24px;
        }


        .field input {

            height: 47px;
        }
    }

</style>
```

</head>

<body>

<div class="auth-container">

```
<div class="auth-box">


    <!-- =====================================================
         LOGO
    ====================================================== -->

    <div class="logo-area">

        <div class="logo-icon">
            🎓
        </div>

        <h1 class="logo-title">
            MySchool
        </h1>

        <p class="logo-subtitle">
            School Management System
        </p>

    </div>


    <!-- =====================================================
         WELCOME
    ====================================================== -->

    <div class="login-heading">

        <h1>
            Welcome Back
        </h1>

        <p>
            Sign in to your account to continue.
        </p>

    </div>


    <!-- =====================================================
         ERROR MESSAGE
    ====================================================== -->

    <?php if (!empty($message)): ?>

        <div class="message error">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         LOGIN FORM
    ====================================================== -->

    <form
        method="POST"
        action=""
        class="login-form"
    >


        <!-- EMAIL / PHONE -->

        <div class="field">

            <div class="field-label-row">

                <label for="login">

                    Email Address or Phone Number

                    <span class="required">*</span>

                </label>

            </div>


            <div class="input-wrap">


                <span class="input-icon">

                    <!-- envelope -->

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <rect
                            x="3"
                            y="5"
                            width="18"
                            height="14"
                            rx="2"
                        />

                        <path
                            d="M3 7l9 6 9-6"
                        />

                    </svg>

                </span>


                <input
                    type="text"
                    id="login"
                    name="login"
                    placeholder="Enter your email or phone number"
                    value="<?= htmlspecialchars(
                        $_POST["login"] ?? ""
                    ) ?>"
                    autocomplete="username"
                    required
                >

            </div>

        </div>


        <!-- PASSWORD -->

        <div class="field">


            <div class="field-label-row">

                <label for="password">

                    Password

                    <span class="required">*</span>

                </label>


                <a
                    href="#"
                    class="forgot-link"
                    onclick="alert('Please contact the school administrator to reset your password.'); return false;"
                >

                    Forgot password?

                </a>

            </div>


            <div class="input-wrap">


                <span class="input-icon">

                    <!-- lock -->

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <rect
                            x="4"
                            y="10"
                            width="16"
                            height="11"
                            rx="2"
                        />

                        <path
                            d="M8 10V7a4 4 0 0 1 8 0v3"
                        />

                    </svg>

                </span>


                <input
                    type="password"
                    id="password"
                    name="password"
                    class="password-input"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >


                <!-- =================================================
                     EYE BUTTON
                ================================================== -->

                <button
                    type="button"
                    class="password-toggle"
                    id="passwordToggle"
                    aria-label="Show password"
                    title="Show password"
                >

                    <!-- EYE -->

                    <svg
                        id="eyeIcon"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <path
                            d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"
                        />

                        <circle
                            cx="12"
                            cy="12"
                            r="3"
                        />

                    </svg>

                </button>

            </div>

        </div>


        <!-- =====================================================
             LOGIN BUTTON
        ====================================================== -->

        <button
            type="submit"
            class="login-button"
        >

            <span class="button-content">

                <svg
                    class="button-icon"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >

                    <rect
                        x="4"
                        y="10"
                        width="16"
                        height="11"
                        rx="2"
                    />

                    <path
                        d="M8 10V7a4 4 0 0 1 8 0v3"
                    />

                </svg>

                Login to MySchool

            </span>

        </button>


    </form>


    <!-- =====================================================
         CREATE ACCOUNT
    ====================================================== -->

    <p class="switch-link">

        Don't have an account?

        <a href="register.php">
            Create Your Account
        </a>

    </p>


    <!-- =====================================================
         SECURITY
    ====================================================== -->

    <div class="security-note">

        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >

            <rect
                x="4"
                y="10"
                width="16"
                height="11"
                rx="2"
            />

            <path
                d="M8 10V7a4 4 0 0 1 8 0v3"
            />

        </svg>

        Your account information is securely protected.

    </div>


</div>
```

</div>

<!-- =============================================================
     PASSWORD SHOW / HIDE JAVASCRIPT
============================================================== -->

<script>

    const passwordInput =
        document.getElementById("password");

    const passwordToggle =
        document.getElementById("passwordToggle");

    const eyeIcon =
        document.getElementById("eyeIcon");


    passwordToggle.addEventListener("click", function () {

        if (passwordInput.type === "password") {

            /* SHOW PASSWORD */

            passwordInput.type = "text";

            passwordToggle.setAttribute(
                "aria-label",
                "Hide password"
            );

            passwordToggle.setAttribute(
                "title",
                "Hide password"
            );


            /* Change eye to crossed eye */

            eyeIcon.innerHTML = `

                <path
                    d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z">
                </path>

                <circle
                    cx="12"
                    cy="12"
                    r="3">
                </circle>

                <path
                    d="M3 3l18 18">
                </path>

            `;

        } else {

            /* HIDE PASSWORD */

            passwordInput.type = "password";

            passwordToggle.setAttribute(
                "aria-label",
                "Show password"
            );

            passwordToggle.setAttribute(
                "title",
                "Show password"
            );


            /* Normal eye */

            eyeIcon.innerHTML = `

                <path
                    d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z">
                </path>

                <circle
                    cx="12"
                    cy="12"
                    r="3">
                </circle>

            `;

        }

    });

</script>

</body>

</html>

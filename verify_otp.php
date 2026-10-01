<?php

session_start();

require_once "db.php";


/*
|--------------------------------------------------------------------------
| Check whether a user is waiting for email verification
|--------------------------------------------------------------------------
|
| register.php should store the newly created user's ID in:
|
| $_SESSION["verification_user_id"]
|
*/

if (
    !isset($_SESSION["verification_user_id"]) ||
    empty($_SESSION["verification_user_id"])
) {

    header("Location: register.php");

    exit;
}


$user_id = $_SESSION["verification_user_id"];

$message = "";

$success = false;


/*
|--------------------------------------------------------------------------
| Handle OTP verification
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $otp_code = trim($_POST["otp_code"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | Validate OTP input
    |--------------------------------------------------------------------------
    */

    if (empty($otp_code)) {

        $message = "Please enter the verification code.";

    }

    elseif (!preg_match('/^[0-9]{6}$/', $otp_code)) {

        $message = "Please enter the 6-digit verification code.";

    }

    else {

        /*
        |--------------------------------------------------------------------------
        | Find the latest registration OTP for this user
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT
                otp_id,
                otp_code,
                expires_at,
                is_used
            FROM otps
            WHERE user_id = ?
              AND purpose = 'REGISTRATION'
            ORDER BY otp_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            $user_id
        ]);

        $otp = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | Check whether OTP exists
        |--------------------------------------------------------------------------
        */

        if (!$otp) {

            $message = "No verification code was found. Please register again or request a new code.";

        }

        /*
        |--------------------------------------------------------------------------
        | Check whether OTP has already been used
        |--------------------------------------------------------------------------
        */

        elseif ((int)$otp["is_used"] === 1) {

            $message = "This verification code has already been used.";

        }

        /*
        |--------------------------------------------------------------------------
        | Check whether OTP has expired
        |--------------------------------------------------------------------------
        */

        elseif (strtotime($otp["expires_at"]) < time()) {

            $message = "This verification code has expired. Please request a new code.";

        }

        /*
        |--------------------------------------------------------------------------
        | Check OTP
        |--------------------------------------------------------------------------
        */

        elseif (!hash_equals(
            (string)$otp["otp_code"],
            (string)$otp_code
        )) {

            $message = "The verification code is incorrect.";

        }

        else {

            /*
            |--------------------------------------------------------------------------
            | OTP is correct
            |--------------------------------------------------------------------------
            |
            | Start a database transaction so that:
            |
            | 1. OTP becomes used
            | 2. User becomes verified
            | 3. User becomes active
            |
            | all happen together.
            |
            */

            try {

                $conn->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | Mark OTP as used
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare("
                    UPDATE otps
                    SET is_used = 1
                    WHERE otp_id = ?
                ");

                $stmt->execute([
                    $otp["otp_id"]
                ]);


                /*
                |--------------------------------------------------------------------------
                | Verify the user and activate the account
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare("
                    UPDATE users
                    SET
                        is_verified = 1,
                        status = 'ACTIVE'
                    WHERE user_id = ?
                ");

                $stmt->execute([
                    $user_id
                ]);


                /*
                |--------------------------------------------------------------------------
                | Get the user's role
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare("
                    SELECT
                        user_id,
                        role,
                        email,
                        phone_number
                    FROM users
                    WHERE user_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $user_id
                ]);

                $user = $stmt->fetch();


                if (!$user) {

                    throw new Exception(
                        "User account could not be found."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Commit changes
                |--------------------------------------------------------------------------
                */

                $conn->commit();


                /*
                |--------------------------------------------------------------------------
                | Create login session
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);


                $_SESSION["user_id"] = $user["user_id"];

                $_SESSION["role"] = $user["role"];

                $_SESSION["email"] = $user["email"];

                $_SESSION["phone"] = $user["phone_number"];

                $_SESSION["logged_in"] = true;


                /*
                |--------------------------------------------------------------------------
                | Remove temporary verification session
                |--------------------------------------------------------------------------
                */

                unset($_SESSION["verification_user_id"]);


                /*
                |--------------------------------------------------------------------------
                | Redirect according to role
                |--------------------------------------------------------------------------
                */

                switch ($user["role"]) {

                    case "PARENT":

                        header("Location: parent.php");

                        exit;


                    case "STUDENT":

                        header("Location: student.php");

                        exit;


                    case "TEACHER":

                        header("Location: teacher.php");

                        exit;


                    case "SCHOOL_ADMIN":

                        header("Location: school_admin.php");

                        exit;


                    case "SUPER_ADMIN":

                        header("Location: super_admin.php");

                        exit;


                    default:

                        header("Location: dashboard.php");

                        exit;
                }

            }

            catch (Exception $e) {

                /*
                |--------------------------------------------------------------------------
                | Roll back database changes if something fails
                |--------------------------------------------------------------------------
                */

                if ($conn->inTransaction()) {

                    $conn->rollBack();

                }

                $message = "Something went wrong while verifying your account. Please try again.";
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Email Verification - MySchool</title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {

            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                linear-gradient(
                    rgba(8, 35, 58, 0.82),
                    rgba(8, 35, 58, 0.82)
                ),
                url("https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=2000&q=85");

            background-size: cover;

            background-position: center;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 25px;
        }


        .verification-container {

            width: 100%;

            max-width: 500px;

        }


        .verification-card {

            background:
                rgba(255, 255, 255, 0.97);

            border-radius: 24px;

            padding: 45px;

            box-shadow:
                0 25px 70px rgba(0, 0, 0, 0.30);

            text-align: center;

        }


        .logo {

            width: 65px;

            height: 65px;

            margin: 0 auto 18px;

            border-radius: 18px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #06b6d4
                );

            color: white;

            font-size: 28px;

            font-weight: 800;

            box-shadow:
                0 10px 25px rgba(37, 99, 235, 0.25);
        }


        h1 {

            color: #102a43;

            font-size: 30px;

            margin-bottom: 8px;

        }


        .subtitle {

            color: #64748b;

            font-size: 15px;

            line-height: 1.6;

            margin-bottom: 30px;

        }


        .message {

            padding: 15px 18px;

            border-radius: 12px;

            margin-bottom: 22px;

            font-size: 14px;

            line-height: 1.5;

            text-align: left;

        }


        .message.error {

            background: #fff1f2;

            border: 1px solid #fecdd3;

            color: #be123c;

        }


        .otp-section {

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 18px;

            padding: 25px;

            margin-bottom: 22px;

        }


        .otp-icon {

            font-size: 38px;

            margin-bottom: 12px;

        }


        .otp-title {

            color: #1e3a5f;

            font-size: 18px;

            font-weight: 700;

            margin-bottom: 8px;

        }


        .otp-description {

            color: #64748b;

            font-size: 14px;

            line-height: 1.6;

            margin-bottom: 20px;

        }


        label {

            display: block;

            text-align: left;

            color: #334e68;

            font-size: 14px;

            font-weight: 700;

            margin-bottom: 8px;

        }


        input {

            width: 100%;

            height: 58px;

            border: 1px solid #cbd5e1;

            border-radius: 12px;

            padding: 0 18px;

            font-size: 24px;

            font-weight: 700;

            letter-spacing: 8px;

            text-align: center;

            color: #102a43;

            outline: none;

            background: white;

        }


        input:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 4px rgba(37, 99, 235, 0.12);

        }


        button {

            width: 100%;

            height: 55px;

            border: none;

            border-radius: 12px;

            margin-top: 18px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0891b2
                );

            color: white;

            font-size: 16px;

            font-weight: 700;

            cursor: pointer;

            transition: 0.2s ease;

            box-shadow:
                0 10px 25px rgba(37, 99, 235, 0.25);

        }


        button:hover {

            transform: translateY(-2px);

            box-shadow:
                0 14px 30px rgba(37, 99, 235, 0.30);

        }


        .security {

            margin-top: 22px;

            color: #94a3b8;

            font-size: 13px;

        }


        @media (max-width: 600px) {

            body {

                padding: 15px;

            }

            .verification-card {

                padding: 30px 22px;

                border-radius: 20px;

            }

            h1 {

                font-size: 26px;

            }

        }

    </style>

</head>


<body>


<div class="verification-container">

    <div class="verification-card">


        <div class="logo">

            M

        </div>


        <h1>
            Verify Your Email
        </h1>


        <p class="subtitle">

            We sent a 6-digit verification code
            to your email address.

            Enter the code below to activate
            your MySchool account.

        </p>


        <?php if (!empty($message)): ?>

            <div class="message error">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <div class="otp-section">


            <div class="otp-icon">

                📧

            </div>


            <div class="otp-title">

                Email Verification

            </div>


            <p class="otp-description">

                Check your inbox for the verification
                code sent by MySchool.

                The code is valid for a limited time.

            </p>


            <form method="POST">


                <label for="otp_code">

                    Verification Code

                </label>


                <input
                    type="text"
                    id="otp_code"
                    name="otp_code"
                    maxlength="6"
                    minlength="6"
                    pattern="[0-9]{6}"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    placeholder="000000"
                    required
                >


                <button type="submit">

                    Verify Email

                </button>


            </form>


        </div>


        <div class="security">

            🔒 Your account information is securely protected.

        </div>


    </div>

</div>


</body>

</html>
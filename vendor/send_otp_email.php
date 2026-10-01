<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/vendor/autoload.php";


function sendOtpEmail(
    $recipient_email,
    $recipient_name,
    $otp_code
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | LOAD ENVIRONMENT VARIABLES
        |--------------------------------------------------------------------------
        */

        $smtp_host =
            $_ENV["MAIL_HOST"] ??
            getenv("MAIL_HOST");

        $smtp_username =
            $_ENV["MAIL_USERNAME"] ??
            getenv("MAIL_USERNAME");

        $smtp_password =
            $_ENV["MAIL_PASSWORD"] ??
            getenv("MAIL_PASSWORD");

        $smtp_port =
            $_ENV["MAIL_PORT"] ??
            getenv("MAIL_PORT");

        $mail_from =
            $_ENV["MAIL_FROM_ADDRESS"] ??
            getenv("MAIL_FROM_ADDRESS");

        $mail_from_name =
            $_ENV["MAIL_FROM_NAME"] ??
            getenv("MAIL_FROM_NAME");


        /*
        |--------------------------------------------------------------------------
        | CHECK REQUIRED SETTINGS
        |--------------------------------------------------------------------------
        */

        if (
            empty($smtp_host) ||
            empty($smtp_username) ||
            empty($smtp_password) ||
            empty($mail_from)
        ) {

            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | DEFAULT PORT
        |--------------------------------------------------------------------------
        */

        if (empty($smtp_port)) {

            $smtp_port = 587;
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE MAILER
        |--------------------------------------------------------------------------
        */

        $mail = new PHPMailer(true);


        /*
        |--------------------------------------------------------------------------
        | SMTP CONFIGURATION
        |--------------------------------------------------------------------------
        */

        $mail->isSMTP();

        $mail->Host =
            $smtp_host;

        $mail->SMTPAuth = true;

        $mail->Username =
            $smtp_username;

        $mail->Password =
            $smtp_password;

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port =
            (int)$smtp_port;


        /*
        |--------------------------------------------------------------------------
        | SENDER
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            $mail_from,
            $mail_from_name ?: "MySchool"
        );


        /*
        |--------------------------------------------------------------------------
        | RECIPIENT
        |--------------------------------------------------------------------------
        */

        $mail->addAddress(
            $recipient_email,
            $recipient_name
        );


        /*
        |--------------------------------------------------------------------------
        | EMAIL FORMAT
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(true);


        $mail->Subject =
            "MySchool Email Verification Code";


        /*
        |--------------------------------------------------------------------------
        | EMAIL CONTENT
        |--------------------------------------------------------------------------
        */

        $safe_name =
            htmlspecialchars(
                $recipient_name,
                ENT_QUOTES,
                "UTF-8"
            );


        $safe_otp =
            htmlspecialchars(
                $otp_code,
                ENT_QUOTES,
                "UTF-8"
            );


        $mail->Body = "

        <!DOCTYPE html>

        <html>

        <head>

            <meta charset='UTF-8'>

            <title>
                MySchool Verification
            </title>

        </head>

        <body
            style='
                margin:0;
                padding:0;
                background:#f1f5f9;
                font-family:Arial, sans-serif;
            '
        >

            <div
                style='
                    max-width:600px;
                    margin:40px auto;
                    background:#ffffff;
                    border-radius:16px;
                    overflow:hidden;
                    box-shadow:0 10px 30px rgba(0,0,0,0.08);
                '
            >

                <div
                    style='
                        background:#2563eb;
                        color:#ffffff;
                        padding:30px;
                        text-align:center;
                    '
                >

                    <h1
                        style='
                            margin:0;
                            font-size:28px;
                        '
                    >
                        MySchool
                    </h1>

                    <p
                        style='
                            margin:8px 0 0;
                            opacity:0.9;
                        '
                    >
                        School Management System
                    </p>

                </div>


                <div
                    style='
                        padding:35px;
                        color:#334155;
                    '
                >

                    <h2>
                        Verify Your Email
                    </h2>


                    <p>
                        Hello {$safe_name},
                    </p>


                    <p>
                        Thank you for registering
                        with MySchool.
                    </p>


                    <p>
                        Use the verification code
                        below to activate your account:
                    </p>


                    <div
                        style='
                            margin:30px 0;
                            text-align:center;
                        '
                    >

                        <span
                            style='
                                display:inline-block;
                                padding:18px 30px;
                                background:#eff6ff;
                                color:#2563eb;
                                border-radius:12px;
                                font-size:32px;
                                font-weight:bold;
                                letter-spacing:8px;
                            '
                        >
                            {$safe_otp}
                        </span>

                    </div>


                    <p>
                        This code will expire in
                        <strong>10 minutes</strong>.
                    </p>


                    <p
                        style='
                            color:#64748b;
                            font-size:13px;
                            margin-top:30px;
                        '
                    >
                        If you did not create this account,
                        you can safely ignore this email.
                    </p>

                </div>


                <div
                    style='
                        padding:20px;
                        text-align:center;
                        background:#f8fafc;
                        color:#64748b;
                        font-size:12px;
                    '
                >

                    MySchool School Management System

                </div>

            </div>

        </body>

        </html>
        ";


        /*
        |--------------------------------------------------------------------------
        | PLAIN TEXT VERSION
        |--------------------------------------------------------------------------
        */

        $mail->AltBody =

            "Hello "
            . $recipient_name
            . ",\n\n"

            . "Your MySchool verification code is: "
            . $otp_code
            . "\n\n"

            . "This code will expire in 10 minutes.";


        /*
        |--------------------------------------------------------------------------
        | SEND
        |--------------------------------------------------------------------------
        */

        $mail->send();


        return true;


    } catch (Exception $e) {

        /*
        |--------------------------------------------------------------------------
        | EMAIL FAILED
        |--------------------------------------------------------------------------
        */

        error_log(
            "OTP email failed: "
            . $e->getMessage()
        );


        return false;
    }
}
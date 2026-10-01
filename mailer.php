<?php

require_once __DIR__ . "/vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendOTPEmail($recipientEmail, $recipientName, $otp)
{
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = $_ENV["MAIL_HOST"];

        $mail->SMTPAuth = true;

        $mail->Username = $_ENV["MAIL_USERNAME"];

        $mail->Password = $_ENV["MAIL_PASSWORD"];

        $mail->SMTPSecure = $_ENV["MAIL_ENCRYPTION"];

        $mail->Port = (int) $_ENV["MAIL_PORT"];


        /*
         * Sender
         */

        $mail->setFrom(
            $_ENV["MAIL_FROM_ADDRESS"],
            $_ENV["MAIL_FROM_NAME"]
        );


        /*
         * Recipient
         */

        $mail->addAddress(
            $recipientEmail,
            $recipientName
        );


        /*
         * Email format
         */

        $mail->isHTML(true);

        $mail->Subject = "MySchool Email Verification Code";


        $mail->Body = '

        <!DOCTYPE html>

        <html>

        <head>

            <meta charset="UTF-8">

            <style>

                body {
                    margin: 0;
                    padding: 0;
                    background: #f4f7fb;
                    font-family: Arial, sans-serif;
                }

                .container {
                    max-width: 600px;
                    margin: 40px auto;
                    background: #ffffff;
                    border-radius: 16px;
                    overflow: hidden;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
                }

                .header {
                    background: linear-gradient(
                        135deg,
                        #2563eb,
                        #0ea5e9
                    );
                    color: white;
                    padding: 30px;
                    text-align: center;
                }

                .header h1 {
                    margin: 0;
                    font-size: 28px;
                }

                .content {
                    padding: 35px;
                    color: #334155;
                }

                .otp {
                    margin: 30px auto;
                    padding: 18px;
                    width: 220px;
                    text-align: center;
                    background: #eff6ff;
                    border: 2px dashed #2563eb;
                    border-radius: 12px;
                    font-size: 32px;
                    font-weight: bold;
                    letter-spacing: 8px;
                    color: #1d4ed8;
                }

                .footer {
                    padding: 20px;
                    text-align: center;
                    color: #94a3b8;
                    font-size: 13px;
                    background: #f8fafc;
                }

            </style>

        </head>

        <body>

            <div class="container">

                <div class="header">

                    <h1>MySchool</h1>

                    <p>Email Verification</p>

                </div>

                <div class="content">

                    <h2>Hello ' .
                    htmlspecialchars($recipientName) .
                    ',</h2>

                    <p>
                        Thank you for registering with the
                        MySchool School Management System.
                    </p>

                    <p>
                        Use the verification code below
                        to activate your account:
                    </p>

                    <div class="otp">
                        ' . htmlspecialchars($otp) . '
                    </div>

                    <p>
                        This code will expire in
                        <strong>10 minutes</strong>.
                    </p>

                    <p>
                        If you did not create this account,
                        you can safely ignore this email.
                    </p>

                </div>

                <div class="footer">

                    MySchool School Management System

                </div>

            </div>

        </body>

        </html>
        ';


        /*
         * Plain text fallback
         */

        $mail->AltBody =
            "Your MySchool verification code is: "
            . $otp
            . ". This code expires in 10 minutes.";


        return $mail->send();

    } catch (Exception $e) {

        return false;

    }
}
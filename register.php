<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once "db.php";
require_once "send_otp_email.php";


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$message = "";

$message_type = "";

$selected_role = $_POST['role'] ?? '';

$full_name = $_POST['full_name'] ?? '';

$email = $_POST['email'] ?? '';

$phone_number = $_POST['phone_number'] ?? '';

$password = $_POST['password'] ?? '';

$confirm_password = $_POST['confirm_password'] ?? '';

$admission_number = $_POST['admission_number'] ?? '';

$gender = $_POST['gender'] ?? '';

$date_of_birth = $_POST['date_of_birth'] ?? '';

$child_admission_numbers = $_POST['child_admission_numbers'] ?? '';

$national_id = $_POST['national_id'] ?? '';

$relationship = $_POST['relationship'] ?? 'Parent';

$tsc_number = $_POST['tsc_number'] ?? '';

$specialization = $_POST['specialization'] ?? '';

$class_id = $_POST['class_id'] ?? '';

$validated_children = [];


/*
|--------------------------------------------------------------------------
| LOAD CLASSES
|--------------------------------------------------------------------------
*/

$classes = [];

try {

    $class_stmt = $conn->query("
        SELECT
            class_id,
            class_name,
            stream_name
        FROM classes
        ORDER BY
            class_name ASC,
            stream_name ASC
    ");

    $classes = $class_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $classes = [];
}


/*
|--------------------------------------------------------------------------
| REGISTRATION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        /*
        |--------------------------------------------------------------------------
        | CLEAN VALUES
        |--------------------------------------------------------------------------
        */

        $selected_role = trim($selected_role);

        $full_name = trim($full_name);

        $email = trim($email);

        $phone_number = trim($phone_number);

        $admission_number = trim($admission_number);

        $gender = trim($gender);

        $date_of_birth = trim($date_of_birth);

        $child_admission_numbers =
            trim($child_admission_numbers);

        $national_id = trim($national_id);

        $relationship = trim($relationship);

        $tsc_number = trim($tsc_number);

        $specialization = trim($specialization);

        $class_id = trim($class_id);


        /*
        |--------------------------------------------------------------------------
        | ROLE VALIDATION
        |--------------------------------------------------------------------------
        */

        $allowed_roles = [
            "PARENT",
            "STUDENT",
            "TEACHER"
        ];

        if (
            !in_array(
                $selected_role,
                $allowed_roles,
                true
            )
        ) {

            throw new Exception(
                "Please select a valid account type."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | COMMON VALIDATION
        |--------------------------------------------------------------------------
        */

        if (empty($full_name)) {

            throw new Exception(
                "Please enter your full name."
            );
        }


        if (empty($password)) {

            throw new Exception(
                "Please enter a password."
            );
        }


        if (empty($confirm_password)) {

            throw new Exception(
                "Please confirm your password."
            );
        }


        if ($password !== $confirm_password) {

            throw new Exception(
                "The passwords do not match."
            );
        }


        if (strlen($password) < 6) {

            throw new Exception(
                "Password must be at least 6 characters."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | EMAIL IS REQUIRED
        |--------------------------------------------------------------------------
        |
        | We are using email verification.
        |
        */

        if (empty($email)) {

            throw new Exception(
                "Email address is required because your account must be verified by email."
            );
        }


        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            throw new Exception(
                "Please enter a valid email address."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE EMAIL
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT
                user_id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([
            $email
        ]);

        if ($stmt->fetch()) {

            throw new Exception(
                "An account with this email address already exists."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE PHONE
        |--------------------------------------------------------------------------
        */

        if (!empty($phone_number)) {

            $stmt = $conn->prepare("
                SELECT
                    user_id
                FROM users
                WHERE phone_number = ?
                LIMIT 1
            ");

            $stmt->execute([
                $phone_number
            ]);

            if ($stmt->fetch()) {

                throw new Exception(
                    "An account with this phone number already exists."
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | STUDENT VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($selected_role === "STUDENT") {

            if (empty($admission_number)) {

                throw new Exception(
                    "Please enter the student's admission number."
                );
            }


            if (empty($gender)) {

                throw new Exception(
                    "Please select the student's gender."
                );
            }


            if (empty($date_of_birth)) {

                throw new Exception(
                    "Please enter the student's date of birth."
                );
            }


            if (empty($class_id)) {

                throw new Exception(
                    "Please select the student's class."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK CLASS EXISTS
            |--------------------------------------------------------------------------
            */

            $class_check = $conn->prepare("
                SELECT
                    class_id
                FROM classes
                WHERE class_id = ?
                LIMIT 1
            ");

            $class_check->execute([
                $class_id
            ]);

            if (!$class_check->fetch()) {

                throw new Exception(
                    "The selected class does not exist."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK ADMISSION NUMBER
            |--------------------------------------------------------------------------
            |
            | The student is allowed to create a NEW admission number.
            |
            | We only reject it if that admission number has already
            | been registered.
            |
            */

            $stmt = $conn->prepare("
                SELECT
                    student_id
                FROM students
                WHERE adm_number = ?
                LIMIT 1
            ");

            $stmt->execute([
                $admission_number
            ]);

            if ($stmt->fetch()) {

                throw new Exception(
                    "This admission number is already registered."
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | PARENT VALIDATION
        |--------------------------------------------------------------------------
        |
        | One parent can have many children.
        |
        */

        if ($selected_role === "PARENT") {

            if (empty($child_admission_numbers)) {

                throw new Exception(
                    "Please enter at least one child's admission number."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Convert commas, spaces and new lines
            |--------------------------------------------------------------------------
            */

            $children = preg_split(
                '/[\s,]+/',
                $child_admission_numbers,
                -1,
                PREG_SPLIT_NO_EMPTY
            );


            $children = array_unique(
                $children
            );


            if (empty($children)) {

                throw new Exception(
                    "Please enter at least one valid admission number."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Validate every child
            |--------------------------------------------------------------------------
            */

            foreach ($children as $child_admission) {

                $child_admission =
                    trim($child_admission);


                if (empty($child_admission)) {

                    continue;
                }


                $stmt = $conn->prepare("
                    SELECT
                        student_id,
                        adm_number,
                        full_name,
                        status
                    FROM students
                    WHERE adm_number = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $child_admission
                ]);


                $child = $stmt->fetch();


                if (!$child) {

                    throw new Exception(
                        "Admission number "
                        . htmlspecialchars($child_admission)
                        . " was not found. Please check the admission number."
                    );
                }


                if ($child["status"] !== "ACTIVE") {

                    throw new Exception(
                        "Student "
                        . htmlspecialchars($child["adm_number"])
                        . " is not currently active."
                    );
                }


                $validated_children[] = $child;
            }


            if (empty($validated_children)) {

                throw new Exception(
                    "No valid students were found."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK NATIONAL ID
            |--------------------------------------------------------------------------
            */

            if (!empty($national_id)) {

                $stmt = $conn->prepare("
                    SELECT
                        parent_id
                    FROM parents
                    WHERE national_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $national_id
                ]);

                if ($stmt->fetch()) {

                    throw new Exception(
                        "This National ID is already registered."
                    );
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | TEACHER VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($selected_role === "TEACHER") {

            if (empty($tsc_number)) {

                throw new Exception(
                    "Please enter your TSC number."
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | BEGIN DATABASE TRANSACTION
        |--------------------------------------------------------------------------
        */

        $conn->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | CREATE USER ACCOUNT
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | Account starts as PENDING and unverified.
        |
        */

        $password_hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        $stmt = $conn->prepare("
            INSERT INTO users
            (
                phone_number,
                email,
                password_hash,
                role,
                is_verified,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                0,
                'PENDING'
            )
        ");


        $stmt->execute([

            !empty($phone_number)
                ? $phone_number
                : null,

            $email,

            $password_hash,

            $selected_role

        ]);


        /*
        |--------------------------------------------------------------------------
        | GET NEW USER ID
        |--------------------------------------------------------------------------
        */

        $user_id = $conn->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | CREATE PARENT
        |--------------------------------------------------------------------------
        */

        if ($selected_role === "PARENT") {

            $stmt = $conn->prepare("
                INSERT INTO parents
                (
                    user_id,
                    full_name,
                    national_id,
                    relationship
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");


            $stmt->execute([

                $user_id,

                $full_name,

                !empty($national_id)
                    ? $national_id
                    : null,

                !empty($relationship)
                    ? $relationship
                    : "Parent"

            ]);


            /*
            |--------------------------------------------------------------------------
            | GET PARENT ID
            |--------------------------------------------------------------------------
            */

            $parent_id =
                $conn->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | LINK ALL CHILDREN
            |--------------------------------------------------------------------------
            */

            $link_stmt = $conn->prepare("
                INSERT INTO parent_student_map
                (
                    parent_id,
                    student_id
                )
                VALUES
                (
                    ?,
                    ?
                )
            ");


            foreach (
                $validated_children
                as $child
            ) {

                $link_stmt->execute([

                    $parent_id,

                    $child["student_id"]

                ]);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE STUDENT
        |--------------------------------------------------------------------------
        */

        elseif ($selected_role === "STUDENT") {

            $stmt = $conn->prepare("
                INSERT INTO students
                (
                    user_id,
                    adm_number,
                    full_name,
                    gender,
                    date_of_birth,
                    class_id,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'ACTIVE'
                )
            ");


            $stmt->execute([

                $user_id,

                $admission_number,

                $full_name,

                $gender,

                $date_of_birth,

                $class_id

            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE TEACHER
        |--------------------------------------------------------------------------
        */

        elseif ($selected_role === "TEACHER") {

            $stmt = $conn->prepare("
                INSERT INTO teachers
                (
                    user_id,
                    full_name,
                    phone_number,
                    email,
                    tsc_number,
                    specialization
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");


            $stmt->execute([

                $user_id,

                $full_name,

                !empty($phone_number)
                    ? $phone_number
                    : "",

                !empty($email)
                    ? $email
                    : null,

                !empty($tsc_number)
                    ? $tsc_number
                    : null,

                !empty($specialization)
                    ? $specialization
                    : null

            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE OTP
        |--------------------------------------------------------------------------
        */

        $otp_code = str_pad(
            (string)random_int(0, 999999),
            6,
            "0",
            STR_PAD_LEFT
        );


        /*
        |--------------------------------------------------------------------------
        | OTP EXPIRATION
        |--------------------------------------------------------------------------
        */

        $expires_at = date(
            "Y-m-d H:i:s",
            time() + (10 * 60)
        );


        /*
        |--------------------------------------------------------------------------
        | STORE OTP
        |--------------------------------------------------------------------------
        */

        $otp_stmt = $conn->prepare("
            INSERT INTO otps
            (
                user_id,
                otp_code,
                purpose,
                expires_at,
                is_used
            )
            VALUES
            (
                ?,
                ?,
                'REGISTRATION',
                ?,
                0
            )
        ");


        $otp_stmt->execute([

            $user_id,

            $otp_code,

            $expires_at

        ]);


        /*
        |--------------------------------------------------------------------------
        | COMMIT DATABASE
        |--------------------------------------------------------------------------
        */

        $conn->commit();


        /*
        |--------------------------------------------------------------------------
        | SEND OTP EMAIL
        |--------------------------------------------------------------------------
        */

        $email_sent = sendOtpEmail(
            $email,
            $full_name,
            $otp_code
        );


        /*
        |--------------------------------------------------------------------------
        | EMAIL FAILED
        |--------------------------------------------------------------------------
        */

        if (!$email_sent) {

            /*
            | Remove account because the user cannot verify it.
            */

            $delete_stmt = $conn->prepare("
                DELETE FROM users
                WHERE user_id = ?
            ");

            $delete_stmt->execute([
                $user_id
            ]);


            throw new Exception(
                "Your account could not be created because the verification email could not be sent. Please check your email configuration and try again."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | STORE VERIFICATION SESSION
        |--------------------------------------------------------------------------
        |
        | We DO NOT log the user in yet.
        |
        */

        $_SESSION["verification_user_id"] =
            $user_id;

        $_SESSION["verification_email"] =
            $email;

        $_SESSION["verification_role"] =
            $selected_role;


        /*
        |--------------------------------------------------------------------------
        | REDIRECT TO OTP PAGE
        |--------------------------------------------------------------------------
        */

        header(
            "Location: verify_otp.php"
        );

        exit;


    } catch (Exception $e) {

        /*
        |--------------------------------------------------------------------------
        | ROLLBACK
        |--------------------------------------------------------------------------
        */

        if ($conn->inTransaction()) {

            $conn->rollBack();
        }


        $message =
            $e->getMessage();

        $message_type =
            "error";
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

    <title>
        Create Account - MySchool
    </title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {

            min-height: 100vh;

            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

            background-image:

                linear-gradient(
                    rgba(7, 35, 58, 0.72),
                    rgba(7, 35, 58, 0.78)
                ),

                url(
                    "https://images.unsplash.com/photo-1580582932707-520aed937b7b?auto=format&fit=crop&w=2000&q=85"
                );

            background-size: cover;

            background-position: center;

            background-attachment: fixed;

            color: #17324d;

            padding: 40px 20px;
        }


        .page-wrapper {

            width: 100%;

            max-width: 900px;

            margin: 0 auto;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.97
                );

            border-radius: 28px;

            box-shadow:
                0 25px 70px
                rgba(0, 0, 0, 0.28);

            overflow: hidden;
        }


        .brand {

            text-align: center;

            padding:
                34px
                30px
                18px;
        }


        .brand-logo {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            width: 58px;

            height: 58px;

            border-radius: 18px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0891b2
                );

            color: white;

            font-size: 28px;

            box-shadow:
                0 10px 25px
                rgba(37, 99, 235, 0.25);

            margin-bottom: 12px;
        }


        .brand h1 {

            font-size: 30px;

            color: #17324d;

            margin-bottom: 6px;
        }


        .brand p {

            color: #64748b;

            font-size: 14px;
        }


        .form-area {

            padding:
                10px
                40px
                40px;
        }


        .form-title {

            text-align: center;

            margin-bottom: 30px;
        }


        .form-title h2 {

            font-size: 25px;

            color: #17324d;

            margin-bottom: 8px;
        }


        .form-title p {

            color: #64748b;

            font-size: 14px;
        }


        .message {

            padding: 14px 16px;

            border-radius: 12px;

            margin-bottom: 22px;

            font-size: 14px;
        }


        .message.error {

            background: #fef2f2;

            color: #b91c1c;

            border:
                1px solid
                #fecaca;
        }


        .message.success {

            background: #f0fdf4;

            color: #15803d;

            border:
                1px solid
                #bbf7d0;
        }


        .section {

            background: #f8fafc;

            border:
                1px solid
                #e2e8f0;

            border-radius: 18px;

            padding: 24px;

            margin-bottom: 22px;
        }


        .section-title {

            display: flex;

            align-items: center;

            gap: 10px;

            margin-bottom: 20px;
        }


        .section-title-icon {

            width: 38px;

            height: 38px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #dbeafe;

            color: #2563eb;

            border-radius: 10px;
        }


        .section-title h3 {

            color: #17324d;

            font-size: 17px;
        }


        .section-title p {

            color: #64748b;

            font-size: 12px;

            margin-top: 2px;
        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;
        }


        .full-width {

            grid-column:
                1 / -1;
        }


        .field {

            display: flex;

            flex-direction: column;

            gap: 7px;
        }


        label {

            font-size: 13px;

            font-weight: 600;

            color: #334155;
        }


        .required {

            color: #dc2626;
        }


        input,
        select,
        textarea {

            width: 100%;

            padding:
                13px
                14px;

            border:
                1px solid
                #cbd5e1;

            border-radius: 11px;

            background: white;

            color: #17324d;

            font-size: 14px;

            outline: none;

            transition:
                0.2s ease;
        }


        textarea {

            min-height: 130px;

            resize: vertical;
        }


        input:focus,
        select:focus,
        textarea:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 4px
                rgba(
                    37,
                    99,
                    235,
                    0.10
                );
        }


        .help-text {

            color: #64748b;

            font-size: 12px;

            line-height: 1.6;

            margin-top: 6px;
        }


        .role-fields {

            display: none;
        }


        .role-fields.active {

            display: block;
        }


        .role-selector {

            margin-bottom: 22px;
        }


        .submit-button {

            width: 100%;

            border: none;

            border-radius: 13px;

            padding: 15px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0891b2
                );

            color: white;

            font-size: 15px;

            font-weight: 700;

            cursor: pointer;

            box-shadow:
                0 10px 25px
                rgba(
                    37,
                    99,
                    235,
                    0.25
                );

            transition:
                0.2s ease;
        }


        .submit-button:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 14px 30px
                rgba(
                    37,
                    99,
                    235,
                    0.32
                );
        }


        .bottom-link {

            text-align: center;

            margin-top: 20px;

            color: #64748b;

            font-size: 14px;
        }


        .bottom-link a {

            color: #2563eb;

            font-weight: 700;

            text-decoration: none;
        }


        .bottom-link a:hover {

            text-decoration: underline;
        }


        .hidden {

            display: none !important;
        }


        @media (max-width: 700px) {

            body {

                padding:
                    15px 10px;
            }


            .page-wrapper {

                border-radius: 20px;
            }


            .form-area {

                padding:
                    10px
                    20px
                    25px;
            }


            .form-grid {

                grid-template-columns:
                    1fr;
            }


            .full-width {

                grid-column:
                    auto;
            }


            .brand {

                padding:
                    25px
                    20px
                    15px;
            }
        }

    </style>

</head>


<body>


<div class="page-wrapper">


    <!-- BRAND -->

    <div class="brand">

        <div class="brand-logo">
            🎓
        </div>

        <h1>
            MySchool
        </h1>

        <p>
            School Management System
        </p>

    </div>


    <div class="form-area">


        <div class="form-title">

            <h2>
                Create Your Account
            </h2>

            <p>
                Register your account and verify your email to continue.
            </p>

        </div>


        <?php if (!empty($message)): ?>

            <div
                class="message <?= htmlspecialchars(
                    $message_type
                ) ?>"
            >

                <?= htmlspecialchars(
                    $message
                ) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
        >


            <!-- ACCOUNT TYPE -->

            <div class="section">

                <div class="section-title">

                    <div class="section-title-icon">
                        👤
                    </div>

                    <div>

                        <h3>
                            Account Type
                        </h3>

                        <p>
                            Select the type of account you want to create.
                        </p>

                    </div>

                </div>


                <div class="field">

                    <label
                        for="role"
                    >
                        Account Type
                        <span class="required">*</span>
                    </label>


                    <select
                        name="role"
                        id="role"
                        onchange="showFields()"
                        required
                    >

                        <option
                            value=""
                            <?= $selected_role === ""
                                ? "selected"
                                : "" ?>
                        >
                            Select account type
                        </option>


                        <option
                            value="PARENT"
                            <?= $selected_role === "PARENT"
                                ? "selected"
                                : "" ?>
                        >
                            Parent / Guardian
                        </option>


                        <option
                            value="STUDENT"
                            <?= $selected_role === "STUDENT"
                                ? "selected"
                                : "" ?>
                        >
                            Student
                        </option>


                        <option
                            value="TEACHER"
                            <?= $selected_role === "TEACHER"
                                ? "selected"
                                : "" ?>
                        >
                            Teacher
                        </option>

                    </select>

                </div>

            </div>


            <!-- COMMON INFORMATION -->

            <div class="section">

                <div class="section-title">

                    <div class="section-title-icon">
                        ✉️
                    </div>

                    <div>

                        <h3>
                            Account Information
                        </h3>

                        <p>
                            Your email will be verified before your account is activated.
                        </p>

                    </div>

                </div>


                <div class="form-grid">


                    <div class="field">

                        <label
                            for="full_name"
                        >
                            Full Name
                            <span class="required">*</span>
                        </label>


                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            placeholder="Enter your full name"
                            value="<?= htmlspecialchars(
                                $full_name,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="field">

                        <label
                            for="email"
                        >
                            Email Address
                            <span class="required">*</span>
                        </label>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="example@gmail.com"
                            value="<?= htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="field">

                        <label
                            for="phone_number"
                        >
                            Phone Number
                        </label>


                        <input
                            type="text"
                            id="phone_number"
                            name="phone_number"
                            placeholder="07XXXXXXXX"
                            value="<?= htmlspecialchars(
                                $phone_number,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>


                    <div class="field">

                        <label
                            for="password"
                        >
                            Password
                            <span class="required">*</span>
                        </label>


                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a password"
                            required
                        >

                    </div>


                    <div class="field">

                        <label
                            for="confirm_password"
                        >
                            Confirm Password
                            <span class="required">*</span>
                        </label>


                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Confirm your password"
                            required
                        >

                    </div>


                </div>

            </div>


            <!-- PARENT -->

            <div
                id="parent-fields"
                class="section role-fields
                    <?= $selected_role === "PARENT"
                        ? "active"
                        : "" ?>"
            >

                <div class="section-title">

                    <div class="section-title-icon">
                        👨‍👩‍👧
                    </div>

                    <div>

                        <h3>
                            Children / Students
                        </h3>

                        <p>
                            Link this account to your child or children.
                        </p>

                    </div>

                </div>


                <div class="field">

                    <label
                        for="child_admission_numbers"
                    >

                        Child Admission Number(s)

                        <span class="required">
                            *
                        </span>

                    </label>


                    <textarea
                        id="child_admission_numbers"
                        name="child_admission_numbers"
                        placeholder="Example:

ADM001
ADM002
ADM003

Or:

ADM001, ADM002, ADM003"
                    ><?= htmlspecialchars(
                        $child_admission_numbers,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?></textarea>


                    <div class="help-text">

                        Enter the admission number of every child
                        you want linked to this parent account.

                        <br><br>

                        You can enter one child or many children.

                        <br>

                        Separate admission numbers using commas,
                        spaces, or new lines.

                        <br><br>

                        The system will verify every admission
                        number before creating the account.

                    </div>

                </div>


                <br>


                <div class="form-grid">


                    <div class="field">

                        <label
                            for="national_id"
                        >
                            National ID
                        </label>


                        <input
                            type="text"
                            id="national_id"
                            name="national_id"
                            placeholder="Optional"
                            value="<?= htmlspecialchars(
                                $national_id,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>


                    <div class="field">

                        <label
                            for="relationship"
                        >
                            Relationship
                        </label>


                        <select
                            id="relationship"
                            name="relationship"
                        >

                            <option
                                value="Parent"
                                <?= $relationship === "Parent"
                                    ? "selected"
                                    : "" ?>
                            >
                                Parent
                            </option>


                            <option
                                value="Mother"
                                <?= $relationship === "Mother"
                                    ? "selected"
                                    : "" ?>
                            >
                                Mother
                            </option>


                            <option
                                value="Father"
                                <?= $relationship === "Father"
                                    ? "selected"
                                    : "" ?>
                            >
                                Father
                            </option>


                            <option
                                value="Guardian"
                                <?= $relationship === "Guardian"
                                    ? "selected"
                                    : "" ?>
                            >
                                Guardian
                            </option>

                        </select>

                    </div>


                </div>

            </div>


            <!-- STUDENT -->

            <div
                id="student-fields"
                class="section role-fields
                    <?= $selected_role === "STUDENT"
                        ? "active"
                        : "" ?>"
            >

                <div class="section-title">

                    <div class="section-title-icon">
                        🎓
                    </div>

                    <div>

                        <h3>
                            Student Information
                        </h3>

                        <p>
                            Enter the student's school details.
                        </p>

                    </div>

                </div>


                <div class="form-grid">


                    <div class="field">

                        <label
                            for="admission_number"
                        >

                            Admission Number

                            <span class="required">
                                *
                            </span>

                        </label>


                        <input
                            type="text"
                            id="admission_number"
                            name="admission_number"
                            placeholder="e.g. ADM001"
                            value="<?= htmlspecialchars(
                                $admission_number,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>


                    <div class="field">

                        <label
                            for="gender"
                        >

                            Gender

                            <span class="required">
                                *
                            </span>

                        </label>


                        <select
                            id="gender"
                            name="gender"
                        >

                            <option
                                value=""
                            >
                                Select gender
                            </option>


                            <option
                                value="MALE"
                                <?= $gender === "MALE"
                                    ? "selected"
                                    : "" ?>
                            >
                                Male
                            </option>


                            <option
                                value="FEMALE"
                                <?= $gender === "FEMALE"
                                    ? "selected"
                                    : "" ?>
                            >
                                Female
                            </option>


                            <option
                                value="OTHER"
                                <?= $gender === "OTHER"
                                    ? "selected"
                                    : "" ?>
                            >
                                Other
                            </option>

                        </select>

                    </div>


                    <div class="field">

                        <label
                            for="date_of_birth"
                        >

                            Date of Birth

                            <span class="required">
                                *
                            </span>

                        </label>


                        <input
                            type="date"
                            id="date_of_birth"
                            name="date_of_birth"
                            value="<?= htmlspecialchars(
                                $date_of_birth,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>


                    <div class="field">

                        <label
                            for="class_id"
                        >

                            Class

                            <span class="required">
                                *
                            </span>

                        </label>


                        <select
                            id="class_id"
                            name="class_id"
                        >

                            <option
                                value=""
                            >
                                Select class
                            </option>


                            <?php foreach (
                                $classes
                                as $class
                            ): ?>

                                <option
                                    value="<?= htmlspecialchars(
                                        $class["class_id"]
                                    ) ?>"
                                    <?= (string)$class_id ===
                                        (string)$class["class_id"]
                                        ? "selected"
                                        : "" ?>
                                >

                                    <?= htmlspecialchars(
                                        $class["class_name"]
                                    ) ?>

                                    -

                                    <?= htmlspecialchars(
                                        $class["stream_name"]
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                </div>

            </div>


            <!-- TEACHER -->

            <div
                id="teacher-fields"
                class="section role-fields
                    <?= $selected_role === "TEACHER"
                        ? "active"
                        : "" ?>"
            >

                <div class="section-title">

                    <div class="section-title-icon">
                        👨‍🏫
                    </div>

                    <div>

                        <h3>
                            Teacher Information
                        </h3>

                        <p>
                            Enter your teaching information.
                        </p>

                    </div>

                </div>


                <div class="form-grid">


                    <div class="field">

                        <label
                            for="tsc_number"
                        >

                            TSC Number

                            <span class="required">
                                *
                            </span>

                        </label>


                        <input
                            type="text"
                            id="tsc_number"
                            name="tsc_number"
                            placeholder="Enter TSC number"
                            value="<?= htmlspecialchars(
                                $tsc_number,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>


                    <div class="field">

                        <label
                            for="specialization"
                        >
                            Specialization
                        </label>


                        <input
                            type="text"
                            id="specialization"
                            name="specialization"
                            placeholder="e.g. Mathematics"
                            value="<?= htmlspecialchars(
                                $specialization,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>


                </div>

            </div>


            <!-- SUBMIT -->

            <button
                type="submit"
                class="submit-button"
            >

                Create Account & Verify Email

            </button>


        </form>


        <div class="bottom-link">

            Already have an account?

            <a href="login.php">
                Login
            </a>

        </div>


    </div>

</div>


<script>

function showFields() {

    const role =
        document.getElementById("role").value;


    const parentFields =
        document.getElementById(
            "parent-fields"
        );


    const studentFields =
        document.getElementById(
            "student-fields"
        );


    const teacherFields =
        document.getElementById(
            "teacher-fields"
        );


    parentFields.classList.remove(
        "active"
    );


    studentFields.classList.remove(
        "active"
    );


    teacherFields.classList.remove(
        "active"
    );


    if (role === "PARENT") {

        parentFields.classList.add(
            "active"
        );

    }


    if (role === "STUDENT") {

        studentFields.classList.add(
            "active"
        );

    }


    if (role === "TEACHER") {

        teacherFields.classList.add(
            "active"
        );

    }

}


document.addEventListener(
    "DOMContentLoaded",
    function () {

        showFields();

    }
);

</script>


</body>

</html>
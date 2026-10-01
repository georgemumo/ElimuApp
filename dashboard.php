<?php

session_start();

require_once "db.php";


/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true
) {

    header("Location: login.php");

    exit;
}


$user_id = $_SESSION["user_id"];

$role = $_SESSION["role"];

$user = null;


/*
|--------------------------------------------------------------------------
| GET ROLE-SPECIFIC INFORMATION
|--------------------------------------------------------------------------
*/

if ($role === "PARENT") {

    $stmt = $conn->prepare("
        SELECT
            u.email,
            u.phone_number,
            u.role,
            p.full_name,
            p.national_id,
            p.relationship
        FROM users u
        INNER JOIN parents p
            ON u.user_id = p.user_id
        WHERE u.user_id = ?
    ");

    $stmt->execute([$user_id]);

    $user = $stmt->fetch();
}


elseif ($role === "TEACHER") {

    $stmt = $conn->prepare("
        SELECT
            u.email,
            u.phone_number,
            u.role,
            t.full_name,
            t.tsc_number,
            t.specialization
        FROM users u
        INNER JOIN teachers t
            ON u.user_id = t.user_id
        WHERE u.user_id = ?
    ");

    $stmt->execute([$user_id]);

    $user = $stmt->fetch();
}


elseif ($role === "STUDENT") {

    $stmt = $conn->prepare("
        SELECT
            u.email,
            u.phone_number,
            u.role,
            s.full_name,
            s.adm_number,
            s.gender,
            s.date_of_birth,
            c.class_name,
            c.stream_name
        FROM users u
        INNER JOIN students s
            ON u.user_id = s.user_id
        INNER JOIN classes c
            ON s.class_id = c.class_id
        WHERE u.user_id = ?
    ");

    $stmt->execute([$user_id]);

    $user = $stmt->fetch();
}


else {

    $stmt = $conn->prepare("
        SELECT
            email,
            phone_number,
            role
        FROM users
        WHERE user_id = ?
    ");

    $stmt->execute([$user_id]);

    $user = $stmt->fetch();
}


/*
|--------------------------------------------------------------------------
| DISPLAY NAME
|--------------------------------------------------------------------------
*/

$display_name = !empty($user["full_name"])
    ? $user["full_name"]
    : "User";


/*
|--------------------------------------------------------------------------
| ROLE LABEL
|--------------------------------------------------------------------------
*/

$role_label = str_replace(
    "_",
    " ",
    $role
);


/*
|--------------------------------------------------------------------------
| ROLE ICON
|--------------------------------------------------------------------------
*/

if ($role === "SUPER_ADMIN") {

    $role_icon = "👑";

}

elseif ($role === "SCHOOL_ADMIN") {

    $role_icon = "🏫";

}

elseif ($role === "TEACHER") {

    $role_icon = "👨‍🏫";

}

elseif ($role === "PARENT") {

    $role_icon = "👨‍👩‍👧";

}

elseif ($role === "STUDENT") {

    $role_icon = "🎓";

}

else {

    $role_icon = "👤";

}


/*
|--------------------------------------------------------------------------
| INITIALS
|--------------------------------------------------------------------------
*/

$name_parts = explode(
    " ",
    trim($display_name)
);

$initials = "";

if (!empty($name_parts[0])) {

    $initials .= strtoupper(
        substr($name_parts[0], 0, 1)
    );
}

if (
    count($name_parts) > 1 &&
    !empty($name_parts[count($name_parts) - 1])
) {

    $initials .= strtoupper(
        substr(
            $name_parts[count($name_parts) - 1],
            0,
            1
        )
    );
}

if (empty($initials)) {

    $initials = "U";
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
        Dashboard - MySchool
    </title>


    <!-- Google Font -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <style>

        /*
        |--------------------------------------------------------------------------
        | GLOBAL
        |--------------------------------------------------------------------------
        */

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;
        }


        html {

            scroll-behavior: smooth;
        }


        body {

            min-height: 100vh;

            font-family:
                "Inter",
                Arial,
                sans-serif;

            background:
                #f1f5f9;

            color: #102a43;
        }


        /*
        |--------------------------------------------------------------------------
        | DASHBOARD LAYOUT
        |--------------------------------------------------------------------------
        */

        .dashboard {

            min-height: 100vh;

            display: flex;

            background:
                #f1f5f9;
        }


        /*
        |--------------------------------------------------------------------------
        | SIDEBAR
        |--------------------------------------------------------------------------
        */

        .sidebar {

            width: 260px;

            min-height: 100vh;

            background:
                linear-gradient(
                    180deg,
                    #0f2f4f 0%,
                    #0b4261 55%,
                    #075985 100%
                );

            color: white;

            padding: 25px 17px;

            position: fixed;

            left: 0;

            top: 0;

            bottom: 0;

            z-index: 100;

            display: flex;

            flex-direction: column;

            box-shadow:
                8px 0 30px
                rgba(
                    15,
                    47,
                    79,
                    0.10
                );
        }


        /*
        |--------------------------------------------------------------------------
        | LOGO
        |--------------------------------------------------------------------------
        */

        .sidebar-logo {

            display: flex;

            align-items: center;

            gap: 11px;

            padding:
                5px
                8px
                28px;
        }


        .sidebar-logo-icon {

            width: 43px;

            height: 43px;

            border-radius: 13px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.14
                );

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 21px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.12
                );
        }


        .sidebar-logo h1 {

            font-size: 20px;

            font-weight: 800;

            letter-spacing: -0.5px;
        }


        .sidebar-logo span {

            color: #7dd3fc;
        }


        /*
        |--------------------------------------------------------------------------
        | NAVIGATION TITLE
        |--------------------------------------------------------------------------
        */

        .nav-title {

            color:
                rgba(
                    255,
                    255,
                    255,
                    0.45
                );

            font-size: 10px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 1.2px;

            padding:
                0
                13px
                9px;
        }


        /*
        |--------------------------------------------------------------------------
        | NAVIGATION
        |--------------------------------------------------------------------------
        */

        .sidebar-nav {

            display: flex;

            flex-direction: column;

            gap: 5px;
        }


        .nav-item {

            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                12px
                13px;

            border-radius: 11px;

            color:
                rgba(
                    255,
                    255,
                    255,
                    0.72
                );

            text-decoration: none;

            font-size: 12px;

            font-weight: 500;

            transition:
                background
                0.2s ease,

                color
                0.2s ease,

                transform
                0.2s ease;
        }


        .nav-item:hover {

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.09
                );

            color: white;

            transform:
                translateX(2px);
        }


        .nav-item.active {

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.14
                );

            color: white;

            box-shadow:
                inset 3px 0 0
                #38bdf8;
        }


        .nav-icon {

            width: 25px;

            font-size: 16px;

            text-align: center;
        }


        /*
        |--------------------------------------------------------------------------
        | SIDEBAR BOTTOM
        |--------------------------------------------------------------------------
        */

        .sidebar-bottom {

            margin-top: auto;

            border-top:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.10
                );

            padding-top: 17px;
        }


        .sidebar-user {

            display: flex;

            align-items: center;

            gap: 10px;

            padding:
                8px;
        }


        .sidebar-avatar {

            width: 36px;

            height: 36px;

            flex-shrink: 0;

            border-radius: 11px;

            background:
                #38bdf8;

            color:
                #082f49;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 12px;

            font-weight: 800;
        }


        .sidebar-user-info {

            min-width: 0;
        }


        .sidebar-user-info strong {

            display: block;

            font-size: 11px;

            color: white;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }


        .sidebar-user-info span {

            display: block;

            margin-top: 3px;

            font-size: 9px;

            color:
                rgba(
                    255,
                    255,
                    255,
                    0.50
                );
        }


        /*
        |--------------------------------------------------------------------------
        | MAIN CONTENT
        |--------------------------------------------------------------------------
        */

        .main-content {

            width: calc(100% - 260px);

            margin-left: 260px;

            min-height: 100vh;
        }


        /*
        |--------------------------------------------------------------------------
        | TOP BAR
        |--------------------------------------------------------------------------
        */

        .topbar {

            height: 75px;

            background: white;

            border-bottom:
                1px solid
                #e2e8f0;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0
                32px;

            position: sticky;

            top: 0;

            z-index: 50;
        }


        .topbar-left h2 {

            font-size: 18px;

            font-weight: 800;

            color: #102a43;
        }


        .topbar-left p {

            font-size: 11px;

            color: #94a3b8;

            margin-top: 4px;
        }


        .topbar-right {

            display: flex;

            align-items: center;

            gap: 15px;
        }


        .notification {

            width: 39px;

            height: 39px;

            border-radius: 11px;

            background: #f8fafc;

            border:
                1px solid
                #e2e8f0;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 16px;

            cursor: pointer;

            position: relative;
        }


        .notification-dot {

            position: absolute;

            width: 7px;

            height: 7px;

            border-radius: 50%;

            background: #ef4444;

            top: 8px;

            right: 8px;

            border: 2px solid white;
        }


        .topbar-profile {

            display: flex;

            align-items: center;

            gap: 9px;

            padding-left: 10px;

            border-left:
                1px solid
                #e2e8f0;
        }


        .topbar-avatar {

            width: 38px;

            height: 38px;

            border-radius: 11px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0284c7
                );

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 12px;

            font-weight: 800;
        }


        .topbar-profile strong {

            display: block;

            font-size: 11px;

            color: #334e68;
        }


        .topbar-profile span {

            display: block;

            margin-top: 2px;

            font-size: 9px;

            color: #94a3b8;
        }


        /*
        |--------------------------------------------------------------------------
        | PAGE
        |--------------------------------------------------------------------------
        */

        .page {

            padding: 30px 32px 40px;
        }


        /*
        |--------------------------------------------------------------------------
        | WELCOME HERO
        |--------------------------------------------------------------------------
        */

        .welcome {

            min-height: 190px;

            border-radius: 22px;

            padding:
                30px 34px;

            position: relative;

            overflow: hidden;

            background-image:

                linear-gradient(
                    110deg,
                    rgba(
                        15,
                        47,
                        79,
                        0.96
                    ),
                    rgba(
                        7,
                        89,
                        133,
                        0.82
                    )
                ),

                url(
                    "https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=1600&q=85"
                );

            background-size: cover;

            background-position: center;

            color: white;

            box-shadow:
                0 15px 35px
                rgba(
                    15,
                    47,
                    79,
                    0.16
                );
        }


        .welcome::after {

            content: "";

            position: absolute;

            width: 230px;

            height: 230px;

            border-radius: 50%;

            background:
                rgba(
                    56,
                    189,
                    248,
                    0.14
                );

            right: -60px;

            top: -80px;
        }


        .welcome-content {

            position: relative;

            z-index: 2;

            max-width: 620px;
        }


        .welcome-badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding:
                6px
                10px;

            border-radius: 20px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    0.12
                );

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.12
                );

            font-size: 10px;

            font-weight: 600;

            margin-bottom: 14px;
        }


        .welcome h1 {

            font-size: 27px;

            font-weight: 800;

            letter-spacing: -0.8px;
        }


        .welcome h1 span {

            color: #7dd3fc;
        }


        .welcome p {

            margin-top: 9px;

            color:
                rgba(
                    255,
                    255,
                    255,
                    0.70
                );

            font-size: 12px;

            line-height: 1.6;
        }


        /*
        |--------------------------------------------------------------------------
        | STATS
        |--------------------------------------------------------------------------
        */

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 17px;

            margin-top: 20px;
        }


        .stat-card {

            background: white;

            border:
                1px solid
                #e5eaf0;

            border-radius: 17px;

            padding: 19px;

            display: flex;

            align-items: center;

            gap: 13px;

            box-shadow:
                0 5px 20px
                rgba(
                    15,
                    47,
                    79,
                    0.04
                );

            transition:
                transform
                0.2s ease,

                box-shadow
                0.2s ease;
        }


        .stat-card:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 10px 25px
                rgba(
                    15,
                    47,
                    79,
                    0.08
                );
        }


        .stat-icon {

            width: 43px;

            height: 43px;

            flex-shrink: 0;

            border-radius: 13px;

            background: #eff6ff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 19px;
        }


        .stat-info span {

            display: block;

            color: #94a3b8;

            font-size: 10px;

            margin-bottom: 4px;
        }


        .stat-info strong {

            display: block;

            color: #102a43;

            font-size: 16px;

            font-weight: 800;
        }


        /*
        |--------------------------------------------------------------------------
        | CONTENT GRID
        |--------------------------------------------------------------------------
        */

        .content-grid {

            display: grid;

            grid-template-columns:
                1.45fr
                0.85fr;

            gap: 20px;

            margin-top: 20px;
        }


        /*
        |--------------------------------------------------------------------------
        | CARD
        |--------------------------------------------------------------------------
        */

        .card {

            background: white;

            border:
                1px solid
                #e5eaf0;

            border-radius: 18px;

            padding: 23px;

            box-shadow:
                0 5px 20px
                rgba(
                    15,
                    47,
                    79,
                    0.04
                );
        }


        .card-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 20px;

            padding-bottom: 15px;

            border-bottom:
                1px solid
                #edf2f7;
        }


        .card-header-left {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .card-header-icon {

            width: 36px;

            height: 36px;

            border-radius: 10px;

            background: #eff6ff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 16px;
        }


        .card h3 {

            color: #102a43;

            font-size: 14px;

            font-weight: 700;
        }


        .card-header p {

            color: #94a3b8;

            font-size: 10px;

            margin-top: 3px;
        }


        /*
        |--------------------------------------------------------------------------
        | PROFILE INFORMATION
        |--------------------------------------------------------------------------
        */

        .profile-list {

            display: grid;

            grid-template-columns:
                1fr
                1fr;

            gap: 12px;
        }


        .profile-item {

            padding:
                14px;

            border-radius: 12px;

            background: #f8fafc;

            border:
                1px solid
                #edf2f7;
        }


        .profile-item span {

            display: block;

            font-size: 9px;

            color: #94a3b8;

            margin-bottom: 5px;

            text-transform: uppercase;

            letter-spacing: 0.4px;

            font-weight: 600;
        }


        .profile-item strong {

            display: block;

            font-size: 12px;

            color: #334e68;

            word-break: break-word;
        }


        /*
        |--------------------------------------------------------------------------
        | ACCOUNT TYPE BADGE
        |--------------------------------------------------------------------------
        */

        .role-badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding:
                6px
                10px;

            border-radius: 20px;

            background: #eff6ff;

            color: #1d4ed8;

            font-size: 10px;

            font-weight: 700;
        }


        /*
        |--------------------------------------------------------------------------
        | QUICK ACTIONS
        |--------------------------------------------------------------------------
        */

        .actions {

            display: grid;

            grid-template-columns:
                1fr
                1fr;

            gap: 10px;
        }


        .action {

            min-height: 105px;

            border:
                1px solid
                #e5eaf0;

            border-radius: 14px;

            padding: 15px;

            text-decoration: none;

            background: white;

            transition:
                transform
                0.2s ease,

                box-shadow
                0.2s ease,

                border-color
                0.2s ease;
        }


        .action:hover {

            transform:
                translateY(-3px);

            border-color:
                #bfdbfe;

            box-shadow:
                0 10px 25px
                rgba(
                    37,
                    99,
                    235,
                    0.08
                );
        }


        .action-icon {

            width: 35px;

            height: 35px;

            border-radius: 10px;

            background: #eff6ff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 16px;

            margin-bottom: 10px;
        }


        .action strong {

            display: block;

            color: #334e68;

            font-size: 11px;

            margin-bottom: 4px;
        }


        .action span {

            color: #94a3b8;

            font-size: 9px;

            line-height: 1.4;
        }


        /*
        |--------------------------------------------------------------------------
        | ROLE INFORMATION
        |--------------------------------------------------------------------------
        */

        .role-info {

            margin-top: 20px;

            padding: 20px;

            border-radius: 17px;

            background:
                linear-gradient(
                    135deg,
                    #eff6ff,
                    #f0f9ff
                );

            border:
                1px solid
                #dbeafe;
        }


        .role-info-title {

            display: flex;

            align-items: center;

            gap: 9px;

            margin-bottom: 15px;
        }


        .role-info-title span {

            font-size: 19px;
        }


        .role-info-title strong {

            font-size: 12px;

            color: #1e3a5f;
        }


        .role-info p {

            color: #64748b;

            font-size: 11px;

            line-height: 1.6;
        }


        /*
        |--------------------------------------------------------------------------
        | LOGOUT
        |--------------------------------------------------------------------------
        */

        .logout {

            display: flex;

            align-items: center;

            gap: 8px;

            color:
                #ef4444;

            text-decoration: none;

            font-size: 10px;

            font-weight: 600;

            padding:
                9px
                12px;

            border-radius: 9px;

            transition:
                background
                0.2s ease;
        }


        .logout:hover {

            background: #fef2f2;
        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE HEADER
        |--------------------------------------------------------------------------
        */

        .mobile-menu {

            display: none;

            width: 39px;

            height: 39px;

            border-radius: 10px;

            border:
                1px solid
                #e2e8f0;

            background: white;

            align-items: center;

            justify-content: center;

            cursor: pointer;

            font-size: 18px;
        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1100px) {

            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }


            .content-grid {

                grid-template-columns:
                    1fr;
            }
        }


        @media (max-width: 800px) {

            .sidebar {

                transform:
                    translateX(-100%);

                transition:
                    transform
                    0.25s ease;
            }


            .sidebar.open {

                transform:
                    translateX(0);
            }


            .main-content {

                width: 100%;

                margin-left: 0;
            }


            .mobile-menu {

                display: flex;
            }


            .topbar {

                padding:
                    0
                    18px;
            }


            .topbar-left {

                display: flex;

                align-items: center;

                gap: 10px;
            }


            .page {

                padding:
                    20px
                    15px
                    30px;
            }
        }


        @media (max-width: 600px) {

            .stats-grid {

                grid-template-columns:
                    1fr;
            }


            .welcome {

                padding:
                    25px
                    22px;
            }


            .welcome h1 {

                font-size: 22px;
            }


            .profile-list {

                grid-template-columns:
                    1fr;
            }


            .topbar-profile {

                display: none;
            }


            .topbar-left p {

                display: none;
            }


            .card {

                padding: 18px;
            }


            .actions {

                grid-template-columns:
                    1fr
                    1fr;
            }
        }

    </style>

</head>


<body>


<div class="dashboard">


    <!--
    ======================================================================
    SIDEBAR
    ======================================================================
    -->

    <aside
        class="sidebar"
        id="sidebar"
    >


        <!-- LOGO -->

        <div class="sidebar-logo">

            <div class="sidebar-logo-icon">

                🎓

            </div>


            <h1>

                My<span>School</span>

            </h1>

        </div>


        <!-- NAVIGATION -->

        <div class="nav-title">

            Main Menu

        </div>


        <nav class="sidebar-nav">


            <a
                href="dashboard.php"
                class="nav-item active"
            >

                <span class="nav-icon">

                    🏠

                </span>

                Dashboard

            </a>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">

                    👤

                </span>

                Profile

            </a>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">

                    📚

                </span>

                Academics

            </a>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">

                    💰

                </span>

                Fees

            </a>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">

                    💬

                </span>

                Messages

            </a>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">

                    📅

                </span>

                Calendar

            </a>

        </nav>


        <!-- BOTTOM USER -->

        <div class="sidebar-bottom">

            <div class="sidebar-user">


                <div class="sidebar-avatar">

                    <?= htmlspecialchars($initials) ?>

                </div>


                <div class="sidebar-user-info">

                    <strong>

                        <?= htmlspecialchars(
                            $display_name
                        ) ?>

                    </strong>


                    <span>

                        <?= htmlspecialchars(
                            $role_label
                        ) ?>

                    </span>

                </div>

            </div>


            <a
                href="logout.php"
                class="nav-item"
            >

                <span class="nav-icon">

                    🚪

                </span>

                Logout

            </a>

        </div>

    </aside>


    <!--
    ======================================================================
    MAIN CONTENT
    ======================================================================
    -->

    <div class="main-content">


        <!-- TOP BAR -->

        <header class="topbar">


            <div class="topbar-left">


                <div
                    class="mobile-menu"
                    onclick="toggleSidebar()"
                >

                    ☰

                </div>


                <div>

                    <h2>

                        Dashboard

                    </h2>


                    <p>

                        School Management System

                    </p>

                </div>

            </div>


            <div class="topbar-right">


                <div class="notification">

                    🔔

                    <span
                        class="notification-dot"
                    ></span>

                </div>


                <div class="topbar-profile">


                    <div class="topbar-avatar">

                        <?= htmlspecialchars(
                            $initials
                        ) ?>

                    </div>


                    <div>

                        <strong>

                            <?= htmlspecialchars(
                                $display_name
                            ) ?>

                        </strong>


                        <span>

                            <?= htmlspecialchars(
                                $role_label
                            ) ?>

                        </span>

                    </div>

                </div>

            </div>

        </header>


        <!-- PAGE -->

        <main class="page">


            <!--
            ==================================================================
            WELCOME
            ==================================================================
            -->

            <section class="welcome">


                <div class="welcome-content">


                    <div class="welcome-badge">

                        <?= $role_icon ?>

                        <?= htmlspecialchars(
                            $role_label
                        ) ?>

                    </div>


                    <h1>

                        Welcome back,
                        <span>

                            <?= htmlspecialchars(
                                $display_name
                            ) ?>

                        </span>

                        👋

                    </h1>


                    <p>

                        Welcome to your MySchool
                        dashboard. Manage your school
                        information, academics, fees and
                        messages from one place.

                    </p>

                </div>

            </section>


            <!--
            ==================================================================
            STATS
            ==================================================================
            -->

            <section class="stats-grid">


                <div class="stat-card">

                    <div class="stat-icon">

                        👤

                    </div>


                    <div class="stat-info">

                        <span>

                            Account

                        </span>


                        <strong>

                            Active

                        </strong>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon">

                        🎓

                    </div>


                    <div class="stat-info">

                        <span>

                            Role

                        </span>


                        <strong>

                            <?= htmlspecialchars(
                                $role_label
                            ) ?>

                        </strong>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon">

                        📚

                    </div>


                    <div class="stat-info">

                        <span>

                            Academics

                        </span>


                        <strong>

                            Available

                        </strong>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon">

                        🔔

                    </div>


                    <div class="stat-info">

                        <span>

                            Notifications

                        </span>


                        <strong>

                            New

                        </strong>

                    </div>

                </div>

            </section>


            <!--
            ==================================================================
            CONTENT
            ==================================================================
            -->

            <section class="content-grid">


                <!--
                ==============================================================
                ACCOUNT INFORMATION
                ==============================================================
                -->

                <div class="card">


                    <div class="card-header">


                        <div class="card-header-left">


                            <div class="card-header-icon">

                                👤

                            </div>


                            <div>

                                <h3>

                                    Account Information

                                </h3>


                                <p>

                                    Your registered details

                                </p>

                            </div>

                        </div>


                        <div class="role-badge">

                            <?= $role_icon ?>

                            <?= htmlspecialchars(
                                $role_label
                            ) ?>

                        </div>

                    </div>


                    <div class="profile-list">


                        <!-- EMAIL -->

                        <div class="profile-item">

                            <span>

                                Email Address

                            </span>


                            <strong>

                                <?= htmlspecialchars(
                                    $user["email"]
                                    ?? "Not provided"
                                ) ?>

                            </strong>

                        </div>


                        <!-- PHONE -->

                        <div class="profile-item">

                            <span>

                                Phone Number

                            </span>


                            <strong>

                                <?= htmlspecialchars(
                                    $user["phone_number"]
                                    ?? "Not provided"
                                ) ?>

                            </strong>

                        </div>


                        <!-- FULL NAME -->

                        <?php if (!empty($user["full_name"])): ?>

                            <div class="profile-item">

                                <span>

                                    Full Name

                                </span>


                                <strong>

                                    <?= htmlspecialchars(
                                        $user["full_name"]
                                    ) ?>

                                </strong>

                            </div>

                        <?php endif; ?>


                        <!-- STUDENT -->

                        <?php if ($role === "STUDENT"): ?>


                            <div class="profile-item">

                                <span>

                                    Admission Number

                                </span>


                                <strong>

                                    <?= htmlspecialchars(
                                        $user["adm_number"]
                                        ?? "Not provided"
                                    ) ?>

                                </strong>

                            </div>


                            <div class="profile-item">

                                <span>

                                    Class

                                </span>


                                <strong>

                                    <?= htmlspecialchars(
                                        ($user["class_name"]
                                        ?? "Not assigned")
                                        . " - "
                                        . ($user["stream_name"]
                                        ?? "")
                                    ) ?>

                                </strong>

                            </div>


                            <div class="profile-item">

                                <span>

                                    Gender

                                </span>


                                <strong>

                                    <?= htmlspecialchars(
                                        $user["gender"]
                                        ?? "Not provided"
                                    ) ?>

                                </strong>

                            </div>


                            <div class="profile-item">

                                <span>

                                    Date of Birth

                                </span>


                                <strong>

                                    <?= htmlspecialchars(
                                        $user["date_of_birth"]
                                        ?? "Not provided"
                                    ) ?>

                                </strong>

                            </div>


                        <?php endif; ?>


                        <!-- TEACHER -->

                        <?php if ($role === "TEACHER"): ?>


                            <div class="profile-item">

                                <span>

                                    TSC Number

                                </span>


                                <strong>

                                    <?= htmlspecialchars(
                                        $user["tsc_number"]
                                        ?? "Not provided"
                                    ) ?>

                                </strong>

                            </div>


                            <div class="profile-item">

                                <span>

                                    Specialization

                                </span>


                                <strong>

                                    <?= htmlspecialchars(
                                        $user["specialization"]
                                        ?? "Not provided"
                                    ) ?>

                                </strong>

                            </div>


                        <?php endif; ?>


                        <!-- PARENT -->

                        <?php if ($role === "PARENT"): ?>


                            <div class="profile-item">

                                <span>

                                    National ID

                                </span>


                                <strong>

                                    <?= htmlspecialchars(
                                        $user["national_id"]
                                        ?? "Not provided"
                                    ) ?>

                                </strong>

                            </div>


                            <div class="profile-item">

                                <span>

                                    Relationship

                                </span>


                                <strong>

                                    <?= htmlspecialchars(
                                        $user["relationship"]
                                        ?? "Not provided"
                                    ) ?>

                                </strong>

                            </div>


                        <?php endif; ?>

                    </div>


                    <!-- ROLE DESCRIPTION -->

                    <div class="role-info">


                        <div class="role-info-title">

                            <span>

                                <?= $role_icon ?>

                            </span>


                            <strong>

                                <?= htmlspecialchars(
                                    $role_label
                                ) ?> Account

                            </strong>

                        </div>


                        <p>

                            Your account is currently
                            active. Use the dashboard
                            menu to access the features
                            available to you.

                        </p>

                    </div>

                </div>


                <!--
                ==============================================================
                QUICK ACTIONS
                ==============================================================
                -->

                <div class="card">


                    <div class="card-header">


                        <div class="card-header-left">


                            <div class="card-header-icon">

                                ⚡

                            </div>


                            <div>

                                <h3>

                                    Quick Actions

                                </h3>


                                <p>

                                    Frequently used features

                                </p>

                            </div>

                        </div>

                    </div>


                    <div class="actions">


                        <a
                            href="#"
                            class="action"
                        >

                            <div class="action-icon">

                                👤

                            </div>


                            <strong>

                                Profile

                            </strong>


                            <span>

                                Manage your personal
                                information.

                            </span>

                        </a>


                        <a
                            href="#"
                            class="action"
                        >

                            <div class="action-icon">

                                📚

                            </div>


                            <strong>

                                Academics

                            </strong>


                            <span>

                                View academic
                                information.

                            </span>

                        </a>


                        <a
                            href="#"
                            class="action"
                        >

                            <div class="action-icon">

                                💰

                            </div>


                            <strong>

                                Fees

                            </strong>


                            <span>

                                View your fee
                                information.

                            </span>

                        </a>


                        <a
                            href="#"
                            class="action"
                        >

                            <div class="action-icon">

                                💬

                            </div>


                            <strong>

                                Messages

                            </strong>


                            <span>

                                View your latest
                                messages.

                            </span>

                        </a>


                        <a
                            href="#"
                            class="action"
                        >

                            <div class="action-icon">

                                📅

                            </div>


                            <strong>

                                Calendar

                            </strong>


                            <span>

                                View school events
                                and activities.

                            </span>

                        </a>


                        <a
                            href="logout.php"
                            class="action"
                        >

                            <div class="action-icon">

                                🚪

                            </div>


                            <strong>

                                Logout

                            </strong>


                            <span>

                                Securely sign out
                                of your account.

                            </span>

                        </a>

                    </div>

                </div>

            </section>

        </main>

    </div>

</div>


<script>

function toggleSidebar() {

    const sidebar =
        document.getElementById("sidebar");

    sidebar.classList.toggle("open");

}


/*
|--------------------------------------------------------------------------
| CLOSE SIDEBAR WHEN CLICKING OUTSIDE
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "click",
    function(event) {

        const sidebar =
            document.getElementById("sidebar");

        const menu =
            document.querySelector(".mobile-menu");


        if (
            window.innerWidth <= 800 &&
            sidebar.classList.contains("open") &&
            !sidebar.contains(event.target) &&
            !menu.contains(event.target)
        ) {

            sidebar.classList.remove("open");

        }

    }
);

</script>


</body>

</html>
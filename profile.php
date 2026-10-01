<?php
session_start();
require_once __DIR__ . '/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// 1. Fetch main user record from `users` table (ID column is `user_id`)
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User session invalid or user not found.");
}

// Extract variables safely with proper schema field names
$user_name  = $user['full_name'] ?? '';
$user_email = $user['email'] ?? '';
$user_phone = $user['phone_number'] ?? '';
$user_pic   = $user['profile_pic'] ?? '';
$user_role  = strtolower($user['role'] ?? 'user');

// Helper function to send email notification using PHPMailer
function sendProfileUpdateEmail($toEmail, $userName) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'] ?? '';
        $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;

        $mail->setFrom($_ENV['SMTP_USER'] ?? 'no-reply@myschool.com', $_ENV['APP_NAME'] ?? 'MyElimu');
        $mail->addAddress($toEmail, $userName);

        $mail->isHTML(true);
        $mail->Subject = 'Profile Updated Successfully';
        $mail->Body    = "<p>Hello <strong>" . htmlspecialchars($userName) . "</strong>,</p>" .
                         "<p>Your profile information on MyElimu was updated successfully.</p>" .
                         "<p>If you did not perform this change, please contact support immediately.</p>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Profile update email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

// 2. Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name) || empty($email)) {
        $error = "Name and Email address are required.";
    } else {
        try {
            $conn->beginTransaction();

            $imagePath = $user_pic;

            // Handle Profile Image Upload
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath   = $_FILES['profile_picture']['tmp_name'];
                $fileName      = $_FILES['profile_picture']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    $uploadFileDir = './uploads/profile_pics/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }

                    $newFileName = 'user_' . $user_id . '_' . time() . '.' . $fileExtension;
                    $dest_path   = $uploadFileDir . $newFileName;

                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $imagePath = $dest_path;
                    }
                } else {
                    $error = "Invalid image format. Allowed: JPG, JPEG, PNG, WEBP.";
                }
            }

            if (empty($error)) {
                // Update primary `users` table
                $update_stmt = $conn->prepare("
                    UPDATE users 
                    SET email = :email, phone_number = :phone 
                    WHERE user_id = :id
                ");
                $update_stmt->execute([
                    'email' => $email,
                    'phone' => $phone,
                    'id'    => $user_id
                ]);

                // Synchronize sub-table records depending on user role
                if ($user_role === 'parent') {
                    $stmt_role = $conn->prepare("UPDATE parents SET full_name = :name, phone = :phone, profile_pic = :pic WHERE user_id = :user_id");
                    $stmt_role->execute([
                        'name'    => $name,
                        'phone'   => $phone,
                        'pic'     => $imagePath,
                        'user_id' => $user_id
                    ]);
                } elseif ($user_role === 'teacher') {
                    $stmt_role = $conn->prepare("UPDATE teachers SET full_name = :name, phone = :phone WHERE user_id = :user_id");
                    $stmt_role->execute([
                        'name'    => $name,
                        'phone'   => $phone,
                        'user_id' => $user_id
                    ]);
                } elseif ($user_role === 'student') {
                    $stmt_role = $conn->prepare("UPDATE students SET full_name = :name WHERE user_id = :user_id");
                    $stmt_role->execute([
                        'name'    => $name,
                        'user_id' => $user_id
                    ]);
                }

                $conn->commit();

                // Send email alert
                sendProfileUpdateEmail($email, $name);

                // Update runtime values for view
                $user_name  = $name;
                $user_email = $email;
                $user_phone = $phone;
                $user_pic   = $imagePath;

                $success = "Profile updated successfully!";
                
                }
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Failed to update profile: " . $e->getMessage();
        }
        header("Location: dashboard.php");
    }
}

// Dynamic Initials Fallback Generator
function getInitials($fullName) {
    $words = array_filter(explode(' ', trim($fullName)));
    if (count($words) >= 2) {
        $first = array_shift($words);
        $last  = array_pop($words);
        return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
    } elseif (count($words) === 1) {
        return strtoupper(mb_substr($words[0], 0, 2));
    }
    return 'U';
}

$userInitials = getInitials($user_name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MyElimu</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f6f9; margin: 0; padding: 40px 15px; }
        .profile-card { max-width: 500px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .profile-card h2 { text-align: center; margin-bottom: 25px; color: #2c3e50; font-size: 22px; }
        .avatar-box { width: 100px; height: 100px; border-radius: 50%; background: #2c3e50; color: #ffffff; font-size: 36px; font-weight: bold; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; overflow: hidden; border: 3px solid #e2e8f0; }
        .avatar-box img { width: 100%; height: 100%; object-fit: cover; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; color: #333; }
        .form-group input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .form-group input:focus { border-color: #27ae60; }
        .btn-submit { width: 100%; padding: 12px; background-color: #27ae60; color: white; border: none; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.2s; margin-top: 10px; }
        .btn-submit:hover { background-color: #219150; }
        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; font-size: 14px; margin-bottom: 20px; text-align: center; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; font-size: 14px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>

<div class="profile-card">
    <h2>User Profile (<?= htmlspecialchars(strtoupper($user_role)) ?>)</h2>

    <?php if (!empty($success)): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="avatar-box">
        <?php if (!empty($user_pic) && file_exists($user_pic)): ?>
            <img src="<?= htmlspecialchars($user_pic) ?>" alt="Profile Picture">
        <?php else: ?>
            <span><?= htmlspecialchars($userInitials) ?></span>
        <?php endif; ?>
    </div>

    <form method="POST" action="profile.php" enctype="multipart/form-data">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($user_name) ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_email) ?>" required>
        </div>

        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user_phone) ?>">
        </div>

        <div class="form-group">
            <label for="profile_picture">Profile Picture</label>
            <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
        </div>

        <a href="dashboard.php" ><button type="submit" class="btn-submit">Save Changes</button> </a>
        
    </form>
</div>

</body>
</html>
<?php

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/message_helpers.php';

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = strtoupper(trim((string) ($_SESSION['role'] ?? '')));
$allowedRoles = ['PARENT', 'STUDENT', 'TEACHER', 'SCHOOL_ADMIN', 'SUPER_ADMIN'];
$dashboardUrl = [
    'PARENT' => 'parent.php',
    'STUDENT' => 'student.php',
    'TEACHER' => 'teacher.php',
    'SCHOOL_ADMIN' => 'admin.php',
    'SUPER_ADMIN' => 'admin.php'
][$role] ?? 'login.php';

if ($userId <= 0 || !in_array($role, $allowedRoles, true)) {
    header('Location: login.php');
    exit;
}

function messageEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


function messageAttachmentDirectory(): string
{
    $directory = __DIR__ . '/uploads/messages';

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The message upload directory could not be created.');
    }

    return $directory;
}

function messageUploadAttachment(array $file, string $kind): array
{
    $kind = strtoupper(trim($kind));

    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid attachment upload.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'The attachment is larger than the server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'The attachment is too large.',
            UPLOAD_ERR_PARTIAL => 'The attachment upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'Choose a file first.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload directory is missing.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not save the attachment.',
            UPLOAD_ERR_EXTENSION => 'The server stopped the attachment upload.'
        ];

        throw new RuntimeException($errors[$file['error']] ?? 'The attachment upload failed.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The uploaded file could not be verified.');
    }

    $maxSize = match ($kind) {
        'DOCUMENT' => 20 * 1024 * 1024,
        'PHOTO_VIDEO', 'CAMERA' => 25 * 1024 * 1024,
        'AUDIO' => 15 * 1024 * 1024,
        'STICKER' => 5 * 1024 * 1024,
        default => 25 * 1024 * 1024
    };

    if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxSize) {
        throw new RuntimeException(
            'The selected file is too large. Maximum allowed for this attachment is '
            . number_format($maxSize / 1048576, 0) . ' MB.'
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);

    $documentMimes = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip'
    ];

    $imageMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];

    $videoMimes = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi'
    ];

    $audioMimes = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
        'audio/webm' => 'webm',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a'
    ];

    if ($kind === 'DOCUMENT') {
        if (!isset($documentMimes[$mime])) {
            throw new RuntimeException('That file type is not allowed as a document.');
        }

        $attachmentType = 'DOCUMENT';
        $extension = $documentMimes[$mime];
    } elseif ($kind === 'AUDIO') {
        if (!isset($audioMimes[$mime])) {
            throw new RuntimeException('That file is not a supported audio file.');
        }

        $attachmentType = 'AUDIO';
        $extension = $audioMimes[$mime];
    } elseif ($kind === 'STICKER') {
        if (!isset($imageMimes[$mime])) {
            throw new RuntimeException('A sticker must be an image.');
        }

        $attachmentType = 'STICKER';
        $extension = $imageMimes[$mime];
    } elseif (in_array($kind, ['PHOTO_VIDEO', 'CAMERA'], true)) {
        if (isset($imageMimes[$mime])) {
            $attachmentType = $kind === 'CAMERA' ? 'CAMERA' : 'PHOTO';
            $extension = $imageMimes[$mime];
        } elseif (isset($videoMimes[$mime]) && $kind === 'PHOTO_VIDEO') {
            $attachmentType = 'VIDEO';
            $extension = $videoMimes[$mime];
        } else {
            throw new RuntimeException('That photo or video format is not supported.');
        }
    } else {
        throw new RuntimeException('Unsupported attachment option.');
    }

    $originalName = trim((string) ($file['name'] ?? 'attachment'));
    $originalName = preg_replace('/[^\pL\pN._ -]+/u', '_', $originalName);
    $originalName = trim((string) $originalName, " .\t\n\r\0\x0B");

    if ($originalName === '') {
        $originalName = 'attachment.' . $extension;
    }

    $storedName = bin2hex(random_bytes(18)) . '.' . $extension;
    $directory = messageAttachmentDirectory();
    $absolutePath = $directory . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('The server could not save the attachment.');
    }

    @chmod($absolutePath, 0644);

    return [
        'type' => $attachmentType,
        'url' => 'uploads/messages/' . $storedName,
        'name' => $originalName,
        'meta' => [
            'mime' => $mime,
            'size' => (int) $file['size']
        ]
    ];
}

function messageSpecialAttachment(string $type, array $data): array
{
    $type = strtoupper(trim($type));

    if ($type === 'CONTACT') {
        $name = trim((string) ($data['contact_name'] ?? ''));
        $phone = trim((string) ($data['contact_phone'] ?? ''));
        $email = trim((string) ($data['contact_email'] ?? ''));

        if ($name === '' || $phone === '') {
            throw new RuntimeException('Enter the contact name and phone number.');
        }

        if (mb_strlen($name) > 100 || mb_strlen($phone) > 30 || mb_strlen($email) > 150) {
            throw new RuntimeException('The contact information is too long.');
        }

        return [
            'type' => 'CONTACT',
            'name' => $name,
            'meta' => [
                'name' => $name,
                'phone' => $phone,
                'email' => $email
            ]
        ];
    }

    if ($type === 'POLL') {
        $question = trim((string) ($data['poll_question'] ?? ''));
        $options = $data['poll_options'] ?? [];

        if (!is_array($options)) {
            $options = [];
        }

        $options = array_values(array_filter(
            array_map(
                static fn ($option): string => trim((string) $option),
                $options
            ),
            static fn (string $option): bool => $option !== ''
        ));

        if ($question === '' || count($options) < 2) {
            throw new RuntimeException('A poll needs a question and at least two options.');
        }

        if (mb_strlen($question) > 300 || count($options) > 6) {
            throw new RuntimeException('Polls can have 2 to 6 options and a question up to 300 characters.');
        }

        foreach ($options as $option) {
            if (mb_strlen($option) > 150) {
                throw new RuntimeException('Each poll option must be 150 characters or less.');
            }
        }

        return [
            'type' => 'POLL',
            'name' => $question,
            'meta' => [
                'question' => $question,
                'options' => $options
            ]
        ];
    }

    if ($type === 'EVENT') {
        $title = trim((string) ($data['event_title'] ?? ''));
        $date = trim((string) ($data['event_date'] ?? ''));
        $time = trim((string) ($data['event_time'] ?? ''));
        $location = trim((string) ($data['event_location'] ?? ''));
        $description = trim((string) ($data['event_description'] ?? ''));

        if ($title === '' || $date === '') {
            throw new RuntimeException('Enter the event title and date.');
        }

        $dateObject = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            throw new RuntimeException('Enter a valid event date.');
        }

        if ($time !== '') {
            $timeObject = DateTime::createFromFormat('H:i', $time);
            if (!$timeObject || $timeObject->format('H:i') !== $time) {
                throw new RuntimeException('Enter a valid event time.');
            }
        }

        if (
            mb_strlen($title) > 200
            || mb_strlen($location) > 200
            || mb_strlen($description) > 1000
        ) {
            throw new RuntimeException('The event information is too long.');
        }

        return [
            'type' => 'EVENT',
            'name' => $title,
            'meta' => [
                'title' => $title,
                'date' => $date,
                'time' => $time,
                'location' => $location,
                'description' => $description
            ]
        ];
    }

    throw new RuntimeException('Unsupported attachment option.');
}

function messageAttachmentMeta(?string $json): array
{
    if (!$json) {
        return [];
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : [];
}

function messageProfile(PDO $conn, int $userId, string $role): array
{
    $tableAndId = [
        'PARENT' => ['parents', 'parent_id'],
        'STUDENT' => ['students', 'student_id'],
        'TEACHER' => ['teachers', 'teacher_id']
    ][$role];

    $statement = $conn->prepare(
        'SELECT ' . $tableAndId[1] . ' AS profile_id
         FROM ' . $tableAndId[0] . ' WHERE user_id = ? LIMIT 1'
    );
    $statement->execute([$userId]);
    $profileId = $statement->fetchColumn();

    if (!$profileId) {
        throw new RuntimeException('Your account is not linked to a messaging profile.');
    }

    return [(int) $profileId];
}

function messageEligibleContacts(PDO $conn, int $userId, string $role): array
{
    $contacts = [];
    $addContacts = static function (array $rows) use (&$contacts): void {
        foreach ($rows as $row) {
            $row['partner_user_id'] = (int) $row['partner_user_id'];
            $row['student_id'] = (int) $row['student_id'];
            $contacts[$row['partner_user_id'] . ':' . $row['student_id']] = $row;
        }
    };

    messageProfile($conn, $userId, $role);

    if ($role === 'STUDENT') {
        $statement = $conn->prepare(
            "SELECT DISTINCT t.user_id AS partner_user_id, t.full_name AS partner_name,
                    'TEACHER' AS partner_role, s.student_id, s.full_name AS student_name
             FROM students s
             INNER JOIN teacher_subject_assignments tsa ON tsa.class_id = s.class_id AND tsa.is_active = 1
             INNER JOIN teachers t ON t.teacher_id = tsa.teacher_id
             INNER JOIN users u ON u.user_id = t.user_id AND u.role = 'TEACHER'
             WHERE s.user_id = ? AND s.status = 'ACTIVE'"
        );
        $statement->execute([$userId]);
        $addContacts($statement->fetchAll(PDO::FETCH_ASSOC));

        $statement = $conn->prepare(
            "SELECT DISTINCT p.user_id AS partner_user_id, p.full_name AS partner_name,
                    'PARENT' AS partner_role, s.student_id, s.full_name AS student_name
             FROM students s
             INNER JOIN parent_student_map psm ON psm.student_id = s.student_id
             INNER JOIN parents p ON p.parent_id = psm.parent_id
             INNER JOIN users u ON u.user_id = p.user_id AND u.role = 'PARENT'
             WHERE s.user_id = ? AND s.status = 'ACTIVE'"
        );
        $statement->execute([$userId]);
        $addContacts($statement->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($role === 'PARENT') {
        $statement = $conn->prepare(
            "SELECT DISTINCT us.user_id AS partner_user_id, s.full_name AS partner_name,
                    'STUDENT' AS partner_role, s.student_id, s.full_name AS student_name
             FROM parents p
             INNER JOIN parent_student_map psm ON psm.parent_id = p.parent_id
             INNER JOIN students s ON s.student_id = psm.student_id AND s.status = 'ACTIVE'
             INNER JOIN users us ON us.user_id = s.user_id AND us.role = 'STUDENT'
             WHERE p.user_id = ?"
        );
        $statement->execute([$userId]);
        $addContacts($statement->fetchAll(PDO::FETCH_ASSOC));

        $statement = $conn->prepare(
            "SELECT DISTINCT ut.user_id AS partner_user_id, t.full_name AS partner_name,
                    'TEACHER' AS partner_role, s.student_id, s.full_name AS student_name
             FROM parents p
             INNER JOIN parent_student_map psm ON psm.parent_id = p.parent_id
             INNER JOIN students s ON s.student_id = psm.student_id AND s.status = 'ACTIVE'
             INNER JOIN teacher_subject_assignments tsa ON tsa.class_id = s.class_id AND tsa.is_active = 1
             INNER JOIN teachers t ON t.teacher_id = tsa.teacher_id
             INNER JOIN users ut ON ut.user_id = t.user_id AND ut.role = 'TEACHER'
             WHERE p.user_id = ?"
        );
        $statement->execute([$userId]);
        $addContacts($statement->fetchAll(PDO::FETCH_ASSOC));
    } else {
        $statement = $conn->prepare(
            "SELECT DISTINCT us.user_id AS partner_user_id, s.full_name AS partner_name,
                    'STUDENT' AS partner_role, s.student_id, s.full_name AS student_name
             FROM teachers t
             INNER JOIN teacher_subject_assignments tsa ON tsa.teacher_id = t.teacher_id AND tsa.is_active = 1
             INNER JOIN students s ON s.class_id = tsa.class_id AND s.status = 'ACTIVE'
             INNER JOIN users us ON us.user_id = s.user_id AND us.role = 'STUDENT'
             WHERE t.user_id = ?"
        );
        $statement->execute([$userId]);
        $addContacts($statement->fetchAll(PDO::FETCH_ASSOC));

        $statement = $conn->prepare(
            "SELECT DISTINCT up.user_id AS partner_user_id, p.full_name AS partner_name,
                    'PARENT' AS partner_role, s.student_id, s.full_name AS student_name
             FROM teachers t
             INNER JOIN teacher_subject_assignments tsa ON tsa.teacher_id = t.teacher_id AND tsa.is_active = 1
             INNER JOIN students s ON s.class_id = tsa.class_id AND s.status = 'ACTIVE'
             INNER JOIN parent_student_map psm ON psm.student_id = s.student_id
             INNER JOIN parents p ON p.parent_id = psm.parent_id
             INNER JOIN users up ON up.user_id = p.user_id AND up.role = 'PARENT'
             WHERE t.user_id = ?"
        );
        $statement->execute([$userId]);
        $addContacts($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    uasort($contacts, static fn (array $left, array $right): int =>
        strcasecmp($left['partner_name'], $right['partner_name'])
    );

    return $contacts;
}

function messageConversationId(PDO $conn, int $userId, int $partnerId, int $studentId): ?int
{
    $userOne = min($userId, $partnerId);
    $userTwo = max($userId, $partnerId);
    $statement = $conn->prepare(
        'SELECT conversation_id FROM conversations
                 WHERE conversation_type = \'DIRECT\'
                     AND user_one_id = ? AND user_two_id = ? AND student_id = ? LIMIT 1'
    );
    $statement->execute([$userOne, $userTwo, $studentId]);
    $conversationId = $statement->fetchColumn();
    return $conversationId === false ? null : (int) $conversationId;
}

function messageRecipientAccounts(PDO $conn): array
{
    return $conn->query(
        "SELECT u.user_id, u.role,
                COALESCE(p.full_name, s.full_name, t.full_name, u.email, 'Account') AS full_name
         FROM users u
         LEFT JOIN parents p ON p.user_id = u.user_id
         LEFT JOIN students s ON s.user_id = u.user_id
         LEFT JOIN teachers t ON t.user_id = u.user_id
         WHERE (u.role = 'PARENT' AND p.parent_id IS NOT NULL)
            OR (u.role = 'STUDENT' AND s.student_id IS NOT NULL)
            OR (u.role = 'TEACHER' AND t.teacher_id IS NOT NULL)
         ORDER BY u.role, full_name"
    )->fetchAll(PDO::FETCH_ASSOC);
}

if (empty($_SESSION['messages_csrf'])) {
    $_SESSION['messages_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['messages_csrf'];
$pageError = '';
$activeConversationId = (int) ($_GET['conversation_id'] ?? 0);
$contacts = [];
$recipientAccounts = [];
$conversations = [];
$activeConversation = null;
$messages = [];

try {
    if (in_array($role, ['SCHOOL_ADMIN', 'SUPER_ADMIN'], true)) {
        $recipientAccounts = messageRecipientAccounts($conn);
    } else {
        syncUserClassGroups($conn, $userId, $role);
        $contacts = messageEligibleContacts($conn, $userId, $role);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, (string) $_POST['csrf_token'])) {
            throw new RuntimeException('The form expired. Refresh the page and try again.');
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'broadcast' && in_array($role, ['SCHOOL_ADMIN', 'SUPER_ADMIN'], true)) {
            $audience = (string) ($_POST['audience'] ?? '');
            $selectedAccounts = [];
            $title = '';
            if ($audience === 'ALL') {
                $selectedAccounts = $recipientAccounts;
                $title = 'Announcement to all accounts';
            } elseif (in_array($audience, ['PARENT', 'STUDENT', 'TEACHER'], true)) {
                $selectedAccounts = array_values(array_filter(
                    $recipientAccounts,
                    static fn (array $account): bool => $account['role'] === $audience
                ));
                $title = 'Announcement to all ' . strtolower($audience) . 's';
            } elseif (str_starts_with($audience, 'USER:')) {
                $selectedUserId = (int) substr($audience, 5);
                foreach ($recipientAccounts as $account) {
                    if ((int) $account['user_id'] === $selectedUserId) {
                        $selectedAccounts = [$account];
                        $title = 'Announcement to ' . $account['full_name'];
                        break;
                    }
                }
            }

            if (!$selectedAccounts) {
                throw new RuntimeException('Choose an account or audience that has registered users.');
            }

            $conn->beginTransaction();
            try {
                $statement = $conn->prepare(
                    "INSERT INTO conversations (conversation_type, title, created_by_user_id)
                     VALUES ('ANNOUNCEMENT', ?, ?)"
                );
                $statement->execute([$title, $userId]);
                $announcementId = (int) $conn->lastInsertId();
                $participantStatement = $conn->prepare(
                    'INSERT INTO conversation_participants (conversation_id, user_id, can_send)
                     VALUES (?, ?, ?)'
                );
                $participantStatement->execute([$announcementId, $userId, 1]);
                foreach ($selectedAccounts as $account) {
                    $participantStatement->execute([$announcementId, (int) $account['user_id'], 0]);
                }
                sendConversationMessage($conn, $userId, $role, $announcementId, (string) ($_POST['message_body'] ?? ''));
                $conn->commit();
            } catch (Throwable $exception) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                throw $exception;
            }

            header('Location: messages.php?conversation_id=' . $announcementId);
            exit;
        }

        if ($action === 'send_attachment') {
            if (!in_array($role, ['PARENT', 'STUDENT', 'TEACHER', 'SCHOOL_ADMIN', 'SUPER_ADMIN'], true)) {
                throw new RuntimeException('Your account cannot send attachments.');
            }

            $activeConversationId = (int) ($_POST['conversation_id'] ?? 0);
            $attachmentKind = (string) ($_POST['attachment_kind'] ?? '');

            if ($activeConversationId <= 0) {
                throw new RuntimeException('Open a conversation before attaching a file.');
            }

            if (!isset($_FILES['attachment'])) {
                throw new RuntimeException('Choose a file first.');
            }

            $attachment = messageUploadAttachment(
                $_FILES['attachment'],
                $attachmentKind
            );

            $messageBody = trim((string) ($_POST['message_body'] ?? ''));
            try {
                sendConversationMessage(
                    $conn,
                    $userId,
                    $role,
                    $activeConversationId,
                    $messageBody,
                    $attachment
                );
            } catch (Throwable $exception) {
                $uploadedPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $attachment['url']);
                if (is_file($uploadedPath)) {
                    unlink($uploadedPath);
                }
                throw $exception;
            }

            header('Location: messages.php?conversation_id=' . $activeConversationId);
            exit;
        }

        if ($action === 'send_special') {
            if (!in_array($role, ['PARENT', 'STUDENT', 'TEACHER', 'SCHOOL_ADMIN', 'SUPER_ADMIN'], true)) {
                throw new RuntimeException('Your account cannot send this type of attachment.');
            }

            $activeConversationId = (int) ($_POST['conversation_id'] ?? 0);
            $specialType = (string) ($_POST['special_type'] ?? '');

            if ($activeConversationId <= 0) {
                throw new RuntimeException('Open a conversation first.');
            }

            $attachment = messageSpecialAttachment($specialType, $_POST);

            sendConversationMessage(
                $conn,
                $userId,
                $role,
                $activeConversationId,
                '',
                $attachment
            );

            header('Location: messages.php?conversation_id=' . $activeConversationId);
            exit;
        }

        if ($action === 'vote_poll') {
            $activeConversationId = (int) ($_POST['conversation_id'] ?? 0);
            $messageId = (int) ($_POST['message_id'] ?? 0);
            $optionIndex = (int) ($_POST['option_index'] ?? -1);
            voteMessagePoll($conn, $userId, $activeConversationId, $messageId, $optionIndex);
            header('Location: messages.php?conversation_id=' . $activeConversationId);
            exit;
        }

        if ($action === 'start') {
            if (!in_array($role, ['PARENT', 'STUDENT', 'TEACHER'], true)) {
                throw new RuntimeException('Only school community accounts can start direct chats.');
            }
            $contactKey = (string) ($_POST['contact_key'] ?? '');
            if (!isset($contacts[$contactKey])) {
                throw new RuntimeException('Choose an eligible account to start a conversation.');
            }
            $partnerId = (int) $contacts[$contactKey]['partner_user_id'];
            $studentId = (int) $contacts[$contactKey]['student_id'];
            if ($partnerId === $userId) {
                throw new RuntimeException('You cannot start a conversation with your own account.');
            }

            $activeConversationId = messageConversationId($conn, $userId, $partnerId, $studentId) ?? 0;
            if ($activeConversationId === 0) {
                $statement = $conn->prepare(
                    "INSERT INTO conversations (conversation_type, user_one_id, user_two_id, student_id)
                     VALUES ('DIRECT', ?, ?, ?)"
                );
                try {
                    $statement->execute([min($userId, $partnerId), max($userId, $partnerId), $studentId]);
                    $activeConversationId = (int) $conn->lastInsertId();
                    $participantStatement = $conn->prepare(
                        'INSERT INTO conversation_participants (conversation_id, user_id, can_send)
                         VALUES (?, ?, 1)'
                    );
                    $participantStatement->execute([$activeConversationId, $userId]);
                    $participantStatement->execute([$activeConversationId, $partnerId]);
                } catch (PDOException $exception) {
                    $activeConversationId = messageConversationId($conn, $userId, $partnerId, $studentId) ?? 0;
                    if ($activeConversationId === 0) {
                        throw $exception;
                    }
                }
            }

            $firstMessage = trim((string) ($_POST['message_body'] ?? ''));
            if ($firstMessage !== '') {
                sendConversationMessage($conn, $userId, $role, $activeConversationId, $firstMessage);
            }
            header('Location: messages.php?conversation_id=' . $activeConversationId);
            exit;
        }

        if ($action === 'send') {
            $activeConversationId = (int) ($_POST['conversation_id'] ?? 0);
            sendConversationMessage($conn, $userId, $role, $activeConversationId, (string) ($_POST['message_body'] ?? ''));
            header('Location: messages.php?conversation_id=' . $activeConversationId);
            exit;
        }

        throw new RuntimeException('The requested message action is not valid.');
    }

        $statement = $conn->prepare(
        'SELECT c.conversation_id, c.user_one_id, c.user_two_id, c.student_id,
            c.conversation_type, c.group_class_id, c.title, c.created_by_user_id,
            c.updated_at, cp.can_send,
                (SELECT m.message_body FROM messages m WHERE m.conversation_id = c.conversation_id
                 ORDER BY m.message_id DESC LIMIT 1) AS latest_message,
            (SELECT COUNT(*) FROM messages m
             INNER JOIN message_recipients mr ON mr.message_id = m.message_id
             WHERE m.conversation_id = c.conversation_id AND mr.user_id = ? AND mr.is_read = 0) AS unread_count
         FROM conversations c
         INNER JOIN conversation_participants cp ON cp.conversation_id = c.conversation_id
         WHERE cp.user_id = ?
         ORDER BY c.updated_at DESC'
    );
        $statement->execute([$userId, $userId]);
    $conversations = $statement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($conversations as &$conversation) {
        $conversation['conversation_id'] = (int) $conversation['conversation_id'];
        $conversation['student_id'] = (int) $conversation['student_id'];
        $conversation['unread_count'] = (int) $conversation['unread_count'];
        $conversation['can_send'] = (int) $conversation['can_send'];
        if ($conversation['conversation_type'] === 'CLASS_GROUP') {
            $classStatement = $conn->prepare('SELECT class_name, stream_name FROM classes WHERE class_id = ? LIMIT 1');
            $classStatement->execute([(int) $conversation['group_class_id']]);
            $class = $classStatement->fetch(PDO::FETCH_ASSOC) ?: ['class_name' => 'Class', 'stream_name' => ''];
            $conversation['contact'] = [
                'partner_role' => 'CLASS GROUP',
                'partner_name' => $conversation['title'] ?: 'Class Chat',
                'student_name' => trim($class['class_name'] . ' ' . $class['stream_name'])
            ];
        } elseif ($conversation['conversation_type'] === 'ANNOUNCEMENT') {
            $conversation['contact'] = [
                'partner_role' => 'ADMIN ANNOUNCEMENT',
                'partner_name' => $conversation['title'] ?: 'Administrator announcement',
                'student_name' => 'One-way announcement'
            ];
        } else {
            $conversation['partner_user_id'] = (int) $conversation['user_one_id'] === $userId
                ? (int) $conversation['user_two_id']
                : (int) $conversation['user_one_id'];
            $conversation['contact'] = $contacts[$conversation['partner_user_id'] . ':' . $conversation['student_id']] ?? null;
        }

        if ($conversation['contact'] === null) {
            $contactStatement = $conn->prepare(
                "SELECT u.role AS partner_role,
                        COALESCE(p.full_name, t.full_name, s.full_name, u.email, 'Account') AS partner_name,
                        st.full_name AS student_name
                 FROM users u
                 LEFT JOIN parents p ON p.user_id = u.user_id
                 LEFT JOIN teachers t ON t.user_id = u.user_id
                 LEFT JOIN students s ON s.user_id = u.user_id
                 LEFT JOIN students st ON st.student_id = ?
                 WHERE u.user_id = ? LIMIT 1"
            );
            $contactStatement->execute([$conversation['student_id'], $conversation['partner_user_id']]);
            $conversation['contact'] = $contactStatement->fetch(PDO::FETCH_ASSOC) ?: [
                'partner_role' => 'USER', 'partner_name' => 'Account', 'student_name' => 'Student'
            ];
        }
    }
    unset($conversation);

    $activeConversation = null;
    $messages = [];
    if ($activeConversationId > 0) {
        foreach ($conversations as $conversation) {
            if ($conversation['conversation_id'] === $activeConversationId) {
                $activeConversation = $conversation;
                break;
            }
        }

        if ($activeConversation === null) {
            throw new RuntimeException('That conversation is not available to your account.');
        }

        $conn->prepare(
            'UPDATE message_recipients mr
             INNER JOIN messages m ON m.message_id = mr.message_id
             SET mr.is_read = 1, mr.read_at = CURRENT_TIMESTAMP
             WHERE mr.user_id = ? AND mr.is_read = 0 AND m.conversation_id = ?'
        )->execute([$userId, $activeConversationId]);
        $activeConversation['unread_count'] = 0;
        foreach ($conversations as &$conversation) {
            if ($conversation['conversation_id'] === $activeConversationId) {
                $conversation['unread_count'] = 0;
                break;
            }
        }
        unset($conversation);

        $messageStatement = $conn->prepare(
            "SELECT m.message_id, m.sender_user_id, m.message_body,
                m.attachment_type, m.attachment_url, m.attachment_name, m.attachment_meta,
                m.created_at,
                CASE WHEN m.sender_user_id = ? THEN 1 ELSE 0 END AS is_mine,
                COALESCE(p.full_name, s.full_name, t.full_name, u.email, 'Account') AS sender_name
             FROM messages m
             INNER JOIN users u ON u.user_id = m.sender_user_id
             LEFT JOIN parents p ON p.user_id = u.user_id
             LEFT JOIN students s ON s.user_id = u.user_id
             LEFT JOIN teachers t ON t.user_id = u.user_id
             WHERE m.conversation_id = ?
             ORDER BY m.message_id DESC
               LIMIT 300"
        );
        $messageStatement->execute([$userId, $activeConversationId]);
        $messages = array_reverse($messageStatement->fetchAll(PDO::FETCH_ASSOC));

        $pollVotesStatement = $conn->prepare(
            'SELECT option_index, COUNT(*) AS vote_count
             FROM message_poll_votes
             WHERE message_id = ?
             GROUP BY option_index'
        );
        $userVoteStatement = $conn->prepare(
            'SELECT option_index FROM message_poll_votes
             WHERE message_id = ? AND user_id = ? LIMIT 1'
        );
        foreach ($messages as &$message) {
            if (strtoupper((string) ($message['attachment_type'] ?? '')) !== 'POLL') {
                continue;
            }

            $pollMeta = messageAttachmentMeta($message['attachment_meta'] ?? null);
            $optionCount = count($pollMeta['options'] ?? []);
            $message['poll_counts'] = array_fill(0, $optionCount, 0);
            $pollVotesStatement->execute([(int) $message['message_id']]);
            foreach ($pollVotesStatement->fetchAll(PDO::FETCH_ASSOC) as $voteCount) {
                $index = (int) $voteCount['option_index'];
                if (array_key_exists($index, $message['poll_counts'])) {
                    $message['poll_counts'][$index] = (int) $voteCount['vote_count'];
                }
            }
            $userVoteStatement->execute([(int) $message['message_id'], $userId]);
            $userVote = $userVoteStatement->fetchColumn();
            $message['poll_user_vote'] = $userVote === false ? null : (int) $userVote;
            $message['poll_total_votes'] = array_sum($message['poll_counts']);
        }
        unset($message);
    }
} catch (Throwable $exception) {
    $pageError = $exception->getMessage();
}

$unreadCount = 0;
foreach ($conversations as $conversation) {
    $unreadCount += (int) ($conversation['unread_count'] ?? 0);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Messages | MySchool</title>
    <style>
        :root{font-family:Arial,Helvetica,sans-serif;color:#203342;background:#f2f6f7;font-synthesis:none}
        *{box-sizing:border-box}body{margin:0}.messages-page{max-width:1440px;margin:0 auto;padding:28px}
        .messages-header{display:flex;justify-content:space-between;align-items:end;margin-bottom:20px;gap:16px}
        .messages-header-actions{display:flex;align-items:center;gap:16px}.dashboard-link{color:#176f80;font-size:13px;font-weight:700;text-decoration:none}.dashboard-link:hover{text-decoration:underline}
        .messages-header h1{margin:0;font-size:25px;color:#173b50}.messages-header p{margin:5px 0 0;color:#6b7f88;font-size:13px}
        .unread-total{font-size:13px;color:#14758a;font-weight:700}.messages-grid{display:grid;grid-template-columns:310px minmax(0,1fr);gap:16px;min-height:620px}
        .messages-panel{background:#fff;border:1px solid #dfe8e9;border-radius:8px;overflow:hidden}.panel-title{padding:15px 17px;border-bottom:1px solid #e8eeee;font-size:14px;font-weight:700;color:#234252}
        .conversation-list{max-height:525px;overflow:auto}.conversation-item{display:block;padding:13px 16px;border-bottom:1px solid #eef2f2;color:inherit;text-decoration:none}.conversation-item:hover,.conversation-item.selected{background:#edf7f8}
        .conversation-name{display:flex;justify-content:space-between;gap:8px;font-size:13px;font-weight:700}.conversation-preview{margin-top:5px;color:#708187;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.conversation-context{margin-top:4px;color:#809096;font-size:11px}
        .unread-badge{min-width:19px;height:19px;padding:0 5px;border-radius:10px;background:#11859a;color:white;display:inline-flex;align-items:center;justify-content:center;font-size:11px}
        .start-form{padding:15px;border-top:1px solid #e8eeee}.start-form label{display:block;margin-bottom:6px;color:#516770;font-size:12px;font-weight:700}.start-form select,.start-form textarea,.compose textarea{width:100%;border:1px solid #d5e0e1;border-radius:5px;padding:10px;font:inherit;font-size:13px;background:#fff}.start-form textarea{margin-top:9px;min-height:68px;resize:vertical}.primary-button{border:0;border-radius:5px;background:#117b8e;color:white;padding:10px 13px;font:inherit;font-size:13px;font-weight:700;cursor:pointer}.start-form .primary-button{width:100%;margin-top:9px}
        .group-note{padding:12px 15px;color:#71858a;font-size:12px;line-height:1.5;border-top:1px solid #e8eeee}
        .chat-panel{display:flex;flex-direction:column;min-height:620px}.chat-heading{padding:16px 20px;border-bottom:1px solid #e8eeee}.chat-heading strong{display:block;font-size:15px}.chat-heading span{display:block;margin-top:4px;color:#788a90;font-size:12px}.chat-messages{display:flex;flex:1;flex-direction:column;gap:10px;padding:20px;overflow:auto;background:#f8fbfb}.message-bubble{max-width:min(75%,620px);padding:11px 13px;border-radius:8px;background:white;border:1px solid #e2ebeb;align-self:flex-start;overflow-wrap:anywhere}.message-bubble.mine{background:#e1f3f4;border-color:#cce7e9;align-self:flex-end}.message-body{font-size:13px;line-height:1.5;white-space:pre-wrap}.message-time{margin-top:6px;font-size:10px;color:#7a8b90}.compose{display:flex;align-items:end;gap:10px;padding:14px;border-top:1px solid #e8eeee;position:relative}.compose textarea{min-height:44px;max-height:150px;resize:vertical}
        .attach-wrap{position:relative;flex:0 0 auto}.attach-button{width:44px;height:44px;border:1px solid #d5e0e1;background:#fff;border-radius:50%;font-size:23px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#4d626a}.attach-button:hover{background:#f2f7f7}
        .attach-menu{position:absolute;left:0;bottom:54px;width:230px;background:#fff;border:1px solid #dfe8e9;border-radius:12px;box-shadow:0 12px 35px rgba(25,55,65,.18);padding:7px;display:none;z-index:20}.attach-menu.open{display:block}
        .attach-option{width:100%;display:flex;align-items:center;gap:11px;border:0;background:#fff;padding:11px 10px;border-radius:8px;text-align:left;font:inherit;font-size:13px;color:#304b56;cursor:pointer}.attach-option:hover{background:#f2f7f7}.attach-option .attach-icon{width:25px;text-align:center;font-size:18px}
        .attach-divider{height:1px;background:#edf1f1;margin:5px 4px}.file-input{display:none}
        .attachment-card{margin-top:8px;padding:10px;border:1px solid #dce8e9;border-radius:8px;background:#f8fbfb}.attachment-card a{color:#116f81;text-decoration:none;font-weight:700}.attachment-card a:hover{text-decoration:underline}
        .attachment-image{display:block;max-width:min(330px,100%);max-height:280px;border-radius:8px;margin-top:3px}.attachment-video{display:block;max-width:min(380px,100%);border-radius:8px;margin-top:3px}.attachment-audio{width:min(380px,100%);margin-top:3px}
        .contact-card,.poll-card,.event-card{margin-top:2px}.attachment-title{font-weight:700;color:#274650;margin-bottom:5px}.attachment-subtitle{font-size:12px;color:#71848a;line-height:1.45}.poll-vote-form{margin:0}.poll-option{display:flex;justify-content:space-between;gap:12px;width:100%;margin-top:7px;padding:8px 10px;border:1px solid #d7e3e4;border-radius:7px;background:#fff;text-align:left;font-size:12px;cursor:pointer}.poll-option:hover,.poll-option.selected{border-color:#17899a;background:#effafb}.poll-vote-count{color:#6d8187;white-space:nowrap}.event-row{font-size:12px;margin-top:5px}.event-row strong{color:#405b65}
        .attach-preview{display:none;padding:7px 14px;border-top:1px solid #edf1f1;background:#fbfdfd;font-size:12px;color:#5e747b}.attach-preview.show{display:block}
        .modal-backdrop{position:fixed;inset:0;background:rgba(20,40,45,.45);display:none;align-items:center;justify-content:center;padding:20px;z-index:100}.modal-backdrop.open{display:flex}.modal-box{width:min(480px,100%);background:#fff;border-radius:12px;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,.2)}.modal-box h3{margin:0 0 14px;color:#234252}.modal-box label{display:block;font-size:12px;font-weight:700;color:#536970;margin:10px 0 5px}.modal-box input,.modal-box textarea{width:100%;border:1px solid #d5e0e1;border-radius:6px;padding:10px;font:inherit;font-size:13px}.modal-box textarea{min-height:75px;resize:vertical}.modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:15px}.secondary-button{border:1px solid #ccdada;border-radius:6px;background:#fff;padding:9px 13px;font:inherit;font-size:13px;cursor:pointer}.poll-option-input{margin-top:7px}.modal-note{font-size:11px;color:#788b91;margin-top:5px}
.empty-state{margin:auto;padding:30px;text-align:center;color:#708187;font-size:13px}.empty-state strong{display:block;margin-bottom:7px;color:#34515d;font-size:15px}.page-error{margin-bottom:14px;padding:12px 14px;border:1px solid #f2c7c7;background:#fff1f1;color:#8a3333;border-radius:6px;font-size:13px}
        @media(max-width:760px){.messages-page{padding:16px}.messages-grid{grid-template-columns:1fr}.conversation-list{max-height:250px}.chat-panel{min-height:450px}.message-bubble{max-width:90%}.messages-header{align-items:start;flex-direction:column}}
    </style>
</head>
<body>
<main class="messages-page">
    <header class="messages-header">
        <div><h1>Messages</h1><p>Conversations with your school community</p></div>
        <div class="messages-header-actions"><a class="dashboard-link" href="<?= messageEscape($dashboardUrl) ?>">Dashboard</a><div class="unread-total"><?= $unreadCount ?> unread</div></div>
    </header>

    <?php if ($pageError !== ''): ?><div class="page-error"><?= messageEscape($pageError) ?></div><?php endif; ?>

    <div class="messages-grid">
        <section class="messages-panel">
            <div class="panel-title">Conversations</div>
            <div class="conversation-list">
                <?php if (!$conversations): ?><div class="empty-state">No conversations yet.</div><?php endif; ?>
                <?php foreach ($conversations as $conversation): ?>
                    <?php $contact = $conversation['contact']; ?>
                    <a class="conversation-item<?= $activeConversationId === $conversation['conversation_id'] ? ' selected' : '' ?>" href="messages.php?conversation_id=<?= $conversation['conversation_id'] ?>">
                        <div class="conversation-name"><span><?= messageEscape($contact['partner_name']) ?> · <?= messageEscape(ucfirst(strtolower($contact['partner_role']))) ?></span><?php if ($conversation['unread_count'] > 0): ?><span class="unread-badge"><?= $conversation['unread_count'] ?></span><?php endif; ?></div>
                        <div class="conversation-preview"><?= messageEscape($conversation['latest_message'] ?: 'Conversation started') ?></div>
                        <div class="conversation-context">Regarding <?= messageEscape($contact['student_name'] ?? 'Student') ?></div>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (in_array($role, ['SCHOOL_ADMIN', 'SUPER_ADMIN'], true)): ?>
            <form class="start-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= messageEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="broadcast">
                <label for="audience">Send announcement</label>
                <select id="audience" name="audience" required>
                    <option value="">Choose audience</option>
                    <option value="ALL">All parents, students, and teachers</option>
                    <option value="PARENT">All parents</option>
                    <option value="STUDENT">All students</option>
                    <option value="TEACHER">All teachers</option>
                    <?php foreach ($recipientAccounts as $account): ?>
                        <option value="USER:<?= (int) $account['user_id'] ?>"><?= messageEscape($account['full_name'] . ' · ' . ucfirst(strtolower($account['role']))) ?></option>
                    <?php endforeach; ?>
                </select>
                <textarea name="message_body" maxlength="5000" placeholder="Write an announcement" required></textarea>
                <button class="primary-button" type="submit">Send announcement</button>
            </form>
            <?php else: ?>
            <div class="group-note">Your current class chats appear here automatically with enrolled students, linked parents, and assigned teachers.</div>
            <form class="start-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= messageEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="start">
                <label for="contact">Start a conversation</label>
                <select id="contact" name="contact_key" required>
                    <option value="">Choose a person and student</option>
                    <?php foreach ($contacts as $key => $contact): ?>
                        <option value="<?= messageEscape($key) ?>"><?= messageEscape($contact['partner_name'] . ' · ' . ucfirst(strtolower($contact['partner_role'])) . ' · ' . $contact['student_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <textarea name="message_body" maxlength="5000" placeholder="Optional first message"></textarea>
                <button class="primary-button" type="submit">Open conversation</button>
            </form>
            <?php endif; ?>
        </section>

        <section class="messages-panel chat-panel">
            <?php if ($activeConversation): ?>
                <?php $activeContact = $activeConversation['contact']; ?>
                <header class="chat-heading"><strong><?= messageEscape($activeContact['partner_name']) ?></strong><span><?= messageEscape(ucfirst(strtolower($activeContact['partner_role']))) ?> · <?= messageEscape($activeContact['student_name'] ?? '') ?></span></header>
                <div class="chat-messages">
                    <?php if (!$messages): ?><div class="empty-state"><strong>Conversation opened</strong>Send the first message to begin.</div><?php endif; ?>
                    <?php foreach ($messages as $message): ?>
                        <article class="message-bubble<?= $message['is_mine'] ? ' mine' : '' ?>">
                            <?php if (!$message['is_mine'] && in_array($activeConversation['conversation_type'], ['CLASS_GROUP', 'ANNOUNCEMENT'], true)): ?><div class="message-time"><?= messageEscape($message['sender_name']) ?></div><?php endif; ?>
                            <?php
                            $attachmentType = strtoupper((string) ($message['attachment_type'] ?? ''));
                            $attachmentUrl = (string) ($message['attachment_url'] ?? '');
                            $attachmentName = (string) ($message['attachment_name'] ?? '');
                            $attachmentMeta = messageAttachmentMeta($message['attachment_meta'] ?? null);
                            ?>
                            <?php if ($message['message_body'] !== ''): ?>
                                <div class="message-body"><?= messageEscape($message['message_body']) ?></div>
                            <?php endif; ?>

                            <?php if ($attachmentType !== ''): ?>
                                <div class="attachment-card">
                                    <?php if (in_array($attachmentType, ['PHOTO', 'CAMERA'], true) && $attachmentUrl !== ''): ?>
                                        <img class="attachment-image" src="<?= messageEscape($attachmentUrl) ?>" alt="<?= messageEscape($attachmentName ?: 'Photo') ?>">
                                        <?php if ($attachmentName !== ''): ?><div class="attachment-subtitle"><?= messageEscape($attachmentName) ?></div><?php endif; ?>

                                    <?php elseif ($attachmentType === 'VIDEO' && $attachmentUrl !== ''): ?>
                                        <video class="attachment-video" controls preload="metadata">
                                            <source src="<?= messageEscape($attachmentUrl) ?>">
                                            Your browser does not support video playback.
                                        </video>
                                        <div class="attachment-subtitle"><?= messageEscape($attachmentName ?: 'Video') ?></div>

                                    <?php elseif ($attachmentType === 'AUDIO' && $attachmentUrl !== ''): ?>
                                        <div class="attachment-title">🎧 <?= messageEscape($attachmentName ?: 'Audio') ?></div>
                                        <audio class="attachment-audio" controls preload="metadata">
                                            <source src="<?= messageEscape($attachmentUrl) ?>">
                                            Your browser does not support audio playback.
                                        </audio>

                                    <?php elseif ($attachmentType === 'DOCUMENT' && $attachmentUrl !== ''): ?>
                                        <div class="attachment-title">📄 <?= messageEscape($attachmentName ?: 'Document') ?></div>
                                        <a href="<?= messageEscape($attachmentUrl) ?>" target="_blank" rel="noopener">Open document</a>

                                    <?php elseif ($attachmentType === 'STICKER' && $attachmentUrl !== ''): ?>
                                        <img class="attachment-image" src="<?= messageEscape($attachmentUrl) ?>" alt="Sticker">

                                    <?php elseif ($attachmentType === 'CONTACT'): ?>
                                        <div class="contact-card">
                                            <div class="attachment-title">👤 <?= messageEscape($attachmentMeta['name'] ?? 'Contact') ?></div>
                                            <div class="attachment-subtitle">📞 <?= messageEscape($attachmentMeta['phone'] ?? '') ?></div>
                                            <?php if (!empty($attachmentMeta['email'])): ?><div class="attachment-subtitle">✉️ <?= messageEscape($attachmentMeta['email']) ?></div><?php endif; ?>
                                        </div>

                                    <?php elseif ($attachmentType === 'POLL'): ?>
                                        <div class="poll-card">
                                            <div class="attachment-title">📊 <?= messageEscape($attachmentMeta['question'] ?? 'Poll') ?></div>
                                            <?php foreach (($attachmentMeta['options'] ?? []) as $optionIndex => $option): ?>
                                                <form class="poll-vote-form" method="post">
                                                    <input type="hidden" name="csrf_token" value="<?= messageEscape($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="vote_poll">
                                                    <input type="hidden" name="conversation_id" value="<?= $activeConversationId ?>">
                                                    <input type="hidden" name="message_id" value="<?= (int) $message['message_id'] ?>">
                                                    <button class="poll-option<?= $message['poll_user_vote'] === (int) $optionIndex ? ' selected' : '' ?>" type="submit" name="option_index" value="<?= (int) $optionIndex ?>">
                                                        <span><?= messageEscape($option) ?></span>
                                                        <span class="poll-vote-count"><?= (int) ($message['poll_counts'][$optionIndex] ?? 0) ?> votes</span>
                                                    </button>
                                                </form>
                                            <?php endforeach; ?>
                                            <div class="attachment-subtitle" style="margin-top:7px"><?= (int) $message['poll_total_votes'] ?> total votes<?= $message['poll_user_vote'] !== null ? ' · Your vote is saved and can be changed' : '' ?></div>
                                        </div>

                                    <?php elseif ($attachmentType === 'EVENT'): ?>
                                        <div class="event-card">
                                            <div class="attachment-title">📅 <?= messageEscape($attachmentMeta['title'] ?? 'Event') ?></div>
                                            <div class="event-row"><strong>Date:</strong> <?= messageEscape($attachmentMeta['date'] ?? '') ?></div>
                                            <?php if (!empty($attachmentMeta['time'])): ?><div class="event-row"><strong>Time:</strong> <?= messageEscape($attachmentMeta['time']) ?></div><?php endif; ?>
                                            <?php if (!empty($attachmentMeta['location'])): ?><div class="event-row"><strong>Location:</strong> <?= messageEscape($attachmentMeta['location']) ?></div><?php endif; ?>
                                            <?php if (!empty($attachmentMeta['description'])): ?><div class="attachment-subtitle" style="margin-top:7px"><?= messageEscape($attachmentMeta['description']) ?></div><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="message-time"><?= messageEscape(date('M j, g:i a', strtotime($message['created_at']))) ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($activeConversation['can_send']): ?>
                <div class="attach-preview" id="attachmentPreview"></div>

                <form class="compose" id="messageCompose" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= messageEscape($csrfToken) ?>">
                    <input type="hidden" name="action" id="composeAction" value="send">
                    <input type="hidden" name="conversation_id" value="<?= $activeConversationId ?>">
                    <input type="hidden" name="attachment_kind" id="attachmentKind" value="">

                    <div class="attach-wrap">
                        <button class="attach-button" type="button" id="attachButton" aria-label="Attach" aria-expanded="false">📎</button>

                        <div class="attach-menu" id="attachMenu">
                            <button class="attach-option" type="button" data-kind="DOCUMENT"><span class="attach-icon">📄</span><span>Document</span></button>
                            <button class="attach-option" type="button" data-kind="PHOTO_VIDEO"><span class="attach-icon">🖼️</span><span>Photos &amp; videos</span></button>
                            <button class="attach-option" type="button" data-kind="CAMERA"><span class="attach-icon">📷</span><span>Camera</span></button>
                            <button class="attach-option" type="button" data-kind="AUDIO"><span class="attach-icon">🎧</span><span>Audio</span></button>
                            <div class="attach-divider"></div>
                            <button class="attach-option" type="button" data-special="CONTACT"><span class="attach-icon">👤</span><span>Contact</span></button>
                            <button class="attach-option" type="button" data-special="POLL"><span class="attach-icon">☷</span><span>Poll</span></button>
                            <button class="attach-option" type="button" data-special="EVENT"><span class="attach-icon">📅</span><span>Event</span></button>
                            <button class="attach-option" type="button" data-kind="STICKER"><span class="attach-icon">😊</span><span>New sticker</span></button>
                        </div>
                    </div>

                    <input class="file-input" type="file" id="attachmentInput" name="attachment">

                    <textarea name="message_body" id="messageBody" maxlength="5000" placeholder="Write a message" aria-label="Write a message"></textarea>
                    <button class="primary-button" type="submit">Send</button>
                </form>

                <!-- Contact modal -->
                <div class="modal-backdrop" id="contactModal">
                    <div class="modal-box">
                        <h3>Send contact</h3>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= messageEscape($csrfToken) ?>">
                            <input type="hidden" name="action" value="send_special">
                            <input type="hidden" name="special_type" value="CONTACT">
                            <input type="hidden" name="conversation_id" value="<?= $activeConversationId ?>">
                            <label>Contact name</label>
                            <input name="contact_name" maxlength="100" required>
                            <label>Phone number</label>
                            <input name="contact_phone" maxlength="30" required>
                            <label>Email (optional)</label>
                            <input name="contact_email" type="email" maxlength="150">
                            <div class="modal-actions"><button type="button" class="secondary-button" data-close-modal="contactModal">Cancel</button><button class="primary-button" type="submit">Send contact</button></div>
                        </form>
                    </div>
                </div>

                <!-- Poll modal -->
                <div class="modal-backdrop" id="pollModal">
                    <div class="modal-box">
                        <h3>Create poll</h3>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= messageEscape($csrfToken) ?>">
                            <input type="hidden" name="action" value="send_special">
                            <input type="hidden" name="special_type" value="POLL">
                            <input type="hidden" name="conversation_id" value="<?= $activeConversationId ?>">
                            <label>Question</label>
                            <input name="poll_question" maxlength="300" required>
                            <label>Options</label>
                            <input class="poll-option-input" name="poll_options[]" maxlength="150" placeholder="Option 1" required>
                            <input class="poll-option-input" name="poll_options[]" maxlength="150" placeholder="Option 2" required>
                            <input class="poll-option-input" name="poll_options[]" maxlength="150" placeholder="Option 3 (optional)">
                            <input class="poll-option-input" name="poll_options[]" maxlength="150" placeholder="Option 4 (optional)">
                            <input class="poll-option-input" name="poll_options[]" maxlength="150" placeholder="Option 5 (optional)">
                            <input class="poll-option-input" name="poll_options[]" maxlength="150" placeholder="Option 6 (optional)">
                            <div class="modal-note">Choose 2 to 6 options.</div>
                            <div class="modal-actions"><button type="button" class="secondary-button" data-close-modal="pollModal">Cancel</button><button class="primary-button" type="submit">Send poll</button></div>
                        </form>
                    </div>
                </div>

                <!-- Event modal -->
                <div class="modal-backdrop" id="eventModal">
                    <div class="modal-box">
                        <h3>Create event</h3>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= messageEscape($csrfToken) ?>">
                            <input type="hidden" name="action" value="send_special">
                            <input type="hidden" name="special_type" value="EVENT">
                            <input type="hidden" name="conversation_id" value="<?= $activeConversationId ?>">
                            <label>Event title</label>
                            <input name="event_title" maxlength="200" required>
                            <label>Date</label>
                            <input name="event_date" type="date" required>
                            <label>Time (optional)</label>
                            <input name="event_time" type="time">
                            <label>Location (optional)</label>
                            <input name="event_location" maxlength="200">
                            <label>Description (optional)</label>
                            <textarea name="event_description" maxlength="1000"></textarea>
                            <div class="modal-actions"><button type="button" class="secondary-button" data-close-modal="eventModal">Cancel</button><button class="primary-button" type="submit">Send event</button></div>
                        </form>
                    </div>
                </div>

                <?php else: ?>
                <div class="group-note">This is a one-way administrator announcement. Replies are disabled.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state"><strong>Select a conversation</strong>Or start one with an eligible parent, student, or teacher.</div>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
(function () {
    const attachButton = document.getElementById('attachButton');
    const attachMenu = document.getElementById('attachMenu');
    const fileInput = document.getElementById('attachmentInput');
    const attachmentKind = document.getElementById('attachmentKind');
    const composeAction = document.getElementById('composeAction');
    const composeForm = document.getElementById('messageCompose');
    const preview = document.getElementById('attachmentPreview');
    let pendingAttachmentKind = '';

    if (!attachButton || !attachMenu || !fileInput || !composeForm) {
        return;
    }

    const closeMenu = () => {
        attachMenu.classList.remove('open');
        attachButton.setAttribute('aria-expanded', 'false');
    };

    attachButton.addEventListener('click', function (event) {
        event.stopPropagation();
        const open = attachMenu.classList.toggle('open');
        attachButton.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function (event) {
        if (!attachMenu.contains(event.target) && event.target !== attachButton) {
            closeMenu();
        }
    });

    document.querySelectorAll('.attach-option[data-kind]').forEach(function (button) {
        button.addEventListener('click', function () {
            const kind = button.dataset.kind;
            pendingAttachmentKind = kind;

            fileInput.value = '';

            if (kind === 'DOCUMENT') {
                fileInput.accept = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,application/pdf';
                fileInput.removeAttribute('capture');
            } else if (kind === 'PHOTO_VIDEO') {
                fileInput.accept = 'image/*,video/*';
                fileInput.removeAttribute('capture');
            } else if (kind === 'CAMERA') {
                fileInput.accept = 'image/*';
                fileInput.setAttribute('capture', 'environment');
            } else if (kind === 'AUDIO') {
                fileInput.accept = 'audio/*';
                fileInput.setAttribute('capture', 'microphone');
            } else if (kind === 'STICKER') {
                fileInput.accept = 'image/*';
                fileInput.removeAttribute('capture');
            }

            closeMenu();
            fileInput.click();
        });
    });

    fileInput.addEventListener('change', function () {
        if (!fileInput.files || !fileInput.files.length) {
            return;
        }

        const file = fileInput.files[0];
        attachmentKind.value = pendingAttachmentKind;
        composeAction.value = 'send_attachment';
        preview.textContent = 'Attaching: ' + file.name;
        preview.classList.add('show');

        composeForm.submit();
    });

    fileInput.addEventListener('cancel', function () {
        pendingAttachmentKind = '';
        attachmentKind.value = '';
        composeAction.value = 'send';
        preview.textContent = '';
        preview.classList.remove('show');
    });

    document.querySelectorAll('.attach-option[data-special]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeMenu();
            const modal = document.getElementById(
                button.dataset.special.toLowerCase() === 'contact'
                    ? 'contactModal'
                    : button.dataset.special.toLowerCase() === 'poll'
                        ? 'pollModal'
                        : 'eventModal'
            );

            if (modal) {
                modal.classList.add('open');
            }
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modal = document.getElementById(button.dataset.closeModal);
            if (modal) {
                modal.classList.remove('open');
            }
        });
    });

    document.querySelectorAll('.modal-backdrop').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.classList.remove('open');
            }
        });
    });
})();
</script>

</body>
</html>
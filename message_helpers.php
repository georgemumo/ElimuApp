<?php

function unreadMessageCount(PDO $conn, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    try {
        $statement = $conn->prepare(
            'SELECT COUNT(*)
             FROM message_recipients
             WHERE user_id = ? AND is_read = 0'
        );
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    } catch (PDOException $exception) {
        return 0;
    }
}

/**
 * Sends a normal text message or a message with one attachment.
 *
 * $attachment format:
 * [
 *   'type' => 'DOCUMENT|PHOTO|VIDEO|AUDIO|CAMERA|CONTACT|POLL|EVENT|STICKER',
 *   'url' => 'uploads/messages/....',
 *   'name' => 'original filename',
 *   'meta' => ['key' => 'value'] // optional
 * ]
 */
function sendConversationMessage(
    PDO $conn,
    int $userId,
    string $role,
    int $conversationId,
    string $body,
    ?array $attachment = null
): int {
    $body = trim($body);

    $bodyLength = function_exists('mb_strlen')
        ? mb_strlen($body, 'UTF-8')
        : strlen($body);

    $allowedAttachmentTypes = [
        'DOCUMENT',
        'PHOTO',
        'VIDEO',
        'AUDIO',
        'CAMERA',
        'CONTACT',
        'POLL',
        'EVENT',
        'STICKER'
    ];

    if ($bodyLength > 5000) {
        throw new RuntimeException('Messages must contain a maximum of 5,000 characters.');
    }

    if ($attachment !== null) {
        $attachmentType = strtoupper((string) ($attachment['type'] ?? ''));

        if (!in_array($attachmentType, $allowedAttachmentTypes, true)) {
            throw new RuntimeException('The attachment type is not supported.');
        }

        if ($attachmentType !== 'CONTACT'
            && $attachmentType !== 'POLL'
            && $attachmentType !== 'EVENT'
            && trim((string) ($attachment['url'] ?? '')) === ''
        ) {
            throw new RuntimeException('The attachment file is missing.');
        }

        if ($attachmentType === 'CONTACT'
            || $attachmentType === 'POLL'
            || $attachmentType === 'EVENT'
        ) {
            if (!isset($attachment['meta']) || !is_array($attachment['meta'])) {
                throw new RuntimeException('The attachment information is missing.');
            }
        }
    } elseif ($body === '') {
        throw new RuntimeException('Enter a message or attach something.');
    }

    $ownsTransaction = !$conn->inTransaction();

    if ($ownsTransaction) {
        $conn->beginTransaction();
    }

    try {
        $access = $conn->prepare(
            'SELECT c.conversation_type, c.created_by_user_id, cp.can_send
             FROM conversations c
             INNER JOIN conversation_participants cp
                ON cp.conversation_id = c.conversation_id
             WHERE c.conversation_id = ? AND cp.user_id = ?
             LIMIT 1'
        );
        $access->execute([$conversationId, $userId]);

        $permission = $access->fetch(PDO::FETCH_ASSOC);

        if (!$permission || !(int) $permission['can_send']) {
            throw new RuntimeException('That conversation is not available to your account.');
        }

        if (
            $permission['conversation_type'] === 'ANNOUNCEMENT'
            && (
                !in_array($role, ['SCHOOL_ADMIN', 'SUPER_ADMIN'], true)
                || (int) $permission['created_by_user_id'] !== $userId
            )
        ) {
            throw new RuntimeException(
                'Only the administrator who sent this announcement can send to it.'
            );
        }

        $attachmentType = null;
        $attachmentUrl = null;
        $attachmentName = null;
        $attachmentMeta = null;

        if ($attachment !== null) {
            $attachmentType = strtoupper((string) ($attachment['type'] ?? ''));
            $attachmentUrl = trim((string) ($attachment['url'] ?? '')) ?: null;
            $attachmentName = trim((string) ($attachment['name'] ?? '')) ?: null;

            if (isset($attachment['meta']) && is_array($attachment['meta'])) {
                $attachmentMeta = json_encode(
                    $attachment['meta'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                if ($attachmentMeta === false) {
                    throw new RuntimeException('Could not prepare the attachment information.');
                }
            }
        }

        $statement = $conn->prepare(
            'INSERT INTO messages
                (
                    conversation_id,
                    sender_user_id,
                    message_body,
                    attachment_type,
                    attachment_url,
                    attachment_name,
                    attachment_meta
                )
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $statement->execute([
            $conversationId,
            $userId,
            $body,
            $attachmentType,
            $attachmentUrl,
            $attachmentName,
            $attachmentMeta
        ]);

        $messageId = (int) $conn->lastInsertId();

        /*
         * Every recipient gets their own unread record.
         * This is what allows Option A:
         * 3 unread messages = 🔔 3.
         */
        $recipientStatement = $conn->prepare(
            'INSERT INTO message_recipients (message_id, user_id)
             SELECT ?, user_id
             FROM conversation_participants
             WHERE conversation_id = ? AND user_id <> ?'
        );

        $recipientStatement->execute([
            $messageId,
            $conversationId,
            $userId
        ]);

        $conn->prepare(
            'UPDATE conversations
             SET updated_at = CURRENT_TIMESTAMP
             WHERE conversation_id = ?'
        )->execute([$conversationId]);

        if ($ownsTransaction) {
            $conn->commit();
        }

        return $messageId;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }

        throw $exception;
    }
}

function voteMessagePoll(PDO $conn, int $userId, int $conversationId, int $messageId, int $optionIndex): void
{
    $membership = $conn->prepare(
        'SELECT 1 FROM conversation_participants
         WHERE conversation_id = ? AND user_id = ? LIMIT 1'
    );
    $membership->execute([$conversationId, $userId]);
    if (!$membership->fetchColumn()) {
        throw new RuntimeException('You are not a member of this conversation.');
    }

    $poll = $conn->prepare(
        "SELECT m.attachment_meta
         FROM messages m
         WHERE m.message_id = ? AND m.conversation_id = ? AND m.attachment_type = 'POLL'
         LIMIT 1"
    );
    $poll->execute([$messageId, $conversationId]);
    $metaJson = $poll->fetchColumn();
    $meta = is_string($metaJson) ? json_decode($metaJson, true) : null;
    $options = is_array($meta) ? ($meta['options'] ?? null) : null;

    if (!is_array($options) || !array_key_exists($optionIndex, $options)) {
        throw new RuntimeException('That poll option is not valid.');
    }

    $vote = $conn->prepare(
        'INSERT INTO message_poll_votes (message_id, user_id, option_index)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE option_index = VALUES(option_index), voted_at = CURRENT_TIMESTAMP'
    );
    $vote->execute([$messageId, $userId, $optionIndex]);
}

function syncUserClassGroups(PDO $conn, int $userId, string $role): void
{
    $role = strtoupper($role);

    if (
        $userId <= 0
        || !in_array($role, ['PARENT', 'STUDENT', 'TEACHER'], true)
    ) {
        return;
    }

    $currentYear = (int) date('Y');

    if ($role === 'STUDENT') {
        $statement = $conn->prepare(
            "SELECT class_id
             FROM students
             WHERE user_id = ? AND status = 'ACTIVE'"
        );
        $statement->execute([$userId]);
    } elseif ($role === 'PARENT') {
        $statement = $conn->prepare(
            "SELECT DISTINCT s.class_id
             FROM parents p
             INNER JOIN parent_student_map psm
                ON psm.parent_id = p.parent_id
             INNER JOIN students s
                ON s.student_id = psm.student_id
               AND s.status = 'ACTIVE'
             WHERE p.user_id = ?"
        );
        $statement->execute([$userId]);
    } else {
        $statement = $conn->prepare(
            'SELECT DISTINCT class_id
             FROM teacher_subject_assignments
             WHERE teacher_id = (
                 SELECT teacher_id
                 FROM teachers
                 WHERE user_id = ?
                 LIMIT 1
             )
             AND academic_year = ?
             AND is_active = 1'
        );
        $statement->execute([$userId, $currentYear]);
    }

    $classIds = array_values(
        array_unique(
            array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN))
        )
    );

    if (!$classIds) {
        $conn->prepare(
            "DELETE cp
             FROM conversation_participants cp
             INNER JOIN conversations c
                ON c.conversation_id = cp.conversation_id
             WHERE cp.user_id = ?
               AND c.conversation_type = 'CLASS_GROUP'"
        )->execute([$userId]);

        return;
    }

    $placeholders = implode(',', array_fill(0, count($classIds), '?'));

    $conn->prepare(
        "DELETE cp
         FROM conversation_participants cp
         INNER JOIN conversations c
            ON c.conversation_id = cp.conversation_id
         WHERE cp.user_id = ?
           AND c.conversation_type = 'CLASS_GROUP'
           AND c.group_class_id NOT IN ($placeholders)"
    )->execute(array_merge([$userId], $classIds));

    $classStatement = $conn->prepare(
        'SELECT class_name, stream_name
         FROM classes
         WHERE class_id = ?'
    );

    $createGroup = $conn->prepare(
        "INSERT INTO conversations
            (conversation_type, group_class_id, title)
         VALUES
            ('CLASS_GROUP', ?, ?)
         ON DUPLICATE KEY UPDATE
            conversation_id = LAST_INSERT_ID(conversation_id),
            title = VALUES(title)"
    );

    $groupIdStatement = $conn->prepare(
        "SELECT conversation_id
         FROM conversations
         WHERE conversation_type = 'CLASS_GROUP'
           AND group_class_id = ?
         LIMIT 1"
    );

    $membersStatement = $conn->prepare(
        "SELECT user_id
         FROM (
             SELECT s.user_id AS user_id
             FROM students s
             INNER JOIN users u
                ON u.user_id = s.user_id
               AND u.role = 'STUDENT'
             WHERE s.class_id = ?
               AND s.status = 'ACTIVE'
               AND s.user_id IS NOT NULL

             UNION

             SELECT p.user_id
             FROM students s
             INNER JOIN parent_student_map psm
                ON psm.student_id = s.student_id
             INNER JOIN parents p
                ON p.parent_id = psm.parent_id
             INNER JOIN users u
                ON u.user_id = p.user_id
               AND u.role = 'PARENT'
             WHERE s.class_id = ?
               AND s.status = 'ACTIVE'

             UNION

             SELECT t.user_id
             FROM teacher_subject_assignments tsa
             INNER JOIN teachers t
                ON t.teacher_id = tsa.teacher_id
             INNER JOIN users u
                ON u.user_id = t.user_id
               AND u.role = 'TEACHER'
             WHERE tsa.class_id = ?
               AND tsa.academic_year = ?
               AND tsa.is_active = 1
         ) roster
         WHERE user_id IS NOT NULL"
    );

    $addMember = $conn->prepare(
        'INSERT INTO conversation_participants
            (conversation_id, user_id, can_send)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE can_send = 1'
    );

    foreach ($classIds as $classId) {
        $classStatement->execute([$classId]);
        $class = $classStatement->fetch(PDO::FETCH_ASSOC);

        if (!$class) {
            continue;
        }

        $classLabel = trim(
            (string) preg_replace(
                '/\s+/',
                ' ',
                $class['class_name'] . ' ' . $class['stream_name']
            )
        );

        $title = $classLabel . ' · Class Chat';

        $createGroup->execute([$classId, $title]);

        $groupIdStatement->execute([$classId]);
        $conversationId = (int) $groupIdStatement->fetchColumn();

        $membersStatement->execute([
            $classId,
            $classId,
            $classId,
            $currentYear
        ]);

        $memberIds = array_values(
            array_unique(
                array_map(
                    'intval',
                    $membersStatement->fetchAll(PDO::FETCH_COLUMN)
                )
            )
        );

        foreach ($memberIds as $memberId) {
            $addMember->execute([$conversationId, $memberId]);
        }

        if ($memberIds) {
            $memberPlaceholders = implode(
                ',',
                array_fill(0, count($memberIds), '?')
            );

            $removeStale = $conn->prepare(
                "DELETE FROM conversation_participants
                 WHERE conversation_id = ?
                   AND user_id NOT IN ($memberPlaceholders)"
            );

            $removeStale->execute(
                array_merge([$conversationId], $memberIds)
            );
        } else {
            $conn->prepare(
                'DELETE FROM conversation_participants
                 WHERE conversation_id = ?'
            )->execute([$conversationId]);
        }
    }
}

-- Clean setup: Disable foreign keys during initialization
SET FOREIGN_KEY_CHECKS = 0;


-- ============================================================================
-- 1. CENTRAL AUTHENTICATION & USERS
-- ============================================================================
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `phone_number` VARCHAR(15) UNIQUE NULL,
    `email` VARCHAR(100) UNIQUE NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('SUPER_ADMIN', 'SCHOOL_ADMIN', 'TEACHER', 'PARENT', 'STUDENT') NOT NULL,
    `is_verified` TINYINT(1) DEFAULT 0,
    `status` ENUM('ACTIVE', 'SUSPENDED', 'PENDING') DEFAULT 'PENDING',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 2. OTP VERIFICATION (Sign-Up, Password Resets & 2FA)
-- ============================================================================
CREATE TABLE `otps` (
    `otp_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `otp_code` VARCHAR(6) NOT NULL,
    `purpose` ENUM('REGISTRATION', 'PASSWORD_RESET', 'LOGIN_2FA') NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `is_used` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 3. ACADEMIC STRUCTURE (Classes & Subjects)
-- ============================================================================
CREATE TABLE `classes` (
    `class_id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_name` VARCHAR(50) NOT NULL, -- e.g., "Grade 8", "Form 4"
    `stream_name` VARCHAR(50) NOT NULL  -- e.g., "East", "Blue"
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `subjects` (
    `subject_id` INT AUTO_INCREMENT PRIMARY KEY,
    `subject_code` VARCHAR(20) UNIQUE NOT NULL,
    `subject_name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 4. USER PROFILES (Parents, Students, Teachers)
-- ============================================================================
CREATE TABLE `parents` (
    `parent_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNIQUE NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(15) NULL,
    `national_id` VARCHAR(20) UNIQUE NULL,
    `relationship` VARCHAR(50) DEFAULT 'Parent',
    `profile_pic` VARCHAR(255) NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `students` (
    `student_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNIQUE NULL, -- Linked if student gets direct app access
    `adm_number` VARCHAR(30) UNIQUE NOT NULL, -- Core identifier for auth/fee lookups
    `full_name` VARCHAR(100) NOT NULL,
    `gender` ENUM('MALE', 'FEMALE', 'OTHER') NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `class_id` INT NOT NULL,
    `status` ENUM('ACTIVE', 'ALUMNI', 'TRANSFERRED', 'SUSPENDED') DEFAULT 'ACTIVE',
    `profile_pic` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `parent_student_map` (
    `map_id` INT AUTO_INCREMENT PRIMARY KEY,
    `parent_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    FOREIGN KEY (`parent_id`) REFERENCES `parents`(`parent_id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_parent_student` (`parent_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `teachers` (
    `teacher_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNIQUE NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `phone_number` VARCHAR(15) UNIQUE NOT NULL, -- Dedicated phone field
    `phone` VARCHAR(15) NULL, -- Alternative phone field for updates
    `email` VARCHAR(100) NULL,
    `tsc_number` VARCHAR(30) UNIQUE NULL,
    `specialization` VARCHAR(100) NULL,
    `profile_pic` VARCHAR(255) NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 5. FINANCE & FEES MANAGEMENT
-- ============================================================================
CREATE TABLE `fee_accounts` (
    `account_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNIQUE NOT NULL,
    `total_billed` DECIMAL(10,2) DEFAULT 0.00,
    `total_paid` DECIMAL(10,2) DEFAULT 0.00,
    `current_balance` DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `fee_transactions` (
    `transaction_id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT NOT NULL,
    `transaction_code` VARCHAR(50) UNIQUE NOT NULL, -- e.g., M-Pesa Receipt Code
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('M_PESA', 'BANK_TRANSFER', 'CASH', 'CHEQUE') NOT NULL,
    `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('SUCCESS', 'PENDING', 'FAILED') DEFAULT 'SUCCESS',
    FOREIGN KEY (`account_id`) REFERENCES `fee_accounts`(`account_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 6. ACADEMICS & OPERATIONS (Timetable, Exams, Attendance)
-- ============================================================================
CREATE TABLE `timetables` (
    `timetable_id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `teacher_id` INT NOT NULL,
    `day_of_week` ENUM('MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY') NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`teacher_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `exam_results` (
    `result_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `term` ENUM('TERM_1', 'TERM_2', 'TERM_3') NOT NULL,
    `academic_year` YEAR NOT NULL,
    `score` DECIMAL(5,2) NOT NULL,
    `grade` VARCHAR(5) NULL,
    `remarks` VARCHAR(255) NULL,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `attendance` (
    `attendance_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `status` ENUM('PRESENT', 'ABSENT', 'LATE', 'EXCUSED') NOT NULL,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 7. USER-BASED IN-APP MESSAGING
-- ============================================================================
CREATE TABLE `conversations` (
    `conversation_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_one_id` INT NULL,
    `user_two_id` INT NULL,
    `student_id` INT NULL,
    `subject_id` INT NULL,
    `conversation_type` ENUM('DIRECT', 'CLASS_GROUP', 'ANNOUNCEMENT') NOT NULL DEFAULT 'DIRECT',
    `group_class_id` INT NULL,
    `title` VARCHAR(150) NULL,
    `created_by_user_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_one_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_two_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE SET NULL,
    FOREIGN KEY (`group_class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_conversation` (`user_one_id`, `user_two_id`, `student_id`),
    UNIQUE KEY `unique_class_group` (`conversation_type`, `group_class_id`),
    KEY `idx_conversations_updated` (`updated_at`),
    KEY `idx_conversations_type` (`conversation_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `conversation_participants` (
    `conversation_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `can_send` TINYINT(1) NOT NULL DEFAULT 1,
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`conversation_id`, `user_id`),
    KEY `idx_participants_user` (`user_id`, `conversation_id`),
    FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`conversation_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `messages` (
    `message_id` INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT NOT NULL,
    `sender_user_id` INT NOT NULL, -- References the account that sent this message
    `message_body` TEXT NOT NULL,
    `attachment_url` VARCHAR(255) NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`conversation_id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    KEY `idx_messages_unread` (`conversation_id`, `is_read`, `sender_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `message_recipients` (
    `message_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`message_id`, `user_id`),
    KEY `idx_recipients_unread` (`user_id`, `is_read`, `message_id`),
    FOREIGN KEY (`message_id`) REFERENCES `messages`(`message_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `message_poll_votes` (
    `message_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `option_index` TINYINT UNSIGNED NOT NULL,
    `voted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`message_id`, `user_id`),
    KEY `idx_poll_votes_option` (`message_id`, `option_index`),
    FOREIGN KEY (`message_id`) REFERENCES `messages`(`message_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Re-enable foreign key constraints
SET FOREIGN_KEY_CHECKS = 1;

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. TEACHER SUBJECT / CLASS ASSIGNMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS `teacher_subject_assignments` (
    `assignment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `academic_year` YEAR NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`teacher_id`)
        REFERENCES `teachers`(`teacher_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`class_id`)
        REFERENCES `classes`(`class_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`subject_id`)
        REFERENCES `subjects`(`subject_id`)
        ON DELETE CASCADE,

    UNIQUE KEY `unique_teacher_class_subject_year`
        (`teacher_id`, `class_id`, `subject_id`, `academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================================
-- 2. ASSESSMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS `assessments` (
    `assessment_id` INT AUTO_INCREMENT PRIMARY KEY,

    `assessment_name` VARCHAR(100) NOT NULL,

    `assessment_type`
        ENUM(
            'CAT',
            'MIDTERM',
            'END_TERM',
            'MOCK',
            'ASSIGNMENT',
            'PROJECT',
            'OTHER'
        ) NOT NULL DEFAULT 'CAT',

    `term`
        ENUM(
            'TERM_1',
            'TERM_2',
            'TERM_3'
        ) NOT NULL,

    `academic_year` YEAR NOT NULL,

    `class_id` INT NOT NULL,

    `subject_id` INT NOT NULL,

    `max_score` DECIMAL(5,2) NOT NULL DEFAULT 100,

    `assessment_date` DATE NULL,

    `status`
        ENUM(
            'DRAFT',
            'OPEN',
            'SUBMITTED',
            'APPROVED',
            'PUBLISHED'
        ) DEFAULT 'DRAFT',

    `created_by` INT NOT NULL,

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`class_id`)
        REFERENCES `classes`(`class_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`subject_id`)
        REFERENCES `subjects`(`subject_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`created_by`)
        REFERENCES `users`(`user_id`)
        ON DELETE RESTRICT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================================
-- 3. ASSESSMENT MARKS
-- =========================================================

CREATE TABLE IF NOT EXISTS `assessment_marks` (
    `mark_id` INT AUTO_INCREMENT PRIMARY KEY,

    `assessment_id` INT NOT NULL,

    `student_id` INT NOT NULL,

    `score` DECIMAL(5,2) NOT NULL,

    `grade` VARCHAR(10) NULL,

    `remarks` VARCHAR(255) NULL,

    `entered_by` INT NOT NULL,

    `entered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    `updated_at`
        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`assessment_id`)
        REFERENCES `assessments`(`assessment_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`student_id`)
        REFERENCES `students`(`student_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`entered_by`)
        REFERENCES `users`(`user_id`)
        ON DELETE RESTRICT,

    UNIQUE KEY `unique_assessment_student`
        (`assessment_id`, `student_id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================================
-- 4. ASSESSMENT APPROVAL LOG
-- =========================================================

CREATE TABLE IF NOT EXISTS `assessment_approval_logs` (
    `approval_id` INT AUTO_INCREMENT PRIMARY KEY,

    `assessment_id` INT NOT NULL,

    `user_id` INT NOT NULL,

    `action`
        ENUM(
            'SUBMITTED',
            'APPROVED',
            'REJECTED',
            'PUBLISHED'
        ) NOT NULL,

    `comment` VARCHAR(500) NULL,

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`assessment_id`)
        REFERENCES `assessments`(`assessment_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`user_id`)
        REFERENCES `users`(`user_id`)
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================================
-- 5. CBC COMPETENCIES
-- =========================================================

CREATE TABLE IF NOT EXISTS `competencies` (
    `competency_id` INT AUTO_INCREMENT PRIMARY KEY,

    `competency_name` VARCHAR(150) NOT NULL,

    `description` TEXT NULL,

    `is_active` TINYINT(1) DEFAULT 1

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================================
-- 6. CBC ASSESSMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS `cbc_assessments` (
    `cbc_assessment_id` INT AUTO_INCREMENT PRIMARY KEY,

    `student_id` INT NOT NULL,

    `subject_id` INT NOT NULL,

    `competency_id` INT NOT NULL,

    `term`
        ENUM(
            'TERM_1',
            'TERM_2',
            'TERM_3'
        ) NOT NULL,

    `academic_year` YEAR NOT NULL,

    `performance_level`
        ENUM(
            'EE',
            'ME',
            'AE',
            'BE'
        ) NOT NULL,

    `teacher_comment` TEXT NULL,

    `teacher_id` INT NOT NULL,

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`student_id`)
        REFERENCES `students`(`student_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`subject_id`)
        REFERENCES `subjects`(`subject_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`competency_id`)
        REFERENCES `competencies`(`competency_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`teacher_id`)
        REFERENCES `teachers`(`teacher_id`)
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================================
-- 7. ACADEMIC COMMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS `academic_comments` (
    `comment_id` INT AUTO_INCREMENT PRIMARY KEY,

    `student_id` INT NOT NULL,

    `teacher_id` INT NOT NULL,

    `term`
        ENUM(
            'TERM_1',
            'TERM_2',
            'TERM_3'
        ) NOT NULL,

    `academic_year` YEAR NOT NULL,

    `comment_type`
        ENUM(
            'SUBJECT',
            'GENERAL',
            'IMPROVEMENT',
            'PRINCIPAL'
        ) NOT NULL,

    `comment_text` TEXT NOT NULL,

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`student_id`)
        REFERENCES `students`(`student_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`teacher_id`)
        REFERENCES `teachers`(`teacher_id`)
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================================
-- 8. ASSIGNMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS `assignments` (
    `assignment_id` INT AUTO_INCREMENT PRIMARY KEY,

    `teacher_id` INT NOT NULL,

    `class_id` INT NOT NULL,

    `subject_id` INT NOT NULL,

    `title` VARCHAR(200) NOT NULL,

    `description` TEXT NULL,

    `attachment_url` VARCHAR(255) NULL,

    `max_marks` DECIMAL(5,2) NULL,

    `assigned_date` DATE NOT NULL,

    `due_date` DATE NOT NULL,

    `status`
        ENUM(
            'DRAFT',
            'PUBLISHED',
            'CLOSED'
        ) DEFAULT 'PUBLISHED',

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`teacher_id`)
        REFERENCES `teachers`(`teacher_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`class_id`)
        REFERENCES `classes`(`class_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`subject_id`)
        REFERENCES `subjects`(`subject_id`)
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================================
-- 9. ASSIGNMENT SUBMISSIONS
-- =========================================================

CREATE TABLE IF NOT EXISTS `assignment_submissions` (
    `submission_id` INT AUTO_INCREMENT PRIMARY KEY,

    `assignment_id` INT NOT NULL,

    `student_id` INT NOT NULL,

    `submission_text` TEXT NULL,

    `attachment_url` VARCHAR(255) NULL,

    `submitted_at` TIMESTAMP NULL,

    `marks` DECIMAL(5,2) NULL,

    `teacher_feedback` TEXT NULL,

    `status`
        ENUM(
            'NOT_SUBMITTED',
            'SUBMITTED',
            'GRADED',
            'LATE'
        ) DEFAULT 'NOT_SUBMITTED',

    FOREIGN KEY (`assignment_id`)
        REFERENCES `assignments`(`assignment_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`student_id`)
        REFERENCES `students`(`student_id`)
        ON DELETE CASCADE,

    UNIQUE KEY `unique_assignment_student`
        (`assignment_id`, `student_id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================================
-- 10. LEARNING RESOURCES
-- =========================================================

CREATE TABLE IF NOT EXISTS `learning_resources` (
    `resource_id` INT AUTO_INCREMENT PRIMARY KEY,

    `teacher_id` INT NOT NULL,

    `class_id` INT NULL,

    `subject_id` INT NULL,

    `title` VARCHAR(200) NOT NULL,

    `description` TEXT NULL,

    `resource_type`
        ENUM(
            'NOTE',
            'PDF',
            'WORKSHEET',
            'PAST_PAPER',
            'VIDEO',
            'LINK',
            'OTHER'
        ) NOT NULL,

    `file_url` VARCHAR(255) NULL,

    `external_url` VARCHAR(500) NULL,

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`teacher_id`)
        REFERENCES `teachers`(`teacher_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`class_id`)
        REFERENCES `classes`(`class_id`)
        ON DELETE SET NULL,

    FOREIGN KEY (`subject_id`)
        REFERENCES `subjects`(`subject_id`)
        ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================================
-- 11. STUDENT ACHIEVEMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS `student_achievements` (
    `achievement_id` INT AUTO_INCREMENT PRIMARY KEY,

    `student_id` INT NOT NULL,

    `title` VARCHAR(200) NOT NULL,

    `description` TEXT NULL,

    `achievement_type`
        ENUM(
            'ACADEMIC',
            'SPORTS',
            'COMPETITION',
            'TALENT',
            'LEADERSHIP',
            'OTHER'
        ) NOT NULL,

    `achievement_date` DATE NULL,

    `certificate_url` VARCHAR(255) NULL,

    `recorded_by` INT NOT NULL,

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`student_id`)
        REFERENCES `students`(`student_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`recorded_by`)
        REFERENCES `users`(`user_id`)
        ON DELETE RESTRICT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================================
-- 12. STUDENT INTERVENTIONS
-- =========================================================

CREATE TABLE IF NOT EXISTS `student_interventions` (
    `intervention_id` INT AUTO_INCREMENT PRIMARY KEY,

    `student_id` INT NOT NULL,

    `teacher_id` INT NOT NULL,

    `subject_id` INT NULL,

    `term`
        ENUM(
            'TERM_1',
            'TERM_2',
            'TERM_3'
        ) NOT NULL,

    `academic_year` YEAR NOT NULL,

    `reason` VARCHAR(255) NOT NULL,

    `action_taken` TEXT NOT NULL,

    `follow_up_date` DATE NULL,

    `status`
        ENUM(
            'OPEN',
            'IN_PROGRESS',
            'RESOLVED'
        ) DEFAULT 'OPEN',

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`student_id`)
        REFERENCES `students`(`student_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`teacher_id`)
        REFERENCES `teachers`(`teacher_id`)
        ON DELETE CASCADE,

    FOREIGN KEY (`subject_id`)
        REFERENCES `subjects`(`subject_id`)
        ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 13. ACADEMIC REPORTS
-- =========================================================
-- Run once after migrations_messages.sql on the existing user-pair database.
-- Current direct threads and messages are preserved and backfilled as members.

ALTER TABLE conversations
    MODIFY user_one_id INT NULL,
    MODIFY user_two_id INT NULL,
    MODIFY student_id INT NULL,
    ADD COLUMN conversation_type ENUM('DIRECT', 'CLASS_GROUP', 'ANNOUNCEMENT') NOT NULL DEFAULT 'DIRECT' AFTER subject_id,
    ADD COLUMN group_class_id INT NULL AFTER conversation_type,
    ADD COLUMN title VARCHAR(150) NULL AFTER group_class_id,
    ADD COLUMN created_by_user_id INT NULL AFTER title,
    ADD CONSTRAINT fk_conversations_group_class
        FOREIGN KEY (group_class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_conversations_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    ADD UNIQUE KEY unique_class_group (conversation_type, group_class_id),
    ADD KEY idx_conversations_type (conversation_type);

CREATE TABLE conversation_participants (
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    can_send TINYINT(1) NOT NULL DEFAULT 1,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (conversation_id, user_id),
    KEY idx_participants_user (user_id, conversation_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO conversation_participants (conversation_id, user_id, can_send)
SELECT conversation_id, user_one_id, 1 FROM conversations
WHERE conversation_type = 'DIRECT' AND user_one_id IS NOT NULL;

INSERT IGNORE INTO conversation_participants (conversation_id, user_id, can_send)
SELECT conversation_id, user_two_id, 1 FROM conversations
WHERE conversation_type = 'DIRECT' AND user_two_id IS NOT NULL;

CREATE TABLE message_recipients (
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (message_id, user_id),
    KEY idx_recipients_unread (user_id, is_read, message_id),
    FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 -- Use this once only on databases whose conversations table has parent_id,
-- teacher_id, and student_id. Back up the database before running.
-- Conversation IDs and existing messages are preserved.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE conversations_v2 (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_one_id INT NOT NULL,
    user_two_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_one_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (user_two_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE SET NULL,
    UNIQUE KEY unique_conversation (user_one_id, user_two_id, student_id),
    KEY idx_conversations_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO conversations_v2
    (conversation_id, user_one_id, user_two_id, student_id, subject_id, created_at, updated_at)
SELECT
    c.conversation_id,
    LEAST(p.user_id, t.user_id),
    GREATEST(p.user_id, t.user_id),
    c.student_id,
    c.subject_id,
    c.created_at,
    c.updated_at
FROM conversations c
INNER JOIN parents p ON p.parent_id = c.parent_id
INNER JOIN teachers t ON t.teacher_id = c.teacher_id;
-- Run once against an existing user_one_id/user_two_id schema after a backup.
-- The student context remains nullable for any historical unscoped threads;
-- all new threads created by messages.php include a student context.

UPDATE conversations
SET user_one_id = LEAST(user_one_id, user_two_id),
    user_two_id = GREATEST(user_one_id, user_two_id);

ALTER TABLE conversations
    ADD COLUMN student_id INT NULL AFTER user_two_id,
    ADD COLUMN subject_id INT NULL AFTER student_id,
    ADD CONSTRAINT fk_conversations_student
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_conversations_subject
        FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE SET NULL,
    ADD UNIQUE KEY unique_conversation (user_one_id, user_two_id, student_id),
    ADD KEY idx_conversations_updated (updated_at);

ALTER TABLE messages
    MODIFY COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0,
    ADD KEY idx_messages_unread (conversation_id, is_read, sender_user_id);
CREATE TABLE messages_v2 (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_user_id INT NOT NULL,
    message_body TEXT NOT NULL,
    attachment_url VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations_v2(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    KEY idx_messages_unread (conversation_id, is_read, sender_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO messages_v2
    (message_id, conversation_id, sender_user_id, message_body, attachment_url, is_read, created_at)
SELECT m.message_id, m.conversation_id, m.sender_user_id, m.message_body,
       m.attachment_url, m.is_read, m.created_at
FROM messages m
INNER JOIN conversations_v2 c ON c.conversation_id = m.conversation_id;

DROP TABLE messages;
DROP TABLE conversations;
RENAME TABLE conversations_v2 TO conversations, messages_v2 TO messages;

SET FOREIGN_KEY_CHECKS = 1;
INSERT IGNORE INTO message_recipients (message_id, user_id, is_read, read_at)
SELECT m.message_id,
       CASE WHEN m.sender_user_id = c.user_one_id THEN c.user_two_id ELSE c.user_one_id END,
       m.is_read,
       CASE WHEN m.is_read = 1 THEN m.created_at ELSE NULL END
FROM messages m
INNER JOIN conversations c ON c.conversation_id = m.conversation_id
WHERE c.conversation_type = 'DIRECT'
  AND c.user_one_id IS NOT NULL AND c.user_two_id IS NOT NULL
  AND m.sender_user_id IN (c.user_one_id, c.user_two_id);
CREATE TABLE IF NOT EXISTS `academic_reports` (
    `report_id` INT AUTO_INCREMENT PRIMARY KEY,

    `student_id` INT NOT NULL,

    `term`
        ENUM(
            'TERM_1',
            'TERM_2',
            'TERM_3'
        ) NOT NULL,

    `academic_year` YEAR NOT NULL,

    `overall_average` DECIMAL(5,2) NULL,

    `overall_grade` VARCHAR(10) NULL,

    `class_position` INT NULL,

    `stream_position` INT NULL,

    `teacher_comment` TEXT NULL,

    `principal_comment` TEXT NULL,

    `status`
        ENUM(
            'DRAFT',
            'APPROVED',
            'PUBLISHED'
        ) DEFAULT 'DRAFT',

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`student_id`)
        REFERENCES `students`(`student_id`)
        ON DELETE CASCADE,

    UNIQUE KEY `unique_student_term_year`
        (`student_id`, `term`, `academic_year`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Run this once in phpMyAdmin on your school-system database.
-- These columns extend your EXISTING messages table.

ALTER TABLE `messages`
    ADD COLUMN `attachment_type` ENUM(
        'DOCUMENT',
        'PHOTO',
        'VIDEO',
        'AUDIO',
        'CAMERA',
        'CONTACT',
        'POLL',
        'EVENT',
        'STICKER'
    ) NULL AFTER `message_body`,
    
    ADD COLUMN `attachment_name` VARCHAR(255) NULL AFTER `attachment_url`,
    ADD COLUMN `attachment_meta` TEXT NULL AFTER `attachment_name`;

-- Recommended indexes for a large school system.
CREATE INDEX `idx_messages_conversation`
    ON `messages` (`conversation_id`, `message_id`);

CREATE INDEX `idx_message_recipients_user_read`
    ON `message_recipients` (`user_id`, `is_read`, `message_id`);


SET FOREIGN_KEY_CHECKS = 1;
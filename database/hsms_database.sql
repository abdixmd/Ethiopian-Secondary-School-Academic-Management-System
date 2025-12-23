-- Enhanced database schema with additional features

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS hsms_ethiopia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hsms_ethiopia;

-- ==================== CORE TABLES ====================

-- Users table for authentication
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'registrar', 'teacher', 'student', 'parent', 'education_officer') NOT NULL,
    avatar VARCHAR(255),
    biometric_id VARCHAR(50) UNIQUE, -- Added for biometric scanner integration
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    last_attempt TIMESTAMP NULL,
    password_changed_at TIMESTAMP NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_biometric_id (biometric_id)
);

-- Students table
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    user_id INT UNIQUE, -- Link to the users table
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    date_of_birth DATE NOT NULL,
    place_of_birth VARCHAR(100),
    grade_level ENUM('9', '10', '11', '12') NOT NULL,
    section VARCHAR(10),
    year_enrolled YEAR NOT NULL,
    parent_name VARCHAR(100),
    parent_phone VARCHAR(20),
    parent_email VARCHAR(100),
    address TEXT,
    emergency_contact VARCHAR(20),
    medical_notes TEXT,
    photo VARCHAR(255),
    status ENUM('active', 'graduated', 'transferred', 'dropped', 'suspended') DEFAULT 'active',
    class_teacher_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_grade_level (grade_level),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_teacher_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Teachers table
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id VARCHAR(20) UNIQUE NOT NULL,
    user_id INT UNIQUE, -- Link to the users table
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    qualification VARCHAR(100),
    specialization VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    hire_date DATE NOT NULL,
    employment_type ENUM('permanent', 'contract', 'part-time') DEFAULT 'permanent',
    salary DECIMAL(10,2),
    status ENUM('active', 'inactive', 'on_leave', 'retired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Subjects table (MoE Standard)
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_code VARCHAR(10) UNIQUE NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    grade_level ENUM('9', '10', '11', '12') NOT NULL,
    description TEXT,
    credit_hours INT DEFAULT 4,
    is_core BOOLEAN DEFAULT TRUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subject_code (subject_code),
    INDEX idx_grade_level (grade_level)
);

-- ==================== ACADEMIC TABLES ====================

-- Teacher assignments
CREATE TABLE teacher_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    grade_level ENUM('9', '10', '11', '12') NOT NULL,
    section VARCHAR(10),
    academic_year YEAR NOT NULL,
    term INT DEFAULT 1,
    hours_per_week INT DEFAULT 4,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (teacher_id, subject_id, grade_level, section, academic_year, term),
    INDEX idx_teacher_grade (teacher_id, grade_level),
    INDEX idx_subject_grade (subject_id, grade_level)
);

-- Class schedule
CREATE TABLE class_schedule (
    id INT PRIMARY KEY AUTO_INCREMENT,
    grade_level ENUM('9', '10', '11', '12') NOT NULL,
    section VARCHAR(10),
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
    period INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    room_number VARCHAR(20),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    academic_year YEAR NOT NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_schedule (grade_level, section, day_of_week, period, academic_year),
    INDEX idx_grade_section (grade_level, section),
    INDEX idx_teacher_schedule (teacher_id, day_of_week)
);

-- Attendance table
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL, -- Changed from student_id to user_id
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
    grade_level ENUM('9', '10', '11', '12'),
    section VARCHAR(10),
    period INT,
    remarks TEXT,
    recorded_by INT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (user_id, date, period),
    INDEX idx_date (date),
    INDEX idx_user_date (user_id, date)
);

-- Assessments table
CREATE TABLE assessments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    assessment_type ENUM('quiz', 'test', 'assignment', 'midterm', 'final', 'project') NOT NULL,
    title VARCHAR(100) NOT NULL,
    max_score DECIMAL(5,2) DEFAULT 100.00,
    obtained_score DECIMAL(5,2) NOT NULL,
    assessment_date DATE NOT NULL,
    grade_level ENUM('9', '10', '11', '12') NOT NULL,
    term INT DEFAULT 1,
    academic_year YEAR NOT NULL,
    recorded_by INT NOT NULL,
    remarks TEXT,
    is_finalized BOOLEAN DEFAULT FALSE,
    finalized_by INT,
    finalized_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (finalized_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student_subject (student_id, subject_id),
    INDEX idx_assessment_date (assessment_date),
    INDEX idx_grade_subject (grade_level, subject_id)
);

-- Student grades summary
CREATE TABLE student_grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    grade_level ENUM('9', '10', '11', '12') NOT NULL,
    term INT DEFAULT 1,
    academic_year YEAR NOT NULL,
    quiz_total DECIMAL(5,2) DEFAULT 0,
    test_total DECIMAL(5,2) DEFAULT 0,
    assignment_total DECIMAL(5,2) DEFAULT 0,
    midterm_score DECIMAL(5,2) DEFAULT 0,
    final_score DECIMAL(5,2) DEFAULT 0,
    total_score DECIMAL(5,2) DEFAULT 0,
    grade_point DECIMAL(3,2) DEFAULT 0,
    remarks TEXT,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_grade (student_id, subject_id, term, academic_year),
    INDEX idx_student_year (student_id, academic_year),
    INDEX idx_grade_year (grade_level, academic_year)
);

-- ==================== NATIONAL EXAMS ====================

-- National exam results
CREATE TABLE national_exam_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    exam_type ENUM('grade10', 'grade12') NOT NULL,
    exam_year YEAR NOT NULL,
    mathematics_score DECIMAL(5,2),
    english_score DECIMAL(5,2),
    physics_score DECIMAL(5,2),
    chemistry_score DECIMAL(5,2),
    biology_score DECIMAL(5,2),
    history_score DECIMAL(5,2),
    geography_score DECIMAL(5,2),
    civics_score DECIMAL(5,2),
    ict_score DECIMAL(5,2),
    total_score DECIMAL(6,2),
    average_score DECIMAL(5,2),
    result_status ENUM('passed', 'failed', 'conditional') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_exam (student_id, exam_type, exam_year),
    INDEX idx_exam_year (exam_type, exam_year),
    INDEX idx_student_exam (student_id, exam_type)
);

-- National exam eligibility
CREATE TABLE national_exam_eligibility (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    exam_type ENUM('grade10', 'grade12') NOT NULL,
    academic_year YEAR NOT NULL,
    is_eligible BOOLEAN DEFAULT TRUE,
    eligibility_reason TEXT,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_eligibility (student_id, exam_type, academic_year),
    INDEX idx_exam_eligibility (exam_type, academic_year, is_eligible)
);

-- ==================== FINANCIAL TABLES ====================

-- Fees structure
CREATE TABLE fee_structure (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fee_type VARCHAR(50) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    grade_level ENUM('all', '9', '10', '11', '12') DEFAULT 'all',
    academic_year YEAR NOT NULL,
    term INT DEFAULT 1,
    due_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fee_type (fee_type),
    INDEX idx_grade_year (grade_level, academic_year)
);

-- Student fees
CREATE TABLE fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    fee_structure_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    academic_year YEAR NOT NULL,
    term INT DEFAULT 1,
    due_date DATE NOT NULL,
    status ENUM('pending', 'partial', 'paid', 'overdue', 'waived') DEFAULT 'pending',
    waived_amount DECIMAL(10,2) DEFAULT 0,
    waived_by INT,
    waived_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structure(id) ON DELETE CASCADE,
    FOREIGN KEY (waived_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student_status (student_id, status),
    INDEX idx_due_date (due_date),
    INDEX idx_academic_year (academic_year, term)
);

-- Payments table
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fee_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'check', 'mobile_money') NOT NULL,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    bank_name VARCHAR(100),
    check_number VARCHAR(50),
    transaction_id VARCHAR(100),
    recorded_by INT NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_payment_date (payment_date),
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_recorded_by (recorded_by)
);

-- ==================== COMMUNICATION TABLES ====================

-- Announcements table
CREATE TABLE announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    target_audience ENUM('all', 'students', 'parents', 'teachers', 'specific_grade') NOT NULL,
    grade_level ENUM('9', '10', '11', '12', 'all'),
    is_important BOOLEAN DEFAULT FALSE,
    attachment VARCHAR(255),
    posted_by INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status_date (status, start_date, end_date),
    INDEX idx_target_audience (target_audience, grade_level)
);

-- Messages table
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_important BOOLEAN DEFAULT FALSE,
    parent_message_id INT NULL,
    attachment VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_message_id) REFERENCES messages(id) ON DELETE SET NULL,
    INDEX idx_receiver_read (receiver_id, is_read),
    INDEX idx_sender_created (sender_id, created_at),
    INDEX idx_parent_message (parent_message_id)
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'danger') DEFAULT 'info',
    icon VARCHAR(50),
    link VARCHAR(500),
    is_read BOOLEAN DEFAULT FALSE,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at)
);

-- ==================== SYSTEM TABLES ====================

-- System settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json', 'array') DEFAULT 'string',
    category VARCHAR(50),
    description TEXT,
    is_editable BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_setting_key (setting_key)
);

-- Audit logs
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_timestamp (user_id, timestamp),
    INDEX idx_action_table (action, table_name),
    INDEX idx_timestamp (timestamp)
);

-- Login attempts
CREATE TABLE login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    success BOOLEAN DEFAULT FALSE,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_ip (username, ip_address),
    INDEX idx_attempt_time (attempt_time)
);

-- Backup logs
CREATE TABLE backup_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    backup_type ENUM('full', 'incremental', 'database', 'files') NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT,
    status ENUM('success', 'failed', 'partial') NOT NULL,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_backup_type (backup_type),
    INDEX idx_created_at (created_at)
);

-- File attachments
CREATE TABLE file_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entity_type ENUM('student', 'teacher', 'announcement', 'assessment', 'message', 'payment') NOT NULL,
    entity_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_uploaded_at (uploaded_at)
);

-- ==================== ACADEMIC MANAGEMENT TABLES ====================

-- Academic calendar
CREATE TABLE academic_calendar (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    event_type ENUM('holiday', 'exam', 'meeting', 'activity', 'deadline', 'other') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    start_time TIME,
    end_time TIME,
    grade_level ENUM('all', '9', '10', '11', '12') DEFAULT 'all',
    section VARCHAR(10),
    location VARCHAR(100),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_event_date (event_type, start_date),
    INDEX idx_grade_date (grade_level, start_date)
);

-- Student promotions
CREATE TABLE student_promotions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    from_grade ENUM('9', '10', '11', '12') NOT NULL,
    to_grade ENUM('10', '11', '12', 'graduated') NOT NULL,
    academic_year YEAR NOT NULL,
    average_score DECIMAL(5,2),
    promotion_status ENUM('promoted', 'repeated', 'conditional') NOT NULL,
    remarks TEXT,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student_year (student_id, academic_year),
    INDEX idx_promotion_status (promotion_status, academic_year)
);

-- Teacher leaves
CREATE TABLE teacher_leaves (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    leave_type ENUM('annual', 'sick', 'maternity', 'paternity', 'unpaid', 'other') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL,
    reason TEXT,
    supporting_document VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_teacher_status (teacher_id, status),
    INDEX idx_leave_date (start_date, end_date)
);

-- ==================== API & SECURITY TABLES ====================

CREATE TABLE api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    permissions JSON,
    expires_at DATETIME NULL,
    last_used DATETIME NULL,
    status ENUM('active', 'inactive', 'revoked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_api_key (api_key),
    INDEX idx_user (user_id)
);

CREATE TABLE api_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    token_type ENUM('access', 'refresh') NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_token (user_id, token_type)
);

CREATE TABLE token_blacklist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

CREATE TABLE verification_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user (user_id)
);

-- ==================== VIEWS ====================

-- Student summary view
CREATE VIEW student_summary_view AS
SELECT 
    s.id,
    s.student_id,
    CONCAT(s.first_name, ' ', s.last_name) as full_name,
    s.grade_level,
    s.section,
    s.status,
    COUNT(DISTINCT a.id) as total_attendance,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
    AVG(ass.obtained_score) as average_score,
    SUM(CASE WHEN f.status IN ('pending', 'partial') THEN f.amount ELSE 0 END) as pending_fees
FROM students s
LEFT JOIN attendance a ON s.user_id = a.user_id AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
LEFT JOIN assessments ass ON s.id = ass.student_id AND ass.assessment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
LEFT JOIN fees f ON s.id = f.student_id AND f.status IN ('pending', 'partial')
GROUP BY s.id;

-- Teacher summary view
CREATE VIEW teacher_summary_view AS
SELECT 
    t.id,
    t.teacher_id,
    CONCAT(t.first_name, ' ', t.last_name) as full_name,
    COUNT(DISTINCT ta.subject_id) as subjects_assigned,
    COUNT(DISTINCT ta.grade_level) as grades_assigned,
    SUM(ta.hours_per_week) as total_hours,
    t.status
FROM teachers t
LEFT JOIN teacher_assignments ta ON t.id = ta.teacher_id AND ta.academic_year = YEAR(CURDATE())
GROUP BY t.id;

-- ==================== TRIGGERS ====================

-- Update student updated_at timestamp
DELIMITER $$
CREATE TRIGGER students_before_update
BEFORE UPDATE ON students
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

-- Update teacher updated_at timestamp
DELIMITER $$
CREATE TRIGGER teachers_before_update
BEFORE UPDATE ON teachers
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

-- Log user actions
DELIMITER $$
CREATE TRIGGER users_after_update
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values)
        VALUES (
            1, -- This should be dynamically set to the current user ID
            'UPDATE_STATUS',
            'users',
            NEW.id,
            JSON_OBJECT('status', OLD.status),
            JSON_OBJECT('status', NEW.status)
        );
    END IF;
END$$
DELIMITER ;

-- ==================== STORED PROCEDURES ====================

-- Calculate student grades
DELIMITER $$
CREATE PROCEDURE CalculateStudentGrades(
    IN p_student_id INT,
    IN p_term INT,
    IN p_academic_year YEAR
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_subject_id INT;
    DECLARE v_grade_level VARCHAR(2);
    DECLARE v_quiz_total DECIMAL(5,2);
    DECLARE v_test_total DECIMAL(5,2);
    DECLARE v_assignment_total DECIMAL(5,2);
    DECLARE v_midterm_score DECIMAL(5,2);
    DECLARE v_final_score DECIMAL(5,2);
    DECLARE v_total_score DECIMAL(5,2);
    
    DECLARE cur CURSOR FOR 
        SELECT DISTINCT subject_id, grade_level 
        FROM assessments 
        WHERE student_id = p_student_id 
        AND term = p_term 
        AND academic_year = p_academic_year;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_subject_id, v_grade_level;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Calculate totals for each assessment type
        SELECT COALESCE(AVG(obtained_score), 0) INTO v_quiz_total
        FROM assessments
        WHERE student_id = p_student_id 
        AND subject_id = v_subject_id 
        AND term = p_term 
        AND academic_year = p_academic_year
        AND assessment_type = 'quiz';
        
        SELECT COALESCE(AVG(obtained_score), 0) INTO v_test_total
        FROM assessments
        WHERE student_id = p_student_id 
        AND subject_id = v_subject_id 
        AND term = p_term 
        AND academic_year = p_academic_year
        AND assessment_type = 'test';
        
        SELECT COALESCE(AVG(obtained_score), 0) INTO v_assignment_total
        FROM assessments
        WHERE student_id = p_student_id 
        AND subject_id = v_subject_id 
        AND term = p_term 
        AND academic_year = p_academic_year
        AND assessment_type = 'assignment';
        
        SELECT COALESCE(MAX(obtained_score), 0) INTO v_midterm_score
        FROM assessments
        WHERE student_id = p_student_id 
        AND subject_id = v_subject_id 
        AND term = p_term 
        AND academic_year = p_academic_year
        AND assessment_type = 'midterm';
        
        SELECT COALESCE(MAX(obtained_score), 0) INTO v_final_score
        FROM assessments
        WHERE student_id = p_student_id 
        AND subject_id = v_subject_id 
        AND term = p_term 
        AND academic_year = p_academic_year
        AND assessment_type = 'final';
        
        -- Calculate total score (weighted average)
        SET v_total_score = (v_quiz_total * 0.15) + (v_test_total * 0.25) + 
                           (v_assignment_total * 0.10) + (v_midterm_score * 0.20) + 
                           (v_final_score * 0.30);
        
        -- Insert or update student grades
        INSERT INTO student_grades (
            student_id, subject_id, grade_level, term, academic_year,
            quiz_total, test_total, assignment_total, midterm_score, 
            final_score, total_score, grade_point
        ) VALUES (
            p_student_id, v_subject_id, v_grade_level, p_term, p_academic_year,
            v_quiz_total, v_test_total, v_assignment_total, v_midterm_score,
            v_final_score, v_total_score, v_total_score / 25
        ) ON DUPLICATE KEY UPDATE
            quiz_total = v_quiz_total,
            test_total = v_test_total,
            assignment_total = v_assignment_total,
            midterm_score = v_midterm_score,
            final_score = v_final_score,
            total_score = v_total_score,
            grade_point = v_total_score / 25,
            calculated_at = CURRENT_TIMESTAMP;
            
    END LOOP;
    
    CLOSE cur;
END$$
DELIMITER ;

-- Generate fee invoices
DELIMITER $$
CREATE PROCEDURE GenerateFeeInvoices(
    IN p_academic_year YEAR,
    IN p_term INT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_student_id INT;
    DECLARE v_grade_level VARCHAR(2);
    DECLARE v_fee_structure_id INT;
    DECLARE v_amount DECIMAL(10,2);
    DECLARE v_due_date DATE;
    
    DECLARE cur CURSOR FOR 
        SELECT s.id, s.grade_level, fs.id, fs.amount, fs.due_date
        FROM students s
        CROSS JOIN fee_structure fs
        WHERE s.status = 'active'
        AND fs.academic_year = p_academic_year
        AND fs.term = p_term
        AND (fs.grade_level = 'all' OR fs.grade_level = s.grade_level)
        AND fs.is_active = TRUE;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_student_id, v_grade_level, v_fee_structure_id, v_amount, v_due_date;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Check if fee already exists
        IF NOT EXISTS (
            SELECT 1 FROM fees 
            WHERE student_id = v_student_id 
            AND fee_structure_id = v_fee_structure_id 
            AND academic_year = p_academic_year 
            AND term = p_term
        ) THEN
            INSERT INTO fees (
                student_id, fee_structure_id, amount, 
                academic_year, term, due_date, status
            ) VALUES (
                v_student_id, v_fee_structure_id, v_amount,
                p_academic_year, p_term, v_due_date, 'pending'
            );
        END IF;
    END LOOP;
    
    CLOSE cur;
END$$
DELIMITER ;

-- ==================== SAMPLE DATA ====================

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, full_name, email, role, status) 
VALUES ('admin', '$2y$10$YourHashedPasswordHere', 'System Administrator', 'admin@hsms.edu.et', 'admin', 'active');

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES
('school_name', 'Ethiopian High School', 'string', 'general', 'Name of the school'),
('school_address', 'Addis Ababa, Ethiopia', 'string', 'general', 'School physical address'),
('school_phone', '+251-11-1234567', 'string', 'general', 'School contact phone'),
('school_email', 'info@school.edu.et', 'string', 'general', 'School email address'),
('academic_year', '2024', 'integer', 'academic', 'Current academic year'),
('current_term', '1', 'integer', 'academic', 'Current academic term'),
('attendance_threshold', '75', 'integer', 'academic', 'Minimum attendance percentage required'),
('assessment_weights', '{"quiz": 15, "test": 25, "assignment": 10, "midterm": 20, "final": 30}', 'json', 'academic', 'Weights for different assessment types'),
('sms_enabled', 'false', 'boolean', 'communication', 'Enable SMS notifications'),
('email_enabled', 'true', 'boolean', 'communication', 'Enable email notifications'),
('max_login_attempts', '5', 'integer', 'security', 'Maximum failed login attempts'),
('session_timeout', '30', 'integer', 'security', 'Session timeout in minutes'),
('maintenance_mode', 'false', 'boolean', 'system', 'Put system in maintenance mode'),
('currency_symbol', 'ETB', 'string', 'financial', 'Currency symbol for fees'),
('date_format', 'Y-m-d', 'string', 'general', 'Default date format'),
('time_format', 'H:i:s', 'string', 'general', 'Default time format'),
('items_per_page', '25', 'integer', 'general', 'Default items per page in lists'),
('theme_color', '#4361ee', 'string', 'appearance', 'Primary theme color'),
('enable_registration', 'false', 'boolean', 'security', 'Enable user registration'),
('require_email_verification', 'true', 'boolean', 'security', 'Require email verification');

-- Insert MoE standard subjects
INSERT INTO subjects (subject_code, subject_name, grade_level, is_core) VALUES
-- Grade 9
('MATH9', 'Mathematics', '9', TRUE),
('ENG9', 'English', '9', TRUE),
('PHY9', 'Physics', '9', TRUE),
('CHEM9', 'Chemistry', '9', TRUE),
('BIO9', 'Biology', '9', TRUE),
('HIS9', 'History', '9', TRUE),
('GEO9', 'Geography', '9', TRUE),
('CIV9', 'Civics', '9', TRUE),
('ICT9', 'ICT', '9', TRUE),
-- Grade 10
('MATH10', 'Mathematics', '10', TRUE),
('ENG10', 'English', '10', TRUE),
('PHY10', 'Physics', '10', TRUE),
('CHEM10', 'Chemistry', '10', TRUE),
('BIO10', 'Biology', '10', TRUE),
('HIS10', 'History', '10', TRUE),
('GEO10', 'Geography', '10', TRUE),
('CIV10', 'Civics', '10', TRUE),
('ICT10', 'ICT', '10', TRUE),
-- Grade 11
('MATH11', 'Mathematics', '11', TRUE),
('ENG11', 'English', '11', TRUE),
('PHY11', 'Physics', '11', TRUE),
('CHEM11', 'Chemistry', '11', TRUE),
('BIO11', 'Biology', '11', TRUE),
('HIS11', 'History', '11', TRUE),
('GEO11', 'Geography', '11', TRUE),
('CIV11', 'Civics', '11', TRUE),
('ICT11', 'ICT', '11', TRUE),
-- Grade 12
('MATH12', 'Mathematics', '12', TRUE),
('ENG12', 'English', '12', TRUE),
('PHY12', 'Physics', '12', TRUE),
('CHEM12', 'Chemistry', '12', TRUE),
('BIO12', 'Biology', '12', TRUE),
('HIS12', 'History', '12', TRUE),
('GEO12', 'Geography', '12', TRUE),
('CIV12', 'Civics', '12', TRUE),
('ICT12', 'ICT', '12', TRUE);

-- Insert sample fee structure
INSERT INTO fee_structure (fee_type, description, amount, grade_level, academic_year, term, due_date) VALUES
('Tuition Fee', 'Academic tuition fee for the term', 5000.00, 'all', 2024, 1, '2024-03-31'),
('Library Fee', 'Library maintenance fee', 200.00, 'all', 2024, 1, '2024-03-31'),
('Sports Fee', 'Sports equipment and activities', 300.00, 'all', 2024, 1, '2024-03-31'),
('Lab Fee', 'Science laboratory fee', 500.00, '10', 2024, 1, '2024-03-31'),
('Lab Fee', 'Science laboratory fee', 500.00, '11', 2024, 1, '2024-03-31'),
('Lab Fee', 'Science laboratory fee', 500.00, '12', 2024, 1, '2024-03-31');

-- Insert sample academic calendar events
INSERT INTO academic_calendar (title, description, event_type, start_date, end_date, grade_level, created_by) VALUES
('First Day of School', 'Beginning of academic year', 'activity', '2024-09-01', '2024-09-01', 'all', 1),
('Midterm Exams', 'Midterm examinations for all grades', 'exam', '2024-10-15', '2024-10-19', 'all', 1),
('Parents Meeting', 'Meeting with parents to discuss student progress', 'meeting', '2024-10-25', '2024-10-25', 'all', 1),
('National Holiday', 'Ethiopian National Holiday', 'holiday', '2024-11-02', '2024-11-02', 'all', 1),
('Final Exams', 'End of term examinations', 'exam', '2024-12-10', '2024-12-20', 'all', 1),
('Grade 12 Graduation', 'Graduation ceremony for Grade 12 students', 'activity', '2024-12-25', '2024-12-25', '12', 1);

-- Create necessary indexes
CREATE INDEX idx_student_name ON students(first_name, last_name);
CREATE INDEX idx_teacher_name ON teachers(first_name, last_name);
CREATE INDEX idx_assessment_type ON assessments(assessment_type, assessment_date);
CREATE INDEX idx_fee_status ON fees(status, due_date);
CREATE INDEX idx_message_sender ON messages(sender_id, created_at);
CREATE INDEX idx_notification_type ON notifications(type, created_at);
CREATE INDEX idx_attendance_status ON attendance(status, date);
CREATE INDEX idx_payment_method ON payments(payment_method, payment_date);

-- Create full-text search indexes
CREATE FULLTEXT INDEX ft_student_names ON students(first_name, middle_name, last_name);
CREATE FULLTEXT INDEX ft_teacher_names ON teachers(first_name, middle_name, last_name);
CREATE FULLTEXT INDEX ft_announcements ON announcements(title, content);

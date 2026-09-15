-- FixMyCampus Database Schema
-- Run this SQL in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS fixmycampus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fixmycampus;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id INT(11) NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','staff','admin','maintenance') NOT NULL DEFAULT 'student',
    department VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(15) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY email (email)
) ENGINE=InnoDB;

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    category_id INT(11) NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT 'bi-tools',
    description TEXT DEFAULT NULL,
    PRIMARY KEY (category_id),
    UNIQUE KEY category_name (category_name)
) ENGINE=InnoDB;

-- Issues Table
CREATE TABLE IF NOT EXISTS issues (
    issue_id INT(11) NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category_id INT(11) DEFAULT NULL,
    location VARCHAR(200) NOT NULL,
    priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    status ENUM('pending','in_progress','resolved','closed','rejected') NOT NULL DEFAULT 'pending',
    reported_by INT(11) NOT NULL,
    assigned_to INT(11) DEFAULT NULL,
    parent_id INT(11) DEFAULT NULL,
    is_parent TINYINT(1) DEFAULT 0,
    affected_count INT(11) DEFAULT 1,
    admin_remark TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (issue_id),
    KEY fk_reported_by (reported_by),
    KEY fk_assigned_to (assigned_to),
    KEY fk_category (category_id),
    KEY fk_parent_issue (parent_id),
    CONSTRAINT fk_reported_by FOREIGN KEY (reported_by) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    CONSTRAINT fk_parent_issue FOREIGN KEY (parent_id) REFERENCES issues(issue_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Issue Images Table
CREATE TABLE IF NOT EXISTS issue_images (
    image_id INT(11) NOT NULL AUTO_INCREMENT,
    issue_id INT(11) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (image_id),
    KEY fk_img_issue (issue_id),
    CONSTRAINT fk_img_issue FOREIGN KEY (issue_id) REFERENCES issues(issue_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Status History Table
CREATE TABLE IF NOT EXISTS status_history (
    history_id INT(11) NOT NULL AUTO_INCREMENT,
    issue_id INT(11) NOT NULL,
    changed_by INT(11) NOT NULL,
    old_status VARCHAR(50) NOT NULL,
    new_status VARCHAR(50) NOT NULL,
    remarks TEXT DEFAULT NULL,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY fk_hist_issue (issue_id),
    KEY fk_hist_user (changed_by),
    CONSTRAINT fk_hist_issue FOREIGN KEY (issue_id) REFERENCES issues(issue_id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_user FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    issue_id INT(11) DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    notif_type VARCHAR(50) DEFAULT 'info',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    KEY fk_notif_user (user_id),
    KEY fk_notif_issue (issue_id),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_issue FOREIGN KEY (issue_id) REFERENCES issues(issue_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===========================
-- SEED DATA
-- ===========================

-- Categories
INSERT INTO categories (category_name, icon, description) VALUES
('Electrical Fault', 'bi-lightning-charge', 'Issues related to electrical wiring, power outages, or lighting failures'),
('Plumbing Issue', 'bi-droplet', 'Leaking pipes, blocked drains, or water supply problems'),
('Cleanliness / Waste', 'bi-trash', 'Overflowing bins, dirty areas, or sanitation concerns'),
('Infrastructure Damage', 'bi-building', 'Damaged walls, broken glass, structural concerns'),
('IT / Network Issue', 'bi-wifi', 'Internet outages, broken computers, or network problems'),
('Furniture Damage', 'bi-columns-gap', 'Broken chairs, desks, or classroom furniture'),
('Safety & Security', 'bi-shield-exclamation', 'Safety hazards, CCTV faults, broken locks'),
('Others', 'bi-three-dots', 'Any other campus issue not listed above');

-- Users (passwords are bcrypt of 'password123')
INSERT INTO users (full_name, email, password, role, department, phone) VALUES
('Admin User', 'admin@fixmycampus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administration', '9876543210'),
('John Student', 'student@fixmycampus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Computer Science', '9876543211'),
('Jane Staff', 'staff@fixmycampus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'Library', '9876543212'),
('Mike Maintenance', 'maintenance@fixmycampus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'maintenance', 'Maintenance Dept', '9876543213'),
('Sarah Techie', 'tech@fixmycampus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'maintenance', 'IT Department', '9876543214');

-- Sample Issues
INSERT INTO issues (title, description, category_id, location, priority, status, reported_by, assigned_to) VALUES
('Light not working in Lab 3', 'The tube lights in Computer Lab 3 have been out for 2 days. Students cannot see properly.', 1, 'Block A - Lab 3', 'high', 'pending', 2, NULL),
('Leaking pipe in men''s washroom', 'There is a major pipe leak near the first floor men''s washroom. Water is flooding the corridor.', 2, 'Block B - 1st Floor Washroom', 'critical', 'in_progress', 3, 4),
('Library area is dirty', 'The reading area near the periodicals section has not been cleaned for a week.', 3, 'Library - Reading Area', 'medium', 'resolved', 2, 4),
('Projector broken in Room 201', 'The HDMI port of the projector is damaged. Faculty cannot use it for teaching.', 6, 'Academic Block - Room 201', 'high', 'pending', 3, NULL),
('WiFi not working in hostel', 'Hostel Block C has had no WiFi for 3 days. Students are unable to submit assignments.', 5, 'Hostel Block C', 'critical', 'in_progress', 2, 5);

-- Status History
INSERT INTO status_history (issue_id, changed_by, old_status, new_status, remarks) VALUES
(2, 1, 'pending', 'in_progress', 'Assigned to Mike. Plumber dispatched.'),
(3, 4, 'in_progress', 'resolved', 'Area cleaned thoroughly. Issue resolved.'),
(5, 1, 'pending', 'in_progress', 'IT team assigned. Working on router reset.');

-- Sample Notifications
INSERT INTO notifications (user_id, issue_id, message, is_read, notif_type) VALUES
(2, 2, 'Your issue #2 (Leaking pipe) is now In Progress. A technician has been assigned.', 0, 'info'),
(2, 3, 'Your issue #3 (Library dirty) has been Resolved. Thank you for reporting!', 1, 'success'),
(1, 1, 'New issue #1 submitted by John Student: Light not working in Lab 3', 0, 'warning'),
(1, 4, 'New issue #4 submitted by Jane Staff: Projector broken in Room 201', 0, 'warning'),
(2, 5, 'Your issue #5 (WiFi not working) is now In Progress.', 0, 'info'),
(5, 5, 'Issue #5 has been assigned to you: WiFi not working in hostel. Please investigate.', 0, 'warning'),
(4, 2, 'Issue #2 has been assigned to you: Leaking pipe in men''s washroom. Priority: CRITICAL', 0, 'danger');

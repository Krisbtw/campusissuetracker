-- FixMyCampus Database Schema (PostgreSQL / Supabase)

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'student',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    department VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(15) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Categories Table
CREATE TABLE IF NOT EXISTS categories (
    category_id SERIAL PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(50) DEFAULT 'bi-tools',
    description TEXT DEFAULT NULL
);

-- 3. Issues Table
CREATE TABLE IF NOT EXISTS issues (
    issue_id SERIAL PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category_id INT DEFAULT NULL REFERENCES categories(category_id) ON DELETE SET NULL,
    location VARCHAR(200) NOT NULL,
    priority VARCHAR(50) NOT NULL DEFAULT 'medium',
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    reported_by INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    assigned_to INT DEFAULT NULL REFERENCES users(user_id) ON DELETE SET NULL,
    parent_id INT DEFAULT NULL REFERENCES issues(issue_id) ON DELETE SET NULL,
    is_parent SMALLINT DEFAULT 0,
    affected_count INT DEFAULT 1,
    reopen_count INT DEFAULT 0,
    admin_remark TEXT DEFAULT NULL,
    rating SMALLINT DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    feedback_at TIMESTAMP DEFAULT NULL,
    resolution_image VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Announcements Table
CREATE TABLE IF NOT EXISTS announcements (
    announcement_id SERIAL PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    urgency VARCHAR(20) DEFAULT 'info',
    created_by INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    is_active SMALLINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Issue Images Table
CREATE TABLE IF NOT EXISTS issue_images (
    image_id SERIAL PRIMARY KEY,
    issue_id INT NOT NULL REFERENCES issues(issue_id) ON DELETE CASCADE,
    image_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Status History Table
CREATE TABLE IF NOT EXISTS status_history (
    history_id SERIAL PRIMARY KEY,
    issue_id INT NOT NULL REFERENCES issues(issue_id) ON DELETE CASCADE,
    changed_by INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    old_status VARCHAR(50) NOT NULL,
    new_status VARCHAR(50) NOT NULL,
    remarks TEXT DEFAULT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    issue_id INT DEFAULT NULL REFERENCES issues(issue_id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    is_read SMALLINT NOT NULL DEFAULT 0,
    notif_type VARCHAR(50) DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_issues_reported_by ON issues(reported_by);
CREATE INDEX IF NOT EXISTS idx_issues_assigned_to ON issues(assigned_to);
CREATE INDEX IF NOT EXISTS idx_issues_category ON issues(category_id);
CREATE INDEX IF NOT EXISTS idx_issues_parent ON issues(parent_id);
CREATE INDEX IF NOT EXISTS idx_issues_status ON issues(status);
CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notif_issue ON notifications(issue_id);

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
('Others', 'bi-three-dots', 'Any other campus issue not listed above')
ON CONFLICT (category_name) DO NOTHING;

-- Default Users (passwords are bcrypt of 'password123')
INSERT INTO users (full_name, email, password, role, department, phone) VALUES
('Admin User', 'admin@fixmycampus.com', '$2y$10$Z35bH6yk4CnQDdqqNoVNwe28rZv5xa206HpLeWUc0qaLYqsGdVxn.', 'admin', 'Administration', '9876543210'),
('John Student', 'student@fixmycampus.com', '$2y$10$Z35bH6yk4CnQDdqqNoVNwe28rZv5xa206HpLeWUc0qaLYqsGdVxn.', 'student', 'Computer Science', '9876543211'),
('Jane Staff', 'staff@fixmycampus.com', '$2y$10$Z35bH6yk4CnQDdqqNoVNwe28rZv5xa206HpLeWUc0qaLYqsGdVxn.', 'staff', 'Library', '9876543212'),
('Mike Maintenance', 'maintenance@fixmycampus.com', '$2y$10$Z35bH6yk4CnQDdqqNoVNwe28rZv5xa206HpLeWUc0qaLYqsGdVxn.', 'maintenance', 'Maintenance Dept', '9876543213'),
('Sarah Techie', 'tech@fixmycampus.com', '$2y$10$Z35bH6yk4CnQDdqqNoVNwe28rZv5xa206HpLeWUc0qaLYqsGdVxn.', 'maintenance', 'IT Department', '9876543214')
ON CONFLICT (email) DO NOTHING;

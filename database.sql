CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('dean', 'panelist', 'student') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL,
    project_title VARCHAR(200) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    student_id INT NOT NULL,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    criteria_name VARCHAR(100) NOT NULL,
    description TEXT,
    weight DECIMAL(5, 2) NOT NULL, -- e.g., 25 for 25%
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    panelist_id INT NOT NULL,
    criteria_id INT NOT NULL,
    score DECIMAL(5, 2) NOT NULL, -- Score given (e.g., 1-10 or 1-100)
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (panelist_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (criteria_id) REFERENCES criteria(id) ON DELETE CASCADE,
    UNIQUE KEY unique_score (team_id, panelist_id, criteria_id)
);

-- Seed Data

-- Users (Password: password123 for all - hashed using PASSWORD_DEFAULT in PHP usually, but for seed we might need a known hash or Insert via PHP. I will use a simple hash for 'password123' if possible or just plain text for now and handle hashing in PHP. For this setup, I'll assume application handles hashing. Let's put a placeholder hash.)
-- $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi is 'password'
INSERT INTO users (username, password, full_name, role) VALUES
('dean', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dean Smith', 'dean'),
('panel1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Panel One', 'panelist'),
('panel2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Panel Two', 'panelist'),
('panel3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Engr. Panel Three', 'panelist'),
('student1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice Student', 'student'),
('student2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob Student', 'student');

-- Teams
INSERT INTO teams (team_name, project_title, description) VALUES
('Alpha Team', 'Smart Library System', 'RFID based library system'),
('Beta Team', 'AI Farm Monitor', 'IoT and AI for farming');

-- Team Members
INSERT INTO team_members (team_id, student_id) VALUES
(1, 5), -- Alice in Alpha
(2, 6); -- Bob in Beta

-- Criteria (Total 100%)
INSERT INTO criteria (criteria_name, weight, display_order) VALUES
('Presentation Skills', 20.00, 1),
('Technical Complexity', 30.00, 2),
('Functionality', 30.00, 3),
('Q&A Response', 20.00, 4);

-- Add Role to Team Members
ALTER TABLE team_members ADD COLUMN role_in_project VARCHAR(100) DEFAULT 'Member';

-- Add Schedule to Teams
ALTER TABLE teams ADD COLUMN schedule_time DATETIME;

-- Ensure constraints (already exists but ensuring uniqueness of student)
-- A student belongs to one group only
ALTER TABLE team_members ADD UNIQUE KEY unique_student_team (student_id);

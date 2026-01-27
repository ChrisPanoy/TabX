-- Rubric Categories for detailed breakdown
CREATE TABLE IF NOT EXISTS rubric_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL, -- e.g. "Group Criteria", "Individual Criteria"
    description TEXT,
    is_default BOOLEAN DEFAULT FALSE
);

-- Pre-seed categories if not exist (handled via logic mostly, but good to have constraint)

-- Update Criteria to support ranges
ALTER TABLE criteria ADD COLUMN min_score INT DEFAULT 0;
ALTER TABLE criteria ADD COLUMN max_score INT DEFAULT 100;

-- Optional: If we want to template them
INSERT INTO criteria (criteria_name, weight, type, display_order, min_score, max_score) VALUES
('Project Innovation', 20, 'group', 1, 0, 100),
('Technical Implementation', 25, 'group', 2, 0, 100),
('System Functionality', 25, 'group', 3, 0, 100),
('Documentation Quality', 15, 'group', 4, 0, 100),
('Poster Design', 5, 'group', 5, 0, 100),
('Overall Impact', 10, 'group', 6, 0, 100),
('Presentation Skills', 25, 'individual', 1, 0, 100),
('Confidence', 15, 'individual', 2, 0, 100),
('Technical Knowledge', 25, 'individual', 3, 0, 100),
('Communication Skills', 15, 'individual', 4, 0, 100),
('Ability to Answer Questions', 20, 'individual', 5, 0, 100);

-- Note: In my current design, criteria are linked to events via `event_id`. 
-- The above insert creates "template" criteria (where event_id is NULL).
-- When creating an event, the user effectively "clones" or selects these.

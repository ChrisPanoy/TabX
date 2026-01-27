-- Add category column to criteria table
ALTER TABLE criteria ADD COLUMN category VARCHAR(50) DEFAULT 'General' AFTER type;

-- Update existing group criteria based on names if possible (best effort)
UPDATE criteria SET category = 'Documentation' WHERE type = 'group' AND (criteria_name LIKE '%Documentation%' OR criteria_name LIKE '%EMRAD%');
UPDATE criteria SET category = 'Poster' WHERE type = 'group' AND (criteria_name LIKE '%Poster%');
UPDATE criteria SET category = 'Brochure' WHERE type = 'group' AND (criteria_name LIKE '%Brochure%');
UPDATE criteria SET category = 'Teaser' WHERE type = 'group' AND (criteria_name LIKE '%Teaser%' OR criteria_name LIKE '%Video%');
UPDATE criteria SET category = 'General' WHERE category IS NULL;

ALTER TABLE resource_modification_requests
ADD COLUMN request_type VARCHAR(50) NOT NULL DEFAULT 'modification ressources' AFTER requested_by;

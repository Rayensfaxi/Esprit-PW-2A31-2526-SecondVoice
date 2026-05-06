ALTER TABLE registrations
ADD UNIQUE KEY unique_registration_user_event (user_id, event_id);

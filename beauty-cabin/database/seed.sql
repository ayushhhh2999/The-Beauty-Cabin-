USE beauty_cabin_v2;

INSERT INTO users (username, email, password, role)
SELECT
    'useradmin',
    'owner@thebeautycabin.local',
    '$2y$12$ob.IiAleycJmv8nvxlTF7uqjgmZ/X80N/rUb73vx1Yt.XyoegQrF6',
    'OWNER'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE role = 'OWNER');

INSERT IGNORE INTO services (name, description, price, duration_minutes, image) VALUES
    ('Haircut', 'Professional cut and styling tailored to your face shape.', 300.00, 30, 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=900&q=80'),
    ('Hair Spa', 'Deep conditioning treatment that restores shine and softness.', 800.00, 60, 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=900&q=80'),
    ('Facial', 'Cleansing facial for fresh, glowing skin.', 1000.00, 60, 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=900&q=80'),
    ('Manicure', 'Nail shaping, cuticle care and polish for beautiful hands.', 500.00, 45, 'https://images.unsplash.com/photo-1610992015732-2449b76344bc?auto=format&fit=crop&w=900&q=80'),
    ('Pedicure', 'Relaxing foot soak, scrub and polish.', 600.00, 45, 'https://images.unsplash.com/photo-1519014816548-bf5fe059798b?auto=format&fit=crop&w=900&q=80'),
    ('Makeup', 'Occasion makeup by our expert artists.', 2000.00, 90, 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&w=900&q=80')
ON DUPLICATE KEY UPDATE image = VALUES(image);

INSERT IGNORE INTO working_hours (weekday, opens_at, closes_at, is_closed) VALUES
    (0, '09:00:00', '20:00:00', FALSE),
    (1, '09:00:00', '20:00:00', FALSE),
    (2, '09:00:00', '20:00:00', FALSE),
    (3, '09:00:00', '20:00:00', FALSE),
    (4, '09:00:00', '20:00:00', FALSE),
    (5, '09:00:00', '21:00:00', FALSE),
    (6, '10:00:00', '18:00:00', FALSE);

INSERT IGNORE INTO salon_settings (setting_key, setting_value) VALUES
    ('name', 'The Beauty Cabin'),
    ('address', 'Salon address to be configured'),
    ('phone', 'Salon phone to be configured'),
    ('email', 'Salon email to be configured');
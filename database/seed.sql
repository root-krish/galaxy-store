-- Demo catalogue and accounts for the Sports Galaxy MySQL schema.
-- Passwords: Admin@123, Customer@123, Delivery@123. Change them before deployment.

USE sports_galaxy;

INSERT IGNORE INTO categories (id, name, slug, icon) VALUES
  (1, 'Team Sports', 'team-sports', '⚽'),
  (2, 'Cricket', 'cricket', '🏏'),
  (3, 'Fitness', 'fitness', '🏋️'),
  (4, 'Racquet Sports', 'racquet-sports', '🏸'),
  (5, 'Outdoor', 'outdoor', '🚴');

INSERT IGNORE INTO users (id, name, email, phone, password_hash, role) VALUES
  (1, 'Aarav Mehta', 'admin@sportsgalaxy.local', '+91 98765 40001', '$2y$12$DoYZldBW2fh/neh/o1uU/OTytihUI2dR58iyANXeDvWwT9Y7Vqlca', 'admin'),
  (2, 'Ananya Roy', 'customer@sportsgalaxy.local', '+91 98765 40002', '$2y$12$xpu4PxqW1D/h8ElXBQTIJO4QM1pYEpGbdH6ZivYAZsW92B7/okMKW', 'customer'),
  (3, 'Rohan Das', 'delivery@sportsgalaxy.local', '+91 98765 40003', '$2y$12$0Zdv9eIS1xAsyJWO1B4Pn.kkPzVnV0zqlqP5BLpqwt1kXM9zcGDZS', 'delivery_partner');

INSERT IGNORE INTO products
  (id, category_id, sku, slug, name, brand, description, cost_price, selling_price, stock_qty, min_stock_qty, expected_restock_date, is_featured)
VALUES
  (1, 1, 'SG-FB-001', 'astro-vision-football', 'Astro Vision Football', 'NIVIA', 'Match-ready hand-stitched football with a durable PU cover.', 650, 1299, 14, 5, NULL, 1),
  (2, 2, 'SG-CR-014', 'prolite-english-willow-bat', 'Prolite English Willow Bat', 'SG', 'Lightweight English willow bat with an extended sweet spot.', 1600, 2899, 4, 5, DATE_ADD(CURDATE(), INTERVAL 6 DAY), 1),
  (3, 5, 'SG-RN-021', 'swift-run-x-trainers', 'Swift Run X Trainers', 'PUMA', 'Cushioned road-running trainers built for responsive daily miles.', 2400, 3999, 0, 4, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 1),
  (4, 1, 'SG-BB-007', 'arena-grip-basketball', 'Arena Grip Basketball', 'COSCO', 'High-grip composite ball for indoor and outdoor courts.', 620, 1199, 17, 5, NULL, 1),
  (5, 4, 'SG-RC-006', 'velocity-700-racket', 'Velocity 700 Racket', 'YONEX', 'Balanced graphite racket with a confidence-building control frame.', 1250, 2499, 6, 5, NULL, 0),
  (6, 3, 'SG-FN-011', 'pulse-flex-yoga-mat', 'Pulse Flex Yoga Mat', 'CULT', 'Non-slip 6 mm training mat with a carry strap.', 390, 899, 25, 6, NULL, 0),
  (7, 2, 'SG-CR-028', 'club-leather-cricket-ball', 'Club Leather Cricket Ball', 'KOOKABURRA', 'Four-piece leather ball for focused club practice sessions.', 310, 699, 32, 8, NULL, 0),
  (8, 3, 'SG-GM-013', 'impact-boxing-gloves', 'Impact Boxing Gloves', 'ADIDAS', 'Protective 12 oz gloves with multi-layer foam cushioning.', 870, 1899, 3, 5, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 0),
  (9, 5, 'SG-OD-019', 'trek-hydro-bottle', 'Trek Hydro Bottle', 'WILDCRAFT', 'Insulated steel bottle that keeps your next adventure hydrated.', 290, 749, 20, 6, NULL, 0),
  (10, 4, 'SG-TN-004', 'baseline-tennis-set', 'Baseline Tennis Set', 'HEAD', 'Two starter rackets and three pressurised balls for weekend rallies.', 1050, 2199, 8, 4, NULL, 0);

INSERT IGNORE INTO discounts (code, name, discount_type, discount_value, minimum_subtotal, is_active)
VALUES ('GALAXY10', 'Galaxy welcome offer', 'percent', 10.00, 0, 1);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('tax_rate', '5'),
  ('shipping_fee', '79'),
  ('free_shipping_at', '999');

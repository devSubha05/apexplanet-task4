-- Rasoi Express - Shop Section Migration
-- Run after database.sql: mysql -u root rasoi_express < shop_migration.sql

-- shops table
CREATE TABLE IF NOT EXISTS shops (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  address VARCHAR(255) NOT NULL,
  phone VARCHAR(15) NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_shops_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_shops_owner (owner_id)
) ENGINE=InnoDB;

-- Link foods to shops (NULL = admin-created, not tied to any shop)
ALTER TABLE foods ADD COLUMN shop_id INT UNSIGNED DEFAULT NULL AFTER category_id;
ALTER TABLE foods ADD INDEX idx_foods_shop (shop_id);
ALTER TABLE foods ADD CONSTRAINT fk_foods_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE SET NULL;

-- Expand user role to include shop_owner
ALTER TABLE users MODIFY COLUMN role ENUM('user','admin','shop_owner') NOT NULL DEFAULT 'user';

-- Seed: shop@rasoi.test / Shop@123
INSERT INTO users (name,email,phone,address,password_hash,role,last_login_at) VALUES
('Shop Owner','shop@rasoi.test','9000000003','45 Food Street, Durgapur',
 '$2y$10$6PfBFdwazSzejElbxj1Xue7s/FOTB3.1x6pJTuRkdzXB4PW71rWBG','shop_owner',NOW());

INSERT INTO shops (name,description,address,phone,owner_id) VALUES
('Durgapur Kitchen','Authentic Bengali and North Indian cuisine','45 Food Street, Durgapur','9000000003',
 (SELECT id FROM users WHERE email='shop@rasoi.test'));


-- Assign all existing dishes to this shop
UPDATE foods SET shop_id = (SELECT id FROM shops WHERE name='Durgapur Kitchen');


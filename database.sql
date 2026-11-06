-- Rasoi Express - Food Ordering System
-- Import in phpMyAdmin or: mysql -u root -p < database.sql
CREATE DATABASE IF NOT EXISTS rasoi_express CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rasoi_express;

DROP TABLE IF EXISTS password_resets, order_items, orders, foods, shops, categories, users;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(15) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin','shop_owner') NOT NULL DEFAULT 'user',
  status ENUM('active','blocked') NOT NULL DEFAULT 'active',
  avatar VARCHAR(255) DEFAULT NULL,
  last_login_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_users_role (role),
  INDEX idx_users_last_login (last_login_at)
) ENGINE=InnoDB;

CREATE TABLE shops (
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

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE foods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  shop_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  price DECIMAL(8,2) NOT NULL,
  image VARCHAR(255) DEFAULT NULL,
  is_veg TINYINT(1) NOT NULL DEFAULT 1,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_foods_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_foods_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE SET NULL,
  INDEX idx_foods_category (category_id),
  INDEX idx_foods_shop (shop_id),
  INDEX idx_foods_name (name),
  INDEX idx_foods_available_price (is_available, price)
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('pending','preparing','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending',
  payment_method ENUM('cod','upi') NOT NULL DEFAULT 'cod',
  delivery_address VARCHAR(255) NOT NULL,
  phone VARCHAR(15) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_orders_user (user_id),
  INDEX idx_orders_status (status),
  INDEX idx_orders_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  food_id INT UNSIGNED DEFAULT NULL,
  food_name VARCHAR(100) NOT NULL,
  price DECIMAL(8,2) NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_food FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE SET NULL,
  INDEX idx_items_order (order_id),
  INDEX idx_items_food (food_id)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_reset_token (token_hash)
) ENGINE=InnoDB;

-- Seed: admin@rasoi.test / Admin@123   |   demo@rasoi.test / User@123   |   shop@rasoi.test / Shop@123
INSERT INTO users (name,email,phone,address,password_hash,role,last_login_at) VALUES
('Site Admin','admin@rasoi.test','9000000001','Admin Office, Durgapur','$2b$12$CknTFsb.Y1dYv8tM4rUeR.4553FP3FaCHbQLs9qPYLnMIyOGvMFwC','admin',NOW()),
('Demo User','demo@rasoi.test','9000000002','12 City Centre, Durgapur','$2b$12$yK68m37tJAtL/WmHQUQjQOnIWLLbvloMTN.k0BKmnnzCz9QSybv76','user',NOW()),
('Shop Owner','shop@rasoi.test','9000000003','45 Food Street, Durgapur','$2y$10$6PfBFdwazSzejElbxj1Xue7s/FOTB3.1x6pJTuRkdzXB4PW71rWBG','shop_owner',NOW());

INSERT INTO shops (name,description,address,phone,owner_id) VALUES
('Durgapur Kitchen','Authentic Bengali and North Indian cuisine made fresh to order.','45 Food Street, Durgapur','9000000003',
 (SELECT id FROM users WHERE email='shop@rasoi.test'));

INSERT INTO categories (name) VALUES ('Starters'),('Main Course'),('Biryani & Rice'),('Breads'),('Desserts'),('Drinks');

INSERT INTO foods (category_id,shop_id,name,description,price,is_veg) VALUES
(1,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Paneer Tikka','Char-grilled cottage cheese with mint chutney',220.00,1),
(1,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Chicken 65','Crispy spiced chicken, curry leaf tempering',240.00,0),
(1,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Veg Spring Roll','Crunchy rolls stuffed with seasoned vegetables',140.00,1),
(2,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Butter Chicken','Creamy tomato gravy, tandoori chicken',320.00,0),
(2,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Dal Makhani','Slow-cooked black lentils finished with butter',210.00,1),
(2,NULL,'Kosha Mangsho','Bengali-style slow-cooked mutton curry',380.00,0),
(2,NULL,'Shahi Paneer','Paneer in a rich cashew and cream gravy',260.00,1),
(3,NULL,'Kolkata Chicken Biryani','Aromatic basmati with chicken, potato and egg',290.00,0),
(3,NULL,'Veg Pulao','Fragrant rice with seasonal vegetables',180.00,1),
(3,NULL,'Jeera Rice','Basmati rice tempered with cumin',120.00,1),
(4,NULL,'Butter Naan','Soft tandoor-baked bread with butter',45.00,1),
(4,NULL,'Luchi (4 pcs)','Puffed fried bread, Bengali style',70.00,1),
(5,NULL,'Rasgulla (2 pcs)','Spongy cottage cheese balls in sugar syrup',60.00,1),
(5,NULL,'Gulab Jamun (2 pcs)','Warm milk dumplings in cardamom syrup',70.00,1),
(5,NULL,'Mishti Doi','Sweetened set yoghurt, clay-pot style',65.00,1),
(6,NULL,'Masala Chai','Ginger and cardamom tea',30.00,1),
(6,NULL,'Sweet Lassi','Thick chilled yoghurt drink',80.00,1),
(6,NULL,'Fresh Lime Soda','Sweet or salted',60.00,1);

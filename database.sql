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
  pincode VARCHAR(10) DEFAULT NULL,
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

INSERT INTO foods (category_id,shop_id,name,description,price,image,is_veg) VALUES
(1,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Paneer Tikka','Char-grilled cottage cheese with mint chutney',220.00,'paneer-tikka.jpg',1),
(1,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Chicken 65','Crispy spiced chicken, curry leaf tempering',240.00,'chicken-65.jpg',0),
(1,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Veg Spring Roll','Crunchy rolls stuffed with seasoned vegetables',140.00,'veg-spring-roll.jpg',1),
(1,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Tandoori Chicken','Juicy bone-in chicken marinated in yogurt and aromatic tandoori spices, char-grilled to smoky perfection.',280.00,'tandoori-chicken.jpg',0),
(1,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Crispy Chilli Baby Corn','Crisp-fried tender baby corn tossed with bell peppers, spring onions, and spicy Indo-Chinese chilli glaze.',180.00,'chilli-babycorn.jpg',1),
(1,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Kolkata Fish Fry','Classic Kolkata style Bhetki fish fillet marinated in fresh coriander-kasundi herbs, golden crumb fried.',260.00,'fish-fry.jpg',0),
(2,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Butter Chicken','Creamy tomato gravy, tandoori chicken',320.00,'butter-chicken.jpg',0),
(2,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Dal Makhani','Slow-cooked black lentils finished with butter',210.00,'dal-makhani.jpg',1),
(2,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Kosha Mangsho','Bengali-style slow-cooked mutton curry',380.00,'kosha-mangsho.jpg',0),
(2,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Shahi Paneer','Paneer in a rich cashew and cream gravy',260.00,'shahi-paneer.jpg',1),
(2,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Kadai Paneer','Fresh cottage cheese cubes sauteed with crisp bell peppers, onions, and freshly roasted kadai spices.',250.00,'kadai-paneer.jpg',1),
(2,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Mutton Rogan Josh','Kashmiri delicacy of tender braised mutton simmered in a slow-cooked aromatic fennel and Kashmiri chili gravy.',390.00,'rogan-josh.jpg',0),
(2,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Chicken Tikka Masala','Smoky char-grilled chicken tikka cooked in a velvety spiced tomato, ginger, and fenugreek cream gravy.',310.00,'chicken-tikka-masala.jpg',0),
(2,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Palak Paneer','Soft paneer cubes cooked in a vibrant, creamy spinach gravy tempered with garlic and cumin.',230.00,'palak-paneer.jpg',1),
(3,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Kolkata Chicken Biryani','Aromatic basmati with chicken, potato and egg',290.00,'kolkata-biryani.jpg',0),
(3,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Hyderabadi Dum Biryani','Fragrant long-grain basmati rice and marinated chicken slow-cooked on dum with saffron, herbs, and caramelized onions.',320.00,'hyderabadi-biryani.jpg',0),
(3,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Paneer Dum Biryani','Royal basmati rice infused with whole spices and layered with marinated cottage cheese cubes and fresh mint.',220.00,'paneer-biryani.jpg',1),
(3,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Veg Pulao','Fragrant rice with seasonal vegetables',180.00,'veg-pulao.jpg',1),
(3,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Jeera Rice','Basmati rice tempered with cumin',120.00,'jeera-rice.jpg',1),
(4,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Butter Naan','Soft tandoor-baked bread with butter',45.00,'butter-naan.jpg',1),
(4,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Garlic Naan','Soft clay-oven baked tandoori bread generously coated with minced garlic, chopped coriander, and butter.',60.00,'garlic-naan.jpg',1),
(4,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Tandoori Roti','Traditional stone-ground whole wheat flatbread baked crisp in the clay oven, topped with fresh butter.',30.00,'tandoori-roti.jpg',1),
(4,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Luchi (4 pcs)','Puffed fried bread, Bengali style',70.00,'luchi.jpg',1),
(5,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Rasgulla (2 pcs)','Spongy cottage cheese balls in sugar syrup',60.00,'rasgulla.jpg',1),
(5,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Gulab Jamun (2 pcs)','Warm milk dumplings in cardamom syrup',70.00,'gulab-jamun.png',1),
(5,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Gajar Ka Halwa','Authentic North Indian winter pudding made of slow-simmered red carrots, milk, mawa, ghee, and cashew nuts.',110.00,'gajar-halwa.jpg',1),
(5,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Mishti Doi','Sweetened set yoghurt, clay-pot style',65.00,'mishti-doi.jpg',1),
(6,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Mango Lassi','Velvety smooth beverage made with sweet Alphonso mango pulp, thick curd, and a dash of cardamom.',95.00,'mango-lassi.jpg',1),
(6,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Cold Coffee with Ice Cream','Chilled blended creamy coffee served rich and refreshing, crowned with a scoop of vanilla ice cream.',120.00,'cold-coffee.jpg',1),
(6,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Sweet Lassi','Thick chilled yoghurt drink',80.00,'sweet-lassi.jpg',1),
(6,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Masala Chai','Ginger and cardamom tea',30.00,'masala-chai.jpg',1),
(6,(SELECT id FROM shops WHERE name='Durgapur Kitchen'),'Fresh Lime Soda','Sweet or salted',60.00,'fresh-lime-soda.jpg',1);

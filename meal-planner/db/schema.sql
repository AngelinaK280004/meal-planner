-- СОЗДАНИЕ БАЗЫ ДАННЫХ

CREATE DATABASE IF NOT EXISTS meal_planner 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE meal_planner;

-- ТАБЛИЦЫ

CREATE TABLE IF NOT EXISTS recipe_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS ingredients (
    ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    product_code VARCHAR(20) UNIQUE,
    unit VARCHAR(20) NOT NULL,
    calories_per_100g DECIMAL(6,2) CHECK (calories_per_100g >= 0),
    protein_per_100g DECIMAL(6,2) CHECK (protein_per_100g >= 0),
    carbs_per_100g DECIMAL(6,2) CHECK (carbs_per_100g >= 0),
    fat_per_100g DECIMAL(6,2) CHECK (fat_per_100g >= 0),
    category VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_admin BOOLEAN DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS anthropometry (
    anthropometry_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    record_date DATE DEFAULT CURRENT_DATE,
    height_cm DECIMAL(5,2) CHECK (height_cm BETWEEN 50 AND 300),
    weight_kg DECIMAL(5,2) CHECK (weight_kg BETWEEN 10 AND 200),
    age INT CHECK (age BETWEEN 1 AND 120),
    gender CHAR(1) CHECK (gender IN ('M','F')),
    bmr_calories INT CHECK (bmr_calories BETWEEN 500 AND 5000),
    daily_calories_goal INT CHECK (daily_calories_goal BETWEEN 500 AND 5000),
    daily_protein_goal DECIMAL(6,2) CHECK (daily_protein_goal >= 0),
    daily_fat_goal DECIMAL(6,2) CHECK (daily_fat_goal >= 0),
    daily_carbs_goal DECIMAL(6,2) CHECK (daily_carbs_goal >= 0),
    activity_level VARCHAR(20) CHECK (activity_level IN ('sedentary','light','moderate','active','very_active')),
    goal_type VARCHAR(30) CHECK (goal_type IN ('weight_loss','maintenance','muscle_gain')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_date (user_id, record_date)
);


CREATE TABLE IF NOT EXISTS user_preferences_core (
    preference_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    max_difficulty INT CHECK (max_difficulty IN (1,2,3)),
    meals_per_day INT CHECK (meals_per_day BETWEEN 1 AND 6),
    preferred_cuisine VARCHAR(30),
    dietary_mask INT UNSIGNED DEFAULT 0,
    dishes_breakfast INT DEFAULT 1,
    drinks_breakfast INT DEFAULT 1,
    dishes_lunch INT DEFAULT 1,
    drinks_lunch INT DEFAULT 1,
    dishes_dinner INT DEFAULT 1,
    drinks_dinner INT DEFAULT 1,
    dishes_snack INT DEFAULT 1,
    drinks_snack INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS dietary_options (
    option_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    mask_value INT NOT NULL UNIQUE,
    description VARCHAR(200)
);

CREATE TABLE IF NOT EXISTS user_excluded_products (
    user_id INT NOT NULL,
    excluded_product_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (user_id, excluded_product_name),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_allergens (
    user_id INT NOT NULL,
    allergen_type VARCHAR(50) NOT NULL,
    PRIMARY KEY (user_id, allergen_type),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS recipes (
    recipe_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    category_id INT,
    cooking_time INT CHECK (cooking_time > 0),
    difficulty INT CHECK (difficulty IN (1,2,3)),
    description TEXT,
    instructions TEXT NOT NULL,
    image_url VARCHAR(500),
    calories_per_serving INT CHECK (calories_per_serving >= 0),
    protein_per_serving DECIMAL(5,2) CHECK (protein_per_serving >= 0),
    carbs_per_serving DECIMAL(5,2) CHECK (carbs_per_serving >= 0),
    fat_per_serving DECIMAL(5,2) CHECK (fat_per_serving >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_drink BOOLEAN DEFAULT FALSE,
    cuisine VARCHAR(30) DEFAULT 'any',
    dietary_mask INT DEFAULT 0,
    FOREIGN KEY (category_id) REFERENCES recipe_categories(category_id) ON DELETE SET NULL,
    INDEX idx_recipes_category (category_id),
    INDEX idx_recipes_difficulty (difficulty),
    INDEX idx_recipes_is_drink (is_drink)
);

CREATE TABLE IF NOT EXISTS recipe_ingredients (
    recipe_ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(8,3) NOT NULL CHECK (quantity > 0),
    unit VARCHAR(20) NOT NULL,
    notes VARCHAR(255),
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id) ON DELETE RESTRICT,
    UNIQUE KEY uk_recipe_ingredient (recipe_id, ingredient_id)
);

CREATE TABLE IF NOT EXISTS recipe_ratings (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recipe_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_recipe (user_id, recipe_id)
);

CREATE TABLE IF NOT EXISTS user_favorites (
    user_id INT NOT NULL,
    recipe_id INT NOT NULL,
    PRIMARY KEY (user_id, recipe_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS menus (
    menu_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_calories INT CHECK (total_calories >= 0),
    status VARCHAR(20) DEFAULT 'draft' CHECK (status IN ('draft','approved','completed')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT chk_period CHECK (period_end > period_start),
    INDEX idx_menu_user (user_id)
);

CREATE TABLE IF NOT EXISTS menu_recipes (
    menu_recipe_id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id INT NOT NULL,
    recipe_id INT NOT NULL,
    meal_type VARCHAR(20) NOT NULL CHECK (meal_type IN ('breakfast','lunch','dinner','snack')),
    day_number INT NOT NULL CHECK (day_number >= 1),
    serving_size INT DEFAULT 1 CHECK (serving_size >= 1),
    FOREIGN KEY (menu_id) REFERENCES menus(menu_id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id) ON DELETE RESTRICT,
    INDEX idx_menu_recipes_menu (menu_id)
);

CREATE TABLE IF NOT EXISTS product_stock (
    stock_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(8,3) NOT NULL CHECK (quantity >= 0),
    unit VARCHAR(20) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id) ON DELETE RESTRICT,
    UNIQUE KEY uk_user_ingredient (user_id, ingredient_id),
    INDEX idx_product_stock_user (user_id)
);

CREATE TABLE IF NOT EXISTS shopping_lists (
    list_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    menu_id INT,
    created_date DATE DEFAULT CURRENT_DATE,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active','completed')),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(menu_id) ON DELETE SET NULL,
    INDEX idx_shopping_list_user (user_id)
);

CREATE TABLE IF NOT EXISTS shopping_list_items (
    list_item_id INT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity_required DECIMAL(8,3) NOT NULL CHECK (quantity_required > 0),
    unit VARCHAR(20) NOT NULL,
    is_purchased BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (list_id) REFERENCES shopping_lists(list_id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id) ON DELETE RESTRICT,
    UNIQUE KEY uk_list_ingredient (list_id, ingredient_id)
);

UPDATE user_preferences_core SET dishes_per_meal = 1, drinks_per_meal = 1 WHERE dishes_per_meal IS NULL;


-- КАТЕГОРИИ РЕЦЕПТОВ 

INSERT IGNORE INTO recipe_categories (name) VALUES
('Завтраки'), ('Супы'), ('Салаты'), ('Основные блюда'), ('Десерты'), ('Напитки'), ('Закуски'), ('Выпечка'), ('Соусы');


SELECT 'База данных полностью создана' AS status;
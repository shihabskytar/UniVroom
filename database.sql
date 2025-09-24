-- UniVroom Database Schema
-- By Students, For Students

CREATE DATABASE IF NOT EXISTS univroom;
USE univroom;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'rider') DEFAULT 'user',
    verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    reset_token VARCHAR(255),
    reset_expires DATETIME,
    profile_image VARCHAR(255),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Riders table (extends users)
CREATE TABLE riders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_type ENUM('car', 'bike', 'rickshaw', 'cng') NOT NULL,
    brand VARCHAR(50),
    model VARCHAR(50),
    plate_no VARCHAR(20) NOT NULL,
    license_no VARCHAR(50),
    status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    is_online BOOLEAN DEFAULT FALSE,
    current_lat DECIMAL(10, 8),
    current_lng DECIMAL(11, 8),
    last_location_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rating DECIMAL(3, 2) DEFAULT 0.00,
    total_trips INT DEFAULT 0,
    total_earnings DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Rides table
CREATE TABLE rides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rider_id INT,
    pickup_address TEXT NOT NULL,
    pickup_lat DECIMAL(10, 8) NOT NULL,
    pickup_lng DECIMAL(11, 8) NOT NULL,
    dropoff_address TEXT NOT NULL,
    dropoff_lat DECIMAL(10, 8) NOT NULL,
    dropoff_lng DECIMAL(11, 8) NOT NULL,
    distance DECIMAL(8, 2) NOT NULL,
    duration INT, -- in minutes
    fare DECIMAL(8, 2) NOT NULL,
    discount_amount DECIMAL(8, 2) DEFAULT 0.00,
    final_fare DECIMAL(8, 2) NOT NULL,
    coupon_code VARCHAR(20),
    status ENUM('requested', 'accepted', 'picked_up', 'in_progress', 'completed', 'cancelled') DEFAULT 'requested',
    payment_method ENUM('cash', 'bkash', 'card') DEFAULT 'cash',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    rider_rating INT,
    user_rating INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL
);

-- Messages table (for chat)
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Product categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT DEFAULT 0,
    category_id INT,
    images TEXT, -- JSON array of image paths
    status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
    views INT DEFAULT 0,
    sales_count INT DEFAULT 0,
    rating DECIMAL(3, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    final_amount DECIMAL(10, 2) NOT NULL,
    coupon_code VARCHAR(20),
    payment_method ENUM('cod', 'bkash', 'card') DEFAULT 'cod',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Product reviews
CREATE TABLE product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

-- Coupons table
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(8, 2) NOT NULL,
    minimum_amount DECIMAL(8, 2) DEFAULT 0.00,
    maximum_discount DECIMAL(8, 2),
    usage_limit INT,
    used_count INT DEFAULT 0,
    applies_to ENUM('rides', 'products', 'both') DEFAULT 'both',
    active BOOLEAN DEFAULT TRUE,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Announcements table
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'danger') DEFAULT 'info',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admins table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'editor') DEFAULT 'editor',
    name VARCHAR(100),
    email VARCHAR(100),
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('ride', 'order', 'system', 'announcement') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    data JSON, -- Additional data for the notification
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Settings table
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert demo data

-- Categories
INSERT INTO categories (name, description, icon) VALUES
('Electronics', 'Laptops, phones, gadgets', 'fas fa-laptop'),
('Books', 'Textbooks, novels, study materials', 'fas fa-book'),
('Clothing', 'Fashion, accessories', 'fas fa-tshirt'),
('Sports', 'Sports equipment, gym gear', 'fas fa-dumbbell'),
('Food', 'Snacks, beverages', 'fas fa-utensils'),
('Stationery', 'Pens, notebooks, supplies', 'fas fa-pen');

-- Demo users
INSERT INTO users (name, email, password_hash, role, verified, phone, address) VALUES
('John Doe', 'john@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', TRUE, '+8801234567890', 'Dhaka University, Dhaka'),
('Jane Smith', 'jane@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rider', TRUE, '+8801234567891', 'BUET, Dhaka'),
('Mike Wilson', 'mike@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rider', TRUE, '+8801234567892', 'NSU, Dhaka'),
('Sarah Johnson', 'sarah@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', TRUE, '+8801234567893', 'IUT, Gazipur');

-- Demo riders
INSERT INTO riders (user_id, vehicle_type, brand, model, plate_no, license_no, status, is_online, current_lat, current_lng, rating, total_trips) VALUES
(2, 'car', 'Toyota', 'Corolla', 'DHA-1234', 'DL123456', 'approved', TRUE, 23.8103, 90.4125, 4.5, 150),
(3, 'bike', 'Honda', 'CBR150R', 'DHA-5678', 'DL789012', 'approved', TRUE, 23.7956, 90.4054, 4.2, 89);

-- Demo products
INSERT INTO products (title, description, price, stock, category_id, images, views, sales_count, rating) VALUES
('MacBook Air M1', 'Excellent condition, barely used. Perfect for students.', 85000.00, 1, 1, '["products/macbook1.jpg", "products/macbook2.jpg"]', 45, 0, 0.00),
('iPhone 13', 'Good condition, all accessories included.', 65000.00, 1, 1, '["products/iphone1.jpg"]', 32, 1, 5.00),
('Calculus Textbook', 'Engineering Mathematics by Stroud, 7th Edition', 1200.00, 3, 2, '["products/calculus.jpg"]', 28, 5, 4.8),
('Nike Air Force 1', 'Size 42, white color, barely worn', 8500.00, 1, 3, '["products/nike1.jpg"]', 19, 0, 0.00),
('Gaming Mouse', 'Logitech G502 Hero, RGB lighting', 4500.00, 2, 1, '["products/mouse1.jpg"]', 15, 2, 4.5),
('Notebook Set', 'Pack of 5 ruled notebooks, 200 pages each', 350.00, 20, 6, '["products/notebooks.jpg"]', 67, 12, 4.2);

-- Demo coupons
INSERT INTO coupons (code, description, discount_type, discount_value, minimum_amount, usage_limit, applies_to, expires_at) VALUES
('STUDENT10', '10% off for students', 'percentage', 10.00, 100.00, 100, 'both', '2024-12-31 23:59:59'),
('RIDE20', '20 BDT off on rides', 'fixed', 20.00, 50.00, 50, 'rides', '2024-12-31 23:59:59'),
('NEWUSER', '15% off for new users', 'percentage', 15.00, 200.00, 200, 'products', '2024-12-31 23:59:59');

-- Demo announcements
INSERT INTO announcements (title, content, type) VALUES
('Welcome to UniVroom!', 'Your one-stop platform for rides and marketplace. Safe travels and happy shopping!', 'success'),
('New Safety Guidelines', 'Please follow COVID-19 safety protocols during rides. Wear masks and maintain distance.', 'warning'),
('Marketplace Launch', 'Our new marketplace is now live! Buy and sell items with fellow students.', 'info');

-- Demo admin
INSERT INTO admins (username, password_hash, role, name, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'Admin User', 'admin@univroom.com');

-- Demo settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_maintenance', '0', 'Site maintenance mode (0=off, 1=on)'),
('ride_booking_enabled', '1', 'Enable ride booking (0=off, 1=on)'),
('marketplace_enabled', '1', 'Enable marketplace (0=off, 1=on)'),
('registration_enabled', '1', 'Enable user registration (0=off, 1=on)'),
('tax_rate', '0.00', 'Tax rate percentage for orders'),
('currency_symbol', 'BDT', 'Currency symbol');

-- Demo orders
INSERT INTO orders (user_id, total_amount, final_amount, payment_method, status, shipping_address) VALUES
(1, 1200.00, 1200.00, 'cod', 'delivered', 'Dhaka University, Room 205, Curzon Hall'),
(4, 4500.00, 4500.00, 'bkash', 'processing', 'IUT, Gazipur, Hostel Block A');

-- Demo order items
INSERT INTO order_items (order_id, product_id, quantity, price, total) VALUES
(1, 3, 1, 1200.00, 1200.00),
(2, 5, 1, 4500.00, 4500.00);

-- Demo rides
INSERT INTO rides (user_id, rider_id, pickup_address, pickup_lat, pickup_lng, dropoff_address, dropoff_lat, dropoff_lng, distance, fare, final_fare, status) VALUES
(1, 1, 'Dhaka University', 23.7268, 90.3950, 'Dhanmondi 27', 23.7461, 90.3742, 5.2, 78.00, 78.00, 'completed'),
(4, 2, 'IUT Gazipur', 23.9808, 90.2267, 'Uttara Sector 7', 23.8759, 90.3795, 12.8, 192.00, 192.00, 'completed');

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_riders_user_id ON riders(user_id);
CREATE INDEX idx_riders_status ON riders(status);
CREATE INDEX idx_rides_user_id ON rides(user_id);
CREATE INDEX idx_rides_rider_id ON rides(rider_id);
CREATE INDEX idx_rides_status ON rides(status);
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_status ON products(status);
CREATE INDEX idx_orders_user_id ON orders(user_id);
CREATE INDEX idx_messages_ride_id ON messages(ride_id);
CREATE INDEX idx_notifications_user_id ON notifications(user_id);

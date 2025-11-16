-- Create Database
CREATE DATABASE IF NOT EXISTS TICKETIX;
USE TICKETIX;

-- Drop tables if they already exist (in correct order to handle foreign key constraints)
DROP TABLE IF EXISTS BRANCH;
DROP TABLE IF EXISTS TICKET;
DROP TABLE IF EXISTS PAYMENT;
DROP TABLE IF EXISTS RESERVE_SEAT;
DROP TABLE IF EXISTS RESERVE;
DROP TABLE IF EXISTS SEAT;
DROP TABLE IF EXISTS MOVIE_SCHEDULE;
DROP TABLE IF EXISTS MOVIE;
DROP TABLE IF EXISTS USER_ACCOUNT;

-- 1️⃣ USER_ACCOUNT Table
CREATE TABLE USER_ACCOUNT(
    acc_id INT PRIMARY KEY AUTO_INCREMENT,
    firstName VARCHAR(50) NOT NULL,
    lastName VARCHAR(50) NOT NULL,
    contNo VARCHAR(12),
    email VARCHAR(50) UNIQUE NOT NULL,
    address VARCHAR(50),
    birthdate DATE,
    user_password VARCHAR(70),
    time_created DATETIME,
    user_status ENUM('online', 'offline') DEFAULT 'offline'
) ENGINE=InnoDB;

ALTER TABLE USER_ACCOUNT
ADD COLUMN reset_token_hash VARCHAR(64) NULL,
ADD COLUMN reset_token_expires_at DATETIME NULL;

ALTER TABLE USER_ACCOUNT ADD COLUMN role VARCHAR(50) DEFAULT 'user';
UPDATE USER_ACCOUNT 
SET role = 'admin' 
WHERE email = 'ticketix0@gmail.com';

USE TICKETIX;
CREATE TABLE IF NOT EXISTS BRANCH(
branch_id INT PRIMARY KEY auto_increment,
branch_name VARCHAR(100) NOT NULL,
branch_location VARCHAR(150),
contact_number VARCHAR (15)
) ENGINE=InnoDB;

INSERT INTO BRANCH (branch_name, branch_location, contact_number)
VALUES
('Light Residences', 'EDSA Cor Madison St., Brgy Barangka Ilaya, Mandaluyong City', '09171234567'),
('SM City Baguio', 'Luneta Hill, Upper Session Road, Baguio City', '09179876543'),
('SM City Marikina', 'Marcos Highway, Kalumpang, Marikina City NCR Second', '09171239876'),
('SM Aura Premier', 'McKinley Parkway, Bonifacio Global City, Taguig City', '09171239876'),
('SM Center Angono', 'E. Rodriguez Jr. Avenue, Angono, Rizal', '09171239876'),
('SM City Sta. Mesa', 'G. Araneta Ave., Sta. Mesa, Manila', '09171239876'),
('SM City Sto. Tomas', 'Poblacion, Sto. Tomas, Batangas', '09171239876'),
('SM Mall of Asia', 'Seaside Blvd., Pasay City', '09171239876'),
('SM Megacenter Cabanatuan', 'Brgy. Balintawak, Cabanatuan City, Nueva Ecija', '09171239876');

SELECT branch_id, branch_name FROM BRANCH;


-- 2️⃣ MOVIE Table
CREATE TABLE MOVIE(
    movie_show_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(50),
    genre VARCHAR(100),
    duration INT,
    rating VARCHAR(20),
    movie_descrp TEXT,
    image_poster VARCHAR(100),
    carousel_image VARCHAR(100),
    now_showing BOOLEAN DEFAULT FALSE,
    coming_soon BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB;
SELECT movie_show_id, title, now_showing FROM MOVIE;


-- 3️⃣ MOVIE_SCHEDULE Table
CREATE TABLE MOVIE_SCHEDULE(
    schedule_id INT PRIMARY KEY AUTO_INCREMENT,
    movie_show_id INT NOT NULL,
    show_date DATE,
    show_hour TIME,
    FOREIGN KEY (movie_show_id) REFERENCES MOVIE(movie_show_id)
) ENGINE=InnoDB;

-- NOTE: The INSERT statements below are OPTIONAL. 
-- When you add a movie from the admin dashboard and mark it as "Now Showing",
-- the system will automatically create schedules for ALL branches.
-- These manual inserts are only needed for initial setup or specific custom schedules.

INSERT INTO MOVIE_SCHEDULE (movie_show_id, show_date, show_hour, branch_id)
VALUES
-- Light Residences (branch_id = 1)
(22, CURDATE(), '12:00:00', 1),
(22, CURDATE(), '16:00:00', 1),
(23, CURDATE(), '14:00:00', 1),
(23, CURDATE(), '18:00:00', 1),

-- SM City Baguio (branch_id = 2)
(22, CURDATE(), '11:00:00', 2),
(22, CURDATE(), '15:00:00', 2),
(23, CURDATE(), '13:00:00', 2),
(23, CURDATE(), '17:00:00', 2),

-- SM City Marikina (branch_id = 3)
(22, CURDATE(), '12:30:00', 3),
(22, CURDATE(), '16:30:00', 3),
(23, CURDATE(), '14:30:00', 3),
(23, CURDATE(), '18:30:00', 3),

-- SM Aura Premier (branch_id = 4)
(22, CURDATE(), '11:15:00', 4), 
(22, CURDATE(), '15:15:00', 4),
(23, CURDATE(), '13:15:00', 4),
(23, CURDATE(), '17:15:00', 4),

-- SM Center Angono (branch_id = 5)
(22, CURDATE(), '12:00:00', 5),
(22, CURDATE(), '16:00:00', 5),
(23, CURDATE(), '14:00:00', 5),
(23, CURDATE(), '18:00:00', 5),

-- SM City Sta. Mesa (branch_id = 6)
(22, CURDATE(), '11:45:00', 6),
(22, CURDATE(), '15:45:00', 6),
(23, CURDATE(), '13:45:00', 6),
(23, CURDATE(), '17:45:00', 6),


-- SM City Sto. Tomas (branch_id = 7)
(22, CURDATE(), '12:30:00', 7),
(22, CURDATE(), '16:30:00', 7),
(23, CURDATE(), '14:30:00', 7),
(23, CURDATE(), '18:30:00', 7),

-- SM Mall of Asia (branch_id = 8)
(22, CURDATE(), '11:00:00', 8), 
(22, CURDATE(), '15:00:00', 8),
(23, CURDATE(), '13:00:00', 8),
(23, CURDATE(), '17:00:00', 8),

-- SM Megacenter Cabanatuan (branch_id = 9)
(22, CURDATE(), '12:15:00', 9), 
(22, CURDATE(), '16:15:00', 9),
(23, CURDATE(), '14:15:00', 9),
(23, CURDATE(), '18:15:00', 9);

ALTER TABLE MOVIE_SCHEDULE
ADD COLUMN branch_id INT NOT NULL;

ALTER TABLE MOVIE_SCHEDULE
ADD CONSTRAINT fk_branch FOREIGN KEY (branch_id) REFERENCES BRANCH(branch_id);

-- 4️⃣ SEAT Table
CREATE TABLE SEAT(
    seat_id INT PRIMARY KEY AUTO_INCREMENT,
    seat_number VARCHAR(10),
    seat_type ENUM('Regular','VIP') DEFAULT 'Regular',
    seat_price DECIMAL(10,2)
) ENGINE=InnoDB;

-- 5️⃣ RESERVE Table
CREATE TABLE RESERVE(
    reservation_id INT PRIMARY KEY AUTO_INCREMENT,
    acc_id INT,
    schedule_id INT,
    reserve_date DATETIME,
    ticket_amount INT,
    sum_price DECIMAL(10,2),
    FOREIGN KEY (acc_id) REFERENCES USER_ACCOUNT(acc_id),
    FOREIGN KEY (schedule_id) REFERENCES MOVIE_SCHEDULE(schedule_id)
) ENGINE=InnoDB;

ALTER TABLE RESERVE ADD COLUMN food_total DECIMAL(10,2) DEFAULT 0.00;


-- 6️⃣ RESERVE_SEAT Table
CREATE TABLE RESERVE_SEAT(
    reserve_seat_id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT,
    seat_id INT,
    FOREIGN KEY (reservation_id) REFERENCES RESERVE(reservation_id),
    FOREIGN KEY (seat_id) REFERENCES SEAT(seat_id)
) ENGINE=InnoDB;

-- 7️⃣ PAYMENT Table
CREATE TABLE PAYMENT(
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    reserve_id INT,
    payment_type ENUM('cash','credit','e-wallet'),
    amount_paid DECIMAL(10,2),
    payment_status ENUM('paid','pending','not-yet'),
    payment_date DATETIME,
    reference_number VARCHAR(100),
    FOREIGN KEY (reserve_id) REFERENCES RESERVE(reservation_id)
) ENGINE=InnoDB;

CREATE TABLE TICKET(
ticket_id INT PRIMARY KEY auto_increment,
reserve_id INT,
payment_id INT,
ticket_number VARCHAR(50),
date_issued DATETIME,
ticket_status ENUM('valid','cancelled','refunded'),
FOREIGN KEY (payment_id) REFERENCES PAYMENT(payment_id),
FOREIGN KEY (reserve_id) REFERENCES RESERVE(reservation_id)
);

ALTER TABLE TICKET
ADD COLUMN e_ticket_code VARCHAR(100) UNIQUE,
ADD COLUMN e_ticket_file VARCHAR(255);
ALTER TABLE TICKET MODIFY ticket_status ENUM('valid','cancelled','refunded');

CREATE TABLE FOOD (
food_id INT PRIMARY KEY AUTO_INCREMENT,
food_name VARCHAR(50) NOT NULL,
food_price DECIMAL(10,2) DEFAULT 0.00
) ENGINE=InnoDB;

ALTER TABLE FOOD ADD COLUMN image_path VARCHAR(255);
INSERT INTO FOOD(food_name, food_price, image_path) VALUES
('All-In-Combo', 199.00, 'images/all-in.png'),
('DogCola', 165.00, 'images/hotdog-coke.png'),
('Froke', 120.00, 'images/fries-coke.png'),
('Fries', 50.00, 'images/fries-solo.png'),
('Hotdog', 60.00, 'images/hotdog-solo.png'),
('Coke', 40.00, 'images/coke-solo.png'),
('Popcorn', 40.00, 'images/popcorn-solo.png');

CREATE TABLE TICKET_FOOD (
ticket_food_id INT PRIMARY KEY AUTO_INCREMENT,
ticket_id INT,
food_id INT ,
quantity INT DEFAULT 1,
FOREIGN KEY (ticket_id) REFERENCES TICKET(ticket_id),
FOREIGN KEY (food_id) REFERENCES FOOD(food_id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE MOVIE_SCHEDULE;
SET FOREIGN_KEY_CHECKS = 1;

DELETE FROM MOVIE_SCHEDULE;
TRUNCATE TABLE BRANCH;
TRUNCATE TABLE MOVIE_SCHEDULE;

SET SQL_SAFE_UPDATES = 0;

DELETE FROM MOVIE_SCHEDULE;  -- delete child rows first
DELETE FROM BRANCH;          -- then delete branches

SET SQL_SAFE_UPDATES = 1;    -- re-enable safe updates

SET SQL_SAFE_UPDATES = 0;
DELETE FROM BRANCH;
SET SQL_SAFE_UPDATES = 1;

SELECT branch_id, branch_name FROM BRANCH;
SELECT movie_show_id, title, now_showing FROM MOVIE;
DESCRIBE MOVIE_SCHEDULE;

SELECT * FROM BRANCH;
SELECT * FROM USER_ACCOUNT;
SELECT * FROM MOVIE;
SELECT * FROM MOVIE_SCHEDULE;
SELECT * FROM SEAT;
SELECT * FROM RESERVE;
SELECT * FROM RESERVE_SEAT;
SELECT * FROM PAYMENT;
SELECT * FROM TICKET;
SELECT * FROM FOOD;
SELECT * FROM TICKET_FOOD;
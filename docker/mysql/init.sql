-- docker/mysql/init.sql

CREATE TABLE IF NOT EXISTS `users` (
                                       `id` INT AUTO_INCREMENT PRIMARY KEY,
                                       `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `mobile_token` VARCHAR(255) NULL, -- Mobil token'ı saklamak için
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

-- Test için örnek bir kullanıcı ekleyelim.
-- Şifre: 'password123' (güvenli bir hash ile saklanmalı, bu sadece örnek)
-- Mobil Token: Bu, mobil uygulamanın üreteceği ve doğrulayacağımız token.
INSERT INTO `users` (`name`, `email`, `password_hash`, `mobile_token`)
VALUES
    ('Ali Veli', 'ali.veli@example.com', '$2y$10$...', 'VALID-MOBILE-TOKEN-FOR-ALI')
    ON DUPLICATE KEY UPDATE `name` = `name`; -- Eğer email zaten varsa ekleme
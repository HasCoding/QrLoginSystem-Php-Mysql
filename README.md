# QR Login System


## 🛠️ Gereksinimler

- Docker & Docker Compose
- Git

## 📦 Kurulum

### 1. Projeyi İndir

```bash
git clone https://github.com/YOUR_USERNAME/qr-login-system.git
cd qr-login-system
```

### 2. Environment Dosyasını Ayarla

`.env` dosyasını düzenle:

```bash
cp .env.example .env
```

`.env` içeriği:
```
MYSQL_ROOT_PASSWORD=rootpass
MYSQL_DATABASE=qr_login
MYSQL_USER=qr_user
MYSQL_PASSWORD=qr_pass
```

### 3. Docker ile Başlat

```bash
# Container'ları build et ve başlat
docker-compose up --build -d

# Logları takip et (opsiyonel)
docker-compose logs -f
```

### 4. Veritabanı Tablolarını Oluştur

MySQL'e bağlanıp tabloları oluştur:

```bash
# MySQL container'a bağlan
docker exec -it qr_mysql mysql -u root -p

# Şifre: rootpass
```

SQL komutlarını çalıştır:

```sql
USE qr_login;

-- Users tablosu
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    mobile_token VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- QR Sessions tablosu
CREATE TABLE qr_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(36) UNIQUE NOT NULL,
    user_id INT NULL,
    status ENUM('pending', 'validated', 'expired') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    validated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session_id (session_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
);

-- Test kullanıcısı ekle
INSERT INTO users (name, email, password_hash, mobile_token) 
VALUES ('Test User', 'test@example.com', 'hashed_password', 'test_mobile_token_123');

-- Otomatik temizlik event'i (opsiyonel)
DELIMITER //
CREATE EVENT IF NOT EXISTS cleanup_expired_qr_sessions
ON SCHEDULE EVERY 5 MINUTE
DO
BEGIN
    DELETE FROM qr_sessions 
    WHERE expires_at < NOW() AND status IN ('pending', 'expired');
END //
DELIMITER ;

-- Event scheduler'ı aktifleştir
SET GLOBAL event_scheduler = ON;
```

## 🌐 Kullanım

### Web Adresleri

- **Ana sayfa:** http://localhost:8080
- **API Base URL:** http://localhost:8080/api.php


## 📄 Lisans

MIT License

---

**Not:** Bu README'yi projenizin ihtiyaçlarına göre güncelleyebilirsiniz.
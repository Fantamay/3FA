CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    pin VARCHAR(255) NOT NULL,
    secret_question1 VARCHAR(255),
    secret_answer1 VARCHAR(255),
    secret_question2 VARCHAR(255),
    secret_answer2 VARCHAR(255),
    secret_question3 VARCHAR(255),
    secret_answer3 VARCHAR(255),
    attempts INT DEFAULT 0,
    blocked TINYINT(1) DEFAULT 0,
    blocked_until DATETIME DEFAULT NULL
);

CREATE TABLE otp_codes (
    user_id INT,
    code CHAR(6),
    expiration DATETIME,
    attempts INT DEFAULT 0,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    ip VARCHAR(45),
    date DATETIME,
    status VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    file_path VARCHAR(255),
    upload_date DATETIME,
    display_name VARCHAR(80) DEFAULT NULL,
    category VARCHAR(50) DEFAULT 'Autres',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE shared_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50),
    detail VARCHAR(255),
    ip VARCHAR(45),
    date DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Migrations si les tables existent déjà :
-- ALTER TABLE users ADD COLUMN blocked_until DATETIME DEFAULT NULL;
-- ALTER TABLE users MODIFY COLUMN pin VARCHAR(255) NOT NULL;
-- ALTER TABLE users ADD COLUMN secret_question1 VARCHAR(255);
-- ALTER TABLE users ADD COLUMN secret_answer1 VARCHAR(255);
-- ALTER TABLE users ADD COLUMN secret_question2 VARCHAR(255);
-- ALTER TABLE users ADD COLUMN secret_answer2 VARCHAR(255);
-- ALTER TABLE users ADD COLUMN secret_question3 VARCHAR(255);
-- ALTER TABLE users ADD COLUMN secret_answer3 VARCHAR(255);
-- ALTER TABLE files ADD COLUMN category VARCHAR(50) DEFAULT 'Autres';

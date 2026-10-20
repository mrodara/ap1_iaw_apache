CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message VARCHAR(150) NOT NULL
);
INSERT INTO messages (message) VALUES
    ('LAMP funciona: Apache, PHP y MySQL'),
    ('Estos datos vienen de MySQL');
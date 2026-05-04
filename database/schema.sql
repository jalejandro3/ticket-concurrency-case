CREATE TABLE IF NOT EXISTS events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    total_capacity INT NOT NULL,
    available_capacity INT NOT NULL,
    version INT NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS tickets (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    event_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    status ENUM('reserved', 'paid', 'expired', 'cancelled') NOT NULL,
    reserved_at TIMESTAMP NOT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (event_id) REFERENCES events(id)
);

-- Seed: Festival Estereo Picnic with 3000 tickets
INSERT INTO events (name, total_capacity, available_capacity)
VALUES ('Festival Estereo Picnic', 3000, 3000);

#
# NR Glam Squad Database Structure
# Version 1.1
# Date Created: 08/11/2018
# Date Last Edited: 09/11/2018
# Author: MF Softworks <mf@nygmarosebeauty.com>
#

CREATE SCHEMA IF NOT EXISTS nrglamsquad;
USE nrglamsquad;

CREATE TABLE IF NOT EXISTS nr_job_roles(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS nr_packages(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(255) NOT NULL UNIQUE,
    package_description TEXT,
    package_price DECIMAL(13,4) NOT NULL
);

CREATE TABLE IF NOT EXISTS nr_package_roles(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    package_id BIGINT NOT NULL,
    role_id BIGINT NOT NULL,
    role_amount_required SMALLINT,
    FOREIGN KEY (package_id) REFERENCES nr_packages(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES nr_job_roles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS  nr_clients(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    profile_photo TEXT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS nr_artists(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    profile_photo TEXT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    stripe_account_token VARCHAR(255) UNIQUE,
    bio TEXT
);

CREATE TABLE IF NOT EXISTS nr_artist_portfolios(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    photo TEXT NOT NULL,
    artist_id BIGINT NOT NULL,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS nr_artist_roles(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    artist_role BIGINT NOT NULL,
    artist_id BIGINT NOT NULL,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE,
    FOREIGN KEY (artist_role) REFERENCES nr_job_roles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS nr_client_fcm_tokens(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fcm_token VARCHAR(255) NOT NULL UNIQUE,
    client_id BIGINT NOT NULL,
    FOREIGN KEY (client_id) REFERENCES nr_clients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS nr_artist_fcm_tokens(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fcm_token VARCHAR(255) NOT NULL UNIQUE,
    artist_id BIGINT NOT NULL,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS nr_payment_cards(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    card_type VARCHAR(255) NOT NULL,
    card_last_digits SMALLINT NOT NULL,
    card_token VARCHAR(255) NOT NULL UNIQUE,
    client_id BIGINT NOT NULL,
    FOREIGN KEY (client_id) REFERENCES nr_clients(id)
);

CREATE TABLE IF NOT EXISTS nr_jobs(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_address TEXT NOT NULL,
    event_datetime DATETIME NOT NULL,
    event_package_id BIGINT NOT NULL,
    event_note TEXT,
    event_clients SMALLINT NOT NULL,
    client_id BIGINT NOT NULL,
    client_card_id BIGINT NOT NULL,
    FOREIGN KEY (event_package_id) REFERENCES nr_packages(id),
    FOREIGN KEY (client_id) REFERENCES nr_clients(id),
    FOREIGN KEY (client_card_id) REFERENCES nr_payment_cards(id)
);

CREATE TABLE IF NOT EXISTS nr_job_references(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_reference_photo TEXT NOT NULL,
    event_id BIGINT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS nr_artist_jobs(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    artist_id BIGINT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id),
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id)
);

CREATE TABLE IF NOT EXISTS nr_client_receipts(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_payment_amount DECIMAL(13,4) NOT NULL,
    event_id BIGINT NOT NULL,
    client_id BIGINT NOT NULL,
    client_card_id BIGINT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id),
    FOREIGN KEY (client_id) REFERENCES nr_clients(id),
    FOREIGN KEY (client_card_id) REFERENCES nr_payment_cards(id)
);

CREATE TABLE IF NOT EXISTS nr_artist_payments(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    artist_payment_amount DECIMAL(13,4) NOT NULL,
    event_id BIGINT NOT NULL,
    artist_id BIGINT NOT NULL,
    artist_stripe_account_token VARCHAR(255) NOT NULL,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id),
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id),
    FOREIGN KEY (artist_stripe_account_token) REFERENCES nr_artists(stripe_account_token)
);
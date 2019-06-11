#
# NR Glam Squad Database Structure
# Date Created: 08/11/2018
# Author: MF Softworks <mf@nygmarosebeauty.com>
#

# DEBUG: Remove DB and start fresh
DROP DATABASE nrglamsquad;

CREATE SCHEMA IF NOT EXISTS nrglamsquad;
USE nrglamsquad;

# Glam Squad Job Roles (MUA, Hair Stylist, Manicurist...)
CREATE TABLE IF NOT EXISTS nr_job_roles(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(255) NOT NULL UNIQUE
);

# Glam Squad Packages (Package A; One MUA One Stylist; 320.00...)
CREATE TABLE IF NOT EXISTS nr_packages(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(255) NOT NULL UNIQUE,
    package_description TEXT,
    package_price DECIMAL(13,4) NOT NULL
);

# Glam Squad Package Roles (1 (Package A); 1 (MUA); 1 (required))
CREATE TABLE IF NOT EXISTS nr_package_roles(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    package_id BIGINT NOT NULL,
    role_id BIGINT NOT NULL,
    role_amount_required SMALLINT,
    FOREIGN KEY (package_id) REFERENCES nr_packages(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES nr_job_roles(id) ON DELETE CASCADE
);

# Glam Squad Client Accounts
CREATE TABLE IF NOT EXISTS  nr_clients(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    profile_photo TEXT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    stripe_customer_id VARCHAR(255) UNIQUE,
    fcm_token VARCHAR(255) UNIQUE,
    twilio_sid VARCHAR(255) UNIQUE
);

# Glam Squad Forgot Password Client Keys (String (Unique Key); 2019-01-16 2:43:00 (Expiration time); 1 (User ID))
CREATE TABLE IF NOT EXISTS nr_client_forgot_password_key(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    unique_key VARCHAR(255) NOT NULL UNIQUE,
    expiration_date DATETIME NOT NULL,
    client_id BIGINT NOT NULL,
    FOREIGN KEY (client_id) REFERENCES nr_clients(id) ON DELETE CASCADE
);

# Glam Squad Artist Accounts
# FIXME: Removed UNIQUE from stripe_account_token for testing purposes
CREATE TABLE IF NOT EXISTS nr_artists(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    profile_photo TEXT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    bio TEXT,
    facebook VARCHAR(255),
    twitter VARCHAR(255),
    instagram VARCHAR(255),
    stripe_account_token VARCHAR(255),
    fcm_token VARCHAR(255) UNIQUE,
    role_id BIGINT,
    probation BIT,
    locked BIT,
    twilio_sid VARCHAR(255) UNIQUE,
    FOREIGN KEY (role_id) REFERENCES nr_job_roles(id) ON DELETE CASCADE
);

# Glam Squad Forgot Password Artist Keys (String (Unique Key); 2019-01-16 2:43:00 (Expiration time); 1 (User ID))
CREATE TABLE IF NOT EXISTS nr_artist_forgot_password_key(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    unique_key VARCHAR(255) NOT NULL UNIQUE,
    expiration_date DATETIME NOT NULL,
    artist_id BIGINT NOT NULL,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE
);

# Artist Locations
CREATE TABLE IF NOT EXISTS nr_artist_locations(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    loc_name VARCHAR(255) NOT NULL,
    loc_lat DECIMAL(9,6) NOT NULL,
    loc_lng DECIMAL(9,6) NOT NULL,
    artist_id BIGINT NOT NULL,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE
);

# Artist Portfolio References ('/srv/media/file.png' (photo); 1 (Artist))
CREATE TABLE IF NOT EXISTS nr_artist_portfolios(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    photo TEXT NOT NULL,
    artist_id BIGINT NOT NULL,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE
);

# Artist Reported Log (1 Client); 1 (Artist))
CREATE TABLE IF NOT EXISTS nr_artist_reports(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    artist_id BIGINT NOT NULL,
    client_id BIGINT NOT NULL,
    FOREIGN KEY (client_id) REFERENCES nr_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE
);

# Client Reported Log (1 Artist); 1 (Client))
CREATE TABLE IF NOT EXISTS nr_client_reports(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT NOT NULL,
    artist_id BIGINT NOT NULL,
    FOREIGN KEY (client_id) REFERENCES nr_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE
);

# Client FCM Topics
CREATE TABLE IF NOT EXISTS nr_client_fcm_topics(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fcm_topic VARCHAR(255) NOT NULL,
    client_id BIGINT NOT NULL,
    UNIQUE(fcm_topic, client_id),
    FOREIGN KEY (client_id) REFERENCES nr_clients(id) ON DELETE CASCADE
);

# Artist FCM Topics
CREATE TABLE IF NOT EXISTS nr_artist_fcm_topics(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fcm_topic VARCHAR(255) NOT NULL,
    artist_id BIGINT NOT NULL,
    UNIQUE(fcm_topic, artist_id),
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE
);

# Client payment cards (Visa; 0444; tok_12345; 1 (Client))
CREATE TABLE IF NOT EXISTS nr_payment_cards(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    card_type VARCHAR(255) NOT NULL,
    card_last_digits SMALLINT NOT NULL,
    card_token VARCHAR(255) NOT NULL UNIQUE,
    client_id BIGINT NOT NULL,
    FOREIGN KEY (client_id) REFERENCES nr_clients(id)
);

# Glam Squad Event (123 fake st; 22112018T09:11 (22/11/2018, 9:11); Event note for artists to read; 1 (Client); 1 (Client Card))
CREATE TABLE IF NOT EXISTS nr_jobs(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_address TEXT NOT NULL,
    event_lat DECIMAL(9,6) NOT NULL,
    event_lng DECIMAL(9,6) NOT NULL,
    event_datetime DATETIME NOT NULL,
    event_note TEXT,
    event_price DECIMAL(13,4) NOT NULL,
    client_id BIGINT NOT NULL,
    client_card_id BIGINT NOT NULL,
    FOREIGN KEY (client_id) REFERENCES nr_clients(id),
    FOREIGN KEY (client_card_id) REFERENCES nr_payment_cards(id)
);

# Glam Squad Event Reminders (1 (Event))
CREATE TABLE IF NOT EXISTS nr_job_reminders(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id) ON DELETE CASCADE
);

# Glam Squad Event Reminders (1 (Event))
CREATE TABLE IF NOT EXISTS nr_job_confirmation_reminders(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id) ON DELETE CASCADE
);

# Glam Squad Client Attendance (1 (Event), 1 (Client), 1 (Attendance))
CREATE TABLE IF NOT EXISTS nr_job_client_attendance(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    client_id BIGINT NOT NULL,
    attendance BIT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES nr_clients(id) ON DELETE CASCADE
);

# Glam Squad Artist Attendance (1 (Event), 1 (Artist), 1 (Attendance))
CREATE TABLE IF NOT EXISTS nr_job_artist_attendance(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    artist_id BIGINT NOT NULL,
    attendance BIT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE
);

# Glam Squad Event Packages (1 (MUA Package); 1 (Event))
CREATE TABLE IF NOT EXISTS nr_job_packages(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_package_id BIGINT NOT NULL,
    event_id BIGINT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (event_package_id) REFERENCES nr_packages(id) ON DELETE CASCADE
);

# Glam Squad Event Extra Hours Booked(1 (Event); 3 (Extra hours purchased))
CREATE TABLE IF NOT EXISTS nr_job_extra_hours(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_hours_booked TINYINT NOT NULL,
    event_id BIGINT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id) ON DELETE CASCADE
);

# Glam Squad Event References ('/srv/media/file.png'; 1 (Event))
CREATE TABLE IF NOT EXISTS nr_job_references(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_reference_photo TEXT NOT NULL,
    event_id BIGINT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id) ON DELETE CASCADE
);

# Glam Squad Artist Jobs (1 (Event); 1 (Artist))
CREATE TABLE IF NOT EXISTS nr_artist_jobs(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    artist_id BIGINT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE
);

# Glam Squad Client Ratings for Artists (1 (Client); 1 (Artist); 1 (Event); 4 (Rating))
CREATE TABLE IF NOT EXISTS nr_artist_ratings(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT NOT NULL,
    artist_id BIGINT NOT NULL,
    event_id BIGINT NOT NULL,
    rating TINYINT NOT NULL,
    FOREIGN KEY (client_id) REFERENCES nr_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id) ON DELETE CASCADE
);

# Glam Squad Artist Ratings for Clients (1 (Client); 1 (Artist); 1 (Event); 4 (Rating))
CREATE TABLE IF NOT EXISTS nr_client_ratings(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT NOT NULL,
    artist_id BIGINT NOT NULL,
    event_id BIGINT NOT NULL,
    rating TINYINT NOT NULL,
    FOREIGN KEY (client_id) REFERENCES nr_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES nr_jobs(id) ON DELETE CASCADE
);

# Glam Squad Event Receipt for Client (160.00; 1 (Event); 1 (Client); 1 (Card))
CREATE TABLE IF NOT EXISTS nr_client_receipts(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    payment_amount DECIMAL(13,4) NOT NULL,
    payment_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_id BIGINT NOT NULL,
    client_id BIGINT NOT NULL,
    client_card_id BIGINT NOT NULL,
    stripe_charge_id VARCHAR(255) NOT NULL,
    FOREIGN KEY (client_id) REFERENCES nr_clients(id),
    FOREIGN KEY (client_card_id) REFERENCES nr_payment_cards(id)
);

# Glam Squad Artist Payments for Events (80.00; 1 (Event); 1 (Artist); 1234 (Stripe Token))
CREATE TABLE IF NOT EXISTS nr_artist_payments(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    payment_amount DECIMAL(13,4) NOT NULL,
    payment_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_id BIGINT NOT NULL,
    artist_id BIGINT NOT NULL,
    artist_stripe_account VARCHAR(255) NOT NULL,
    stripe_transfer_id VARCHAR(255) NOT NULL,
    FOREIGN KEY (artist_id) REFERENCES nr_artists(id)
);

# MUA Role
INSERT INTO nr_job_roles(
    role_name
)
VALUES(
    "Makeup Artist"
);

# Hair Stylist Role
INSERT INTO nr_job_roles(
    role_name
)
VALUES(
    "Hair Stylist"
);

# 1 MUA Package
INSERT INTO nr_packages(
    package_name,
    package_description,
    package_price
)
VALUES(
    "Makeup Artist",
    "Get one makeup artist to elevate your face with 60 minutes of work.",
    150.00
);

# 1 Hair Stylist Package
INSERT INTO nr_packages(
    package_name,
    package_description,
    package_price
)
VALUES(
    "Hair Stylist",
    "Have a hair stylist to make your hair into magic with 60 minutes of work.",
    80.00
);

# MUA Extra Hours Package
INSERT INTO nr_packages(
    package_name,
    package_description,
    package_price
)
VALUES(
    "MUA Extension",
    "Have your makeup artist stay beyond 60 minutes at just $20 per hour.
    (Example: For a 6 hour event, enter 5 for extra hours and pay just $250 for a 6 hour day of makeup artistry all for you!)",
    20.00
);

# MUA Package(1) Requires Makeup Artist(1) Amount 1
INSERT INTO nr_package_roles(
    package_id,
    role_id,
    role_amount_required
)
VALUES(
    1,
    1,
    1
);

# Hair Stylist Package(2) Requires Hair Stylist(2) Amount 1
INSERT INTO nr_package_roles(
    package_id,
    role_id,
    role_amount_required
)
VALUES(
    2,
    2,
    1
);

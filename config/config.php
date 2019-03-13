<?php
// API: HMAC shared secrets
define("API_SECRET", '1GSqDjCYAXeBLuLLVBx3bXlpC5NKUPqC');
define("HMAC_ENABLED", true);
define("GIT_SECRET", "ZKeU8S6FXT4BSt7i0mM8VBdtmVch7t5c");

// Argon2 Password Hashing Config
define("ARGON_CONFIG", [
    "memory_cost" => 4096, 
    "time_cost" => 20,
    "threads" => 2
]);

// MySQL
define("DB_SERVER", "10.130.105.226");
define("DB_USER", "nrglamsquad");
define("DB_PASS", "Sch@@l12");
define("DB_NAME", "nrglamsquad");

// Redis
define("REDIS_HOST", "redis");
// Set Redis timeout of 43,200 seconds (12 hours)
define("REDIS_TIMEOUT", 43200);

// Server
define("SERVER_URL", "https://glam-squad-db.nygmarosebeauty.com");

// Path
define("MEDIA_PATH", "/srv/nr-glam-squad/media/");
define("FORGOT_PASSWORD_URI", "https://glam-squad-db.nygmarosebeauty.com/forgot-password");

// Mail
define("SMTP_HOST", "server120.web-hosting.com");
define("SMTP_USER", "it@nygmarosebeauty.com");
define("SMTP_PASS", "sch@@l12");
define("SMTP_PORT", 587);

// Distance
define("JOB_DISTANCE", 30);

// Enqueue Options
define("ENQUEUE_OPTIONS", [
    "host" => "rabbit",
    "port" => 5672,
    "vhost" => "/",
    "login" => "guest",
    "password" => "guest",
    "persisted" => false,
]);

// API: FCM Data
define("FCM_KEY", "AIzaSyAwxC-XPBcbfQxVzmHzwPNQCWCuM-TiAoc");
define('FCM_SENDER_ID', '427808297057');
define("FCM_NOTIFICATION_ENDPOINT", "https://fcm.googleapis.com/fcm/send");
define("FCM_GROUP_ENDPOINT", "https://fcm.googleapis.com/fcm/notification");

// Availability
define("COUNTRIES", ["SG"]);

// API: Stripe Constant
define("STRIPE_SECRET", "sk_test_ccu7Gl8YxOlksae8zncTMTiE");
define("STRIPE_PAYMENT_ENDPOINT", "https://api.stripe.com/v1/charges");

// Artist percentage calculation
define("ARTIST_PERCENTAGE", 0.85);

// API: Facebook Constants
define("FACEBOOK_APP_ID", "808819612792596");
define("FACEBOOK_APP_SECRET", "696d305cbda1a2fa1be85e1e9b5a4b79");
define("FACEBOOK_GRAPH", "v3.2");

// API: Twitter
define("TWITTER_ENDPOINT", "https://api.twitter.com/1.1/");
define("TWITTER_KEY", "tX0ckYm7Viea90cHKOZjZdwsI");
define("TWITTER_SECRET", "n2GKkqZEPbviDQIVnyXESxei3U8l9YPy4Vf34LXVhb6gqyCzvs");
define("TWITTER_OAUTH_KEY", "4888236412-vd0dxjLqJlQvae62vly60N86F6wGot7bIYMsrx6");
define("TWITTER_OAUTH_SECRET", "nGBU6vfYCARVOUEd4rvmKILiac9tS59zgZwX2iuOeTtt8");

// API: Google
define("GOOGLE_APP_ID", "427808297057-g8ganbj0pcpqcr7b07l0ip7po8n8bqqb.apps.googleusercontent.com");

// API: Twilio
define("TWILIO_ENABLED", true);
// LIVE ID
define("TWILIO_SID", "AC23a43087c0fb1f0e162fc9798ac15660");
define("TWILIO_TOKEN", "ec6bf3d37e528997dde65b1deaa5d1de");
// Chat API Key
define("TWILIO_API_KEY", "SKe49b0d8023b0794710948328017029db");
define("TWILIO_API_SECRET", "LkQpO1r2IiIYt9atyaODEeM45YKA8PMf");
// Chat Notification Key
define("TWILIO_NOTIFICATION_SID", "CR9d66d4e138611b01d337ed7eed3372d1");
// Chat Service
define("TWILIO_SERVICE_DEV_SID", "IS50f456227cd942459b35d2cdfc439224");

// API: DigitalOcean Spaces
define("SPACES_KEY", "IAMTTSH47DMAROYB7DHF");
define("SPACES_SECRET", "3MYGq5NGkybmSYJO97TXJVpzw1MyAXhypBMEiQ2w0fE");
define("SPACES_NAME", "glamsquad");
define("SPACES_REGION", "sgp1");
define("SPACES_ENDPOINT", "https://glamsquad.sgp1.digitaloceanspaces.com/");
define("SPACES_CDN", "https://glamsquad.sgp1.cdn.digitaloceanspaces.com/");
?>
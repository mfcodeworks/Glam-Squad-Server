<?php
// API: HMAC shared secrets
define("API_SECRET", '1GSqDjCYAXeBLuLLVBx3bXlpC5NKUPqC');
define("GIT_SECRET", "ZKeU8S6FXT4BSt7i0mM8VBdtmVch7t5c");

// MySQL constants
define("DB_SERVER", "nrglamsquad.czcoidkwv3pf.us-east-2.rds.amazonaws.com");
define("DB_USER", "nrglamsquad");
define("DB_PASS", "school12");
define("DB_NAME", "nrglamsquad");

// Path constants
define("MEDIA_PATH", "/srv/nr-glam-squad/media/");
define("MEDIA_URI", "https://glam-squad-img.nygmarosebeauty.com/");
define("RESMUSHIT", "http://api.resmush.it/ws.php?img=");
define("FORGOT_PASSWORD_URI", "https://glam-squad-db.nygmarosebeauty.com/forgot-password");

// Mail constants
define("SMTP_HOST", "server120.web-hosting.com");
define("SMTP_USER", "it@nygmarosebeauty.com");
define("SMTP_PASS", "sch@@l12");
define("SMTP_PORT", 587);

// Distance constants
define("JOB_DISTANCE", 30);

// API: FCM Data
define("FCM_KEY", "AIzaSyAwxC-XPBcbfQxVzmHzwPNQCWCuM-TiAoc");
define('FCM_SENDER_ID', '427808297057');
define("FCM_NOTIFICATION_ENDPOINT", "https://fcm.googleapis.com/fcm/send");
define("FCM_GROUP_ENDPOINT", "https://fcm.googleapis.com/fcm/notification");

// Availability constants
define("COUNTRIES", ["SG"]);

// API: Stripe Constant
define("STRIPE_SECRET", "sk_test_ccu7Gl8YxOlksae8zncTMTiE");
define("STRIPE_PAYMENT_ENDPOINT", "https://api.stripe.com/v1/charges");

// Artist percentage calculation
define("ARTIST_PERCENTAGE", 0.85);

// API: Facebook Constants
define("FACEBOOK_APP_ID", "2288922571142545");
define("FACEBOOK_APP_SECRET", "4cb69f8b3f59af76b0b021175deb0b68");
define("FACEBOOK_GRAPH", "v3.2");
?>
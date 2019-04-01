<!DOCTYPE html>
    <head>
        <!-- https://github.com/apache/cordova-plugin-whitelist/blob/master/README.md#content-security-policy -->
        <meta
            http-equiv="Content-Security-Policy"
            content="default-src * 'unsafe-inline' data: blob: gap:;
                script-src 'self' 'unsafe-eval' blob: data: https://glam-squad-db.nygmarosebeauty.com https://stackpath.bootstrapcdn.com https://code.jquery.com https://use.fontawesome.com https://cdnjs.cloudflare.com;
                connect-src * data: blob:;">
        <meta name="format-detection" content="telephone=no">
        <meta name="msapplication-tap-highlight" content="no">
        <meta name="viewport" content="user-scalable=no, initial-scale=1, maximum-scale=1, minimum-scale=1, width=device-width">
        <link rel="shortcut icon" href="/public/img/logo-white.png">
        <link rel='dns-prefetch' href='//glam-squad-db.nygmarosebeauty.com' />
        <title>NR Glam Squad</title>

        <!-- Page CSS -->
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.3/css/all.css" integrity="sha384-UHRtZLI+pbxtHCWp1t77Bi1L4ZtiqrqD80Kn4Z8NTSRyMA2Fd33n5dQ8lWUE00s/" crossorigin="anonymous">
        <link rel="stylesheet" type="text/css" href="/public/css/custom.css">
    </head>
    <body class='clr-dark'>

        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-black" data-role="header">
            <a class="navbar-brand" href="#"></a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Terms &amp; Conditions</a>
                    </li>
                </ul>
            </div>
        </nav>

        <?php
        // Paths
        define('PROJECT_ROOT', dirname(dirname(__FILE__)));
        define('PROJECT_CONFIG', PROJECT_ROOT . '/config/');
        define('PROJECT_INC', PROJECT_ROOT . '/src/');
        define('PROJECT_LIB', PROJECT_ROOT . '/vendor/');

        // Require classes
        require_once PROJECT_CONFIG . "config.php";
        require_once PROJECT_INC . "NRArtist.php";
        require_once PROJECT_INC . "NRClient.php";

        // Get lost password data
        function getData() {

            // Get lost password key & type
            $key = $_GET["key"];
            $type = $_GET["type"];

            // Get data linked to key
            $sql =
            "SELECT {$type}_id, expiration_date
                FROM nr_{$type}_forgot_password_key
                WHERE unique_key = \"$key\";";

            $r = runSQLQuery($sql);

            // Return data or false if not found
            if(!isset($r["data"])) {
                return false;
            } else {
                return $r["data"][0];
            }
        }

        // Get data
        $data = getData();
        $data["key"] = $_GET["key"];
        $data["type"] = $_GET["type"];

        // If key is invalid print the first block, else print the second
        if($data === false || $data["expiration_date"] < date("Y-m-d H:i:s") ) {  ?>

        <!-- Invalid Key Form -->
        <div class="container">
            <div class="col-lg-10 col-sm-10 col-xs 10" data-role="none" id="new-password-container">
                <p>
                    Invalid key, unable to reset password.
                    <br>
                    If you believe this is a mistake please email <a href="mailto:it@nygmarosebeauty.com">it@nygmarosebeauty.com</a>.
                </p>
            </div>
        </div>
        <!-- /Invalid Key Form -->

        <?php } else { ?>

        <!-- Reset Password Form -->
        <div class="container">
            <div class="col-lg-10 col-sm-10 col-xs 10" data-role="none" id="new-password-container">
                <form action='' id='new-password-form'>
                    <div class='form-group list-group-item clr-dark'>
                        <i class="fas fa-lock"></i> New Password
                        <input type='password' name='password' class='form-control input-dark' id='new-password'>
                    </div>
                    <div class='text-center list-group-item clr-dark'>
                        <button type='submit' id="btn-new-password" class='btn clr-primary btn-social' data-role="none" data-key="<?php echo $data["key"]; ?>" data-id="<?php echo $data["{$data["type"]}_id"]; ?>" data-type="<?php echo $data["type"]; ?>"><i class='fas fa-lock'></i> Submit</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- /Reset Password Form -->

        <?php } ?>

        <!-- Page Scripts -->
        <script src="https://code.jquery.com/jquery-3.3.1.min.js" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js" integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js"integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script>
        <script src="/public/js/password.js" type="text/javascript"></script>
    </body>
</html>
<?php
// Copy OUTSIDE public_html as .sbs-config.php; never commit actual credentials.
return [
    'site_url' => 'https://thetrenhailes.com/sbs/',
    'db_dsn' => 'mysql:host=localhost;dbname=YOUR_DATABASE;charset=utf8mb4',
    'db_user' => 'YOUR_DATABASE_USER',
    'db_password' => 'YOUR_DATABASE_PASSWORD',
    // Generate a unique random string of at least 32 characters, then remove after setup.
    'setup_key' => '',
];

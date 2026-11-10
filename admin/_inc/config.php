<?php
// Single admin account for /admin. To change the password, generate a new hash with:
//   php -r 'echo password_hash("NEW-PASSWORD", PASSWORD_BCRYPT, ["cost" => 12]);'
// and replace ADMIN_PASSWORD_HASH below. Never put the plain password in this file.
declare(strict_types=1);

const ADMIN_EMAIL = 'sofyanebarakat@gmail.com';
const ADMIN_PASSWORD_HASH = '$2y$12$uZ8cLVw0kRmqUA770qqqG.n9B2TTaF77hZO72oCCJ4ZkTR4YEvbRy';

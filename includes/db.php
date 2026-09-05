<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * Central Database Connection Wrapper
 */

require_once __DIR__ . '/functions.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDbConnection();
}

return $pdo;

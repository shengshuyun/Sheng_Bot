<?php
session_start();
require_once __DIR__ . '/数据库.php';

function isLoggedIn(): bool
{
    return isset($_SESSION['admin_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function checkInstalled(): void
{
    $db = new SQLiteDatabase();
    if (!$db->isInstalled()) {
        header('Location: install.php');
        exit;
    }
}

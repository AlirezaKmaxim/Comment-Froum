<?php
namespace MDCustomComments\Core;

defined( 'ABSPATH' ) || exit;

class Activator {
    public static function activate() {
        // ایجاد جداول و ساختارهای اولیه دیتابیس
        Installer::install();
    }
}

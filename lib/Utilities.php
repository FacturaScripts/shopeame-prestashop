<?php
declare(strict_types=1);

namespace PrestaShop\Module\Shopeame\Lib;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Utilities
{
    public static function generateApiKey(): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $key = '';
        $max = strlen($characters) - 1;
        for ($i = 0; $i < 32; $i++) {
            $key .= $characters[mt_rand(0, $max)];
        }
        return $key;
    }
}

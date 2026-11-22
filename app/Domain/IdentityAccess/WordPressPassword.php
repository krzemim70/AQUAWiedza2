<?php

namespace App\Domain\IdentityAccess;

/** Weryfikacja skrótów haseł WordPressa: phpass (`$P$`, `$H$`) oraz WP 6.8+ (`$wp$2y$`). Tylko do migracji, nowe hasła to bcrypt. */
class WordPressPassword
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function isLegacy(string $hash): bool
    {
        return str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$') || str_starts_with($hash, '$wp$');
    }

    public static function check(string $password, string $hash): bool
    {
        if (str_starts_with($hash, '$wp$')) {
            return password_verify(base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true)), substr($hash, 3));
        }

        return strlen($hash) === 34 && hash_equals($hash, self::phpass($password, $hash));
    }

    private static function phpass(string $password, string $setting): string
    {
        $rounds = strpos(self::ITOA64, $setting[3]);
        if ($rounds === false || $rounds < 7 || $rounds > 30) {
            return '*0';
        }
        $hash = hash('md5', substr($setting, 4, 8).$password, true);
        for ($i = 1 << $rounds; $i > 0; $i--) {
            $hash = hash('md5', $hash.$password, true);
        }

        return substr($setting, 0, 12).self::encode($hash);
    }

    private static function encode(string $input): string
    {
        $out = '';
        $i = 0;
        $count = strlen($input);
        do {
            $value = ord($input[$i++]);
            $out .= self::ITOA64[$value & 0x3F];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $out .= self::ITOA64[($value >> 6) & 0x3F];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $out .= self::ITOA64[($value >> 12) & 0x3F];
            if ($i++ >= $count) {
                break;
            }
            $out .= self::ITOA64[($value >> 18) & 0x3F];
        } while ($i < $count);

        return $out;
    }
}

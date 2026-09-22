<?php

namespace App\Exceptions;

use Exception;

class DiscordGuildMembershipException extends Exception
{
    public static function guildNotConfigured(): self
    {
        return new self('Discord guild belum dikonfigurasi.');
    }

    public static function notMember(): self
    {
        return new self('Akun Discord ini bukan anggota server PJJ Informatika.');
    }

    public static function verificationFailed(): self
    {
        return new self('Keanggotaan Discord tidak dapat diverifikasi. Silakan coba lagi.');
    }
}

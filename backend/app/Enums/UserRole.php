<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN_SISTEM = 'admin_sistem';
    case PENGELOLA_APLIKASI = 'pengelola_aplikasi';
    case ANALIS_DESAIN = 'analis_desain';
    case UNIT_KERJA = 'unit_kerja';
    case TIM_IMPLEMENTASI_APLIKASI = 'tim_implementasi_aplikasi';
    case DEVOPS_DEVELOPER = 'devops_developer';
    case TIM_UJI_KEAMANAN = 'tim_uji_keamanan';

    /**
     * @return list<string>
     */
    public static function allValues(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function implementationValues(): array
    {
        return [
            self::TIM_IMPLEMENTASI_APLIKASI->value,
            self::DEVOPS_DEVELOPER->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function aplikasiCreateValues(): array
    {
        return [
            self::PENGELOLA_APLIKASI->value,
            self::UNIT_KERJA->value,
        ];
    }
}

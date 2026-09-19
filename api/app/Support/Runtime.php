<?php

namespace App\Support;

/**
 * ¿Dónde se está ejecutando la app? Único punto de decisión de contexto:
 * 'web' (servidor/Docker), 'native-desktop' o 'native-mobile' (binario
 * NativePHP con BD embebida). Nunca se decide con env() sueltos.
 */
final class Runtime
{
    private const NATIVE_CONTEXTS = ['native-desktop', 'native-mobile'];

    /** Contexto configurado en tcms.runtime ('' si no hay nada). */
    public static function context(): string
    {
        return (string) config('tcms.runtime', 'web');
    }

    /**
     * Servidor central (Docker/Coolify o artisan serve para desarrollo web).
     * Cualquier valor desconocido/vacío se trata como web: es el fallo seguro
     * (el central es quien captura sync_log; tratar un typo como nativo
     * detendría la captura en silencio).
     */
    public static function isWeb(): bool
    {
        return ! in_array(self::context(), self::NATIVE_CONTEXTS, true);
    }

    public static function isDesktop(): bool
    {
        return self::context() === 'native-desktop';
    }

    public static function isMobile(): bool
    {
        return self::context() === 'native-mobile';
    }

    /** Cualquier binario NativePHP (escritorio o móvil). */
    public static function isNative(): bool
    {
        return ! self::isWeb();
    }
}

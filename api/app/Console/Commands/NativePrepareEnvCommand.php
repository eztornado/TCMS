<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Prepara y restaura el .env alrededor de un build NativePHP.
 *
 *  native:prepare-env          (prebuild)  respalda el .env de desarrollo en
 *                              .env.backup, instala el overlay .env.native y
 *                              estabiliza la APP_KEY del binario en
 *                              .env.native.build (gitignored, se reutiliza).
 *  native:prepare-env --restore (postbuild) devuelve el .env de desarrollo.
 *
 * Lo usa el script composer `build:native`; el postbuild de
 * config/nativephp.php ejecuta la restauración.
 */
#[Signature('native:prepare-env {--restore : Restaura el .env de desarrollo tras el build}')]
#[Description('Prepara (o restaura, con --restore) el .env para el build nativo')]
class NativePrepareEnvCommand extends Command
{
    private const DEV_BACKUP = '.env.backup';

    private const NATIVE_KEY_FILE = '.env.native.build';

    public function handle(): int
    {
        return $this->option('restore')
            ? $this->restore()
            : $this->prepare();
    }

    private function prepare(): int
    {
        $envPath = base_path('.env');

        // Respalda el entorno de desarrollo (solo si no es ya un entorno nativo
        // a medio restaurar): el postbuild lo devolverá a su sitio.
        if (is_file($envPath) && $this->envValue((string) file_get_contents($envPath), 'TCMS_RUNTIME') === '') {
            copy($envPath, base_path(self::DEV_BACKUP));
            $this->line('.env de desarrollo respaldado en '.self::DEV_BACKUP.'.');
        }

        // El .env nativo estable (APP_KEY propia del binario) se reutiliza
        // entre builds; si no existe, se parte de .env.native.
        if (is_file(base_path(self::NATIVE_KEY_FILE))) {
            copy(base_path(self::NATIVE_KEY_FILE), $envPath);
            $this->info('.env nativo reutilizado (APP_KEY estable).');

            return self::SUCCESS;
        }

        copy(base_path('.env.native'), $envPath);
        $this->call('key:generate', ['--force' => true]);
        copy($envPath, base_path(self::NATIVE_KEY_FILE));
        $this->info('APP_KEY nativa generada por primera vez.');

        return self::SUCCESS;
    }

    private function restore(): int
    {
        $backup = base_path(self::DEV_BACKUP);

        if (! is_file($backup)) {
            $this->line('Sin .env de desarrollo que restaurar.');

            return self::SUCCESS;
        }

        rename($backup, base_path('.env'));
        $this->info('.env de desarrollo restaurado.');

        return self::SUCCESS;
    }

    /** Valor de una clave del .env en disco (no de getenv). */
    private function envValue(string $env, string $key): string
    {
        if (! preg_match('/^'.$key.'="?(.*?)"?\s*$/m', $env, $matches)) {
            return '';
        }

        return trim($matches[1]);
    }
}

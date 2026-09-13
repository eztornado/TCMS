# Modules — código específico de cada proyecto

Este directorio contiene los **módulos de proyecto**. El CORE de TornadoCMS
vive en `app/`, `database/migrations` (core), `routes/` y `config/`; todo lo
que sea específico de un cliente/proyecto (tienda propia, integraciones,
contenido...) va aquí para que **actualizar el core no toque nunca tu código**.

## Crear un módulo nuevo

```bash
php artisan module:make MiProyecto
```

Genera `Modules/MiProyecto/` con su ServiceProvider, `Routes/api.php`,
config y migraciones propias (namespace `Modules\MiProyecto`).

## Convenciones

| Pieza | Ubicación | Nota |
|---|---|---|
| Providers | `Modules/MiProyecto/Providers/*ServiceProvider.php` | Auto-descubiertos por nwidart |
| Rutas API | `Modules/MiProyecto/Routes/api.php` | Prefijo sugerido: el slug del proyecto |
| Migraciones | `Modules/MiProyecto/Database/Migrations/` | Nunca edites las del core |
| Controladores | `Modules/MiProyecto/Http/Controllers/` | |
| Modelos | `Modules/MiProyecto/Models/` | Extiende `App\Models\BaseModel` para auditoría |
| Comandos | `Modules/MiProyecto/Console/` | Programación en el Provider con `Schedule::` |

## Qué va en un módulo y qué no

- **Sí**: entidades propias del proyecto, integraciones externas, lógica de
  negocio específica, seeds de contenido del cliente.
- **No**: cambios en el core (`app/`), hacks en controladores del core,
  editar migraciones core. Para extender sin tocar el core: custom models
  (contenido dinámico), settings, menús, o un módulo con modelos propios.

## Estado del módulo

`modules_statuses.json` (raíz del `api/`) permite habilitar/deshabilitar
módulos por entorno sin borrar código.

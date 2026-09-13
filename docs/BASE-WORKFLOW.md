# TCMS como base: crear proyectos y actualizar el core

Objetivo: **todos tus proyectos comparten este repositorio base** y pueden
recibir sus mejoras (`git pull`) sin que el código propio de cada proyecto
se vea afectado.

## El principio: separación core / proyecto

| Capa | Ubicación core | Ubicación proyecto |
|---|---|---|
| Backend | `api/app`, `api/routes`, `api/database/migrations`, `api/config` | `api/Modules/<Proyecto>` |
| Frontend panel | `front/src` | `front/src/custom` |
| Entidades de contenido | — | Custom Models desde `/content` (sin código) |
| Configuración | `config/tcms.php` | settings en BD (`/settings`) |

Regla de oro: **el proyecto nunca edita ficheros del core**. Todo lo que
necesites extender tiene un punto de enganche (módulo nwidart, rutas custom,
custom models, settings, menús en BD).

## 1. Crear un proyecto nuevo

```bash
# A) Desde GitHub (recomendado): usa este repo como "template"
#    o crea el repo nuevo y empuja la base:
git clone /ruta/a/TCMS mi-proyecto && cd mi-proyecto
git remote rename origin upstream              # upstream = base TCMS
git remote add origin git@github.com:tuorg/mi-proyecto.git
git push -u origin main

# B) Ya en marcha: añade la base como upstream
git remote add upstream git@github.com:tuorg/TCMS.git
```

## 2. Añadir tu código

**Backend** — un módulo por proyecto:

```bash
cd api
php artisan module:make MiProyecto
```

- Modelos en `Modules/MiProyecto/Models/` (extiende `App\Models\BaseModel`
  para auditoría automática).
- Rutas en `Modules/MiProyecto/Routes/api.php`.
- Migraciones propias en `Modules/MiProyecto/Database/Migrations/`.
- Seed de contenido del cliente en el ServiceProvider del módulo.

**Front** — rutas y páginas propias:

```
front/src/custom/routes/mi-proyecto.tsx   # manifiesto de rutas
front/src/custom/pages/...                # páginas
front/src/custom/services/...             # clientes de API propios
```

Las rutas de `custom/routes/*.tsx` se inyectan solas en el layout del panel
(con guard de permiso opcional). El menú se configura en la BD (tabla
`menus`/`menu_items`) o con un seed del módulo — el panel lo pinta filtrado.

**Contenido dinámico** — si la entidad es simple (noticias, FAQ, banners,
patrocinadores...), NO programes nada: crea un Custom Model en
`/content` del panel. Tendrás CRUD, permisos (`list-cm-...`), taxonomías,
estados editoriales y API pública (`/api/cm/{modelo}`) sin escribir código.

## 3. Recibir mejoras del core

```bash
git fetch upstream
git merge upstream/main          # o rebase si prefieres historia lineal
composer install && npm install  # si cambiaron dependencias
php artisan test                 # 49 tests del core deben seguir verdes
git push                         # Coolify redespliega
```

Como tu código vive en `Modules/` y `custom/`, el merge solo trae ficheros
del core: los conflictos son raros y, cuando los hay, son de los tuyos.

## 4. Personalizar el core SIN romperlo

Si necesitas cambiar el comportamiento del core para un solo proyecto:

1. **Primera opción: extensión.** ¿El core ya lo contempla (settings,
   custom models, enums configurables, pasarela `PaymentGateway`)? Úsalo.
2. **Segunda opción: contribución.** Si la mejora es genérica, hazla en el
   core y propón el merge aguas arriba: todos los proyectos la heredan.
3. **Última opción: override en el módulo** (service container, routes con
   mayor prioridad, observers). Documenta el porqué en el módulo.

## 5. Checklist de un proyecto nuevo

- [ ] Repo creado desde la base, remotes `origin` (proyecto) + `upstream` (base)
- [ ] Módulo `api/Modules/<Proyecto>` creado y habilitado
- [ ] Custom models definidos desde el panel (si aplican)
- [ ] Menú del panel con las entradas del proyecto (BD, con `permission`)
- [ ] Roles del proyecto creados desde `/roles` (el admin ya los ve todos)
- [ ] `.env` de Coolify: `APP_KEY`, dominios Sanctum, pasarela, mail
- [ ] `php artisan test` verde antes del primer push

# Viña Santa Carolina – Sistema de Gestión y Trazabilidad

## Estado actual (antes de push)

- **Procedimientos PL/SQL**: todos los objetos PL/SQL están definidos como procedimientos/funciones independientes. No queda ningún `PACKAGE` ni referencias a `CA_EA_ST_IY_PKG_VINA` en el código PHP.
- **Helper PHP**: se añadió `consultar_cursor()` en `db.php` para manejar los cursor retornados por los procedimientos.
- **Procedimientos de listados**: `CA_EA_ST_IY_SP_CRUD_LISTAR_CARGOS` y `CA_EA_ST_IY_SP_CRUD_LISTAR_PERSONAL` existen y están **VALID**.
- **Reporte de auditoría**: el procedimiento `CA_EA_ST_IY_SP_REPORTE_AUDITORIA` está creado y válido; `reporte.php` lo usa vía `consultar_cursor()`.
- **PHP**: `php -l` no devuelve errores en ninguno de los archivos.
- **Servidor dev**: el servidor PHP está corriendo en `http://0.0.0.0:8085`.

## Cómo levantar la aplicación

```bash
cd "${HOME}/Documentos/Base de datos/Base-De-Datos-Proyecto-Final/Codigos"
# (Re)iniciar servidor PHP si está detenido
php -S 0.0.0.0:8085
```

Acceder a `http://localhost:8085/index.php` para iniciar sesión.

## Notas

- Se mantienen algunos `SELECT` directos en `reporte.php` solo para la auditoría; están documentados en el código.
- No se usan paquetes ni tipos complejos; la lógica sigue el estilo de las clases de la materia.
- Los cambios todavía no están *commiteados* ni *pusheados*; revisa con `git status` y haz `git commit` cuando estés listo.

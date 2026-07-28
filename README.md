# Verif-Carné

Primera versión funcional del portal de la Dirección Nacional de Recursos Humanos para el levantamiento y validación de carnés institucionales.

## Funciones incluidas

- Formulario público sin usuario ni contraseña.
- Posición y cédula únicas: no se permite una segunda solicitud con ninguno de esos identificadores.
- Datos personales, rango, promoción y ubicación institucional.
- Estructura de ubicación: Dirección Nacional, Zona, Área y Servicio/Dependencia.
- Foto frontal del carné.
- Foto posterior del carné.
- Foto de la persona sosteniendo el carné.
- Registro del código de barras como parte del levantamiento inicial.
- Generación de número de solicitud aleatorio.
- Bloqueo del formulario después del envío.
- Consulta pública del estado con número de solicitud y últimos cuatro dígitos de la cédula.
- Panel administrativo básico para revisar evidencias y cambiar estados.
- Enlaces directos a WhatsApp y correo.
- Correo automático de recepción cuando el servidor tiene configurado `mail()`.

## Requisitos

- PHP 8.1 o superior.
- Extensión PDO SQLite habilitada.
- Servidor Apache/XAMPP o equivalente.

## Instalación

1. Copiar el proyecto dentro de `htdocs/verif-cate`.
2. Crear la carpeta `storage/uploads` y conceder permisos de escritura al servidor web.
3. Abrir `http://localhost/verif-cate/`.
4. La base SQLite se crea automáticamente en `storage/verif-cate.sqlite`.
5. El panel administrativo se abre desde `admin.php`.

## Acceso administrativo inicial

- Usuario: `admin`
- Contraseña: `Cambiar123!`

Cambiar inmediatamente estas credenciales en `config.php` antes de publicar el sistema.

## Logo institucional

Colocar el archivo oficial proporcionado en:

`assets/img/logo.png`

El diseño ya está preparado para mostrarlo en el encabezado. Mientras no exista el archivo, se muestra un emblema temporal.

## Producción

Antes de publicar:

- Cambiar credenciales administrativas.
- Configurar HTTPS.
- Mover los archivos de evidencia fuera del directorio público o servirlos mediante control de acceso.
- Configurar SMTP en lugar de depender de `mail()`.
- Agregar CAPTCHA y limitación de intentos.
- Definir política de conservación y eliminación de fotografías.
- Migrar a MySQL si será integrado con el sistema institucional de Recursos Humanos.

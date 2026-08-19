<?php

declare(strict_types=1);

/**
 * Conexión PDO compartida por todo el sistema.
 *
 * Se mantienen el nombre del archivo y su ruta
 * para no afectar los require_once existentes.
 */

date_default_timezone_set(
    'America/Mexico_City'
);

/*
|--------------------------------------------------------------------------
| Evitar acceso directo desde el navegador
|--------------------------------------------------------------------------
*/

if (
    isset($_SERVER['SCRIPT_FILENAME'])
    && realpath(
        (string) $_SERVER['SCRIPT_FILENAME']
    ) === __FILE__
) {
    http_response_code(404);
    exit;
}

/*
|--------------------------------------------------------------------------
| Configuración de conexión
|--------------------------------------------------------------------------
|
| Por ahora se mantienen los datos actuales para que el sistema
| continúe funcionando sin cambiar su instalación.
|
| En el futuro podrán configurarse mediante variables de entorno:
|
| SM_DB_HOST
| SM_DB_PORT 
| SM_DB_NAME
| SM_DB_USER
| SM_DB_PASSWORD
|
*/

$host = getenv('SM_DB_HOST')
    ?: 'localhost';

$puerto = getenv('SM_DB_PORT')
    ?: '3306';

$dbname = getenv('SM_DB_NAME')
    ?: 'TU_BASE_DE_DATOS_AQUI';

$user = getenv('SM_DB_USER')
    ?: 'TU_USUARIO_AQUI';

$passwordEntorno = getenv(
    'SM_DB_PASSWORD'
);

$password = $passwordEntorno !== false
    ? $passwordEntorno
    : 'TU_CONTRASEÑA_AQUI';

$charset = 'utf8mb4';

$conexion = null;
$error_conexion = null;

/*
|--------------------------------------------------------------------------
| Crear conexión PDO
|--------------------------------------------------------------------------
*/

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $host,
        $puerto,
        $dbname,
        $charset
    );

    $conexion = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES =>
                false,

            PDO::ATTR_STRINGIFY_FETCHES =>
                false,

            PDO::ATTR_PERSISTENT =>
                false,
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | Zona horaria de MySQL
    |--------------------------------------------------------------------------
    */

    $offset = (
        new DateTimeImmutable(
            'now',
            new DateTimeZone(
                'America/Mexico_City'
            )
        )
    )->format('P');

    $conexion->exec(
        'SET time_zone = '
        . $conexion->quote($offset)
    );

} catch (PDOException $e) {
    $conexion = null;

    $error_conexion =
        'No fue posible establecer conexión '
        . 'con la base de datos.';

    /*
    |--------------------------------------------------------------------------
    | Registrar el error real, pero no mostrárselo al usuario
    |--------------------------------------------------------------------------
    */

    error_log(
        '[CONEXIÓN BD] '
        . $e->getMessage()
    );
}
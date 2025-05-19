<?php
// api/usuarios.php

require_once '../config/db.php'; // Asegúrate que la ruta a db.php sea correcta

header('Content-Type: application/json');

$accion = isset($_REQUEST['accion']) ? $_REQUEST['accion'] : '';
$metodo = $_SERVER['REQUEST_METHOD'];
$db = conectarDB();

if (!$db) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos.']);
    http_response_code(500);
    exit;
}

switch ($metodo) {
    case 'GET':
        if ($accion == 'leer') {
            // No seleccionamos la contraseña por seguridad
            $sql = "SELECT id, nombre, apellido, usuario, email, rol, estado, ultimo_login, fecha_registro, fecha_modificacion 
                    FROM usuarios 
                    ORDER BY apellido ASC, nombre ASC";
            $resultado = $db->query($sql);
            $usuarios = [];
            if ($resultado) {
                while ($fila = $resultado->fetch_assoc()) {
                    $usuarios[] = $fila;
                }
                $resultado->free();
                echo json_encode(['success' => true, 'data' => $usuarios]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al obtener usuarios: ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'leer_uno') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                // No seleccionamos la contraseña por seguridad
                $sql = "SELECT id, nombre, apellido, usuario, email, rol, estado FROM usuarios WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $resultado = $stmt->get_result();
                if ($usuario = $resultado->fetch_assoc()) {
                    echo json_encode(['success' => true, 'data' => $usuario]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado.']);
                    http_response_code(404);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de usuario no válido.']);
                http_response_code(400);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Acción GET no válida para usuarios.']);
            http_response_code(400);
        }
        break;

    case 'POST':
        $datosEntrada = json_decode(file_get_contents('php://input'), true);

        if ($accion == 'crear') {
            if (empty($datosEntrada['nombre']) || empty($datosEntrada['apellido']) || empty($datosEntrada['usuario']) || empty($datosEntrada['email']) || empty($datosEntrada['password']) || !isset($datosEntrada['rol'])) {
                echo json_encode(['success' => false, 'error' => 'Todos los campos marcados con * son requeridos.']);
                http_response_code(400);
                $db->close();
                exit;
            }

            // Validar email
            if (!filter_var($datosEntrada['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'El formato del email no es válido.']);
                http_response_code(400);
                $db->close();
                exit;
            }
            
            // Verificar si el usuario o email ya existen
            $usuario_check = $db->real_escape_string($datosEntrada['usuario']);
            $email_check = $db->real_escape_string($datosEntrada['email']);
            $sql_check = "SELECT id FROM usuarios WHERE usuario = ? OR email = ?";
            $stmt_check = $db->prepare($sql_check);
            $stmt_check->bind_param("ss", $usuario_check, $email_check);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => 'El nombre de usuario o el email ya están registrados.']);
                http_response_code(409); // Conflict
                $stmt_check->close();
                $db->close();
                exit;
            }
            $stmt_check->close();


            $nombre = $db->real_escape_string($datosEntrada['nombre']);
            $apellido = $db->real_escape_string($datosEntrada['apellido']);
            $usuario = $db->real_escape_string($datosEntrada['usuario']);
            $email = $db->real_escape_string($datosEntrada['email']);
            $password = password_hash($datosEntrada['password'], PASSWORD_DEFAULT); // Hashear contraseña
            $rol = (int)$datosEntrada['rol'];
            $estado = isset($datosEntrada['estado']) ? (int)$datosEntrada['estado'] : 1;

            $sql = "INSERT INTO usuarios (nombre, apellido, usuario, email, password, rol, estado, fecha_registro) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sssssis", $nombre, $apellido, $usuario, $email, $password, $rol, $estado);
                if ($stmt->execute()) {
                    $nuevoUsuarioId = $stmt->insert_id;
                    echo json_encode(['success' => true, 'message' => 'Usuario creado exitosamente.', 'id' => $nuevoUsuarioId]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al crear usuario: ' . $stmt->error]);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (crear usuario): ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'actualizar') {
            if (empty($datosEntrada['usuario_id']) || empty($datosEntrada['nombre']) || empty($datosEntrada['apellido']) || empty($datosEntrada['usuario']) || empty($datosEntrada['email']) || !isset($datosEntrada['rol'])) {
                echo json_encode(['success' => false, 'error' => 'Faltan campos requeridos para actualizar el usuario. ID, nombre, apellido, usuario, email y rol son necesarios.']);
                http_response_code(400);
                $db->close();
                exit;
            }
            
            $id = (int)$datosEntrada['usuario_id'];

            // Validar email
            if (!filter_var($datosEntrada['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'El formato del email no es válido.']);
                http_response_code(400);
                $db->close();
                exit;
            }

            // Verificar si el usuario o email ya existen para OTRO usuario
            $usuario_check = $db->real_escape_string($datosEntrada['usuario']);
            $email_check = $db->real_escape_string($datosEntrada['email']);
            $sql_check = "SELECT id FROM usuarios WHERE (usuario = ? OR email = ?) AND id != ?";
            $stmt_check = $db->prepare($sql_check);
            $stmt_check->bind_param("ssi", $usuario_check, $email_check, $id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => 'El nombre de usuario o el email ya están registrados para otro usuario.']);
                http_response_code(409); // Conflict
                $stmt_check->close();
                $db->close();
                exit;
            }
            $stmt_check->close();

            $nombre = $db->real_escape_string($datosEntrada['nombre']);
            $apellido = $db->real_escape_string($datosEntrada['apellido']);
            $usuario = $db->real_escape_string($datosEntrada['usuario']);
            $email = $db->real_escape_string($datosEntrada['email']);
            $rol = (int)$datosEntrada['rol'];
            $estado = isset($datosEntrada['estado']) ? (int)$datosEntrada['estado'] : 1;

            if (!empty($datosEntrada['password'])) {
                $password = password_hash($datosEntrada['password'], PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET 
                            nombre = ?, apellido = ?, usuario = ?, email = ?, password = ?, rol = ?, estado = ?, fecha_modificacion = NOW() 
                        WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("sssssiii", $nombre, $apellido, $usuario, $email, $password, $rol, $estado, $id);
            } else {
                // No actualizar contraseña si no se provee una nueva
                $sql = "UPDATE usuarios SET 
                            nombre = ?, apellido = ?, usuario = ?, email = ?, rol = ?, estado = ?, fecha_modificacion = NOW() 
                        WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("ssssiii", $nombre, $apellido, $usuario, $email, $rol, $estado, $id);
            }

            if ($stmt) {
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Usuario actualizado exitosamente.']);
                    } else {
                        echo json_encode(['success' => true, 'message' => 'Usuario actualizado (sin cambios detectados o ID no encontrado).']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al actualizar usuario: ' . $stmt->error]);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (actualizar usuario): ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'eliminar') {
            $id = isset($datosEntrada['id']) ? (int)$datosEntrada['id'] : 0;

            if ($id > 0) {
                // Evitar eliminar el usuario con ID 1 (admin principal) como medida de seguridad
                if ($id === 1) {
                    echo json_encode(['success' => false, 'error' => 'No se puede eliminar el administrador principal del sistema.']);
                    http_response_code(403); // Forbidden
                    $db->close();
                    exit;
                }

                // Considerar si el usuario tiene registros asociados en otras tablas (compras, ventas, caja, inventario_movimientos)
                // En este caso, las FK están ON DELETE NO ACTION o ON DELETE SET NULL para algunas.
                // Para simplificar, permitiremos la eliminación, pero en un sistema real, se debería verificar o cambiar el estado a inactivo.
                
                $sql = "DELETE FROM usuarios WHERE id = ?";
                $stmt = $db->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            echo json_encode(['success' => true, 'message' => 'Usuario eliminado exitosamente.']);
                        } else {
                            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado o ya eliminado.']);
                            http_response_code(404);
                        }
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Error al eliminar usuario: ' . $stmt->error . '. Verifique dependencias.']);
                        http_response_code(500);
                    }
                    $stmt->close();
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (eliminar usuario): ' . $db->error]);
                    http_response_code(500);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de usuario no válido para eliminar.']);
                http_response_code(400);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Acción POST no válida para usuarios.']);
            http_response_code(400);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Método HTTP no soportado para usuarios.']);
        http_response_code(405);
        break;
}

if ($db) {
    $db->close();
}
?>

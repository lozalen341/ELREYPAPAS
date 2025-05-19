<?php
// api/proveedores.php

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
            $sql = "SELECT id, nombre, contacto, telefono, email, direccion, cuit, estado, fecha_registro, fecha_modificacion 
                    FROM proveedores 
                    ORDER BY nombre ASC";
            $resultado = $db->query($sql);
            $proveedores = [];
            if ($resultado) {
                while ($fila = $resultado->fetch_assoc()) {
                    $proveedores[] = $fila;
                }
                $resultado->free();
                echo json_encode(['success' => true, 'data' => $proveedores]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al obtener proveedores: ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'leer_uno') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $sql = "SELECT id, nombre, contacto, telefono, email, direccion, cuit, estado FROM proveedores WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $resultado = $stmt->get_result();
                if ($proveedor = $resultado->fetch_assoc()) {
                    echo json_encode(['success' => true, 'data' => $proveedor]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Proveedor no encontrado.']);
                    http_response_code(404);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de proveedor no válido.']);
                http_response_code(400);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Acción GET no válida para proveedores.']);
            http_response_code(400);
        }
        break;

    case 'POST':
        $datosEntrada = json_decode(file_get_contents('php://input'), true);

        if ($accion == 'crear') {
            if (empty($datosEntrada['nombre'])) {
                echo json_encode(['success' => false, 'error' => 'El nombre del proveedor es requerido.']);
                http_response_code(400);
                $db->close();
                exit;
            }

            $nombre = $db->real_escape_string($datosEntrada['nombre']);
            $contacto = isset($datosEntrada['contacto']) ? $db->real_escape_string($datosEntrada['contacto']) : null;
            $telefono = isset($datosEntrada['telefono']) ? $db->real_escape_string($datosEntrada['telefono']) : null;
            $email = isset($datosEntrada['email']) ? $db->real_escape_string($datosEntrada['email']) : null;
            $direccion = isset($datosEntrada['direccion']) ? $db->real_escape_string($datosEntrada['direccion']) : null;
            $cuit = isset($datosEntrada['cuit']) ? $db->real_escape_string($datosEntrada['cuit']) : null;
            $estado = isset($datosEntrada['estado']) ? (int)$datosEntrada['estado'] : 1;

            $sql = "INSERT INTO proveedores (nombre, contacto, telefono, email, direccion, cuit, estado, fecha_registro) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssssi", $nombre, $contacto, $telefono, $email, $direccion, $cuit, $estado);
                if ($stmt->execute()) {
                    $nuevoProveedorId = $stmt->insert_id;
                    echo json_encode(['success' => true, 'message' => 'Proveedor creado exitosamente.', 'id' => $nuevoProveedorId]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al crear proveedor: ' . $stmt->error]);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (crear proveedor): ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'actualizar') {
            if (empty($datosEntrada['proveedor_id']) || empty($datosEntrada['nombre'])) {
                echo json_encode(['success' => false, 'error' => 'Faltan campos requeridos para actualizar el proveedor. ID y nombre son necesarios.']);
                http_response_code(400);
                $db->close();
                exit;
            }

            $id = (int)$datosEntrada['proveedor_id'];
            $nombre = $db->real_escape_string($datosEntrada['nombre']);
            $contacto = isset($datosEntrada['contacto']) ? $db->real_escape_string($datosEntrada['contacto']) : null;
            $telefono = isset($datosEntrada['telefono']) ? $db->real_escape_string($datosEntrada['telefono']) : null;
            $email = isset($datosEntrada['email']) ? $db->real_escape_string($datosEntrada['email']) : null;
            $direccion = isset($datosEntrada['direccion']) ? $db->real_escape_string($datosEntrada['direccion']) : null;
            $cuit = isset($datosEntrada['cuit']) ? $db->real_escape_string($datosEntrada['cuit']) : null;
            $estado = isset($datosEntrada['estado']) ? (int)$datosEntrada['estado'] : 1;

            $sql = "UPDATE proveedores SET 
                        nombre = ?, contacto = ?, telefono = ?, email = ?, direccion = ?, cuit = ?, estado = ?, fecha_modificacion = NOW() 
                    WHERE id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssssii", $nombre, $contacto, $telefono, $email, $direccion, $cuit, $estado, $id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Proveedor actualizado exitosamente.']);
                    } else {
                        echo json_encode(['success' => true, 'message' => 'Proveedor actualizado (sin cambios detectados o ID no encontrado).']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al actualizar proveedor: ' . $stmt->error]);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (actualizar proveedor): ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'eliminar') {
            $id = isset($datosEntrada['id']) ? (int)$datosEntrada['id'] : 0;

            if ($id > 0) {
                // La restricción FOREIGN KEY `productos_ibfk_2` tiene ON DELETE SET NULL.
                // Esto significa que si un proveedor es eliminado, el `proveedor_id` en la tabla `productos`
                // para los productos asociados a este proveedor se establecerá en NULL automáticamente.
                // Por lo tanto, no se necesita una verificación explícita aquí para bloquear la eliminación
                // si el proveedor está en uso, a menos que se desee un comportamiento diferente (por ejemplo, impedir la eliminación).

                $sql = "DELETE FROM proveedores WHERE id = ?";
                $stmt = $db->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            echo json_encode(['success' => true, 'message' => 'Proveedor eliminado exitosamente. Los productos asociados ya no tendrán este proveedor.']);
                        } else {
                            echo json_encode(['success' => false, 'error' => 'Proveedor no encontrado o ya eliminado.']);
                            http_response_code(404);
                        }
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Error al eliminar proveedor: ' . $stmt->error]);
                        http_response_code(500);
                    }
                    $stmt->close();
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (eliminar proveedor): ' . $db->error]);
                    http_response_code(500);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de proveedor no válido para eliminar.']);
                http_response_code(400);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Acción POST no válida para proveedores.']);
            http_response_code(400);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Método HTTP no soportado para proveedores.']);
        http_response_code(405);
        break;
}

if ($db) {
    $db->close();
}
?>

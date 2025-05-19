<?php
// api/clientes.php

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
            $sql = "SELECT id, nombre, apellido, documento, telefono, email, direccion, estado, fecha_registro, fecha_modificacion 
                    FROM clientes 
                    ORDER BY apellido ASC, nombre ASC";
            $resultado = $db->query($sql);
            $clientes = [];
            if ($resultado) {
                while ($fila = $resultado->fetch_assoc()) {
                    $clientes[] = $fila;
                }
                $resultado->free();
                echo json_encode(['success' => true, 'data' => $clientes]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al obtener clientes: ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'leer_uno') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $sql = "SELECT id, nombre, apellido, documento, telefono, email, direccion, estado FROM clientes WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $resultado = $stmt->get_result();
                if ($cliente = $resultado->fetch_assoc()) {
                    echo json_encode(['success' => true, 'data' => $cliente]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Cliente no encontrado.']);
                    http_response_code(404);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de cliente no válido.']);
                http_response_code(400);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Acción GET no válida para clientes.']);
            http_response_code(400);
        }
        break;

    case 'POST':
        $datosEntrada = json_decode(file_get_contents('php://input'), true);

        if ($accion == 'crear') {
            if (empty($datosEntrada['nombre']) || empty($datosEntrada['apellido'])) {
                echo json_encode(['success' => false, 'error' => 'El nombre y apellido del cliente son requeridos.']);
                http_response_code(400);
                $db->close();
                exit;
            }

            $nombre = $db->real_escape_string($datosEntrada['nombre']);
            $apellido = $db->real_escape_string($datosEntrada['apellido']);
            $documento = isset($datosEntrada['documento']) ? $db->real_escape_string($datosEntrada['documento']) : null;
            $telefono = isset($datosEntrada['telefono']) ? $db->real_escape_string($datosEntrada['telefono']) : null;
            $email = isset($datosEntrada['email']) ? $db->real_escape_string($datosEntrada['email']) : null;
            $direccion = isset($datosEntrada['direccion']) ? $db->real_escape_string($datosEntrada['direccion']) : null;
            $estado = isset($datosEntrada['estado']) ? (int)$datosEntrada['estado'] : 1;

            $sql = "INSERT INTO clientes (nombre, apellido, documento, telefono, email, direccion, estado, fecha_registro) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssssi", $nombre, $apellido, $documento, $telefono, $email, $direccion, $estado);
                if ($stmt->execute()) {
                    $nuevoClienteId = $stmt->insert_id;
                    echo json_encode(['success' => true, 'message' => 'Cliente creado exitosamente.', 'id' => $nuevoClienteId]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al crear cliente: ' . $stmt->error]);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (crear cliente): ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'actualizar') {
            if (empty($datosEntrada['cliente_id']) || empty($datosEntrada['nombre']) || empty($datosEntrada['apellido'])) {
                echo json_encode(['success' => false, 'error' => 'Faltan campos requeridos para actualizar el cliente. ID, nombre y apellido son necesarios.']);
                http_response_code(400);
                $db->close();
                exit;
            }

            $id = (int)$datosEntrada['cliente_id'];
            $nombre = $db->real_escape_string($datosEntrada['nombre']);
            $apellido = $db->real_escape_string($datosEntrada['apellido']);
            $documento = isset($datosEntrada['documento']) ? $db->real_escape_string($datosEntrada['documento']) : null;
            $telefono = isset($datosEntrada['telefono']) ? $db->real_escape_string($datosEntrada['telefono']) : null;
            $email = isset($datosEntrada['email']) ? $db->real_escape_string($datosEntrada['email']) : null;
            $direccion = isset($datosEntrada['direccion']) ? $db->real_escape_string($datosEntrada['direccion']) : null;
            $estado = isset($datosEntrada['estado']) ? (int)$datosEntrada['estado'] : 1;

            $sql = "UPDATE clientes SET 
                        nombre = ?, apellido = ?, documento = ?, telefono = ?, email = ?, direccion = ?, estado = ?, fecha_modificacion = NOW() 
                    WHERE id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssssii", $nombre, $apellido, $documento, $telefono, $email, $direccion, $estado, $id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Cliente actualizado exitosamente.']);
                    } else {
                        // No rows affected could mean the data was the same, or client ID not found
                        echo json_encode(['success' => true, 'message' => 'Cliente actualizado (sin cambios detectados o ID no encontrado).']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al actualizar cliente: ' . $stmt->error]);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (actualizar cliente): ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'eliminar') {
            $id = isset($datosEntrada['id']) ? (int)$datosEntrada['id'] : 0;

            if ($id > 0) {
                // La restricción FOREIGN KEY `ventas_ibfk_1` para la tabla `ventas` tiene ON DELETE SET NULL.
                // Esto significa que si un cliente es eliminado, el `cliente_id` en la tabla `ventas`
                // para las ventas asociadas a este cliente se establecerá en NULL automáticamente.
                // No se necesita una verificación explícita aquí para bloquear la eliminación.

                $sql = "DELETE FROM clientes WHERE id = ?";
                $stmt = $db->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            echo json_encode(['success' => true, 'message' => 'Cliente eliminado exitosamente. Las ventas asociadas ya no tendrán este cliente.']);
                        } else {
                            echo json_encode(['success' => false, 'error' => 'Cliente no encontrado o ya eliminado.']);
                            http_response_code(404);
                        }
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Error al eliminar cliente: ' . $stmt->error . '. Verifique si el cliente tiene ventas asociadas que impidan su eliminación directa si la restricción fuera ON DELETE NO ACTION o RESTRICT.']);
                        http_response_code(500);
                    }
                    $stmt->close();
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (eliminar cliente): ' . $db->error]);
                    http_response_code(500);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de cliente no válido para eliminar.']);
                http_response_code(400);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Acción POST no válida para clientes.']);
            http_response_code(400);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Método HTTP no soportado para clientes.']);
        http_response_code(405);
        break;
}

if ($db) {
    $db->close();
}
?>

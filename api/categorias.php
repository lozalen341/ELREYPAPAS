<?php
// api/categorias.php

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
            $sql = "SELECT id, nombre, descripcion, estado, fecha_registro, fecha_modificacion 
                    FROM categorias 
                    ORDER BY nombre ASC";
            $resultado = $db->query($sql);
            $categorias = [];
            if ($resultado) {
                while ($fila = $resultado->fetch_assoc()) {
                    $categorias[] = $fila;
                }
                $resultado->free();
                echo json_encode(['success' => true, 'data' => $categorias]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al obtener categorías: ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'leer_una') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $sql = "SELECT id, nombre, descripcion, estado FROM categorias WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $resultado = $stmt->get_result();
                if ($categoria = $resultado->fetch_assoc()) {
                    echo json_encode(['success' => true, 'data' => $categoria]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Categoría no encontrada.']);
                    http_response_code(404);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de categoría no válido.']);
                http_response_code(400);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Acción GET no válida para categorías.']);
            http_response_code(400);
        }
        break;

    case 'POST':
        $datosEntrada = json_decode(file_get_contents('php://input'), true);

        if ($accion == 'crear') {
            if (empty($datosEntrada['nombre'])) {
                echo json_encode(['success' => false, 'error' => 'El nombre de la categoría es requerido.']);
                http_response_code(400);
                $db->close();
                exit;
            }

            $nombre = $db->real_escape_string($datosEntrada['nombre']);
            $descripcion = isset($datosEntrada['descripcion']) ? $db->real_escape_string($datosEntrada['descripcion']) : null;
            $estado = isset($datosEntrada['estado']) ? (int)$datosEntrada['estado'] : 1;

            $sql = "INSERT INTO categorias (nombre, descripcion, estado, fecha_registro) 
                    VALUES (?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssi", $nombre, $descripcion, $estado);
                if ($stmt->execute()) {
                    $nuevaCategoriaId = $stmt->insert_id;
                    echo json_encode(['success' => true, 'message' => 'Categoría creada exitosamente.', 'id' => $nuevaCategoriaId]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al crear categoría: ' . $stmt->error]);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (crear categoría): ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'actualizar') {
            if (empty($datosEntrada['categoria_id']) || empty($datosEntrada['nombre'])) {
                echo json_encode(['success' => false, 'error' => 'Faltan campos requeridos para actualizar la categoría. ID y nombre son necesarios.']);
                http_response_code(400);
                $db->close();
                exit;
            }

            $id = (int)$datosEntrada['categoria_id'];
            $nombre = $db->real_escape_string($datosEntrada['nombre']);
            $descripcion = isset($datosEntrada['descripcion']) ? $db->real_escape_string($datosEntrada['descripcion']) : null;
            $estado = isset($datosEntrada['estado']) ? (int)$datosEntrada['estado'] : 1;

            $sql = "UPDATE categorias SET 
                        nombre = ?, descripcion = ?, estado = ?, fecha_modificacion = NOW() 
                    WHERE id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssii", $nombre, $descripcion, $estado, $id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Categoría actualizada exitosamente.']);
                    } else {
                        echo json_encode(['success' => true, 'message' => 'Categoría actualizada (sin cambios detectados o ID no encontrado).']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al actualizar categoría: ' . $stmt->error]);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (actualizar categoría): ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'eliminar') {
            $id = isset($datosEntrada['id']) ? (int)$datosEntrada['id'] : 0;

            if ($id > 0) {
                // Verificar si la categoría está siendo usada en la tabla 'productos'
                $sql_check = "SELECT COUNT(*) as count FROM productos WHERE categoria_id = ?";
                $stmt_check = $db->prepare($sql_check);
                $stmt_check->bind_param("i", $id);
                $stmt_check->execute();
                $res_check = $stmt_check->get_result()->fetch_assoc();
                $stmt_check->close();

                if ($res_check['count'] > 0) {
                    echo json_encode(['success' => false, 'error' => 'No se puede eliminar la categoría: está asignada a uno o más productos. Considere inactivarla o reasignar los productos.']);
                    http_response_code(409); // Conflict
                    $db->close();
                    exit;
                }

                // Si no hay productos asociados, proceder a eliminar
                $sql = "DELETE FROM categorias WHERE id = ?";
                $stmt = $db->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            echo json_encode(['success' => true, 'message' => 'Categoría eliminada exitosamente.']);
                        } else {
                            echo json_encode(['success' => false, 'error' => 'Categoría no encontrada o ya eliminada.']);
                            http_response_code(404);
                        }
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Error al eliminar categoría: ' . $stmt->error]);
                        http_response_code(500);
                    }
                    $stmt->close();
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (eliminar categoría): ' . $db->error]);
                    http_response_code(500);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de categoría no válido para eliminar.']);
                http_response_code(400);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Acción POST no válida para categorías.']);
            http_response_code(400);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Método HTTP no soportado para categorías.']);
        http_response_code(405);
        break;
}

if ($db) {
    $db->close();
}
?>

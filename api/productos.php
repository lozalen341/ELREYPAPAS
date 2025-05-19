<?php
// api/productos.php

require_once '../config/db.php';

header('Content-Type: application/json');

$accion = isset($_REQUEST['accion']) ? $_REQUEST['accion'] : ''; // Use $_REQUEST to catch GET or POST for accion
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
            $sql = "SELECT p.id, p.codigo, p.nombre, p.descripcion, p.precio_costo, p.precio_venta, p.stock, p.stock_minimo, c.nombre as categoria, pr.nombre as proveedor, p.imagen, p.estado, p.fecha_registro 
                    FROM productos p
                    LEFT JOIN categorias c ON p.categoria_id = c.id
                    LEFT JOIN proveedores pr ON p.proveedor_id = pr.id
                    ORDER BY p.nombre ASC";
            $resultado = $db->query($sql);
            $productos = [];
            if ($resultado) {
                while ($fila = $resultado->fetch_assoc()) {
                    $productos[] = $fila;
                }
                $resultado->free();
                echo json_encode(['success' => true, 'data' => $productos]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al obtener productos: ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'leer_uno') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $sql = "SELECT id, codigo, nombre, descripcion, precio_costo, precio_venta, stock, stock_minimo, categoria_id, proveedor_id, imagen, estado 
                        FROM productos 
                        WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $resultado = $stmt->get_result();
                if ($producto = $resultado->fetch_assoc()) {
                    echo json_encode(['success' => true, 'data' => $producto]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Producto no encontrado.']);
                    http_response_code(404);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de producto no válido.']);
                http_response_code(400);
            }
        } elseif ($accion == 'leer_select_data') {
            $categorias = [];
            $sql_cat = "SELECT id, nombre FROM categorias WHERE estado = 1 ORDER BY nombre ASC";
            $res_cat = $db->query($sql_cat);
            if ($res_cat) {
                while($row = $res_cat->fetch_assoc()) $categorias[] = $row;
                $res_cat->free();
            }

            $proveedores = [];
            $sql_prov = "SELECT id, nombre FROM proveedores WHERE estado = 1 ORDER BY nombre ASC";
            $res_prov = $db->query($sql_prov);
            if ($res_prov) {
                while($row = $res_prov->fetch_assoc()) $proveedores[] = $row;
                $res_prov->free();
            }
            echo json_encode(['success' => true, 'data' => ['categorias' => $categorias, 'proveedores' => $proveedores]]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Acción GET no válida.']);
            http_response_code(400);
        }
        break;

    case 'POST':
        $datosEntrada = json_decode(file_get_contents('php://input'), true);

        if ($accion == 'crear') {
            if (empty($datosEntrada['codigo']) || empty($datosEntrada['nombre']) || !isset($datosEntrada['precio_costo']) || !isset($datosEntrada['precio_venta']) || !isset($datosEntrada['stock'])) {
                echo json_encode(['success' => false, 'error' => 'Faltan campos requeridos para crear.']);
                http_response_code(400);
                $db->close();
                exit;
            }

            $codigo = $db->real_escape_string($datosEntrada['codigo']);
            $nombre = $db->real_escape_string($datosEntrada['nombre']);
            $descripcion = isset($datosEntrada['descripcion']) ? $db->real_escape_string($datosEntrada['descripcion']) : null;
            $precio_costo = (float)$datosEntrada['precio_costo'];
            $precio_venta = (float)$datosEntrada['precio_venta'];
            $stock = (int)$datosEntrada['stock'];
            $stock_minimo = isset($datosEntrada['stock_minimo']) ? (int)$datosEntrada['stock_minimo'] : 5;
            $categoria_id = isset($datosEntrada['categoria_id']) && !empty($datosEntrada['categoria_id']) ? (int)$datosEntrada['categoria_id'] : null;
            $proveedor_id = isset($datosEntrada['proveedor_id']) && !empty($datosEntrada['proveedor_id']) ? (int)$datosEntrada['proveedor_id'] : null;
            $imagen = isset($datosEntrada['imagen']) ? $db->real_escape_string($datosEntrada['imagen']) : null;
            $estado = isset($datosEntrada['estado']) ? (int)$datosEntrada['estado'] : 1;

            $sql = "INSERT INTO productos (codigo, nombre, descripcion, precio_costo, precio_venta, stock, stock_minimo, categoria_id, proveedor_id, imagen, estado, fecha_registro) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sssddiiissi", $codigo, $nombre, $descripcion, $precio_costo, $precio_venta, $stock, $stock_minimo, $categoria_id, $proveedor_id, $imagen, $estado);
                if ($stmt->execute()) {
                    $nuevoProductoId = $stmt->insert_id;
                    echo json_encode(['success' => true, 'message' => 'Producto creado exitosamente.', 'id' => $nuevoProductoId]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al crear producto: ' . $stmt->error]);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (crear): ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'actualizar') {
            if (empty($datosEntrada['producto_id']) || empty($datosEntrada['codigo']) || empty($datosEntrada['nombre']) || !isset($datosEntrada['precio_costo']) || !isset($datosEntrada['precio_venta']) || !isset($datosEntrada['stock'])) {
                echo json_encode(['success' => false, 'error' => 'Faltan campos requeridos para actualizar. ID de producto es necesario.']);
                http_response_code(400);
                $db->close();
                exit;
            }

            $id = (int)$datosEntrada['producto_id'];
            $codigo = $db->real_escape_string($datosEntrada['codigo']);
            $nombre = $db->real_escape_string($datosEntrada['nombre']);
            $descripcion = isset($datosEntrada['descripcion']) ? $db->real_escape_string($datosEntrada['descripcion']) : null;
            $precio_costo = (float)$datosEntrada['precio_costo'];
            $precio_venta = (float)$datosEntrada['precio_venta'];
            $stock = (int)$datosEntrada['stock'];
            $stock_minimo = isset($datosEntrada['stock_minimo']) ? (int)$datosEntrada['stock_minimo'] : 5;
            $categoria_id = isset($datosEntrada['categoria_id']) && !empty($datosEntrada['categoria_id']) ? (int)$datosEntrada['categoria_id'] : null;
            $proveedor_id = isset($datosEntrada['proveedor_id']) && !empty($datosEntrada['proveedor_id']) ? (int)$datosEntrada['proveedor_id'] : null;
            $imagen = isset($datosEntrada['imagen']) ? $db->real_escape_string($datosEntrada['imagen']) : null;
            $estado = isset($datosEntrada['estado']) ? (int)$datosEntrada['estado'] : 1;

            $sql = "UPDATE productos SET 
                        codigo = ?, nombre = ?, descripcion = ?, precio_costo = ?, precio_venta = ?, 
                        stock = ?, stock_minimo = ?, categoria_id = ?, proveedor_id = ?, imagen = ?, estado = ?,
                        fecha_modificacion = NOW() 
                    WHERE id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sssddiiissii", $codigo, $nombre, $descripcion, $precio_costo, $precio_venta, $stock, $stock_minimo, $categoria_id, $proveedor_id, $imagen, $estado, $id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Producto actualizado exitosamente.']);
                    } else {
                        // No rows affected could mean the data was the same, or product ID not found (though we don't explicitly check ID existence before update here)
                        echo json_encode(['success' => true, 'message' => 'Producto actualizado (sin cambios detectados o ID no encontrado).']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al actualizar producto: ' . $stmt->error]);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (actualizar): ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'eliminar') {
            // For DELETE, we might expect ID from query param or body. Let's assume body for consistency with JSON.
            $id = isset($datosEntrada['id']) ? (int)$datosEntrada['id'] : 0;
             if ($id <= 0 && isset($_GET['id'])) { // Fallback to GET param if not in body (less common for POST delete)
                $id = (int)$_GET['id'];
            }

            if ($id > 0) {
                // Consider foreign key constraints. If ON DELETE SET NULL/CASCADE is not set, this might fail.
                // For 'productos' table, 'compra_detalles' and 'venta_detalles' have ON DELETE NO ACTION.
                // This means you cannot delete a product if it's part of a compra_detalle or venta_detalle.
                // A better approach would be to "soft delete" (set estado=0) or check dependencies first.
                // For simplicity, we'll proceed with direct delete. Handle errors appropriately.

                // Check for dependencies in compra_detalles
                $sql_check_compra = "SELECT COUNT(*) as count FROM compra_detalles WHERE producto_id = ?";
                $stmt_check_compra = $db->prepare($sql_check_compra);
                $stmt_check_compra->bind_param("i", $id);
                $stmt_check_compra->execute();
                $res_compra = $stmt_check_compra->get_result()->fetch_assoc();
                $stmt_check_compra->close();

                if ($res_compra['count'] > 0) {
                    echo json_encode(['success' => false, 'error' => 'No se puede eliminar el producto: está referenciado en detalles de compras. Considere inactivarlo.']);
                    http_response_code(409); // Conflict
                    $db->close();
                    exit;
                }

                // Check for dependencies in venta_detalles
                $sql_check_venta = "SELECT COUNT(*) as count FROM venta_detalles WHERE producto_id = ?";
                $stmt_check_venta = $db->prepare($sql_check_venta);
                $stmt_check_venta->bind_param("i", $id);
                $stmt_check_venta->execute();
                $res_venta = $stmt_check_venta->get_result()->fetch_assoc();
                $stmt_check_venta->close();

                if ($res_venta['count'] > 0) {
                    echo json_encode(['success' => false, 'error' => 'No se puede eliminar el producto: está referenciado en detalles de ventas. Considere inactivarlo.']);
                    http_response_code(409); // Conflict
                    $db->close();
                    exit;
                }
                
                // Check for dependencies in inventario_movimientos
                $sql_check_inv = "SELECT COUNT(*) as count FROM inventario_movimientos WHERE producto_id = ?";
                $stmt_check_inv = $db->prepare($sql_check_inv);
                $stmt_check_inv->bind_param("i", $id);
                $stmt_check_inv->execute();
                $res_inv = $stmt_check_inv->get_result()->fetch_assoc();
                $stmt_check_inv->close();

                if ($res_inv['count'] > 0) {
                     echo json_encode(['success' => false, 'error' => 'No se puede eliminar el producto: tiene movimientos de inventario registrados. Considere inactivarlo.']);
                    http_response_code(409); // Conflict
                    $db->close();
                    exit;
                }


                $sql = "DELETE FROM productos WHERE id = ?";
                $stmt = $db->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            echo json_encode(['success' => true, 'message' => 'Producto eliminado exitosamente.']);
                        } else {
                            echo json_encode(['success' => false, 'error' => 'Producto no encontrado o ya eliminado.']);
                            http_response_code(404);
                        }
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Error al eliminar producto: ' . $stmt->error]);
                        http_response_code(500);
                    }
                    $stmt->close();
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (eliminar): ' . $db->error]);
                    http_response_code(500);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de producto no válido para eliminar.']);
                http_response_code(400);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Acción POST no válida.']);
            http_response_code(400);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Método HTTP no soportado.']);
        http_response_code(405);
        break;
}

if ($db) {
    $db->close();
}
?>

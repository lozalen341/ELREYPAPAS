<?php
// api/compras.php

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

// --- Funciones Auxiliares (si son necesarias) ---
// Por ejemplo, para obtener el ID de usuario (simulado por ahora)
function obtenerUsuarioIdActual() {
    // En una aplicación real, esto vendría de la sesión del usuario
    return 1; // Asumimos ID 1 (admin) para propósitos de desarrollo
}


switch ($metodo) {
    case 'GET':
        if ($accion == 'leer') {
            $sql = "SELECT c.id, c.numero_factura, p.nombre as proveedor_nombre, u.usuario as usuario_nombre, 
                           c.fecha_compra, c.subtotal, c.iva, c.total, c.estado, c.comentarios
                    FROM compras c
                    JOIN proveedores p ON c.proveedor_id = p.id
                    JOIN usuarios u ON c.usuario_id = u.id
                    ORDER BY c.fecha_compra DESC, c.id DESC";
            
            $resultado = $db->query($sql);
            $compras = [];
            if ($resultado) {
                while ($fila = $resultado->fetch_assoc()) {
                    $compras[] = $fila;
                }
                $resultado->free();
                echo json_encode(['success' => true, 'data' => $compras]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al obtener compras: ' . $db->error]);
                http_response_code(500);
            }
        } elseif ($accion == 'leer_una') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $compra = null;
                // Obtener encabezado de la compra
                $sql_compra = "SELECT c.id, c.numero_factura, c.proveedor_id, p.nombre as proveedor_nombre, 
                                      c.usuario_id, u.usuario as usuario_nombre, c.fecha_compra, 
                                      c.subtotal, c.iva, c.total, c.estado, c.comentarios
                               FROM compras c
                               JOIN proveedores p ON c.proveedor_id = p.id
                               JOIN usuarios u ON c.usuario_id = u.id
                               WHERE c.id = ?";
                $stmt_compra = $db->prepare($sql_compra);
                $stmt_compra->bind_param("i", $id);
                $stmt_compra->execute();
                $res_compra = $stmt_compra->get_result();
                if ($compra_data = $res_compra->fetch_assoc()) {
                    $compra = $compra_data;
                    // Obtener detalles de la compra
                    $sql_detalles = "SELECT cd.producto_id, prod.codigo as producto_codigo, prod.nombre as producto_nombre, 
                                            cd.cantidad, cd.precio_unitario, cd.subtotal
                                     FROM compra_detalles cd
                                     JOIN productos prod ON cd.producto_id = prod.id
                                     WHERE cd.compra_id = ?";
                    $stmt_detalles = $db->prepare($sql_detalles);
                    $stmt_detalles->bind_param("i", $id);
                    $stmt_detalles->execute();
                    $res_detalles = $stmt_detalles->get_result();
                    $detalles = [];
                    while($detalle = $res_detalles->fetch_assoc()){
                        $detalles[] = $detalle;
                    }
                    $compra['detalles'] = $detalles;
                    $stmt_detalles->close();
                }
                $stmt_compra->close();

                if ($compra) {
                    echo json_encode(['success' => true, 'data' => $compra]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Compra no encontrada.']);
                    http_response_code(404);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de compra no válido.']);
                http_response_code(400);
            }
        } elseif ($accion == 'leer_datos_formulario') {
            // Para llenar los selects en el formulario de nueva compra
            $proveedores = [];
            $sql_prov = "SELECT id, nombre FROM proveedores WHERE estado = 1 ORDER BY nombre ASC";
            $res_prov = $db->query($sql_prov);
            if ($res_prov) {
                while($row = $res_prov->fetch_assoc()) $proveedores[] = $row;
                $res_prov->free();
            }

            $productos = [];
            // Seleccionamos solo productos activos y con información esencial
            $sql_prod = "SELECT id, codigo, nombre, precio_costo FROM productos WHERE estado = 1 ORDER BY nombre ASC";
            $res_prod = $db->query($sql_prod);
            if ($res_prod) {
                while($row = $res_prod->fetch_assoc()) $productos[] = $row;
                $res_prod->free();
            }
            echo json_encode(['success' => true, 'data' => ['proveedores' => $proveedores, 'productos' => $productos]]);
        
        } else {
            echo json_encode(['success' => false, 'error' => 'Acción GET no válida para compras.']);
            http_response_code(400);
        }
        break;

    case 'POST':
        $datosEntrada = json_decode(file_get_contents('php://input'), true);

        if ($accion == 'crear') {
            if (empty($datosEntrada['proveedor_id']) || empty($datosEntrada['detalles']) || !is_array($datosEntrada['detalles'])) {
                echo json_encode(['success' => false, 'error' => 'Faltan campos requeridos: proveedor y al menos un detalle de producto.']);
                http_response_code(400);
                $db->close();
                exit;
            }

            $proveedor_id = (int)$datosEntrada['proveedor_id'];
            $usuario_id = obtenerUsuarioIdActual(); // O tomarlo de $datosEntrada si se envía desde el front-end
            $numero_factura = isset($datosEntrada['numero_factura']) ? $db->real_escape_string($datosEntrada['numero_factura']) : null;
            $fecha_compra_str = isset($datosEntrada['fecha_compra']) ? $datosEntrada['fecha_compra'] : date('Y-m-d H:i:s');
             try {
                $fecha_compra_dt = new DateTime($fecha_compra_str);
                $fecha_compra = $fecha_compra_dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $fecha_compra = date('Y-m-d H:i:s'); // Fallback a la fecha actual si hay error
            }
            $comentarios = isset($datosEntrada['comentarios']) ? $db->real_escape_string($datosEntrada['comentarios']) : null;
            $detalles = $datosEntrada['detalles'];

            $subtotal_compra = 0;
            $total_iva_compra = 0; // Asumimos un IVA general, podría ser por producto
            $total_compra = 0;

            // Validar detalles y calcular totales preliminares
            foreach ($detalles as $detalle) {
                if (empty($detalle['producto_id']) || !isset($detalle['cantidad']) || !isset($detalle['precio_unitario'])) {
                    echo json_encode(['success' => false, 'error' => 'Cada detalle debe tener producto_id, cantidad y precio_unitario.']);
                    http_response_code(400);
                    $db->close();
                    exit;
                }
                $cantidad = (int)$detalle['cantidad'];
                $precio_unitario = (float)$detalle['precio_unitario'];
                if ($cantidad <= 0 || $precio_unitario < 0) {
                     echo json_encode(['success' => false, 'error' => 'Cantidad y precio unitario deben ser positivos.']);
                    http_response_code(400);
                    $db->close();
                    exit;
                }
                $subtotal_linea = $cantidad * $precio_unitario;
                $subtotal_compra += $subtotal_linea;
            }
            
            // Aquí se podría calcular el IVA. Para simplificar, asumimos un IVA del 21% sobre el subtotal.
            // En un sistema real, el IVA puede ser más complejo (por producto, exento, etc.)
            $iva_calculado = $subtotal_compra * 0.21; 
            $total_compra = $subtotal_compra + $iva_calculado;


            $db->begin_transaction();

            try {
                // 1. Insertar en la tabla 'compras'
                $sql_compra = "INSERT INTO compras (proveedor_id, usuario_id, numero_factura, fecha_compra, subtotal, iva, total, estado, comentarios, fecha_registro) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, 'Completada', ?, NOW())";
                $stmt_compra = $db->prepare($sql_compra);
                if (!$stmt_compra) throw new Exception("Error al preparar consulta de compra: " . $db->error);
                
                $stmt_compra->bind_param("iisssdds", $proveedor_id, $usuario_id, $numero_factura, $fecha_compra, $subtotal_compra, $iva_calculado, $total_compra, $comentarios);
                if (!$stmt_compra->execute()) throw new Exception("Error al ejecutar inserción de compra: " . $stmt_compra->error);
                
                $compra_id = $stmt_compra->insert_id;
                $stmt_compra->close();

                // 2. Insertar en 'compra_detalles' y actualizar stock en 'productos' y registrar en 'inventario_movimientos'
                $sql_detalle = "INSERT INTO compra_detalles (compra_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
                $stmt_detalle = $db->prepare($sql_detalle);
                if (!$stmt_detalle) throw new Exception("Error al preparar consulta de detalle de compra: " . $db->error);

                $sql_update_stock = "UPDATE productos SET stock = stock + ?, precio_costo = ? WHERE id = ?"; // Actualizamos también el precio de costo
                $stmt_update_stock = $db->prepare($sql_update_stock);
                if (!$stmt_update_stock) throw new Exception("Error al preparar actualización de stock: " . $db->error);

                $sql_mov_inventario = "INSERT INTO inventario_movimientos (producto_id, usuario_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, referencia, motivo, fecha)
                                       VALUES (?, ?, 'Entrada', ?, ?, ?, ?, 'Ingreso por compra', NOW())";
                $stmt_mov_inventario = $db->prepare($sql_mov_inventario);
                if (!$stmt_mov_inventario) throw new Exception("Error al preparar inserción de movimiento de inventario: " . $db->error);


                foreach ($detalles as $item) {
                    $producto_id = (int)$item['producto_id'];
                    $cantidad = (int)$item['cantidad'];
                    $precio_unitario = (float)$item['precio_unitario'];
                    $subtotal_item = $cantidad * $precio_unitario;

                    // Insertar detalle de compra
                    $stmt_detalle->bind_param("iiidd", $compra_id, $producto_id, $cantidad, $precio_unitario, $subtotal_item);
                    if (!$stmt_detalle->execute()) throw new Exception("Error al insertar detalle de compra para producto ID $producto_id: " . $stmt_detalle->error);

                    // Obtener stock anterior
                    $sql_get_stock = "SELECT stock FROM productos WHERE id = ?";
                    $stmt_get_stock = $db->prepare($sql_get_stock);
                    if (!$stmt_get_stock) throw new Exception("Error al preparar consulta para obtener stock: " . $db->error);
                    $stmt_get_stock->bind_param("i", $producto_id);
                    $stmt_get_stock->execute();
                    $res_stock = $stmt_get_stock->get_result();
                    if (!($stock_data = $res_stock->fetch_assoc())) throw new Exception("Producto ID $producto_id no encontrado para actualizar stock.");
                    $stock_anterior = (int)$stock_data['stock'];
                    $stmt_get_stock->close();
                    
                    $stock_nuevo = $stock_anterior + $cantidad;

                    // Actualizar stock y precio de costo del producto
                    $stmt_update_stock->bind_param("idi", $cantidad, $precio_unitario, $producto_id); // precio_unitario se usa como nuevo precio_costo
                    if (!$stmt_update_stock->execute()) throw new Exception("Error al actualizar stock para producto ID $producto_id: " . $stmt_update_stock->error);

                    // Registrar movimiento de inventario
                    $referencia_mov = "Compra ID: " . $compra_id;
                    $stmt_mov_inventario->bind_param("iiiiis", $producto_id, $usuario_id, $cantidad, $stock_anterior, $stock_nuevo, $referencia_mov);
                    if (!$stmt_mov_inventario->execute()) throw new Exception("Error al registrar movimiento de inventario para producto ID $producto_id: " . $stmt_mov_inventario->error);
                }
                $stmt_detalle->close();
                $stmt_update_stock->close();
                $stmt_mov_inventario->close();

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Compra registrada exitosamente.', 'id' => $compra_id]);

            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'error' => 'Error al registrar la compra: ' . $e->getMessage()]);
                http_response_code(500);
            }
        } elseif ($accion == 'actualizar_estado') { // Ejemplo para actualizar solo estado
            $id = isset($datosEntrada['compra_id']) ? (int)$datosEntrada['compra_id'] : 0;
            $nuevo_estado = isset($datosEntrada['estado']) ? $db->real_escape_string($datosEntrada['estado']) : null;

            if ($id <= 0 || empty($nuevo_estado)) {
                echo json_encode(['success' => false, 'error' => 'ID de compra y nuevo estado son requeridos.']);
                http_response_code(400);
                exit;
            }
            // Validar que el estado sea uno de los permitidos (ej. Completada, Pendiente, Cancelada)
            $estados_permitidos = ['Completada', 'Pendiente', 'Cancelada'];
            if (!in_array($nuevo_estado, $estados_permitidos)) {
                echo json_encode(['success' => false, 'error' => 'Estado no válido.']);
                http_response_code(400);
                exit;
            }
            
            // NOTA: Si se cancela una compra, se debería revertir el stock. Esta lógica no está implementada aquí.
            if ($nuevo_estado === 'Cancelada') {
                 // Aquí iría la lógica compleja de reversión de stock, que es delicada.
                 // Por ahora, solo actualizamos el estado.
                 // Considerar registrar un movimiento de inventario de ajuste o salida.
            }


            $sql = "UPDATE compras SET estado = ?, fecha_modificacion = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("si", $nuevo_estado, $id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Estado de la compra actualizado.']);
                    } else {
                        echo json_encode(['success' => true, 'message' => 'Estado de la compra actualizado (sin cambios o ID no encontrado).']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al actualizar estado de la compra: ' . $stmt->error]);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta (actualizar estado compra): ' . $db->error]);
                http_response_code(500);
            }


        } elseif ($accion == 'eliminar') {
            // La eliminación de compras es compleja por la necesidad de revertir stock.
            // Una mejor práctica es "cancelar" (cambiar estado) en lugar de borrar.
            // Si se implementa borrado, debe ser con mucho cuidado.
            // La FK en compra_detalles tiene ON DELETE CASCADE, así que los detalles se borrarían.
            // Pero el stock NO se revierte automáticamente.
            $id = isset($datosEntrada['id']) ? (int)$datosEntrada['id'] : 0;
            if ($id > 0) {
                 // Primero, verificar si la compra está en un estado que permita la eliminación (ej. no 'Completada' si ya afectó mucho)
                $sql_check_estado = "SELECT estado FROM compras WHERE id = ?";
                $stmt_check = $db->prepare($sql_check_estado);
                $stmt_check->bind_param("i", $id);
                $stmt_check->execute();
                $res_check = $stmt_check->get_result();
                $compra_a_eliminar = $res_check->fetch_assoc();
                $stmt_check->close();

                if (!$compra_a_eliminar) {
                    echo json_encode(['success' => false, 'error' => 'Compra no encontrada para eliminar.']);
                    http_response_code(404);
                    exit;
                }

                // ADVERTENCIA: Esta eliminación es destructiva y NO revierte el stock.
                // Solo se recomienda si se entiende completamente las implicaciones.
                // En un sistema real, se preferiría cambiar el estado a 'Cancelada' y manejar la reversión de stock.
                
                // Para este ejemplo, procederemos con la eliminación si el estado no es "Completada" (o alguna otra lógica)
                // O simplemente advertir y permitirlo. Por ahora, vamos a permitirlo pero con advertencia implícita.

                $db->begin_transaction();
                try {
                    // Opcional: Antes de eliminar detalles y compra, se podría intentar revertir stock.
                    // Esta parte es la más compleja y se omite para esta versión inicial.
                    // Ejemplo conceptual (NO COMPLETO NI SEGURO PARA PRODUCCIÓN SIN VALIDACIONES EXTENSAS):
                    /*
                    $sql_get_detalles = "SELECT producto_id, cantidad FROM compra_detalles WHERE compra_id = ?";
                    $stmt_get_detalles = $db->prepare($sql_get_detalles);
                    $stmt_get_detalles->bind_param("i", $id);
                    $stmt_get_detalles->execute();
                    $res_detalles_rev = $stmt_get_detalles->get_result();
                    while ($detalle_rev = $res_detalles_rev->fetch_assoc()) {
                        // Revertir stock
                        $sql_revert_stock = "UPDATE productos SET stock = stock - ? WHERE id = ?";
                        $stmt_revert = $db->prepare($sql_revert_stock);
                        $stmt_revert->bind_param("ii", $detalle_rev['cantidad'], $detalle_rev['producto_id']);
                        $stmt_revert->execute();
                        $stmt_revert->close();
                        // Registrar movimiento de inventario de salida/reversión
                    }
                    $stmt_get_detalles->close();
                    */

                    // Eliminar detalles (se borran por ON DELETE CASCADE al borrar la compra)
                    // No es necesario un DELETE explícito de compra_detalles si la FK está bien configurada.

                    // Eliminar la compra
                    $sql_delete_compra = "DELETE FROM compras WHERE id = ?";
                    $stmt_delete = $db->prepare($sql_delete_compra);
                    if (!$stmt_delete) throw new Exception("Error al preparar eliminación de compra: " . $db->error);
                    $stmt_delete->bind_param("i", $id);
                    if (!$stmt_delete->execute()) throw new Exception("Error al eliminar compra: " . $stmt_delete->error);
                    
                    if ($stmt_delete->affected_rows > 0) {
                        $db->commit();
                        echo json_encode(['success' => true, 'message' => 'Compra eliminada. Recuerde que el stock NO se ha revertido automáticamente.']);
                    } else {
                        throw new Exception("Compra no encontrada o ya eliminada.");
                    }
                    $stmt_delete->close();

                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(['success' => false, 'error' => 'Error al eliminar la compra: ' . $e->getMessage()]);
                    http_response_code(500);
                }

            } else {
                echo json_encode(['success' => false, 'error' => 'ID de compra no válido para eliminar.']);
                http_response_code(400);
            }

        } else {
            echo json_encode(['success' => false, 'error' => 'Acción POST no válida para compras.']);
            http_response_code(400);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Método HTTP no soportado para compras.']);
        http_response_code(405);
        break;
}

if ($db) {
    $db->close();
}
?>

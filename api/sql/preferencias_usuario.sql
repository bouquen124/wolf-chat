-- Ejecuta este archivo una sola vez en la base de datos whatsapp_clone.
CREATE TABLE IF NOT EXISTS `preferencias_usuario` (
  `usuario_id` bigint(20) unsigned NOT NULL,
  `tema_id` varchar(20) NOT NULL DEFAULT 'verde',
  `fondo_chat_ruta` varchar(500) DEFAULT NULL,
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_preferencias_usuario_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

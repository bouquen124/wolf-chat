-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         10.4.27-MariaDB - mariadb.org binary distribution
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.15.0.7171
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para whatsapp_clone
CREATE DATABASE IF NOT EXISTS `whatsapp_clone` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `whatsapp_clone`;

-- Volcando estructura para tabla whatsapp_clone.archivos
CREATE TABLE IF NOT EXISTS `archivos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre_original` varchar(255) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta` varchar(500) NOT NULL,
  `tipo_mime` varchar(100) NOT NULL,
  `extension` varchar(20) DEFAULT NULL,
  `tamano` bigint(20) unsigned NOT NULL DEFAULT 0,
  `hash_archivo` varchar(128) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_archivos_hash` (`hash_archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.bloqueos
CREATE TABLE IF NOT EXISTS `bloqueos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `usuario_bloqueado_id` bigint(20) unsigned NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bloqueo` (`usuario_id`,`usuario_bloqueado_id`),
  KEY `idx_bloqueos_usuario` (`usuario_id`),
  KEY `idx_bloqueos_bloqueado` (`usuario_bloqueado_id`),
  CONSTRAINT `fk_bloqueos_bloqueado` FOREIGN KEY (`usuario_bloqueado_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bloqueos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.calendario_eventos
CREATE TABLE IF NOT EXISTS `calendario_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `titulo` varchar(120) NOT NULL,
  `hora` time NOT NULL,
  `color` char(7) NOT NULL DEFAULT '#6366f1',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_calendario_usuario_fecha` (`usuario_id`,`fecha`),
  CONSTRAINT `fk_calendario_eventos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.contactos
CREATE TABLE IF NOT EXISTS `contactos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `contacto_id` bigint(20) unsigned NOT NULL,
  `nombre_personalizado` varchar(100) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contacto` (`usuario_id`,`contacto_id`),
  KEY `idx_contactos_usuario` (`usuario_id`),
  KEY `idx_contactos_contacto` (`contacto_id`),
  CONSTRAINT `fk_contactos_contacto` FOREIGN KEY (`contacto_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_contactos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.conversacion_usuarios
CREATE TABLE IF NOT EXISTS `conversacion_usuarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversacion_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `rol` enum('miembro','administrador') NOT NULL DEFAULT 'miembro',
  `fecha_ingreso` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_salida` datetime DEFAULT NULL,
  `estado` enum('activo','salio','expulsado') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conversacion_usuario` (`conversacion_id`,`usuario_id`),
  KEY `idx_cu_usuario` (`usuario_id`),
  KEY `idx_cu_conversacion` (`conversacion_id`),
  CONSTRAINT `fk_cu_conversacion` FOREIGN KEY (`conversacion_id`) REFERENCES `conversaciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cu_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.conversaciones
CREATE TABLE IF NOT EXISTS `conversaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('privada','grupo') NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `foto` varchar(500) DEFAULT NULL,
  `creador_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estado` enum('activa','eliminada') NOT NULL DEFAULT 'activa',
  PRIMARY KEY (`id`),
  KEY `idx_conversaciones_creador` (`creador_id`),
  CONSTRAINT `fk_conversaciones_creador` FOREIGN KEY (`creador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.dispositivos
CREATE TABLE IF NOT EXISTS `dispositivos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `identificador` varchar(255) NOT NULL,
  `plataforma` enum('android','ios','web','otro') NOT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `version_sistema` varchar(50) DEFAULT NULL,
  `token_push` varchar(500) DEFAULT NULL,
  `ultima_actividad` datetime DEFAULT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dispositivo` (`usuario_id`,`identificador`),
  KEY `idx_dispositivos_usuario` (`usuario_id`),
  CONSTRAINT `fk_dispositivos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.estado_audiencia
CREATE TABLE IF NOT EXISTS `estado_audiencia` (
  `estado_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`estado_id`,`usuario_id`),
  KEY `idx_estado_audiencia_usuario` (`usuario_id`),
  CONSTRAINT `fk_estado_audiencia_estado` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_estado_audiencia_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.estados
CREATE TABLE IF NOT EXISTS `estados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('texto','imagen','video') NOT NULL,
  `contenido` text DEFAULT NULL,
  `archivo_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_expiracion` datetime NOT NULL,
  `estado` enum('activo','eliminado') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  KEY `idx_estados_usuario` (`usuario_id`),
  KEY `idx_estados_expiracion` (`fecha_expiracion`),
  KEY `fk_estados_archivo` (`archivo_id`),
  CONSTRAINT `fk_estados_archivo` FOREIGN KEY (`archivo_id`) REFERENCES `archivos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_estados_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.estados_programados
CREATE TABLE IF NOT EXISTS `estados_programados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('texto','imagen','video') NOT NULL,
  `contenido` text DEFAULT NULL,
  `archivo_id` bigint(20) unsigned DEFAULT NULL,
  `programar_en` datetime NOT NULL,
  `duracion_horas` smallint(5) unsigned NOT NULL DEFAULT 24,
  `repetir_semanal` tinyint(1) NOT NULL DEFAULT 0,
  `repetir_hasta` date DEFAULT NULL,
  `estado` enum('pendiente','publicada','cancelada') NOT NULL DEFAULT 'pendiente',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_publicacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_programadas_pendientes` (`estado`,`programar_en`),
  KEY `idx_programadas_usuario` (`usuario_id`,`estado`,`programar_en`),
  KEY `idx_programadas_archivo` (`archivo_id`),
  CONSTRAINT `fk_programadas_archivo` FOREIGN KEY (`archivo_id`) REFERENCES `archivos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_programadas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.estados_vistos
CREATE TABLE IF NOT EXISTS `estados_vistos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `estado_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `fecha_visto` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_estado_visto` (`estado_id`,`usuario_id`),
  KEY `idx_ev_usuario` (`usuario_id`),
  CONSTRAINT `fk_ev_estado` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ev_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.llamada_ice_candidates
CREATE TABLE IF NOT EXISTS `llamada_ice_candidates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `llamada_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `candidate` text NOT NULL,
  `sdp_mid` varchar(255) DEFAULT NULL,
  `sdp_m_line_index` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_ice_llamada` (`llamada_id`) USING BTREE,
  KEY `idx_ice_usuario` (`usuario_id`) USING BTREE,
  CONSTRAINT `fk_ice_llamada` FOREIGN KEY (`llamada_id`) REFERENCES `llamadas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ice_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.llamadas
CREATE TABLE IF NOT EXISTS `llamadas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversacion_id` bigint(20) unsigned NOT NULL,
  `llamante_id` bigint(20) unsigned NOT NULL,
  `receptor_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('voz','video') NOT NULL DEFAULT 'voz',
  `estado` enum('llamando','aceptada','rechazada','cancelada','finalizada','sin_respuesta') NOT NULL DEFAULT 'llamando',
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_respuesta` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `offer_sdp` longtext DEFAULT NULL,
  `answer_sdp` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_llamadas_conversacion` (`conversacion_id`),
  KEY `idx_llamadas_llamante` (`llamante_id`),
  KEY `idx_llamadas_receptor` (`receptor_id`),
  KEY `idx_llamadas_estado` (`estado`),
  CONSTRAINT `fk_llamadas_conversacion` FOREIGN KEY (`conversacion_id`) REFERENCES `conversaciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_llamadas_llamante` FOREIGN KEY (`llamante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_llamadas_receptor` FOREIGN KEY (`receptor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.mensaje_archivos
CREATE TABLE IF NOT EXISTS `mensaje_archivos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mensaje_id` bigint(20) unsigned NOT NULL,
  `archivo_id` bigint(20) unsigned NOT NULL,
  `orden` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mensaje_archivo` (`mensaje_id`,`archivo_id`),
  KEY `idx_ma_mensaje` (`mensaje_id`),
  KEY `idx_ma_archivo` (`archivo_id`),
  CONSTRAINT `fk_ma_archivo` FOREIGN KEY (`archivo_id`) REFERENCES `archivos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ma_mensaje` FOREIGN KEY (`mensaje_id`) REFERENCES `mensajes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.mensaje_destinatarios
CREATE TABLE IF NOT EXISTS `mensaje_destinatarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mensaje_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `estado` enum('enviado','entregado','leido') NOT NULL DEFAULT 'enviado',
  `fecha_entregado` datetime DEFAULT NULL,
  `fecha_leido` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mensaje_destinatario` (`mensaje_id`,`usuario_id`),
  KEY `idx_md_usuario` (`usuario_id`),
  KEY `idx_md_mensaje` (`mensaje_id`),
  CONSTRAINT `fk_md_mensaje` FOREIGN KEY (`mensaje_id`) REFERENCES `mensajes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_md_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.mensajes
CREATE TABLE IF NOT EXISTS `mensajes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversacion_id` bigint(20) unsigned NOT NULL,
  `remitente_id` bigint(20) unsigned NOT NULL,
  `mensaje` text DEFAULT NULL,
  `mensaje_tipo` varchar(30) NOT NULL DEFAULT 'texto',
  `mensaje_respuesta_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_envio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_edicion` datetime DEFAULT NULL,
  `fecha_eliminacion` datetime DEFAULT NULL,
  `estado` enum('activo','editado','eliminado') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  KEY `idx_mensajes_conversacion` (`conversacion_id`,`id`),
  KEY `idx_mensajes_remitente` (`remitente_id`),
  KEY `idx_mensajes_respuesta` (`mensaje_respuesta_id`),
  CONSTRAINT `fk_mensajes_conversacion` FOREIGN KEY (`conversacion_id`) REFERENCES `conversaciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mensajes_remitente` FOREIGN KEY (`remitente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mensajes_respuesta` FOREIGN KEY (`mensaje_respuesta_id`) REFERENCES `mensajes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.notificaciones
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `remitente_id` bigint(20) unsigned DEFAULT NULL,
  `tipo` varchar(50) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `contenido` varchar(500) DEFAULT NULL,
  `referencia_id` bigint(20) unsigned DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `notificada` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_lectura` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notificaciones_usuario` (`usuario_id`,`leida`),
  KEY `idx_notificaciones_entrega` (`usuario_id`,`notificada`,`id`),
  KEY `idx_notificaciones_remitente` (`remitente_id`),
  CONSTRAINT `fk_notificaciones_remitente` FOREIGN KEY (`remitente_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_notificaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.preferencias_notificaciones
CREATE TABLE IF NOT EXISTS `preferencias_notificaciones` (
  `usuario_id` bigint(20) unsigned NOT NULL,
  `privados` tinyint(1) NOT NULL DEFAULT 1,
  `grupos` tinyint(1) NOT NULL DEFAULT 1,
  `estados` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_preferencias_notificaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.preferencias_usuario
CREATE TABLE IF NOT EXISTS `preferencias_usuario` (
  `usuario_id` bigint(20) unsigned NOT NULL,
  `tema_id` varchar(20) NOT NULL DEFAULT 'verde',
  `fondo_chat_ruta` varchar(500) DEFAULT NULL,
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_preferencias_usuario_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.privacidad_estados_contactos
CREATE TABLE IF NOT EXISTS `privacidad_estados_contactos` (
  `usuario_id` bigint(20) unsigned NOT NULL,
  `contacto_id` bigint(20) unsigned NOT NULL,
  `accion` enum('excluir','compartir') NOT NULL,
  PRIMARY KEY (`usuario_id`,`contacto_id`),
  KEY `idx_privacidad_estados_contacto` (`contacto_id`),
  CONSTRAINT `fk_privacidad_estados_contacto` FOREIGN KEY (`contacto_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_privacidad_estados_propietario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.privacidad_restricciones_contacto
CREATE TABLE IF NOT EXISTS `privacidad_restricciones_contacto` (
  `usuario_id` bigint(20) unsigned NOT NULL,
  `contacto_id` bigint(20) unsigned NOT NULL,
  `ocultar_estados` tinyint(1) NOT NULL DEFAULT 0,
  `ocultar_foto` tinyint(1) NOT NULL DEFAULT 0,
  `ocultar_descripcion` tinyint(1) NOT NULL DEFAULT 0,
  `ocultar_ultima_conexion` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`usuario_id`,`contacto_id`),
  KEY `fk_privacidad_restriccion_contacto` (`contacto_id`),
  CONSTRAINT `fk_privacidad_restriccion_contacto` FOREIGN KEY (`contacto_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_privacidad_restriccion_propietario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.privacidad_usuario
CREATE TABLE IF NOT EXISTS `privacidad_usuario` (
  `usuario_id` bigint(20) unsigned NOT NULL,
  `foto_perfil` enum('todos','contactos','nadie') NOT NULL DEFAULT 'contactos',
  `descripcion` enum('todos','contactos','nadie') NOT NULL DEFAULT 'contactos',
  `estados` enum('todos','contactos','nadie','contactos_excepto','solo_seleccionados') NOT NULL DEFAULT 'contactos',
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultima_conexion` enum('todos','contactos','nadie') NOT NULL DEFAULT 'contactos',
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_privacidad_usuario_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.reacciones
CREATE TABLE IF NOT EXISTS `reacciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mensaje_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `emoji` varchar(20) NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reaccion` (`mensaje_id`,`usuario_id`),
  KEY `idx_reacciones_mensaje` (`mensaje_id`),
  KEY `idx_reacciones_usuario` (`usuario_id`),
  CONSTRAINT `fk_reacciones_mensaje` FOREIGN KEY (`mensaje_id`) REFERENCES `mensajes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reacciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.sesiones
CREATE TABLE IF NOT EXISTS `sesiones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `token` varchar(255) NOT NULL,
  `dispositivo` varchar(100) DEFAULT NULL,
  `plataforma` enum('android','ios','web','otro') NOT NULL DEFAULT 'otro',
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_expiracion` datetime DEFAULT NULL,
  `ultima_actividad` datetime DEFAULT NULL,
  `estado` enum('activa','cerrada') NOT NULL DEFAULT 'activa',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sesiones_token` (`token`),
  KEY `idx_sesiones_usuario` (`usuario_id`),
  CONSTRAINT `fk_sesiones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.usuario_presencia
CREATE TABLE IF NOT EXISTS `usuario_presencia` (
  `usuario_id` bigint(20) unsigned NOT NULL,
  `ultima_actividad` datetime NOT NULL,
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_usuario_presencia_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla whatsapp_clone.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `telefono` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `foto` varchar(500) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` enum('activo','inactivo','bloqueado') NOT NULL DEFAULT 'activo',
  `ultimo_acceso` datetime DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuarios_telefono` (`telefono`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;


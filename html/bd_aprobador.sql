-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: db
-- Tiempo de generación: 12-01-2026 a las 16:33:37
-- Versión del servidor: 8.0.44
-- Versión de PHP: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bd_aprobador`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int NOT NULL,
  `contract_id` varchar(50) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` text,
  `performed_by` int DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `contract_id`, `action`, `details`, `performed_by`, `timestamp`) VALUES
(1, 'CTR-2025-001', 'Creación', 'Contrato cargado', 2, '2025-12-16 19:19:43'),
(2, 'CTR-2025-002', 'Creación', 'Contrato cargado', 3, '2025-12-16 19:21:33'),
(3, 'CTR-2025-003', 'Creación', 'Contrato cargado', 3, '2025-12-16 19:33:51'),
(4, 'CTR-2025-004', 'Creación', 'Contrato cargado', 2, '2025-12-16 19:39:00'),
(5, 'CTR-2025-004', 'Cambio de Estado', 'Nuevo estado: OBSERVATIONS', 7, '2025-12-16 19:49:51'),
(6, 'CTR-2025-002', 'Cambio de Estado', 'Nuevo estado: OBSERVATIONS', 7, '2025-12-16 23:17:30'),
(7, 'CTR-2025-003', 'Cambio de Estado', 'Nuevo estado: OBSERVATIONS', 7, '2025-12-17 13:59:46'),
(8, 'CTR-2025-001', 'Cambio de Estado', 'Nuevo estado: OBSERVATIONS', 9, '2025-12-17 14:14:44'),
(9, 'CTR-2025-001', 'Nueva Versión', 'v2 subida', 2, '2025-12-17 14:45:16'),
(10, 'CTR-2025-001', 'Edición de Datos', 'Prioridad a HIGH', 2, '2025-12-17 14:58:14'),
(11, 'CTR-2025-004', 'Nueva Versión', 'v2 subida', 2, '2025-12-17 15:17:26'),
(12, 'CTR-2025-001', 'Cambio de Estado', 'Nuevo estado: LEGAL_REVIEW_FINAL', 7, '2025-12-17 15:22:20'),
(13, 'CTR-2025-001', 'Firma Autorizada', 'Versión firmada (v3) subida.', 7, '2025-12-17 15:28:21'),
(14, 'CTR-2025-001', 'Cambio de Estado', 'Nuevo estado: ARCHIVED', 7, '2025-12-17 16:48:33'),
(15, 'CTR-2025-005', 'Creación', 'Contrato cargado', 2, '2025-12-17 17:12:35'),
(16, 'CTR-2025-005', 'Edición de Datos', 'Categoría', 2, '2025-12-17 17:13:38'),
(17, 'CTR-2025-006', 'Creación', 'Contrato cargado', 2, '2025-12-17 17:15:15'),
(18, 'CTR-2025-004', 'Nueva Versión', 'v3 subida', 2, '2025-12-17 17:17:27'),
(19, 'CTR-2025-004', 'Nueva Versión', 'v4 subida', 2, '2025-12-17 17:20:44'),
(20, 'CTR-2025-006', 'Solicitud Anexos', 'Legal pidió 2 documentos.', 7, '2025-12-22 15:45:38'),
(21, 'CTR-2025-006', 'Solicitud Anexos', 'Legal pidió 1 documentos.', 7, '2025-12-22 15:49:11'),
(22, 'CTR-2025-006', 'Anexo Eliminado', 'Se eliminó el anexo: Código de Conducta', 7, '2025-12-22 16:13:07'),
(23, 'CTR-2025-006', 'Gestión Anexos', 'Legal gestionó anexos: 0 nuevos, 1 actualizados.', 7, '2025-12-22 16:13:44'),
(24, 'CTR-2025-005', 'Gestión Anexos', 'Legal gestionó anexos: 2 nuevos, 0 actualizados.', 7, '2025-12-22 16:18:41'),
(25, 'CTR-2025-006', 'Gestión Anexos', 'Legal gestionó anexos: 1 nuevos, 0 actualizados.', 1, '2025-12-22 16:33:04'),
(26, 'CTR-2025-005', 'Cambio de Estado', 'Nuevo estado: LEGAL_REVIEW_FINAL', 1, '2025-12-22 16:41:12'),
(27, 'CTR-2025-007', 'Creación', 'Contrato cargado', 2, '2025-12-22 17:14:44'),
(28, 'CTR-2025-008', 'Creación', 'Contrato cargado', 2, '2025-12-22 17:25:51'),
(29, 'CTR-2025-006', 'Gestión Anexos', 'Legal gestionó anexos: 0 nuevos, 1 actualizados.', 7, '2025-12-22 17:40:27'),
(30, 'CTR-2025-009', 'Creación', 'Contrato cargado', 2, '2025-12-22 19:27:06'),
(31, 'CTR-2025-006', 'Edición de Datos', 'Título', 2, '2025-12-22 19:45:32'),
(32, 'CTR-2025-009', 'Gestión Anexos', 'Legal gestionó anexos: 2 nuevos, 0 actualizados.', 7, '2025-12-22 20:05:35'),
(33, 'CTR-2025-009', 'Cambio de Estado', 'Nuevo estado: LEGAL_REVIEW_FINAL', 7, '2025-12-22 20:55:50'),
(34, 'CTR-2025-009', 'Firma Autorizada', 'Versión firmada (v2) subida.', 1, '2025-12-22 21:02:48'),
(35, 'CTR-2025-009', 'Cambio de Estado', 'Nuevo estado: ARCHIVED', 1, '2025-12-22 21:06:27'),
(36, 'CTR-2025-009', 'Gestión Anexos', 'Legal gestionó anexos: 0 nuevos, 1 actualizados.', 1, '2025-12-22 21:07:01'),
(37, 'CTR-2025-009', 'Anexo Eliminado', 'Se eliminó el anexo: Código de Conducta', 1, '2025-12-22 21:09:19'),
(38, 'CTR-2025-009', 'Cambio de Estado', 'Nuevo estado: ARCHIVED', 1, '2025-12-22 21:09:23'),
(39, 'CTR-2025-008', 'Gestión Anexos', 'Legal gestionó anexos: 2 nuevos, 0 actualizados.', 7, '2025-12-22 21:13:49'),
(40, 'CTR-2025-008', 'Cambio de Estado', 'Nuevo estado: OBSERVATIONS', 7, '2025-12-22 21:14:18'),
(41, 'CTR-2025-007', 'Cambio de Estado', 'Nuevo estado: OBSERVATIONS', 7, '2025-12-22 22:26:04'),
(42, 'CTR-2025-007', 'Gestión Anexos', 'Legal gestionó anexos: 2 nuevos, 0 actualizados.', 7, '2025-12-22 22:27:27'),
(43, 'CTR-2025-007', 'Gestión Anexos', 'Legal gestionó anexos: 1 nuevos, 0 actualizados.', 7, '2025-12-22 22:28:10'),
(44, 'CTR-2025-006', 'Cambio de Estado', 'Nuevo estado: LEGAL_REVIEW_FINAL', 7, '2025-12-22 22:28:30'),
(45, 'CTR-2025-007', 'Nueva Versión', 'v2 subida', 2, '2025-12-22 22:51:49'),
(46, 'CTR-2025-007', 'Cambio de Estado', 'Nuevo estado: LEGAL_REVIEW_FINAL', 7, '2025-12-22 23:05:38'),
(47, 'CTR-2025-007', 'Firma Autorizada', 'Versión firmada (v3) subida.', 7, '2025-12-23 14:27:05'),
(48, 'CTR-2025-006', 'Firma Autorizada', 'Versión firmada (v2) subida.', 7, '2025-12-23 17:09:10'),
(49, 'CTR-2025-006', 'Cambio de Estado', 'Nuevo estado: ARCHIVED', 7, '2025-12-23 17:09:16'),
(50, 'CTR-2025-010', 'Creación', 'Contrato cargado', 7, '2025-12-23 17:52:12'),
(51, 'CTR-2025-010', 'Anulación', 'Contrato anulado.', 7, '2025-12-23 17:52:22'),
(52, 'CTR-2025-011', 'Creación', 'Contrato cargado', 2, '2025-12-23 20:26:47'),
(53, 'CTR-2025-011', 'Gestión Anexos', 'Legal gestionó anexos: 1 nuevos, 0 actualizados.', 7, '2025-12-23 20:39:42'),
(54, 'CTR-2025-011', 'Anexo Eliminado', 'Se eliminó el anexo: Código de Conducta', 7, '2025-12-23 20:42:46'),
(55, 'CTR-2025-011', 'Gestión Anexos', 'Legal gestionó anexos: 1 nuevos, 0 actualizados.', 7, '2025-12-23 20:43:23'),
(56, 'CTR-2025-011', 'Anexo Eliminado', 'Se eliminó el anexo: Código de Conducta', 7, '2025-12-23 20:43:35'),
(57, 'CTR-2025-011', 'Cambio de Estado', 'Nuevo estado: OBSERVATIONS', 9, '2025-12-23 20:46:35'),
(58, 'CTR-2025-012', 'Creación', 'Contrato cargado', 2, '2025-12-23 20:47:32'),
(59, 'CTR-2025-012', 'Cambio Automático', 'Estado pasó a OBSERVACIONES por comentario de Legal.', 9, '2025-12-23 20:54:21'),
(60, 'CTR-2025-0013', 'Creación', 'Contrato cargado', 2, '2025-12-23 21:00:32'),
(61, 'CTR-2025-012', 'Nueva Versión', 'v2 subida', 2, '2025-12-23 21:02:28'),
(62, 'CTR-2025-012', 'Cambio de Estado', 'Nuevo estado: LEGAL_REVIEW_FINAL', 7, '2025-12-23 21:31:39'),
(63, 'CTR-2025-012', 'Gestión Anexos', 'Legal gestionó anexos: 1 nuevos, 0 actualizados.', 7, '2025-12-23 21:31:48'),
(64, 'CTR-2025-012', 'Firma Autorizada', 'Versión firmada (v3) subida.', 7, '2025-12-23 22:14:38'),
(65, 'CTR-2025-012', 'Firma Autorizada', 'Versión firmada (v4) subida.', 7, '2025-12-23 22:25:17'),
(66, 'CTR-2025-0014', 'Creación', 'Contrato cargado', 2, '2025-12-24 14:37:31'),
(67, 'CTR-2025-0014', 'Gestión Anexos', 'Legal gestionó anexos: 1 nuevos, 0 actualizados.', 7, '2025-12-24 14:40:08'),
(68, 'CTR-2025-0014', 'Cambio Automático', 'Estado pasó a OBSERVACIONES por comentario de Legal.', 7, '2025-12-24 14:42:07'),
(69, 'CTR-2025-0014', 'Nueva Versión', 'v2 subida', 2, '2025-12-24 14:46:19'),
(70, 'CTR-2025-0014', 'Cambio de Estado', 'Nuevo estado: OBSERVATIONS', 7, '2025-12-24 14:50:17'),
(71, 'CTR-2025-0014', 'Nueva Versión', 'v3 subida', 2, '2025-12-24 14:51:01'),
(72, 'CTR-2025-0014', 'Cambio de Estado', 'Nuevo estado: LEGAL_REVIEW_FINAL', 7, '2025-12-24 15:37:05'),
(73, 'CTR-2025-0014', 'Firma Autorizada', 'Versión firmada (v4) subida.', 7, '2025-12-24 15:37:33'),
(74, 'CTR-2025-0014', 'Cambio de Estado', 'Nuevo estado: ARCHIVED', 7, '2025-12-24 15:37:41'),
(75, 'CTR-2025-0015', 'Creación', 'Contrato cargado', 2, '2025-12-29 16:38:19'),
(76, 'CTR-2025-0015', 'Cambio de Estado', 'Nuevo estado: OBSERVATIONS', 7, '2025-12-29 16:39:19'),
(77, 'CTR-2025-0015', 'Nueva Versión', 'v2 subida', 2, '2025-12-29 16:39:58'),
(78, 'CTR-2025-0015', 'Nueva Versión', 'v3 subida', 2, '2025-12-29 16:44:52'),
(79, 'CTR-2025-0015', 'Cambio de Estado', 'Nuevo estado: LEGAL_REVIEW_FINAL', 7, '2025-12-29 16:47:29'),
(80, 'CTR-2025-0015', 'Firma Autorizada', 'Versión firmada (v4) subida.', 7, '2025-12-29 16:47:38'),
(81, 'CTR-2025-0015', 'Cambio de Estado', 'Nuevo estado: ARCHIVED', 7, '2025-12-29 16:47:41'),
(82, 'CTR-2025-0016', 'Creación', 'Contrato cargado', 2, '2025-12-29 17:14:21'),
(83, 'CTR-2025-0016', 'Cambio de Estado', 'Nuevo estado: LEGAL_REVIEW_FINAL', 7, '2025-12-29 17:15:31'),
(84, 'CTR-2025-0016', 'Nueva Versión', 'v2 subida', 2, '2025-12-29 17:18:44'),
(85, 'CTR-2025-0016', 'Nueva Versión', 'v3 subida', 2, '2025-12-29 20:43:51'),
(86, 'CTR-2025-0016', 'Nueva Versión', 'v4 subida', 2, '2025-12-29 20:47:36'),
(87, 'CTR-2025-0016', 'Nueva Versión', 'v5 subida', 2, '2025-12-29 21:12:04'),
(88, 'CTR-2025-0016', 'Nueva Versión', 'v6 subida', 2, '2025-12-29 21:14:34'),
(89, 'CTR-2025-0016', 'Nueva Versión', 'v7 subida', 2, '2025-12-29 21:21:15'),
(90, 'CTR-2025-0016', 'Cambio de Estado', 'Nuevo estado: LEGAL_REVIEW_FINAL', 7, '2025-12-29 22:05:41'),
(91, 'CTR-2025-0016', 'Firma Autorizada', 'Versión firmada (v8) subida.', 7, '2025-12-29 22:19:17'),
(92, 'CTR-2025-0016', 'Cambio de Estado', 'Nuevo estado: ARCHIVED', 7, '2025-12-29 23:06:17'),
(93, 'CTR-2025-012', 'Recordatorio Enviado', 'Se notificó al usuario sobre anexos faltantes.', 7, '2026-01-05 21:32:32'),
(94, 'CTR-2025-012', 'Recordatorio Enviado', 'Se notificó al usuario sobre anexos faltantes.', 7, '2026-01-05 21:35:48'),
(95, 'CTR-2025-012', 'Recordatorio Enviado', 'Se notificó al usuario sobre anexos faltantes.', 7, '2026-01-05 21:43:33'),
(96, 'CTR-2025-012', 'Recordatorio Enviado', 'Se notificó al usuario sobre anexos faltantes (Async).', 7, '2026-01-06 14:57:40'),
(97, 'CTR-2025-012', 'Cambio de Estado', 'Nuevo estado: ARCHIVED', 7, '2026-01-06 15:02:43'),
(98, 'CTR-2026-0001', 'Creación', 'Contrato cargado', 3, '2026-01-07 19:23:09'),
(99, 'CTR-2026-0001', 'Cambio Automático', 'Estado pasó a OBSERVACIONES por comentario de Legal.', 9, '2026-01-07 19:23:50'),
(100, 'CTR-2026-0001', 'Nueva Versión', 'v2 subida', 3, '2026-01-07 19:24:41'),
(101, 'CTR-2026-0001', 'Gestión Anexos', 'Legal gestionó anexos: 1 nuevos, 0 actualizados.', 9, '2026-01-07 21:12:21'),
(102, 'CTR-2026-0001', 'Gestión Anexos', 'Legal gestionó anexos: 1 nuevos, 0 actualizados.', 9, '2026-01-07 21:59:48'),
(103, 'CTR-2026-0001', 'Cambio de Estado', 'Nuevo estado: LEGAL_REVIEW_FINAL', 9, '2026-01-07 22:03:56'),
(104, 'CTR-2026-0001', 'Firma Autorizada', 'Versión firmada (v3) subida.', 9, '2026-01-07 22:04:07'),
(105, 'CTR-2026-0001', 'Recordatorio Enviado', 'Se notificó al usuario sobre anexos faltantes (Async).', 9, '2026-01-07 22:04:31'),
(106, 'CTR-2026-0002', 'Creación', 'Contrato cargado', 9, '2026-01-08 14:31:54'),
(107, 'CTR-2026-0002', 'Cambio de Estado', 'Nuevo estado: LEGAL_REVIEW_FINAL', 9, '2026-01-08 14:32:11'),
(108, 'CTR-2026-0002', 'Firma Autorizada', 'Versión firmada (v2) subida.', 9, '2026-01-08 14:32:19'),
(109, 'CTR-2026-0002', 'Cambio de Estado', 'Nuevo estado: ARCHIVED', 9, '2026-01-08 14:32:56'),
(110, 'CTR-2026-0003', 'Creación', 'Contrato cargado', 2, '2026-01-09 14:28:48'),
(111, 'CTR-2026-0003', 'Cambio de Estado', 'Nuevo estado: OBSERVATIONS', 7, '2026-01-09 14:34:19'),
(112, 'CTR-2026-0003', 'Nueva Versión', 'v2 subida', 2, '2026-01-09 14:37:32'),
(113, 'CTR-2026-0003', 'Gestión Anexos', 'Legal gestionó anexos: 2 nuevos, 0 actualizados.', 7, '2026-01-09 16:07:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contracts`
--

CREATE TABLE `contracts` (
  `id` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `summary` text,
  `supplier` varchar(150) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'OTRO',
  `initiator_id` int DEFAULT NULL,
  `current_approver_id` int DEFAULT NULL,
  `status` enum('UNREVIEWED','IN_REVIEW','OBSERVATIONS','LEGAL_REVIEW_FINAL','INTERNAL_APPROVALS','SIGNATURE','ARCHIVED','CANCELLED') DEFAULT 'UNREVIEWED',
  `observation_turn` enum('LEGAL','USER') DEFAULT NULL,
  `priority` enum('LOW','MEDIUM','HIGH') DEFAULT 'MEDIUM',
  `has_red_flags` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `red_flag_details` text,
  `red_flag_count` int DEFAULT '0',
  `waiting_for_annexes` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `contracts`
--

INSERT INTO `contracts` (`id`, `title`, `summary`, `supplier`, `category`, `initiator_id`, `current_approver_id`, `status`, `observation_turn`, `priority`, `has_red_flags`, `created_at`, `updated_at`, `red_flag_details`, `red_flag_count`, `waiting_for_annexes`) VALUES
('CTR-2025-001', 'Revisión de Contrato de Adquisición de Equipo de Cómputo', 'Contrato para la adquisición de equipo de cómputo entre UABC y JMG TECHNOLOGY, S.A. de C.V. Se identifican riesgos relacionados con fuerza mayor y terminación anticipada.', 'JMG TECHNOLOGY, S.A. DE C.V.', 'Servicios', 2, NULL, 'ARCHIVED', NULL, 'HIGH', 1, '2025-12-16 19:19:43', '2025-12-17 16:48:33', 'ESTÁNDAR 1 (FUERZA MAYOR): El contrato no exime a ANC CAR S.A. de responsabilidad por fuerza mayor (incluyendo pandemias)., ESTÁNDAR 2 (TERMINACIÓN ANTICIPADA): El contrato no otorga a ANC CAR S.A. el derecho a terminar unilateralmente por conveniencia sin justa causa y sin indemnización. Se requiere cláusula específica para ello.', 2, 0),
('CTR-2025-0013', 'Derecho Civil de los Contratos', 'Este documento describe la asignatura \'Derecho Civil de los Contratos\' de la Universidad Autónoma Intercultural de Sinaloa. Detalla los propósitos, presentación, intenciones didácticas y competencias generales y específicas relacionadas con el estudio de los contratos civiles.', 'Universidad Autónoma Intercultural de Sinaloa', 'Otro', 2, NULL, 'UNREVIEWED', NULL, 'LOW', 1, '2025-12-23 21:00:32', '2026-01-09 16:06:20', 'Riesgo A: Falta cláusula de Fuerza Mayor que mencione explícitamente \'pandemias\' o \'emergencias de salud\' y permita terminar sin penalidad., Riesgo B: Falta cláusula de Terminación Anticipada que permita a ANC CAR S.A. salir \'sin justa causa\' (por conveniencia) y \'sin penalidad\'., Riesgo C: Falta cláusula de Jurisdicción que especifique que las disputas se resuelvan en el domicilio de ANC CAR S.A. (Costa Rica).', 3, 0),
('CTR-2025-0014', 'Análisis Filosófico de Contratos Sociales', 'El documento analiza las teorías de Santo Tomás, Hobbes y Rousseau sobre el contrato social y la organización política. Se enfoca en la naturaleza humana, la ley, la sociedad y la libertad.', 'No detectado', 'Otro', 2, NULL, 'ARCHIVED', NULL, 'LOW', 1, '2025-12-24 14:37:31', '2025-12-24 15:37:41', 'Fuerza Mayor: No se encontró cláusula de Fuerza Mayor., Terminación Anticipada: No se encontró cláusula de Terminación Anticipada., Jurisdicción: No se encontró cláusula de Jurisdicción.', 3, 0),
('CTR-2025-0015', 'Contrato de Prestación de Servicios - Universidad ', 'Contrato de prestación de servicios profesionales como antropólogo para un proyecto de ordenamiento territorial. Vigencia de siete (7) meses.', 'DAVID ALEXANDER ROBLES CHAVEZ', 'Servicios', 2, NULL, 'ARCHIVED', NULL, 'MEDIUM', 1, '2025-12-29 16:38:19', '2025-12-29 16:47:41', 'Fuerza Mayor: No menciona explícitamente \'pandemias\' o \'emergencias de salud\'., Terminación Anticipada: No permite terminación unilateral sin justa causa ni penalidad., Jurisdicción: No se especifica la jurisdicción para la resolución de disputas.', 3, 0),
('CTR-2025-0016', 'Contrato de Concesión de Espectro Radioeléctrico', 'Contrato de concesión para el uso y explotación de espectro radioeléctrico para servicios de telecomunicaciones, incluyendo 5G. Se adjudica a la Cooperativa de Electrificación Rural de San Carlos R.L. tras un proceso de licitación.', 'Cooperativa de Electrificación Rural de San Carlos R.L.', 'Servicios', 2, NULL, 'ARCHIVED', NULL, 'HIGH', 1, '2025-12-29 17:14:21', '2025-12-29 23:06:17', 'Fuerza Mayor: Falta cláusula explícita que mencione \'pandemias\' o \'emergencias de salud\' y permita terminación sin penalidad., Terminación Anticipada: Falta cláusula que permita a ANC CAR S.A. (Administración Concedente) terminar sin justa causa y sin penalidad., Jurisdicción: No se especifica la jurisdicción para disputas; el contrato es entre el Gobierno de Costa Rica y un proveedor costarricense, pero la cláusula de jurisdicción no está presente.', 3, 0),
('CTR-2025-002', 'Revisión de Documento - Universidad Autónoma Intercultural de Sinaloa', 'Documento académico sobre Derecho Civil de los Contratos, no aplica a estándares contractuales de ANC CAR S.A.', 'Universidad Autónoma Intercultural de Sinaloa', 'Otro', 3, NULL, 'OBSERVATIONS', 'USER', 'HIGH', 1, '2025-12-16 19:21:33', '2025-12-16 23:17:30', 'ESTÁNDAR 1 (FUERZA MAYOR): Falta cláusula de exención por fuerza mayor o caso fortuito para ANC CAR S.A., ESTÁNDAR 2 (TERMINACIÓN ANTICIPADA): Falta cláusula que permita a ANC CAR S.A. terminar unilateralmente sin causa y sin indemnización., ESTÁNDAR 3 (CUMPLIMIENTO Y CONFIDENCIALIDAD): No se encuentra cláusula de Confidencialidad.', 3, 0),
('CTR-2025-003', 'Anexo de Contrato de Arrendamiento de Equipos (KYOCERA)', 'Anexo de contrato de arrendamiento de equipos de impresión (KYOCERA) por 3 años, detallando ubicaciones, modelos, precios por clic, consumibles y mantenimiento.', 'CONTIMACA DE COSTA RICA S.A', 'Servicios', 3, NULL, 'OBSERVATIONS', 'USER', 'HIGH', 1, '2025-12-16 19:33:51', '2025-12-17 13:59:46', 'Falta de cláusula de Fuerza Mayor: El documento no exime a ANC CAR S.A. de responsabilidad en caso de fuerza mayor (incluyendo pandemias), lo cual es un requisito inamovible., Falta de cláusula de Terminación por Conveniencia: El documento no otorga a ANC CAR S.A. el derecho a terminar el contrato unilateralmente y sin justa causa sin el pago de indemnización, ni especifica un plazo de preaviso.', 2, 0),
('CTR-2025-004', 'Contrato de Adquisición de Papelería', 'Contrato abierto para la adquisición de papelería entre la Agencia de Protección Sanitaria del Gobierno del Distrito Federal y COMERCIAL LEICAM, S.A. de C.V.', 'COMERCIAL LEICAM, S.A. DE C.V.', 'Servicios', 2, NULL, 'OBSERVATIONS', 'LEGAL', 'LOW', 1, '2025-12-16 19:39:00', '2025-12-17 17:17:27', 'ESTÁNDAR 1 (FUERZA MAYOR): La cláusula Novena define fuerza mayor de forma general, pero no exime explícitamente a ANC CAR S.A. de toda responsabilidad/penalidad, ni menciona específicamente pandemias como ejemplo. Se requiere una redacción más explícita para cumplir con el estándar., ESTÁNDAR 2 (TERMINACIÓN ANTICIPADA): La cláusula Vigésima Segunda permite la terminación anticipada por \'causas debidamente justificadas\' que afecten la seguridad, integridad, o prestación de servicios públicos. No se otorga explícitamente a ANC CAR S.A. el derecho a terminar por conveniencia (sin justa causa) sin pagar indemnización, lo cual es un requisito del estándar.', 2, 0),
('CTR-2025-005', 'Adenda y Acta de Consentimiento para Teletrabajo', 'Documentos relacionados con la adenda y el consentimiento para la modalidad de teletrabajo.', 'Organismo de Evaluación y Fiscalización Ambiental - OEFA', 'Otro', 2, NULL, 'LEGAL_REVIEW_FINAL', 'LEGAL', 'LOW', 1, '2025-12-17 17:12:35', '2025-12-22 16:41:12', 'ESTÁNDAR 1 (FUERZA MAYOR): El documento no exime a ANC CAR S.A. de toda responsabilidad/penalidad por fuerza mayor (incluyendo pandemias). No se incluye el texto esperado o equivalente legal., ESTÁNDAR 2 (TERMINACIÓN ANTICIPADA): El documento no otorga a ANC CAR S.A. el derecho a terminar por conveniencia (sin justa causa) sin pagar indemnización. No se incluye el texto esperado, y se enfoca en los derechos de la trabajadora a solicitar reversión de teletrabajo.', 2, 0),
('CTR-2025-006', 'Contrato de Alquiler Inmobiliario', 'Contrato de alquiler de departamento que no cumple con los estándares inamovibles de ANC CAR S.A. en cuanto a fuerza mayor, terminación anticipada y confidencialidad.', 'Orlando Contreras', 'Alquiler de oficinas', 2, NULL, 'ARCHIVED', NULL, 'MEDIUM', 1, '2025-12-17 17:15:15', '2025-12-23 17:09:16', 'ESTÁNDAR 1 (FUERZA MAYOR): El contrato no exime a ANC CAR S.A. de responsabilidad por fuerza mayor (incluyendo pandemias). No se incluye la redacción esperada ni equivalente legal., ESTÁNDAR 2 (TERMINACIÓN ANTICIPADA): El contrato no otorga a ANC CAR S.A. el derecho a terminar por conveniencia sin justa causa y sin indemnización. No se incluye la redacción esperada., ESTÁNDAR 3 (CUMPLIMIENTO Y CONFIDENCIALIDAD): No se incluye una cláusula de Confidencialidad en el documento.', 3, 0),
('CTR-2025-007', 'Revisión de Documento Legal', 'Análisis de un documento legal para identificar riesgos y asegurar el cumplimiento de los estándares de ANC CAR S.A.', 'EMPRESA DE RECURSOS TECNOLOGICOS S.A. ESP', 'Otro', 2, NULL, 'SIGNATURE', NULL, 'MEDIUM', 1, '2025-12-22 17:14:44', '2025-12-23 14:27:05', 'Falta de cláusula de Fuerza Mayor eximiendo a ANC CAR S.A. de responsabilidad, incluyendo pandemias., Ausencia de cláusula de Terminación Anticipada que permita a ANC CAR S.A. terminar unilateralmente sin justa causa y sin indemnización, notificando con 30 días., No se encontró una cláusula de Confidencialidad explícita en el documento.', 3, 1),
('CTR-2025-008', 'Sentencia Laboral', 'Sentencia laboral que resuelve una demanda por reconocimiento y pago de salarios y prestaciones sociales. El contrato es de término indefinido.', 'EMPRESA DE RECURSOS TECNOLOGICOS S.A. E.S.P.', 'Servicios', 2, NULL, 'OBSERVATIONS', 'USER', 'HIGH', 1, '2025-12-22 17:25:51', '2025-12-22 21:14:18', 'Riesgo 1: No se especifica un plazo de preaviso para la terminación del contrato por parte de ANC CAR S.A. sin penalidades., Riesgo 2: No existe cláusula de Fuerza Mayor que proteja a ANC CAR S.A. ante eventos imprevistos., Riesgo 3: La jurisdicción se establece en Santiago de Cali, lo cual podría ser inconveniente y costoso para ANC CAR S.A., Riesgo 4: No se menciona explícitamente si el contrato tiene renovación automática, lo que podría atrapar a ANC CAR S.A. en términos desfavorables.', 4, 1),
('CTR-2025-009', 'Contrato de Servicios de Mantenimiento', 'Contrato de prestación de servicios de mantenimiento integral para la flota vehicular de ANC CAR S.A. con vigencia de un año, prorrogable.', 'SOLUCIONES TÉCNICAS GLOBALES S.R.L.', 'Servicios', 2, NULL, 'ARCHIVED', NULL, 'LOW', 0, '2025-12-22 19:27:06', '2025-12-22 21:09:23', 'Riesgo 1: No se encontró una cláusula de Fuerza Mayor en el documento., Riesgo 2: No se encontró una cláusula de Terminación Anticipada en el documento., Riesgo 3: No se encontró una cláusula de Jurisdicción en el documento.', 0, 0),
('CTR-2025-010', 'Contrato de Servicios de Mantenimiento', 'Contrato de prestación de servicios de mantenimiento integral para la flota vehicular de ANC CAR S.A. con SOLUCIONES TÉCNICAS GLOBALES S.R.L. Vigencia de 1 año prorrogable.', 'SOLUCIONES TÉCNICAS GLOBALES S.R.L.', 'Servicios', 7, NULL, 'CANCELLED', NULL, 'HIGH', 1, '2025-12-23 17:52:12', '2025-12-23 17:52:22', 'Riesgo 1: Jurisdicción - Las disputas se someterán a los Tribunales de la ciudad de Santo Domingo (República Dominicana), lo cual no corresponde al domicilio de ANC CAR S.A. (Costa Rica).', 1, 0),
('CTR-2025-011', 'Contrato de Mantenimiento Vehicular', 'Contrato de prestación de servicios de mantenimiento integral para la flota vehicular de ANC CAR S.A. con vigencia de un año, prorrogable.', 'SOLUCIONES TÉCNICAS GLOBALES S.R.L.', 'Servicios', 2, NULL, 'OBSERVATIONS', 'USER', 'MEDIUM', 1, '2025-12-23 20:26:47', '2025-12-23 20:46:35', 'Riesgo A: Falta cláusula de Fuerza Mayor que mencione explícitamente \'pandemias\' o \'emergencias de salud\' y permita terminar sin penalidad.', 1, 0),
('CTR-2025-012', 'Contrato de Adquisición de Equipo de Computo', 'Contrato de adquisición de equipo de cómputo entre la Universidad Autónoma de Baja California (UABC) y JMG TECHNOLOGY, S.A. DE C.V. El contrato especifica la compra de equipo de cómputo por un monto de $543,564.00 MXN.', 'JMG TECHNOLOGY, S.A. DE C.V.', 'Otro', 2, NULL, 'ARCHIVED', NULL, 'HIGH', 1, '2025-12-23 20:47:32', '2026-01-06 15:02:43', 'Fuerza Mayor: No se menciona explícitamente \'pandemias\' o \'emergencias de salud\' y no se otorga la facultad de terminar el contrato unilateralmente sin penalidad en caso de fuerza mayor., Terminación Anticipada: No se encuentra una cláusula que permita a UABC terminar el contrato unilateralmente y anticipadamente sin justa causa o por conveniencia y sin penalidad., Jurisdicción: El contrato no especifica la jurisdicción en caso de disputas, pero al ser ambas partes de México (UABC de Mexicali, Baja California y el Proveedor de Mexicali, Baja California), se asume que la jurisdicción sería local y no de Costa Rica.', 3, 0),
('CTR-2026-0001', 'Contrato Abierto para Adquisición de Papelería', 'Contrato abierto para la adquisición de papelería entre la Agencia de Protección Sanitaria del Gobierno del Distrito Federal y COMERCIAL LEICAM, S.A. DE C.V. Establece términos de entrega, pago y condiciones generales.', 'COMERCIAL LEICAM, S.A. DE C.V.', 'Servicios', 3, NULL, 'SIGNATURE', NULL, 'HIGH', 1, '2026-01-07 19:23:09', '2026-01-07 22:04:07', 'Fuerza Mayor: La cláusula de Fuerza Mayor no menciona explícitamente \'pandemias\' o \'emergencias de salud\', y aunque permite la terminación sin responsabilidad, no detalla si es sin penalidad en todos los casos.', 1, 1),
('CTR-2026-0002', 'Contrato Marco de Adquisición de Papelería', 'Contrato para el suministro y adquisición de papelería y artículos de oficina. Establece términos de objeto, entrega, pago y garantías entre el Proveedor y el Cliente.', 'COMERCIAL LEICAM, S.A. DE C.V.', 'Servicios', 9, NULL, 'ARCHIVED', NULL, 'MEDIUM', 1, '2026-01-08 14:31:54', '2026-01-08 14:32:56', 'Riesgo A: La cláusula de Fuerza Mayor no menciona explícitamente \'pandemias\' o \'emergencias de salud\'., Riesgo C: La cláusula de Jurisdicción se somete a los tribunales de los Estados Unidos Mexicanos, no al domicilio de ANC CAR S.A. (Costa Rica).', 2, 0),
('CTR-2026-0003', 'Contrato Marco para Papelería', 'Contrato para el suministro y adquisición de papelería y artículos de oficina. Establece términos de objeto, entrega, pago y garantías entre el Proveedor y el Cliente.', 'COMERCIAL LEICAM, S.A. DE C.V.', 'Servicios', 2, NULL, 'OBSERVATIONS', 'USER', 'MEDIUM', 1, '2026-01-09 14:28:48', '2026-01-09 16:17:37', 'Riesgo A: La cláusula de Fuerza Mayor no menciona explícitamente \'pandemias\' o \'emergencias de salud\'., Riesgo C: La cláusula de Jurisdicción se somete a los tribunales de los Estados Unidos Mexicanos, no al domicilio de ANC CAR S.A. (Costa Rica).', 2, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contract_annexes`
--

CREATE TABLE `contract_annexes` (
  `id` int NOT NULL,
  `contract_id` varchar(50) DEFAULT NULL,
  `doc_type` enum('CODE_CONDUCT','ANTI_CORRUPTION','NDA','OTHER') DEFAULT NULL,
  `doc_label` varchar(100) DEFAULT NULL,
  `template_path` varchar(255) DEFAULT NULL,
  `template_name` varchar(255) DEFAULT NULL,
  `signed_path` varchar(255) DEFAULT NULL,
  `signed_name` varchar(255) DEFAULT NULL,
  `status` enum('PENDING','SIGNED') DEFAULT 'PENDING',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `contract_annexes`
--

INSERT INTO `contract_annexes` (`id`, `contract_id`, `doc_type`, `doc_label`, `template_path`, `template_name`, `signed_path`, `signed_name`, `status`, `updated_at`) VALUES
(2, 'CTR-2025-006', 'ANTI_CORRUPTION', 'Política Anticorrupción', 'uploads/CTR-2025-006_ANNEX_TMPL_ANTI_CORRUPTION.pdf', '000 2011 00671 00 (27 03 2019) acta 007.contrato de trabajo.tiempo suplementario.empresa de empleo temporal. Dr. Luis Gabriel Moreno lovera.pdf', 'uploads/CTR-2025-006_ANN_SIGNED_2.docx', 'CONTRATO DE PRESTACIÓN DE SERVICIOS DE MANTENIMIENTO INTEGRAL (FICTICIO).docx', 'SIGNED', '2025-12-22 19:43:41'),
(3, 'CTR-2025-006', 'CODE_CONDUCT', 'Código de Conducta', 'uploads/CTR-2025-006_ANNEX_TMPL_CODE_CONDUCT.pdf', 'Derecho Civil de los Contratos.pdf', 'uploads/CTR-2025-006_ANN_SIGNED_3.pdf', 'ANC CAR S.A 713 - ANEXO A14 firmado 4.pdf', 'SIGNED', '2025-12-22 19:44:12'),
(4, 'CTR-2025-005', 'CODE_CONDUCT', 'Código de Conducta', 'uploads/CTR-2025-005_ANNEX_TMPL_CODE_CONDUCT.pdf', 'CONTRATO DE SUMINISTRO N 034 DE 2016.pdf', 'uploads/CTR-2025-005_ANN_SIGNED_4.pdf', 'ANC CAR S.A 713 - ANEXO A14 firmado 4.pdf', 'SIGNED', '2025-12-22 16:22:46'),
(5, 'CTR-2025-005', 'NDA', 'NDA', 'uploads/CTR-2025-005_ANNEX_TMPL_NDA.pdf', 'Vehicle Prep Checklist Spanish (1).pdf', 'uploads/CTR-2025-005_ANN_SIGNED_5.xlsx', 'Infracciones_SIAF.xlsx', 'SIGNED', '2025-12-22 16:23:03'),
(6, 'CTR-2025-006', 'NDA', 'NDA', 'uploads/CTR-2025-006_ANNEX_TMPL_NDA.pdf', 'Derecho Civil de los Contratos.pdf', 'uploads/CTR-2025-006_ANN_SIGNED_6.docx', 'CONTRATO DE PRESTACIÓN DE SERVICIOS DE MANTENIMIENTO INTEGRAL (FICTICIO).docx', 'SIGNED', '2025-12-22 19:52:16'),
(8, 'CTR-2025-009', 'ANTI_CORRUPTION', 'Política Anticorrupción', 'uploads/CTR-2025-009_ANNEX_TMPL_ANTI_CORRUPTION.pdf', '000 2011 00671 00 (27 03 2019) acta 007.contrato de trabajo.tiempo suplementario.empresa de empleo temporal. Dr. Luis Gabriel Moreno lovera.pdf', 'uploads/CTR-2025-009_ANN_SIGNED_8.xlsx', 'Scrum for Projects.xlsx', 'SIGNED', '2025-12-22 20:47:47'),
(9, 'CTR-2025-008', 'CODE_CONDUCT', 'Código de Conducta', 'uploads/CTR-2025-008_ANNEX_TMPL_CODE_CONDUCT.docx', 'CONTRATO DE PRESTACIÓN DE SERVICIOS DE MANTENIMIENTO INTEGRAL (FICTICIO).docx', NULL, NULL, 'PENDING', '2025-12-22 21:13:49'),
(10, 'CTR-2025-008', 'NDA', 'NDA', 'uploads/CTR-2025-008_ANNEX_TMPL_NDA.pdf', '000 2011 00671 00 (27 03 2019) acta 007.contrato de trabajo.tiempo suplementario.empresa de empleo temporal. Dr. Luis Gabriel Moreno lovera.pdf', NULL, NULL, 'PENDING', '2025-12-22 21:13:49'),
(11, 'CTR-2025-007', 'CODE_CONDUCT', 'Código de Conducta', 'uploads/CTR-2025-007_ANNEX_TMPL_CODE_CONDUCT.docx', 'CONTRATO DE PRESTACIÓN DE SERVICIOS DE MANTENIMIENTO INTEGRAL (FICTICIO).docx', NULL, NULL, 'PENDING', '2025-12-22 22:27:26'),
(12, 'CTR-2025-007', 'NDA', 'NDA', 'uploads/CTR-2025-007_ANNEX_TMPL_NDA.pdf', '000 2011 00671 00 (27 03 2019) acta 007.contrato de trabajo.tiempo suplementario.empresa de empleo temporal. Dr. Luis Gabriel Moreno lovera.pdf', NULL, NULL, 'PENDING', '2025-12-22 22:27:27'),
(13, 'CTR-2025-007', 'ANTI_CORRUPTION', 'Política Anticorrupción', 'uploads/CTR-2025-007_ANNEX_TMPL_ANTI_CORRUPTION.docx', 'CONTRATO COMERCIAL LEICAM 2.docx', NULL, NULL, 'PENDING', '2025-12-22 22:28:10'),
(16, 'CTR-2025-012', 'CODE_CONDUCT', 'Código de Conducta', 'uploads/CTR-2025-012_ANNEX_TMPL_CODE_CONDUCT.pdf', 'CONTRATO DE PRESTACION DE SERVICIOS N 046 DE 2017.pdf', 'uploads/CTR-2025-012_ANN_SIGNED_16.docx', 'CONTRATO DE PRESTACIÓN DE SERVICIOS DE MANTENIMIENTO INTEGRAL 0 (FICTICIO).docx', 'SIGNED', '2026-01-06 14:59:22'),
(17, 'CTR-2025-0014', 'CODE_CONDUCT', 'Código de Conducta', 'uploads/CTR-2025-0014_ANNEX_TMPL_CODE_CONDUCT.docx', 'El contrato social..docx', 'uploads/CTR-2025-0014_ANN_SIGNED_17.docx', 'El contrato social..docx', 'SIGNED', '2025-12-24 14:51:10'),
(18, 'CTR-2026-0001', 'CODE_CONDUCT', 'Código de Conducta', 'uploads/CTR-2026-0001_ANNEX_TMPL_CODE_CONDUCT.docx', 'CONTRATO MARCO DE ADQUISICIÓN DE PAPELERÍA ANC.docx', 'uploads/CTR-2026-0001_ANN_SIGNED_18.docx', 'CONTRATO MARCO DE ADQUISICIÓN DE PAPELERÍA ANC 2.docx', 'SIGNED', '2026-01-07 22:00:44'),
(19, 'CTR-2026-0001', 'NDA', 'NDA', 'uploads/CTR-2026-0001_ANNEX_TMPL_NDA.docx', 'CONTRATO COMERCIAL LEICAM 2 (1) (1).docx', NULL, NULL, 'PENDING', '2026-01-07 21:59:48'),
(20, 'CTR-2026-0003', 'CODE_CONDUCT', 'Código de Conducta', 'uploads/CTR-2026-0003_ANNEX_TMPL_CODE_CONDUCT.docx', 'CONTRATO COMERCIAL LEICAM 2 (1).docx', NULL, NULL, 'PENDING', '2026-01-09 16:07:18'),
(21, 'CTR-2026-0003', 'ANTI_CORRUPTION', 'Política Anticorrupción', 'uploads/CTR-2026-0003_ANNEX_TMPL_ANTI_CORRUPTION.docx', 'CONTRATO MARCO DE ADQUISICIÓN DE PAPELERÍA ANC.docx', NULL, NULL, 'PENDING', '2026-01-09 16:07:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contract_versions`
--

CREATE TABLE `contract_versions` (
  `id` int NOT NULL,
  `contract_id` varchar(50) DEFAULT NULL,
  `version_number` int NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_hash` char(64) DEFAULT NULL,
  `uploaded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `contract_versions`
--

INSERT INTO `contract_versions` (`id`, `contract_id`, `version_number`, `file_name`, `file_path`, `file_hash`, `uploaded_by`, `created_at`) VALUES
(1, 'CTR-2025-001', 1, 'CONTRATO.pdf', 'uploads/CTR-2025-001_v1.pdf', '1821b8a39be6799cb0bd194bfd950abb678de906e3442c535752efac2cb40043', 2, '2025-12-16 19:19:43'),
(2, 'CTR-2025-002', 1, 'Derecho Civil de los Contratos.pdf', 'uploads/CTR-2025-002_v1.pdf', '856c813ea05d316582e238fb72e498cdabb6ce7a0d2e0b175154b9fdeb751925', 3, '2025-12-16 19:21:33'),
(3, 'CTR-2025-003', 1, 'ANC CAR S.A 713 - ANEXO A14 firmado 4.pdf', 'uploads/CTR-2025-003_v1.pdf', '8c086d0e477b3e725e2062d1f111b7b961cd2847f2ee0ca8433222ff88e54af6', 3, '2025-12-16 19:33:51'),
(4, 'CTR-2025-004', 1, 'CONTRATO COMERCIAL LEICAM.docx', 'uploads/CTR-2025-004_v1.docx', 'f789121a56f326325ef36843eb9a0e566751ab8fd424c085d2d2f5fa24c8fd8c', 2, '2025-12-16 19:39:00'),
(5, 'CTR-2025-001', 2, 'CONTRATO.pdf', 'uploads/CTR-2025-001_v2.pdf', '1821b8a39be6799cb0bd194bfd950abb678de906e3442c535752efac2cb40043', 2, '2025-12-17 14:45:16'),
(6, 'CTR-2025-004', 2, 'CONTRATO COMERCIAL LEICAM (1).docx', 'uploads/CTR-2025-004_v2.docx', 'f789121a56f326325ef36843eb9a0e566751ab8fd424c085d2d2f5fa24c8fd8c', 2, '2025-12-17 15:17:26'),
(7, 'CTR-2025-001', 3, 'CONTRATO.pdf', 'uploads/CTR-2025-001_v3_SIGNED.pdf', '1821b8a39be6799cb0bd194bfd950abb678de906e3442c535752efac2cb40043', 7, '2025-12-17 15:28:21'),
(8, 'CTR-2025-005', 1, 'Modelo de Contrato de Teletrabajo.pdf', 'uploads/CTR-2025-005_v1.pdf', '72e03a57672efddbe7d9bca124ca7c0611d1ab70c703246350f49f9c97556527', 2, '2025-12-17 17:12:35'),
(9, 'CTR-2025-006', 1, 'Contrato de Alquiler de Apartamento.docx', 'uploads/CTR-2025-006_v1.docx', 'aa7bc36287d0024cfa4528fc27f0209a558c1a5dc0029503768619446e4ed09d', 2, '2025-12-17 17:15:15'),
(10, 'CTR-2025-004', 3, 'CONTRATO COMERCIAL LEICAM (1).docx', 'uploads/CTR-2025-004_v3.docx', 'f789121a56f326325ef36843eb9a0e566751ab8fd424c085d2d2f5fa24c8fd8c', 2, '2025-12-17 17:17:27'),
(11, 'CTR-2025-004', 4, 'CONTRATO COMERCIAL LEICAM 2.docx', 'uploads/CTR-2025-004_v4.docx', '7416447e19a6cd5aec8c925786c976e85a90898fbf1b9ba27556d7427f7b773d', 2, '2025-12-17 17:20:44'),
(12, 'CTR-2025-007', 1, '000 2011 00671 00 (27 03 2019) acta 007.contrato de trabajo.tiempo suplementario.empresa de empleo temporal. Dr. Luis Gabriel Moreno lovera.pdf', 'uploads/CTR-2025-007_v1.pdf', '6224cb88b722e7cd7e3cdd1bac85af4532403f14ffbcd86f1faaa2a648530e40', 2, '2025-12-22 17:14:44'),
(13, 'CTR-2025-008', 1, '000 2011 00671 00 (27 03 2019) acta 007.contrato de trabajo.tiempo suplementario.empresa de empleo temporal. Dr. Luis Gabriel Moreno lovera.pdf', 'uploads/CTR-2025-008_v1.pdf', '6224cb88b722e7cd7e3cdd1bac85af4532403f14ffbcd86f1faaa2a648530e40', 2, '2025-12-22 17:25:51'),
(14, 'CTR-2025-009', 1, 'CONTRATO DE PRESTACIÓN DE SERVICIOS DE MANTENIMIENTO INTEGRAL (FICTICIO).docx', 'uploads/CTR-2025-009_v1.docx', '8575962a75e528075130b17439f475f998dc4a1ca4541ef08ec53da078619236', 2, '2025-12-22 19:27:06'),
(15, 'CTR-2025-009', 2, 'CONTRATO COMERCIAL LEICAM 2.docx', 'uploads/CTR-2025-009_v2_SIGNED.docx', '7416447e19a6cd5aec8c925786c976e85a90898fbf1b9ba27556d7427f7b773d', 1, '2025-12-22 21:02:48'),
(16, 'CTR-2025-007', 2, 'CONTRATO DE PRESTACIÓN DE SERVICIOS DE MANTENIMIENTO INTEGRAL (FICTICIO).docx', 'uploads/CTR-2025-007_v2.docx', '8575962a75e528075130b17439f475f998dc4a1ca4541ef08ec53da078619236', 2, '2025-12-22 22:51:49'),
(17, 'CTR-2025-007', 3, 'CONTRATO DE PRESTACION DE SERVICIOS N 046 DE 2017.pdf', 'uploads/CTR-2025-007_v3_SIGNED.pdf', 'c1ea3974aac36dd82863e8366cd65dd66cee7fdca32bee1e23ed392f7dd08e7c', 7, '2025-12-23 14:27:05'),
(18, 'CTR-2025-006', 2, 'CONTRATO DE PRESTACION DE SERVICIOS N 046 DE 2017.pdf', 'uploads/CTR-2025-006_v2_SIGNED.pdf', 'c1ea3974aac36dd82863e8366cd65dd66cee7fdca32bee1e23ed392f7dd08e7c', 7, '2025-12-23 17:09:10'),
(19, 'CTR-2025-010', 1, 'CONTRATO DE PRESTACIÓN DE SERVICIOS DE MANTENIMIENTO INTEGRAL 1 (FICTICIO) .docx', 'uploads/CTR-2025-010_v1.docx', 'dc217ddd12a3864af96eefc3343b61f63ac41d46429f7e61a1127f7c7b267bd5', 7, '2025-12-23 17:52:12'),
(20, 'CTR-2025-011', 1, 'CONTRATO DE PRESTACIÓN DE SERVICIOS DE MANTENIMIENTO INTEGRAL 0 (FICTICIO).docx', 'uploads/CTR-2025-011_v1.docx', '02a7691b4e239997205bc3786dc23489570f8a296416e9cad2ababf449311f1b', 2, '2025-12-23 20:26:47'),
(21, 'CTR-2025-012', 1, 'CONTRATO.pdf', 'uploads/CTR-2025-012_v1.pdf', '1821b8a39be6799cb0bd194bfd950abb678de906e3442c535752efac2cb40043', 2, '2025-12-23 20:47:32'),
(22, 'CTR-2025-0013', 1, 'Derecho Civil de los Contratos.pdf', 'uploads/CTR-2025-0013_v1.pdf', '856c813ea05d316582e238fb72e498cdabb6ce7a0d2e0b175154b9fdeb751925', 2, '2025-12-23 21:00:32'),
(23, 'CTR-2025-012', 2, 'PRODUCT BACKLOG INFRACCIONES.xlsx', 'uploads/CTR-2025-012_v2.xlsx', '76b6a45c62acf41280d94309d6402f478c99346995b1f3d60afa9ed6d53c2806', 2, '2025-12-23 21:02:28'),
(24, 'CTR-2025-012', 3, 'CONTRATO DE PRESTACION DE SERVICIOS N 046 DE 2017.pdf', 'uploads/CTR-2025-012_v3_SIGNED.pdf', 'c1ea3974aac36dd82863e8366cd65dd66cee7fdca32bee1e23ed392f7dd08e7c', 7, '2025-12-23 22:14:38'),
(25, 'CTR-2025-012', 4, 'ANC CAR S.A 713 - ANEXO A14 firmado 4.pdf', 'uploads/CTR-2025-012_v4_SIGNED.pdf', '8c086d0e477b3e725e2062d1f111b7b961cd2847f2ee0ca8433222ff88e54af6', 7, '2025-12-23 22:25:17'),
(26, 'CTR-2025-0014', 1, 'El contrato social..docx', 'uploads/CTR-2025-0014_v1.docx', '7538396b4f44b31a9e46bbecbc70d81b1bb439e2269868487f060b71ce3f73e2', 2, '2025-12-24 14:37:31'),
(27, 'CTR-2025-0014', 2, 'El contrato social 1.docx', 'uploads/CTR-2025-0014_v2.docx', 'a18cbe9ee9d761a3b449c7fce329f3667e0cf5c72deb2313239519a8b9735b53', 2, '2025-12-24 14:46:19'),
(28, 'CTR-2025-0014', 3, 'El contrato social 1.docx', 'uploads/CTR-2025-0014_v3.docx', 'ff8d446a5aacf6edd5c69379fa34a615ec0817e3a82a06c73d1f87998d11150e', 2, '2025-12-24 14:51:01'),
(29, 'CTR-2025-0014', 4, 'El contrato social 1 (1).docx', 'uploads/CTR-2025-0014_v4_SIGNED.docx', 'a18cbe9ee9d761a3b449c7fce329f3667e0cf5c72deb2313239519a8b9735b53', 7, '2025-12-24 15:37:33'),
(30, 'CTR-2025-0015', 1, 'CONTRATO DE PRESTACION DE SERVICIOS N 046 DE 2017.pdf', 'uploads/CTR-2025-0015_v1.pdf', 'c1ea3974aac36dd82863e8366cd65dd66cee7fdca32bee1e23ed392f7dd08e7c', 2, '2025-12-29 16:38:19'),
(31, 'CTR-2025-0015', 2, 'CONTRATO DE PRESTACION DE SERVICIOS N 046 DE 2017.pdf', 'uploads/CTR-2025-0015_v2.pdf', 'df4825d32c249942b53f4934eab05dc06056d141ce2f1856a7de48bdff0c3ea0', 2, '2025-12-29 16:39:58'),
(32, 'CTR-2025-0015', 3, 'CONTRATO DE PRESTACION DE SERVICIOS N 046 DE 2017.pdf', 'uploads/CTR-2025-0015_v3.pdf', 'c1ea3974aac36dd82863e8366cd65dd66cee7fdca32bee1e23ed392f7dd08e7c', 2, '2025-12-29 16:44:52'),
(33, 'CTR-2025-0015', 4, 'CONTRATO DE PRESTACION DE SERVICIOS N 046 DE 2017.pdf', 'uploads/CTR-2025-0015_v4_SIGNED.pdf', 'c1ea3974aac36dd82863e8366cd65dd66cee7fdca32bee1e23ed392f7dd08e7c', 7, '2025-12-29 16:47:38'),
(34, 'CTR-2025-0016', 1, 'Anexo 7 - Modelo contrato 2024LY-000001-SUTEL - Coopelesca.pdf', 'uploads/CTR-2025-0016_v1.pdf', 'e901d87b9ee6b78c46ac4cc11860db2b3beeddbe7ae1880a00e09c23fe79ccb1', 2, '2025-12-29 17:14:21'),
(35, 'CTR-2025-0016', 2, 'Anexo 7 - Modelo contrato 2024LY-000001-SUTEL - Coopelesca.pdf', 'uploads/CTR-2025-0016_v2.pdf', '67aa69e6fa58d8b67e06f1096803c0e170edcc4d2126cb56096c8ef1ff7ed6cb', 2, '2025-12-29 17:18:44'),
(36, 'CTR-2025-0016', 3, 'Anexo 7 - Modelo contrato 2024LY-000001-SUTEL - Coopelesca.pdf', 'uploads/CTR-2025-0016_v3.pdf', '32a6642e8cf9fc1403dddcc2ab0483dac66841c7aed6d96f7aca176fbf8f204b', 2, '2025-12-29 20:43:51'),
(37, 'CTR-2025-0016', 4, 'Anexo 7 - 2 Modelo contrato 2024LY-000001-SUTEL - Coopelesca.pdf', 'uploads/CTR-2025-0016_v4.pdf', 'ede3bc005123b1d799a2a133bd35489d510425cd513a4c62c270c5af41cf55e5', 2, '2025-12-29 20:47:36'),
(38, 'CTR-2025-0016', 5, 'Anexo 7 - 2 Modelo contrato 2024LY-000001-SUTEL - Coopelesca.pdf', 'uploads/CTR-2025-0016_v5.pdf', '398da24a7b11cbcd3ea441af3844445bcf4d91d7c6c2145ea8134ed1a82f2983', 2, '2025-12-29 21:12:04'),
(39, 'CTR-2025-0016', 6, 'Anexo 7 - 2 Modelo contrato 2024LY-000001-SUTEL - Coopelesca.pdf', 'uploads/CTR-2025-0016_v6.pdf', '8c75e37cad31e6ca6e55b4165a384c14e8aa65ae8687d62255f493aee916d1ff', 2, '2025-12-29 21:14:34'),
(40, 'CTR-2025-0016', 7, 'Anexo 7 - 2 Modelo contrato 2024LY-000001-SUTEL - Coopelesca.docx', 'uploads/CTR-2025-0016_v7.docx', 'ed9c6adf79a605f17be1bd8bd09bb08c23b1135001abc1099abab35cb4941464', 2, '2025-12-29 21:21:15'),
(41, 'CTR-2025-0016', 8, 'Anexo 7 - 2 Modelo contrato 2024LY-000001-SUTEL - Coopelesca.docx', 'uploads/CTR-2025-0016_v8_SIGNED.docx', 'ed9c6adf79a605f17be1bd8bd09bb08c23b1135001abc1099abab35cb4941464', 7, '2025-12-29 22:19:17'),
(42, 'CTR-2026-0001', 1, 'CONTRATO COMERCIAL LEICAM 2 (X).docx', 'uploads/CTR-2026-0001_v1.docx', '091bf1d57494f14de44a1724736a6e52300f41d4f01ffaef818f6c070f541956', 3, '2026-01-07 19:23:09'),
(43, 'CTR-2026-0001', 2, 'CONTRATO COMERCIAL LEICAM 2 (1).docx', 'uploads/CTR-2026-0001_v2.docx', '6880ba7518ef6cf13b5dc0ddba19b64f0c3954f7b20b972bb67db97361bc80e2', 3, '2026-01-07 19:24:41'),
(44, 'CTR-2026-0001', 3, 'CONTRATO MARCO DE ADQUISICIÓN DE PAPELERÍA ANC 2.docx', 'uploads/CTR-2026-0001_v3_SIGNED.docx', '4f66e212ea034488d8ddb50f607a60fad39446c0a26fb04aec3d9f0fee604799', 9, '2026-01-07 22:04:07'),
(45, 'CTR-2026-0002', 1, 'CONTRATO MARCO DE ADQUISICIÓN DE PAPELERÍA ANC.docx', 'uploads/CTR-2026-0002_v1.docx', '61e7bcf55b9ec63c5327c2acb0ba79891c047e7644edcdae8f073d3edd2a7b73', 9, '2026-01-08 14:31:54'),
(46, 'CTR-2026-0002', 2, 'CONTRATO MARCO DE ADQUISICIÓN DE PAPELERÍA ANC.docx', 'uploads/CTR-2026-0002_v2_SIGNED.docx', '61e7bcf55b9ec63c5327c2acb0ba79891c047e7644edcdae8f073d3edd2a7b73', 9, '2026-01-08 14:32:19'),
(47, 'CTR-2026-0003', 1, 'CONTRATO MARCO DE ADQUISICIÓN DE PAPELERÍA ANC.docx', 'uploads/CTR-2026-0003_v1.docx', '61e7bcf55b9ec63c5327c2acb0ba79891c047e7644edcdae8f073d3edd2a7b73', 2, '2026-01-09 14:28:48'),
(48, 'CTR-2026-0003', 2, 'CONTRATO MARCO DE ADQUISICIÓN DE PAPELERÍA ANC 2.docx', 'uploads/CTR-2026-0003_v2.docx', '8f7934c5322fe916460f8d77e4c9efbe3f09893ef4f752a516e1a05872adafb0', 2, '2026-01-09 14:37:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `observations`
--

CREATE TABLE `observations` (
  `id` int NOT NULL,
  `contract_id` varchar(50) DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `comment_text` text,
  `type` enum('LEGAL','GENERAL') DEFAULT 'GENERAL',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `observations`
--

INSERT INTO `observations` (`id`, `contract_id`, `user_id`, `comment_text`, `type`, `created_at`) VALUES
(1, 'CTR-2025-004', 7, 'Se requiere una redacción más explícita para cumplir con el estándar., ESTÁNDAR 2 TERMINACIÓN ANTICIPADA', 'LEGAL', '2025-12-16 19:49:51'),
(2, 'CTR-2025-002', 7, 'Falta cláusula que permita a ANC CAR S.A. terminar unilateralmente sin causa y sin indemnización.', 'LEGAL', '2025-12-16 23:17:30'),
(3, 'CTR-2025-003', 7, 'Falta de cláusula de Fuerza Mayor', 'LEGAL', '2025-12-17 13:59:46'),
(4, 'CTR-2025-001', 9, 'El contrato no exime a ANC CAR S.A. de responsabilidad por fuerza mayor', 'LEGAL', '2025-12-17 14:14:44'),
(5, 'CTR-2025-001', 7, 'El contrato no otorga a ANC CAR S.A. el derecho a terminar unilateralmente por conveniencia sin justa causa y sin indemnización', 'LEGAL', '2025-12-17 14:42:57'),
(6, 'CTR-2025-001', 2, 'versión actualizada', 'GENERAL', '2025-12-17 14:45:16'),
(7, 'CTR-2025-004', 7, 'los documentos son idénticos, no has subido un documento actualizado', 'LEGAL', '2025-12-17 15:19:04'),
(8, 'CTR-2025-001', 7, 'Documento final firmado subido. ', 'LEGAL', '2025-12-17 15:28:21'),
(9, 'CTR-2025-004', 2, 'versión corregida', 'GENERAL', '2025-12-17 17:20:44'),
(10, 'CTR-2025-009', 1, 'Documento final firmado subido. ', 'LEGAL', '2025-12-22 21:02:48'),
(11, 'CTR-2025-008', 7, 'No se especifica un plazo de preaviso para la terminación del contrato', 'LEGAL', '2025-12-22 21:14:18'),
(12, 'CTR-2025-007', 7, 'Falta de cláusula de Fuerza Mayor', 'LEGAL', '2025-12-22 22:26:04'),
(13, 'CTR-2025-007', 7, 'Documento final firmado subido. ', 'LEGAL', '2025-12-23 14:27:05'),
(14, 'CTR-2025-006', 7, 'Documento final firmado subido. ', 'LEGAL', '2025-12-23 17:09:10'),
(15, 'CTR-2025-011', 7, 'Falta cláusula de Fuerza Mayor que mencione explícitamente \'pandemias\' o \'emergencias de salud\' y permita terminar sin penalidad.', 'LEGAL', '2025-12-23 20:39:56'),
(16, 'CTR-2025-011', 9, 'corregir proveedor', 'LEGAL', '2025-12-23 20:46:35'),
(17, 'CTR-2025-012', 2, 'favor dar prioridad', 'GENERAL', '2025-12-23 20:51:54'),
(18, 'CTR-2025-012', 9, 'No se encuentra una cláusula que permita a ANC terminar el contrato unilateralmente', 'LEGAL', '2025-12-23 20:54:21'),
(19, 'CTR-2025-012', 7, 'Documento final firmado subido. ', 'LEGAL', '2025-12-23 22:14:38'),
(20, 'CTR-2025-012', 7, 'Documento final firmado subido. ', 'LEGAL', '2025-12-23 22:25:17'),
(21, 'CTR-2025-0014', 7, 'No se encontró cláusula de Fuerza Mayor.', 'LEGAL', '2025-12-24 14:42:07'),
(22, 'CTR-2025-0014', 7, 'No se encontró cláusula de Terminación Anticipada', 'LEGAL', '2025-12-24 14:50:17'),
(23, 'CTR-2025-0014', 7, 'Documento final firmado subido. ', 'LEGAL', '2025-12-24 15:37:33'),
(24, 'CTR-2025-0015', 7, 'No permite terminación unilateral sin justa causa ni penalidad.', 'LEGAL', '2025-12-29 16:39:19'),
(25, 'CTR-2025-0015', 7, 'No menciona explícitamente \'pandemias\' o \'emergencias de salud', 'LEGAL', '2025-12-29 16:42:28'),
(26, 'CTR-2025-0015', 7, 'Documento final firmado subido. ', 'LEGAL', '2025-12-29 16:47:38'),
(27, 'CTR-2025-0016', 7, 'No se especifica la jurisdicción para disputas', 'LEGAL', '2025-12-29 17:15:31'),
(28, 'CTR-2025-0016', 2, 'cambio menor un punto final en comparación con la versión anterior', 'GENERAL', '2025-12-29 21:14:34'),
(29, 'CTR-2025-0016', 2, 'comparación entre pdf y docx', 'GENERAL', '2025-12-29 21:21:15'),
(30, 'CTR-2025-0016', 7, 'Documento final firmado subido. ', 'LEGAL', '2025-12-29 22:19:17'),
(31, 'CTR-2026-0001', 9, 'La cláusula de Fuerza Mayor no menciona explícitamente \'pandemias\' o \'emergencias de salud\',', 'LEGAL', '2026-01-07 19:23:50'),
(32, 'CTR-2026-0001', 9, 'Documento final firmado subido. ', 'LEGAL', '2026-01-07 22:04:07'),
(33, 'CTR-2026-0002', 9, 'Documento final firmado subido. ', 'LEGAL', '2026-01-08 14:32:19'),
(34, 'CTR-2026-0003', 7, 'La cláusula de Jurisdicción se somete a los tribunales de los Estados Unidos Mexicanos, no al domicilio de ANC CAR S.A. (Costa Rica).', 'LEGAL', '2026-01-09 14:34:19'),
(35, 'CTR-2026-0003', 7, 'La cláusula de Fuerza Mayor no menciona explícitamente \'pandemias\' o \'emergencias de salud\'.', 'LEGAL', '2026-01-09 15:45:36'),
(36, 'CTR-2026-0003', 2, 'x', 'GENERAL', '2026-01-09 16:05:07'),
(37, 'CTR-2026-0003', 7, 'y', 'LEGAL', '2026-01-09 16:10:35'),
(38, 'CTR-2026-0003', 2, 'z', 'GENERAL', '2026-01-09 16:14:46'),
(39, 'CTR-2026-0003', 7, 'A', 'LEGAL', '2026-01-09 16:17:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role` enum('SUPER_ADMIN','DEPT_HEAD','LEGAL','MANAGER') NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notification_preference` enum('NONE','INVOLVED','ALL') DEFAULT 'INVOLVED',
  `can_manage_users` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `email`, `role`, `department`, `is_active`, `created_at`, `notification_preference`, `can_manage_users`) VALUES
(1, 'admin', '$2y$10$zKquppFvq.NxtYvcpvtxzeVeI7UU8kHuGdhbC4Vo/pVi5Tss6mn.G', 'Admin', 'admin@legalflow.local', 'SUPER_ADMIN', 'IT', 1, '2025-11-28 19:23:58', 'NONE', 0),
(2, 'William', '$2y$10$eRrglkQ1eb7vz5biLBUSruTypLihPqDS3GAr/TzZ3mYJezW3dNOfa', 'William Valverde', 'william.valverde@grupoanc.com', 'DEPT_HEAD', 'Contabilidad', 1, '2025-11-28 22:23:35', 'INVOLVED', 1),
(3, 'Arnoldo', '$2y$10$IG6kfLCYi0SmkLZm0ePU7.t2mFGYoV1F7J4H4SQmyPojxR1Tz2S..', 'Arnoldo Montero', 'arnoldo.montero@grupoancccc.com', 'DEPT_HEAD', 'IT', 1, '2025-11-28 23:16:44', 'INVOLVED', 0),
(6, 'Auxiliadora', '$2y$10$guNd1U0tZfdN1VTq0v.4le2m7sUNFbkaJ0OggrgdXfjJexuTp0xFm', 'Auxiliadora Alfaro', 'auxiliadora.alfaro@ehiglobal.crr', 'MANAGER', 'Gerente Financiero', 1, '2025-12-02 17:45:06', 'INVOLVED', 0),
(7, 'Maria', '$2y$10$yVX3sBK4zTxYOV5CEFSj1O3/6b6Uhj3BGvquFrLWy2p26f8flHNYS', 'María Fernanda Fallas', 'williamrvv@hotmail.com', 'LEGAL', 'Legal', 1, '2025-12-02 22:27:54', 'INVOLVED', 0),
(9, 'fernanda', '$2y$10$uekcmThWFDaFkZPf3vicpOZaFiU3evDUWGVWKWUKig3XhQDQI7uCu', 'Fernanda Gomez Zamora', 'williamrvv@gmail.com', 'LEGAL', 'Legal', 1, '2025-12-08 19:19:18', 'INVOLVED', 0);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indices de la tabla `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `initiator_id` (`initiator_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `fk_approver` (`current_approver_id`);

--
-- Indices de la tabla `contract_annexes`
--
ALTER TABLE `contract_annexes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`);

--
-- Indices de la tabla `contract_versions`
--
ALTER TABLE `contract_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indices de la tabla `observations`
--
ALTER TABLE `observations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT de la tabla `contract_annexes`
--
ALTER TABLE `contract_annexes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `contract_versions`
--
ALTER TABLE `contract_versions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT de la tabla `observations`
--
ALTER TABLE `observations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  ADD CONSTRAINT `audit_logs_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_ibfk_1` FOREIGN KEY (`initiator_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_approver` FOREIGN KEY (`current_approver_id`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `contract_annexes`
--
ALTER TABLE `contract_annexes`
  ADD CONSTRAINT `contract_annexes_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `contract_versions`
--
ALTER TABLE `contract_versions`
  ADD CONSTRAINT `contract_versions_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  ADD CONSTRAINT `contract_versions_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `observations`
--
ALTER TABLE `observations`
  ADD CONSTRAINT `observations_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  ADD CONSTRAINT `observations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 19-12-2025 a las 11:22:49
-- Versión del servidor: 11.4.8-MariaDB-cll-lve-log
-- Versión de PHP: 8.4.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `grupoam6_repfull`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registros`
--

CREATE TABLE `registros` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) DEFAULT 'Sistema',
  `codigo_contenedor` varchar(11) NOT NULL,
  `peso` decimal(10,2) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `estatus` varchar(20) DEFAULT 'OK'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registros_manzanillo`
--

CREATE TABLE `registros_manzanillo` (
  `id` int(11) NOT NULL,
  `unidad` varchar(100) DEFAULT NULL,
  `fecha_carga` varchar(20) DEFAULT NULL,
  `hora_carga` varchar(10) DEFAULT NULL,
  `litros_diesel` decimal(10,2) DEFAULT 0.00,
  `nombre_archivo_origen` varchar(255) DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_config`
--

CREATE TABLE `system_config` (
  `id` int(11) NOT NULL,
  `access_pin_hash` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trip_reports`
--

CREATE TABLE `trip_reports` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `unit_number` varchar(50) DEFAULT 'N/D',
  `report_date` varchar(100) DEFAULT 'N/D',
  `report_time` varchar(50) DEFAULT 'N/D',
  `km_recorrido` varchar(50) DEFAULT 'N/D',
  `distancia_conducida` varchar(50) DEFAULT 'N/D',
  `distancia_top_gear` varchar(50) DEFAULT 'N/D',
  `distancia_cambio_bajo` varchar(50) DEFAULT 'N/D',
  `combustible_viaje` varchar(50) DEFAULT 'N/D',
  `combustible_manejando` varchar(50) DEFAULT 'N/D',
  `combustible_ralenti` varchar(50) DEFAULT 'N/D',
  `def_usado` varchar(50) DEFAULT 'N/D',
  `tiempo_viaje` varchar(50) DEFAULT 'N/D',
  `tiempo_manejando` varchar(50) DEFAULT 'N/D',
  `tiempo_ralenti` varchar(50) DEFAULT 'N/D',
  `tiempo_top_gear` varchar(50) DEFAULT 'N/D',
  `tiempo_crucero` varchar(50) DEFAULT 'N/D',
  `tiempo_exceso_velocidad` varchar(50) DEFAULT 'N/D',
  `velocidad_maxima` varchar(50) DEFAULT 'N/D',
  `rpm_maxima` varchar(50) DEFAULT 'N/D',
  `velocidad_promedio` varchar(50) DEFAULT 'N/D',
  `rendimiento_viaje` varchar(50) DEFAULT 'N/D',
  `rendimiento_manejando` varchar(50) DEFAULT 'N/D',
  `factor_carga` varchar(50) DEFAULT 'N/D',
  `eventos_exceso_velocidad` varchar(50) DEFAULT 'N/D',
  `eventos_frenado` varchar(50) DEFAULT 'N/D',
  `tiempo_neutro_coasting` varchar(50) DEFAULT 'N/D',
  `tiempo_pto` varchar(50) DEFAULT 'N/D',
  `combustible_pto` varchar(50) DEFAULT 'N/D',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `km_hubodometro` varchar(50) DEFAULT NULL,
  `travesia_km` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `registros`
--
ALTER TABLE `registros`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `registros_manzanillo`
--
ALTER TABLE `registros_manzanillo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unidad_fecha_hora` (`unidad`,`fecha_carga`,`hora_carga`);

--
-- Indices de la tabla `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `trip_reports`
--
ALTER TABLE `trip_reports`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `registros`
--
ALTER TABLE `registros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `registros_manzanillo`
--
ALTER TABLE `registros_manzanillo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `trip_reports`
--
ALTER TABLE `trip_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

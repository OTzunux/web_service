
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


--
-- Estructura de tabla para la tabla `boletas_asignacion_api`
--

CREATE TABLE `boletas_asignacion_api` (
  `bolasig_codigo` int NOT NULL,
  `bolasig_codigo_banco` int NOT NULL,
  `bolasig_boleta_codigo` int NOT NULL,
  `bolasig_cuenta` int NOT NULL,
  `bolasig_codigo_alumno` int NOT NULL,
  `bolasig_cui_alumno` bigint NOT NULL,
  `bolasig_monto` double NOT NULL,
  `bolasig_pagado` double NOT NULL,
  `bolasig_fecha` date NOT NULL,
  `bolasig_agencia` int NOT NULL,
  `bolasig_autobanco` int NOT NULL,
  `bolasig_usuario` int NOT NULL,
  `bolasig_terminal` varchar(250) CHARACTER SET utf8 COLLATE utf8_spanish_ci NOT NULL,
  `bolasig_situacion` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `boletas_asignacion_api`
--
ALTER TABLE `boletas_asignacion_api`
  ADD PRIMARY KEY (`bolasig_codigo`,`bolasig_codigo_banco`,`bolasig_boleta_codigo`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

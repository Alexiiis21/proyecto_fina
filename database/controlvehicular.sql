CREATE DATABASE IF NOT EXISTS controlvehicular;
USE controlvehicular;
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 08-05-2025 a las 22:50:03
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `controlvehicular31`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `centrosverificacion`
--

CREATE TABLE `centrosverificacion` (
  `ID_Centro_Verificacion` int(11) NOT NULL,
  `Nombre` varchar(255) NOT NULL,
  `Direccion` int(11) NOT NULL,
  `NumeroCentroVerificacion` varchar(50) NOT NULL,
  `TipoCentro` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `centrosverificacion`
--

INSERT INTO `centrosverificacion` (`ID_Centro_Verificacion`, `Nombre`, `Direccion`, `NumeroCentroVerificacion`, `TipoCentro`) VALUES
(123, 'bryan cordoba', 11, '12', 'verificacion');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conductores`
--

CREATE TABLE `conductores` (
  `ID_Conductor` int(11) NOT NULL,
  `Nombre` varchar(255) NOT NULL,
  `CURP` varchar(18) NOT NULL,
  `RFC` varchar(13) NOT NULL,
  `Telefono` varchar(15) NOT NULL,
  `CorreoElectronico` varchar(100) NOT NULL,
  `ID_Domicilio` int(11) NOT NULL,
  `Licencia` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `conductores`
--

INSERT INTO `conductores` (`ID_Conductor`, `Nombre`, `CURP`, `RFC`, `Telefono`, `CorreoElectronico`, `ID_Domicilio`, `Licencia`) VALUES
(1, 'Bryan Michel Gonzalez Cordoba', 'GOCB000823HQTNRRA9', 'GOCB00082382A', '4421714729', 'Bryan_cordoba@outlook.com', 1, 1),
(444, 'Alexis Cárdenas Camacho', 'CXCA050621HQTRMLA8', 'GOCB00082382A', '4425405858', 'alexisccaa@gmail.com', 444, 444),
(777, 'David Mata Guerra', 'MAGD0520922HNETRVA', 'MAGD0520922', '4737404037', 'DAVID_MATA@GMAIL.COM', 11, 333),
(789, 'bryan cordoba', 'GOCB000823HQTNRRA9', 'GOCB00082382A', '4421714729', 'Bryan_cordoba@outlook.com', 11, 22);

ALTER TABLE conductores
ADD COLUMN ImagenPerfil VARCHAR(255) NULL 
ADD COLUMN Firma VARCHAR(255) NULL 

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `domicilios`
--

CREATE TABLE `domicilios` (
  `ID_Domicilio` int(11) NOT NULL,
  `Calle` varchar(255) NOT NULL,
  `NumeroExterior` varchar(50) DEFAULT NULL,
  `NumeroInterior` varchar(50) DEFAULT NULL,
  `Colonia` varchar(255) NOT NULL,
  `Municipio` varchar(255) NOT NULL,
  `Estado` varchar(255) NOT NULL,
  `Referencia` varchar(255) DEFAULT NULL,
  `CodigoPostal` smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `domicilios`
--

INSERT INTO `domicilios` (`ID_Domicilio`, `Calle`, `NumeroExterior`, `NumeroInterior`, `Colonia`, `Municipio`, `Estado`, `Referencia`, `CodigoPostal`) VALUES
(1, '2', '3', '4', '5', '6', '7', '8', 9),
(11, 'paseo de oslo', '433', '', 'tejeda', 'corregidora', 'queretaro', '', 32767),
(12, 'Av. Revolución', '123', 'A', 'Centro', 'Querétaro', 'Querétaro', 'Frente a la plaza', 32767),
(13, 'Av. Revolución', '123', 'A', 'Centro', 'Querétaro', 'Querétaro', 'Frente a la plaza', 32767),
(14, 'Av. Revolución', '123', 'A', 'Centro', 'Querétaro', 'Querétaro', 'Frente a la plaza', 32767),
(444, 'paseo de mexico', '412', '', 'tejeda', 'corregidora', 'Querétaro', '', 32767),
(777, 'viajenova ', '300', '', 'La Cantera', 'Celaya', 'Gto', 'n/a', 32767);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `licencias`
--

CREATE TABLE `licencias` (
  `ID_Licencia` int(11) NOT NULL,
  `NumeroLicencia` varchar(50) NOT NULL,
  `FechaNacimiento` date NOT NULL,
  `FechaExpedicion` date NOT NULL,
  `Vigencia` date NOT NULL,
  `Antiguedad` int(11) NOT NULL,
  `TipoLicencia` varchar(10) NOT NULL,
  `GrupoSanguineo` varchar(5) NOT NULL,
  `ID_Conductor` int(11) NOT NULL,
  `ID_Domicilio` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `licencias`
--

INSERT INTO `licencias` (`ID_Licencia`, `NumeroLicencia`, `FechaNacimiento`, `FechaExpedicion`, `Vigencia`, `Antiguedad`, `TipoLicencia`, `GrupoSanguineo`, `ID_Conductor`, `ID_Domicilio`) VALUES
(1, '777', '2000-08-23', '2025-05-06', '2025-05-30', 2, 'A', 'O+', 1, 1),
(90, '90', '2025-02-18', '2025-02-18', '2025-02-18', 1, '1', '1', 789, 11),
(333, '123456789', '2000-08-23', '2022-03-23', '2035-03-23', 3, 'A', 'O+', 789, 11),
(444, '4425405858', '2025-05-08', '2025-05-08', '2025-05-22', 5, 'A', 'o-', 444, 444),
(777, '2', '2006-06-22', '2025-03-19', '2025-04-09', 3, 'B', 'O-', 777, 777);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `licencond`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `licencond` (
`NumeroLicencia` varchar(50)
,`Nombre` varchar(255)
,`FechaNacimiento` date
,`FechaExpedicion` date
,`Vigencia` date
,`Antiguedad` int(11)
,`TipoLicencia` varchar(10)
,`Calle` varchar(255)
,`NumeroExterior` varchar(50)
,`Colonia` varchar(255)
,`CodigoPostal` smallint(6)
,`Municipio` varchar(255)
,`GrupoSanguineo` varchar(5)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `multas`
--

CREATE TABLE `multas` (
  `ID_Multa` int(11) NOT NULL,
  `Fecha` date NOT NULL,
  `Motivo` varchar(255) NOT NULL,
  `Importe` decimal(10,2) DEFAULT NULL,
  `ID_Licencia` int(11) NOT NULL,
  `ID_Oficial` int(11) NOT NULL,
  `ID_Tarjeta_Circulacion` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `multas`
--

INSERT INTO `multas` (`ID_Multa`, `Fecha`, `Motivo`, `Importe`, `ID_Licencia`, `ID_Oficial`, `ID_Tarjeta_Circulacion`) VALUES
(777, '2025-03-04', 'GUAPO', 1400.00, 777, 1000, 777),
(8800, '2025-02-17', 'Exceso de velocidad', 1850.00, 333, 7, 4455);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `oficiales`
--

CREATE TABLE `oficiales` (
  `ID_Oficial` int(11) NOT NULL,
  `Nombre` varchar(255) NOT NULL,
  `NumeroIdentificacion` varchar(50) NOT NULL,
  `Cargo` varchar(50) NOT NULL,
  `ID_Centro_Verificacion` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `oficiales`
--

INSERT INTO `oficiales` (`ID_Oficial`, `Nombre`, `NumeroIdentificacion`, `Cargo`, `ID_Centro_Verificacion`) VALUES
(1, 'Juan ernesto', '12345678', 'Patrullero notavo', 1),
(7, 'Juan Policia', '666', 'Patrullero', 123),
(9, 'Juan', '12345678', 'Administrador', 1),
(10, 'Juan', '12345678', 'Administrador', 1),
(100, 'Juan', '12345678', 'Administrador', 1),
(1000, 'h', '1234', 'll', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `ID_Pago` int(11) NOT NULL,
  `NumeroTransaccion` varchar(50) NOT NULL,
  `LineaCaptura` varchar(50) DEFAULT NULL,
  `FechaLimite` date NOT NULL,
  `FechaPago` date NOT NULL,
  `Importe` decimal(10,2) NOT NULL,
  `ID_Tarjeta_Circulacion` int(11) NOT NULL,
  `MetodoPago` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pagos`
--

INSERT INTO `pagos` (`ID_Pago`, `NumeroTransaccion`, `LineaCaptura`, `FechaLimite`, `FechaPago`, `Importe`, `ID_Tarjeta_Circulacion`, `MetodoPago`) VALUES
(12, '345', 'P0O8IU', '2025-03-13', '2025-03-24', 1200.00, 4455, 'TARJETA'),
(9090, '678', 'JHYE5TG', '2025-12-22', '2025-12-21', 1850.00, 4455, 'EFECTIVO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `propietarios`
--

CREATE TABLE `propietarios` (
  `ID_Propietario` int(11) NOT NULL,
  `Nombre` varchar(255) NOT NULL,
  `RFC` varchar(13) NOT NULL,
  `CURP` varchar(18) NOT NULL,
  `Telefono` varchar(15) NOT NULL,
  `CorreoElectronico` varchar(100) NOT NULL,
  `ID_Domicilio` int(11) NOT NULL,
  `FechaNacimiento` date NOT NULL,
  `Nacionalidad` varchar(50) NOT NULL,
  `Sexo` varchar(10) NOT NULL,
  `EstadoCivil` varchar(20) NOT NULL,
  `TipoIdentificacion` varchar(50) NOT NULL,
  `NumeroIdentificacion` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `propietarios`
--

INSERT INTO `propietarios` (`ID_Propietario`, `Nombre`, `RFC`, `CURP`, `Telefono`, `CorreoElectronico`, `ID_Domicilio`, `FechaNacimiento`, `Nacionalidad`, `Sexo`, `EstadoCivil`, `TipoIdentificacion`, `NumeroIdentificacion`) VALUES
(777, 'David Mata Guerra', 'MAGD0520922', 'MAGD0520922HNETRVA', '4421714729', 'Bryan_cordoba@outlook.com', 777, '2025-03-09', 'Aleman', 'FEMENINO', 'SOLTERO', 'CREDENCIAL DE ELECTOR', '777'),
(989, 'Bryan Cordoba', 'GOCB00082382A', 'GOCB000823HQTNRRA9', '4421714729', 'Bryan_cordoba@outlook.com', 11, '2000-08-23', 'MEXICANA', 'MASCULINO', 'SOLTERO', 'CREDENCIAL DE ELECTOR', '112233');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `tarjcir`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `tarjcir` (
`Nombre` varchar(255)
,`Placas` varchar(10)
,`RFC` varchar(13)
,`NumeroSerie` varchar(50)
,`Modelo` varchar(50)
,`Localidad` varchar(255)
,`Marca` varchar(50)
,`Cilindraje` int(11)
,`FechaExpedicion` date
,`NumeroPuertas` int(11)
,`Clase` varchar(50)
,`NumeroAsientos` int(11)
,`TipoCombustible` varchar(50)
,`Uso` varchar(50)
,`Transmision` varchar(50)
,`NumeroMotor` varchar(50)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tarjetascirculacion`
--

CREATE TABLE `tarjetascirculacion` (
  `ID_Tarjeta_Circulacion` int(11) NOT NULL,
  `ID_Propietario` int(11) NOT NULL,
  `ID_Vehiculo` int(11) NOT NULL,
  `Placas` varchar(10) NOT NULL,
  `Municipio` varchar(255) NOT NULL,
  `Estado` varchar(255) NOT NULL,
  `Localidad` varchar(255) NOT NULL,
  `TipoServicio` varchar(50) NOT NULL,
  `FechaExpedicion` date NOT NULL,
  `FechaVencimiento` date NOT NULL,
  `Origen` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tarjetascirculacion`
--

INSERT INTO `tarjetascirculacion` (`ID_Tarjeta_Circulacion`, `ID_Propietario`, `ID_Vehiculo`, `Placas`, `Municipio`, `Estado`, `Localidad`, `TipoServicio`, `FechaExpedicion`, `FechaVencimiento`, `Origen`) VALUES
(777, 777, 222, 'HB32Y', 'corregidora', 'Querétaro', 'TEJEDA-CORREGIDORA', 'personal', '2011-11-11', '2012-11-12', 'MEXICANO'),
(4455, 989, 12345, 'HB32Y', 'CORREGIDORA', 'QUERETARO', 'QUERETARO', 'PERSONAL', '2025-12-22', '2035-12-22', 'MEXICANO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tarjetasverificacion`
--

CREATE TABLE `tarjetasverificacion` (
  `ID_Tarjeta_Verificacion` int(11) NOT NULL,
  `ID_Vehiculo` int(11) NOT NULL,
  `ID_Centro_Verificacion` int(11) NOT NULL,
  `FechaExpedicion` date NOT NULL,
  `HoraEntrada` time NOT NULL,
  `HoraSalida` time NOT NULL,
  `MotivoVerificacion` varchar(255) NOT NULL,
  `FolioCertificado` varchar(50) NOT NULL,
  `Vigencia` date NOT NULL,
  `ID_Tarjeta_Circulacion` int(11) NOT NULL,
  `NumeroSerieVehiculo` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tarjetasverificacion`
--

INSERT INTO `tarjetasverificacion` (`ID_Tarjeta_Verificacion`, `ID_Vehiculo`, `ID_Centro_Verificacion`, `FechaExpedicion`, `HoraEntrada`, `HoraSalida`, `MotivoVerificacion`, `FolioCertificado`, `Vigencia`, `ID_Tarjeta_Circulacion`, `NumeroSerieVehiculo`) VALUES
(6655, 12345, 123, '2018-12-22', '16:00:00', '18:00:00', 'Motor', 'HYTW44', '2019-12-22', 4455, '111');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `Username` varchar(50) NOT NULL,
  `Password` varchar(50) NOT NULL,
  `Tipo` char(1) DEFAULT NULL,
  `Status` tinyint(1) DEFAULT NULL,
  `Bloquqo` tinyint(1) DEFAULT NULL,
  `Intentos` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`Username`, `Password`, `Tipo`, `Status`, `Bloquqo`, `Intentos`) VALUES
('IRENE', 'I1234', 'A', 0, 0, 0),
('JUAN', 'J1234', 'A', 1, 0, 0),
('LUIS', 'L1234', 'U', 1, 0, 0),
('MARIA', 'M1234', 'A', 1, 1, 0),
('PEDRO', 'P1234', 'U', 1, 1, 0),
('SAUL', 'S1234', 'U', 0, 1, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vehiculos`
--

CREATE TABLE `vehiculos` (
  `ID_Vehiculo` int(11) NOT NULL,
  `NumeroSerie` varchar(50) NOT NULL,
  `Placas` varchar(10) NOT NULL,
  `Marca` varchar(50) NOT NULL,
  `Modelo` varchar(50) NOT NULL,
  `AnoFabricacion` int(11) NOT NULL,
  `Color` varchar(50) NOT NULL,
  `NumeroMotor` varchar(50) NOT NULL,
  `TipoCarroceria` varchar(50) NOT NULL,
  `NumeroAsientos` int(11) NOT NULL,
  `Cilindraje` int(11) NOT NULL,
  `TipoCombustible` varchar(50) NOT NULL,
  `Uso` varchar(50) NOT NULL,
  `Transmision` varchar(50) NOT NULL,
  `NumeroPuertas` int(11) NOT NULL,
  `Clase` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `vehiculos`
--

INSERT INTO `vehiculos` (`ID_Vehiculo`, `NumeroSerie`, `Placas`, `Marca`, `Modelo`, `AnoFabricacion`, `Color`, `NumeroMotor`, `TipoCarroceria`, `NumeroAsientos`, `Cilindraje`, `TipoCombustible`, `Uso`, `Transmision`, `NumeroPuertas`, `Clase`) VALUES
(222, '1626', 'HB32Y', 'MITSUBISHI', 'ECLIPSE SE', 2012, 'NEGRO', '0987', 'ACERO', 5, 8, 'REGULAR', 'PERSONAL', 'AUTOMATICA', 4, 'B'),
(12345, '111', 'HB32Y', 'Honda', 'Yris', 2008, 'Gris', '3', 'Acero', 5, 8, 'Diesel', 'Transporte personal', 'hg', 5, 'B'),
(12346, 'ABC1234', 'XYZ789', 'Toyota', 'Corolla', 2020, 'Blanco', '1234567890', 'Sedán', 5, 1800, 'Gasolina', 'Particular', 'Automática', 4, 'Sedán'),
(12347, 'DEF5678', 'ABC123', 'Honda', 'Civic', 2022, 'Negro', '9876543210', 'Hatchback', 5, 1600, 'Gasolina', 'Particular', 'Manual', 4, 'Hatchback');

-- --------------------------------------------------------

--
-- Estructura para la vista `licencond`
--
DROP TABLE IF EXISTS `licencond`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `licencond`  AS SELECT `l`.`NumeroLicencia` AS `NumeroLicencia`, `c`.`Nombre` AS `Nombre`, `l`.`FechaNacimiento` AS `FechaNacimiento`, `l`.`FechaExpedicion` AS `FechaExpedicion`, `l`.`Vigencia` AS `Vigencia`, `l`.`Antiguedad` AS `Antiguedad`, `l`.`TipoLicencia` AS `TipoLicencia`, `d`.`Calle` AS `Calle`, `d`.`NumeroExterior` AS `NumeroExterior`, `d`.`Colonia` AS `Colonia`, `d`.`CodigoPostal` AS `CodigoPostal`, `d`.`Municipio` AS `Municipio`, `l`.`GrupoSanguineo` AS `GrupoSanguineo` FROM ((`licencias` `l` join `conductores` `c`) join `domicilios` `d`) WHERE `l`.`ID_Licencia` = 444 AND `c`.`ID_Conductor` = `d`.`ID_Domicilio` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `tarjcir`
--
DROP TABLE IF EXISTS `tarjcir`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `tarjcir`  AS SELECT `c`.`Nombre` AS `Nombre`, `t`.`Placas` AS `Placas`, `c`.`RFC` AS `RFC`, `v`.`NumeroSerie` AS `NumeroSerie`, `v`.`Modelo` AS `Modelo`, `t`.`Localidad` AS `Localidad`, `v`.`Marca` AS `Marca`, `v`.`Cilindraje` AS `Cilindraje`, `t`.`FechaExpedicion` AS `FechaExpedicion`, `v`.`NumeroPuertas` AS `NumeroPuertas`, `v`.`Clase` AS `Clase`, `v`.`NumeroAsientos` AS `NumeroAsientos`, `v`.`TipoCombustible` AS `TipoCombustible`, `v`.`Uso` AS `Uso`, `v`.`Transmision` AS `Transmision`, `v`.`NumeroMotor` AS `NumeroMotor` FROM (((`conductores` `c` join `licencias` `l`) join `tarjetascirculacion` `t`) join `vehiculos` `v`) WHERE `c`.`ID_Conductor` = 777 AND `l`.`ID_Licencia` = 1 AND `t`.`ID_Tarjeta_Circulacion` = 777 AND `v`.`ID_Vehiculo` = 222 ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `centrosverificacion`
--
ALTER TABLE `centrosverificacion`
  ADD PRIMARY KEY (`ID_Centro_Verificacion`),
  ADD KEY `Direccion` (`Direccion`);

--
-- Indices de la tabla `conductores`
--
ALTER TABLE `conductores`
  ADD PRIMARY KEY (`ID_Conductor`),
  ADD KEY `ID_Domicilio` (`ID_Domicilio`);

--
-- Indices de la tabla `domicilios`
--
ALTER TABLE `domicilios`
  ADD PRIMARY KEY (`ID_Domicilio`);

--
-- Indices de la tabla `licencias`
--
ALTER TABLE `licencias`
  ADD PRIMARY KEY (`ID_Licencia`),
  ADD KEY `ID_Conductor` (`ID_Conductor`),
  ADD KEY `ID_Domicilio` (`ID_Domicilio`);

--
-- Indices de la tabla `multas`
--
ALTER TABLE `multas`
  ADD PRIMARY KEY (`ID_Multa`),
  ADD KEY `ID_Licencia` (`ID_Licencia`),
  ADD KEY `ID_Oficial` (`ID_Oficial`);

--
-- Indices de la tabla `oficiales`
--
ALTER TABLE `oficiales`
  ADD PRIMARY KEY (`ID_Oficial`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`ID_Pago`),
  ADD KEY `ID_Tarjeta_Circulacion` (`ID_Tarjeta_Circulacion`);

--
-- Indices de la tabla `propietarios`
--
ALTER TABLE `propietarios`
  ADD PRIMARY KEY (`ID_Propietario`),
  ADD KEY `ID_Domicilio` (`ID_Domicilio`);

--
-- Indices de la tabla `tarjetascirculacion`
--
ALTER TABLE `tarjetascirculacion`
  ADD PRIMARY KEY (`ID_Tarjeta_Circulacion`),
  ADD KEY `ID_Propietario` (`ID_Propietario`),
  ADD KEY `ID_Vehiculo` (`ID_Vehiculo`);

--
-- Indices de la tabla `tarjetasverificacion`
--
ALTER TABLE `tarjetasverificacion`
  ADD PRIMARY KEY (`ID_Tarjeta_Verificacion`),
  ADD KEY `ID_Centro_Verificacion` (`ID_Centro_Verificacion`),
  ADD KEY `ID_Tarjeta_Circulacion` (`ID_Tarjeta_Circulacion`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`Username`);

--
-- Indices de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`ID_Vehiculo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `centrosverificacion`
--
ALTER TABLE `centrosverificacion`
  MODIFY `ID_Centro_Verificacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT de la tabla `conductores`
--
ALTER TABLE `conductores`
  MODIFY `ID_Conductor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=791;

--
-- AUTO_INCREMENT de la tabla `domicilios`
--
ALTER TABLE `domicilios`
  MODIFY `ID_Domicilio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=778;

--
-- AUTO_INCREMENT de la tabla `licencias`
--
ALTER TABLE `licencias`
  MODIFY `ID_Licencia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=779;

--
-- AUTO_INCREMENT de la tabla `multas`
--
ALTER TABLE `multas`
  MODIFY `ID_Multa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8801;

--
-- AUTO_INCREMENT de la tabla `oficiales`
--
ALTER TABLE `oficiales`
  MODIFY `ID_Oficial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1001;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `ID_Pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9091;

--
-- AUTO_INCREMENT de la tabla `propietarios`
--
ALTER TABLE `propietarios`
  MODIFY `ID_Propietario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=990;

--
-- AUTO_INCREMENT de la tabla `tarjetascirculacion`
--
ALTER TABLE `tarjetascirculacion`
  MODIFY `ID_Tarjeta_Circulacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4457;

--
-- AUTO_INCREMENT de la tabla `tarjetasverificacion`
--
ALTER TABLE `tarjetasverificacion`
  MODIFY `ID_Tarjeta_Verificacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6656;

--
-- AUTO_INCREMENT de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `ID_Vehiculo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12348;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `centrosverificacion`
--
ALTER TABLE `centrosverificacion`
  ADD CONSTRAINT `centrosverificacion_ibfk_1` FOREIGN KEY (`Direccion`) REFERENCES `domicilios` (`ID_Domicilio`);

--
-- Filtros para la tabla `conductores`
--
ALTER TABLE `conductores`
  ADD CONSTRAINT `conductores_ibfk_1` FOREIGN KEY (`ID_Domicilio`) REFERENCES `domicilios` (`ID_Domicilio`);

--
-- Filtros para la tabla `licencias`
--
ALTER TABLE `licencias`
  ADD CONSTRAINT `licencias_ibfk_1` FOREIGN KEY (`ID_Conductor`) REFERENCES `conductores` (`ID_Conductor`),
  ADD CONSTRAINT `licencias_ibfk_2` FOREIGN KEY (`ID_Domicilio`) REFERENCES `domicilios` (`ID_Domicilio`);

--
-- Filtros para la tabla `multas`
--
ALTER TABLE `multas`
  ADD CONSTRAINT `multas_ibfk_1` FOREIGN KEY (`ID_Licencia`) REFERENCES `licencias` (`ID_Licencia`),
  ADD CONSTRAINT `multas_ibfk_2` FOREIGN KEY (`ID_Oficial`) REFERENCES `oficiales` (`ID_Oficial`);

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`ID_Tarjeta_Circulacion`) REFERENCES `tarjetascirculacion` (`ID_Tarjeta_Circulacion`);

--
-- Filtros para la tabla `propietarios`
--
ALTER TABLE `propietarios`
  ADD CONSTRAINT `propietarios_ibfk_1` FOREIGN KEY (`ID_Domicilio`) REFERENCES `domicilios` (`ID_Domicilio`);

--
-- Filtros para la tabla `tarjetascirculacion`
--
ALTER TABLE `tarjetascirculacion`
  ADD CONSTRAINT `tarjetascirculacion_ibfk_2` FOREIGN KEY (`ID_Propietario`) REFERENCES `propietarios` (`ID_Propietario`),
  ADD CONSTRAINT `tarjetascirculacion_ibfk_3` FOREIGN KEY (`ID_Vehiculo`) REFERENCES `vehiculos` (`ID_Vehiculo`);

--
-- Filtros para la tabla `tarjetasverificacion`
--
ALTER TABLE `tarjetasverificacion`
  ADD CONSTRAINT `tarjetasverificacion_ibfk_1` FOREIGN KEY (`ID_Centro_Verificacion`) REFERENCES `centrosverificacion` (`ID_Centro_Verificacion`),
  ADD CONSTRAINT `tarjetasverificacion_ibfk_2` FOREIGN KEY (`ID_Tarjeta_Circulacion`) REFERENCES `tarjetascirculacion` (`ID_Tarjeta_Circulacion`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

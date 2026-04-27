-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 11, 2026 at 08:43 PM
-- Server version: 5.7.24
-- PHP Version: 8.0.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mapa_sala`
--

-- --------------------------------------------------------

--
-- Table structure for table `feriados`
--

CREATE TABLE `feriados` (
  `id_feriado` int(11) NOT NULL,
  `data_feriado` date NOT NULL,
  `nome_feriado` varchar(100) NOT NULL,
  `tipo` enum('nacional','regional','facultativo') NOT NULL DEFAULT 'nacional',
  `recorrente` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `feriados`
--

INSERT INTO `feriados` (`id_feriado`, `data_feriado`, `nome_feriado`, `tipo`, `recorrente`) VALUES
(1, '2026-01-01', 'Confraternização Universal', 'nacional', 1),
(2, '2026-04-03', 'Sexta-feira Santa', 'nacional', 1),
(3, '2026-04-21', 'Tiradentes', 'nacional', 1),
(4, '2026-05-01', 'Dia do Trabalho', 'nacional', 1),
(5, '2026-07-16', 'Aniversário de Imperatriz', 'nacional', 1),
(6, '2026-09-07', 'Independência do Brasil', 'nacional', 1),
(7, '2026-10-12', 'Nossa Senhora Aparecida', 'nacional', 1),
(8, '2026-11-02', 'Finados', 'nacional', 1),
(9, '2026-11-15', 'Proclamação da República', 'nacional', 1),
(10, '2026-12-25', 'Natal', 'nacional', 1),
(11, '2026-02-17', 'Carnaval', 'nacional', 1);

-- --------------------------------------------------------

--
-- Table structure for table `professores`
--

CREATE TABLE `professores` (
  `id_professor` int(11) NOT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `formacao` varchar(255) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `cursos_complementares` text,
  `foto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `professores`
--

INSERT INTO `professores` (`id_professor`, `nome`, `formacao`, `telefone`, `email`, `cursos_complementares`, `foto`) VALUES
(10, 'Rafael Melo', 'Tecnologia', '(99) 99111-1001', 'rafael.melo@senac.local', 'Didática e Metodologias', NULL),
(11, 'Genival Neiva', 'Moda', '(99) 99111-1002', 'genival.neiva@senac.local', 'Modelagem Básica', NULL),
(12, 'Cláudia Santos', 'Administração', '(99) 99111-1003', 'claudia.santos@senac.local', 'Gestão e Processos', NULL),
(13, 'Gersonilda Suene', 'Administração', '(99) 99111-1004', 'gersonilda.suene@senac.local', 'Atendimento e Rotinas', NULL),
(14, 'Juliana Lima', 'Logística', '(99) 99111-1005', 'juliana.lima@senac.local', 'Gestão de Estoque', NULL),
(15, 'Vinícius Aires', 'TI', '(99) 99111-1006', 'vinicius.aires@senac.local', 'Redes e Suporte', NULL),
(16, 'Roberto Ayres', 'TI', '(99) 99111-1007', 'roberto.ayres@senac.local', 'Windows e Office', NULL),
(17, 'Claudete Sousa', 'Logística', '(99) 99111-1008', 'claudete.sousa@senac.local', 'Operações e Custos', NULL),
(18, 'Valcir Chagas', 'Vendas', '(99) 99111-1009', 'valcir.chagas@senac.local', 'Técnicas Comerciais', NULL),
(19, 'Aritânia Adna', 'Estética', '(99) 99111-1010', 'aritania.adna@senac.local', 'Procedimentos Estéticos', NULL),
(20, 'Andressa Pereira', 'Estética', '(99) 99111-1011', 'andressa.pereira@senac.local', 'Biossegurança', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `salas`
--

CREATE TABLE `salas` (
  `id_sala` int(11) NOT NULL,
  `nome_sala` varchar(255) DEFAULT NULL,
  `capacidade` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `salas`
--

INSERT INTO `salas` (`id_sala`, `nome_sala`, `capacidade`) VALUES
(1, 'Salão de Beleza', 20),
(2, 'Estética', 20),
(3, 'Laboratório 1', 20),
(4, 'Laboratório 2', 20),
(5, 'Sala Idiomas 1', 20),
(6, 'Sala Inovadora', 20),
(7, 'Sala 2', 20),
(8, 'Sala 3', 20),
(9, 'Sala 4', 20),
(10, 'Sala 5', 20),
(11, 'Cozinha Didática', 20),
(12, 'Sala Externa', 100);

-- --------------------------------------------------------

--
-- Table structure for table `turmas`
--

CREATE TABLE `turmas` (
  `id_turma` int(11) NOT NULL,
  `id_sala` int(11) DEFAULT NULL,
  `id_professor` int(11) DEFAULT NULL,
  `nome_turma` varchar(255) NOT NULL,
  `cod_turma` varchar(100) NOT NULL,
  `data_inicio` date NOT NULL,
  `carga_horaria` int(11) NOT NULL,
  `dias_semana` int(11) NOT NULL,
  `turno` enum('manha','tarde','noite') NOT NULL,
  `status` enum('ativa','cancelada','finalizada') NOT NULL DEFAULT 'ativa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `turmas`
--

INSERT INTO `turmas` (`id_turma`, `id_sala`, `id_professor`, `nome_turma`, `cod_turma`, `data_inicio`, `carga_horaria`, `dias_semana`, `turno`, `status`) VALUES
(2, NULL, 10, 'APQ- Logística', '2025.8.65', '2025-04-23', 400, 12, 'tarde', 'ativa'),
(3, 7, 11, 'Modalagem e Costura para Iniciantes', '2026.8.5', '2026-01-28', 60, 31, 'tarde', 'ativa'),
(4, 7, 11, 'Modalagem e Costura para Iniciantes', '2026.8.6', '2026-02-11', 60, 31, 'noite', 'ativa'),
(5, 8, 12, 'APQ Administrativo', '2026.8.30', '2026-03-02', 320, 3, 'tarde', 'ativa'),
(6, 9, 13, 'APQ- Administrativo', '2025.8.46', '2025-04-09', 400, 12, 'tarde', 'ativa'),
(7, 10, 14, 'APQ- Logística', '2025.8.64', '2025-03-23', 400, 12, 'tarde', 'ativa'),
(8, 3, 15, 'Técnico em Informática para Internet', '2025.8.20', '2025-01-20', 1000, 31, 'tarde', 'ativa'),
(9, 4, 16, 'Introdução à Informática- Windows e Office', '2026.8.1', '2026-01-26', 80, 31, 'tarde', 'ativa'),
(10, NULL, 12, 'APQ- Logística', '2025.8.63', '2025-03-26', 400, 12, 'manha', 'ativa'),
(11, 9, 17, 'APQ- Logística', '2025.8.66', '2025-03-25', 400, 12, 'manha', 'ativa'),
(12, 8, 18, 'APQ- Administrativo', '2025.8.85', '2025-05-19', 400, 12, 'manha', 'ativa'),
(13, 10, 12, 'APQ- Administrativo', '2025.8.111', '2025-07-23', 400, 12, 'tarde', 'ativa'),
(14, 8, 18, 'APQ- Vendas', '2025.8.44', '2025-03-10', 400, 12, 'manha', 'ativa'),
(15, 8, 18, 'APQ- Vendas', '2025.8.48', '2025-03-10', 400, 12, 'tarde', 'ativa'),
(16, 4, 16, 'Introdução à Informática- Windows e Office', '2026.8.2', '2025-02-11', 80, 31, 'noite', 'ativa'),
(17, 2, 19, 'Técnico em Estética', '2025.8.19', '2025-01-27', 1092, 7, 'noite', 'ativa'),
(18, 2, 20, 'Técnico em Estética', '2025.8.19', '2026-01-08', 108, 12, 'noite', 'ativa');

-- --------------------------------------------------------

--
-- Table structure for table `turma_encontros`
--

CREATE TABLE `turma_encontros` (
  `id_encontro` int(11) NOT NULL,
  `id_turma` int(11) NOT NULL,
  `id_sala` int(11) NOT NULL,
  `data` date NOT NULL,
  `turno` enum('manha','tarde','noite') NOT NULL,
  `horas` int(11) NOT NULL DEFAULT '0',
  `status` enum('marcado','cancelado') NOT NULL DEFAULT 'marcado',
  `id_professor` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `email`, `senha`) VALUES
(1, 'teste1@gmail.com', '$2y$10$ltTthWxCjgQLEJq9dnUzLe7lDFxjT/ENhLTEe0Vls1zpHzPX1JL3K'),
(2, 'teste2@gmail.com', '$2y$10$V5ToHBrxmqed31A3h9ijQeISv.O61KCiajW7BH4G.QnpW0wKRPHy.');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `feriados`
--
ALTER TABLE `feriados`
  ADD PRIMARY KEY (`id_feriado`),
  ADD UNIQUE KEY `uk_data_feriado` (`data_feriado`);

--
-- Indexes for table `professores`
--
ALTER TABLE `professores`
  ADD PRIMARY KEY (`id_professor`),
  ADD UNIQUE KEY `uk_prof_email` (`email`);

--
-- Indexes for table `salas`
--
ALTER TABLE `salas`
  ADD PRIMARY KEY (`id_sala`);

--
-- Indexes for table `turmas`
--
ALTER TABLE `turmas`
  ADD PRIMARY KEY (`id_turma`),
  ADD KEY `id_sala` (`id_sala`),
  ADD KEY `id_professor` (`id_professor`);

--
-- Indexes for table `turma_encontros`
--
ALTER TABLE `turma_encontros`
  ADD PRIMARY KEY (`id_encontro`),
  ADD UNIQUE KEY `uk_sala_data_turno` (`id_sala`,`data`,`turno`),
  ADD UNIQUE KEY `uk_prof_data_turno` (`id_professor`,`data`,`turno`),
  ADD KEY `id_turma` (`id_turma`),
  ADD KEY `id_sala` (`id_sala`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `feriados`
--
ALTER TABLE `feriados`
  MODIFY `id_feriado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `professores`
--
ALTER TABLE `professores`
  MODIFY `id_professor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `salas`
--
ALTER TABLE `salas`
  MODIFY `id_sala` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `turmas`
--
ALTER TABLE `turmas`
  MODIFY `id_turma` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `turma_encontros`
--
ALTER TABLE `turma_encontros`
  MODIFY `id_encontro` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

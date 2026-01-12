-- Criação do Banco de Dados
CREATE DATABASE IF NOT EXISTS church_digital;
USE church_digital;

-- Tabela de Igrejas (Multi-tenant / White Label)
CREATE TABLE IF NOT EXISTS igrejas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cor_primaria VARCHAR(7) DEFAULT '#3b82f6', -- Tailwind blue-500
    cor_secundaria VARCHAR(7) DEFAULT '#1e40af', -- Tailwind blue-800
    logo_url VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    igreja_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nivel ENUM('admin', 'tesoureiro', 'secretario') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (igreja_id) REFERENCES igrejas(id) ON DELETE CASCADE
);

-- Tabela de Membros
CREATE TABLE IF NOT EXISTS membros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    igreja_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    foto VARCHAR(255) DEFAULT '',
    telefone VARCHAR(20),
    data_nascimento DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (igreja_id) REFERENCES igrejas(id) ON DELETE CASCADE
);

-- Tabela Financeira Básica
CREATE TABLE IF NOT EXISTS financeiro_basico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    igreja_id INT NOT NULL,
    tipo ENUM('dizimo', 'oferta', 'saida') NOT NULL,
    valor DECIMAL(10, 2) NOT NULL,
    descricao VARCHAR(255),
    data_movimento DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (igreja_id) REFERENCES igrejas(id) ON DELETE CASCADE
);

-- Dados Iniciais de Teste
INSERT INTO igrejas (nome, cor_primaria, cor_secundaria) VALUES 
('Igreja Matriz', '#0ea5e9', '#0369a1'), -- Sky Blue
('Igreja Filial', '#ef4444', '#b91c1c'); -- Red

-- Senha padrão: 123456 (Hash gerado via password_hash)
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO usuarios (igreja_id, nome, email, senha, nivel) VALUES 
(1, 'Pastor Carlos', 'admin@matriz.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
(2, 'Missionária Ana', 'admin@filial.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Adiciona novos campos na tabela de membros
-- Data: 2026-01-14
-- Autor: Antigravity

ALTER TABLE membros
ADD COLUMN naturalidade VARCHAR(100),
ADD COLUMN estado_civil VARCHAR(20),
ADD COLUMN profissao VARCHAR(100),
ADD COLUMN cep VARCHAR(10),
ADD COLUMN endereco VARCHAR(255),
ADD COLUMN bairro VARCHAR(100),
ADD COLUMN cidade VARCHAR(100),
ADD COLUMN estado VARCHAR(2),
ADD COLUMN pais VARCHAR(50);

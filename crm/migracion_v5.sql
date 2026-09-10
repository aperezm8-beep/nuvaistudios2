-- ══════════════════════════════════════════════════════════════
--  MIGRACIÓN: Clasificación e anulación de expedientes
--  Ejecutar en la base de datos de Green Wash CRM
-- ══════════════════════════════════════════════════════════════

-- 1. Índice de importancia (1=Muy interesado, 2=Interesado, 3=Poco interesado, 4=Sin interés)
--    NULL = sin clasificar
ALTER TABLE `trans_expedientes`
    ADD COLUMN `CLASIFICACION` tinyint(1) DEFAULT NULL
        COMMENT '1=Muy interesado, 2=Interesado, 3=Poco interesado, 4=Sin interés'
        AFTER `OBSERVACIONES`;

-- 2. Anulación: 0=activo (valor actual de Activo=1), 1=anulado
--    Usamos una columna separada para no tocar el campo Activo existente
ALTER TABLE `trans_expedientes`
    ADD COLUMN `ANULADO` tinyint(1) NOT NULL DEFAULT 0
        COMMENT '0=activo, 1=anulado/descartado'
        AFTER `CLASIFICACION`;

-- 3. Índice para búsquedas rápidas por clasificación y anulación
CREATE INDEX idx_clasificacion ON `trans_expedientes` (`CLASIFICACION`);
CREATE INDEX idx_anulado       ON `trans_expedientes` (`ANULADO`);

-- ══════════════════════════════════════════════════════════════
--  VERIFICACIÓN
-- ══════════════════════════════════════════════════════════════
-- Ejecuta esto para confirmar que los campos se añadieron bien:
-- SHOW COLUMNS FROM trans_expedientes LIKE 'CLASIFICACION';
-- SHOW COLUMNS FROM trans_expedientes LIKE 'ANULADO';

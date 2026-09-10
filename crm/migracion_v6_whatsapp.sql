-- Registro de mensajes procesados para que los reintentos de Meta no dupliquen expedientes.
CREATE TABLE `crm_whatsapp_messages` (
    `MESSAGE_ID` varchar(255) NOT NULL,
    `PHONE` varchar(32) NOT NULL,
    `RECEIVED_AT` datetime NOT NULL,
    PRIMARY KEY (`MESSAGE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
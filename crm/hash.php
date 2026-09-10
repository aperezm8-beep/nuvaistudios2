<?php
echo password_hash("greenwash2026626", PASSWORD_DEFAULT);
?>

INSERT INTO m_usuarios (IdRol, User, Password, Nombre, Email1, Activo, firstLogin) 
VALUES (1, 'AlexAdm', 'PEGA_TU_HASH_AQUI', 'Alexander', 'tyeast8217@gmail.com', 1, 0);
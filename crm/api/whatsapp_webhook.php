<?php
require_once __DIR__ . '/../includes/db.php';

function getEnvValue($key, $default = null) {
    $sources = [
        getenv($key),
        $_ENV[$key] ?? null,
        $_SERVER[$key] ?? null,
    ];

    foreach ($sources as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            $value = trim($candidate);
            $value = preg_replace('/^"|"$/', '', $value);
            $value = preg_replace('/^\'|\'$/', '', $value);
            return $value;
        }
    }

    return $default;
}

// 1. Cargar variables de entorno desde .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $envVars = parse_ini_file($envFile);
    if (is_array($envVars)) {
        foreach ($envVars as $k => $v) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

// 2. Verificación inicial del Webhook por Meta (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    $verifyToken = getEnvValue('WHATSAPP_VERIFY_TOKEN', 'gw_meta_secure_token_2026');
    
    if ($mode === 'subscribe' && $token === $verifyToken) {
        http_response_code(200);
        echo $challenge;
        exit;
    }
    http_response_code(403);
    exit;
}

// 3. Recepción de mensajes y eventos (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    
    // Guardar SIEMPRE en el archivo de log para monitoreo
    $logEntry = date('Y-m-d H:i:s') . " | PAYLOAD RECIBIDO: " . $rawInput . "\n";
    file_put_contents(__DIR__ . '/webhook_debug.log', $logEntry, FILE_APPEND);

    $data = json_decode($rawInput, true);

    if (isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
        $msg = $data['entry'][0]['changes'][0]['value']['messages'][0];
        
        $messageId = $msg['id'] ?? null;
        $from      = normalizePhone($msg['from'] ?? null); // Teléfono del cliente
        $type      = $msg['type'] ?? 'text';

        $phoneNumberId = getEnvValue('WHATSAPP_PHONE_NUMBER_ID', getEnvValue('WA_PHONE_NUMBER_ID'));
        $accessToken   = getEnvValue('META_ACCESS_TOKEN');

        if ($from && $messageId && shouldIgnoreFloodMessage($from, $messageId, $msg['text']['body'] ?? '')) {
            http_response_code(200);
            echo "EVENT_RECEIVED";
            exit;
        }

        // Validar credenciales y registrar en log si faltan
        if (!$phoneNumberId || !$accessToken) {
            $errCreds = date('Y-m-d H:i:s') . " | ERROR: Credenciales no cargadas. PhoneID: '" . ($phoneNumberId ?: 'VACIO') . "', Token: " . ($accessToken ? 'OK' : 'VACIO') . "\n";
            file_put_contents(__DIR__ . '/webhook_debug.log', $errCreds, FILE_APPEND);
        }

        if ($from) {
            // Obtener el estado actual de la conversación del cliente
            $session = getSession($from);
            $step    = $session['step'] ?? 'IDLE';

            if (shouldResetExpiredSession($from, $session)) {
                $session = ['step' => 'IDLE', 'data' => []];
                setSession($from, $session);
                sendTextMessageIfConfigured(
                    $phoneNumberId,
                    $accessToken,
                    $from,
                    "Tu sesión ha expirado por inactividad. Quedamos atentos a tu solicitud y puedes seguir escribiendo cuando quieras."
                );
                $step = 'IDLE';
            }

            // -------------------------------------------------------
            // A. MENSAJE DE TEXTO RECIBIDO
            // -------------------------------------------------------
            if ($type === 'text') {
                $userText  = trim($msg['text']['body'] ?? '');
                $textLower = mb_strtolower($userText, 'UTF-8');

                if (isReturnToMenuRequest($userText)) {
                    setSession($from, ['step' => 'IDLE', 'data' => []]);
                    sendMainMenuToUser($phoneNumberId, $accessToken, $from);
                }
                elseif (isAiActivationRequest($userText) && $step !== 'ASK_NOMBRE' && $step !== 'ASK_APELLIDOS' && $step !== 'ASK_EMAIL' && $step !== 'ASK_ZONA' && $step !== 'ASK_PAIS' && $step !== 'ASK_POBLACION' && $step !== 'ASK_PROVINCIA' && $step !== 'ASK_MODALIDAD' && $step !== 'ASK_ASESOR_EMAIL') {
                    setSession($from, ['step' => 'AI_ASSISTANT', 'data' => []]);
                    sendTextMessageIfConfigured(
                        $phoneNumberId,
                        $accessToken,
                        $from,
                        "Perfecto. Ya estás hablando con el asistente IA de Green Wash.\n\nTe puedo explicar cómo funciona, qué ventajas tiene y cuál es el siguiente paso para ver si encaja contigo.\n\nPuedes escribir:\n- *Quiero más información*\n- *¿Cómo funciona?*\n- *¿Qué ventajas tiene?*\n- *¿Hay franquicia?*\n- *Quiero que me contacten*\n- *Menú principal*\n\nSi prefieres, al final también puedes pedir hablar con un asesor humano."
                    );
                }
                elseif ($step === 'AI_ASSISTANT') {
                    if (isReturnToMenuRequest($userText)) {
                        setSession($from, ['step' => 'IDLE', 'data' => []]);
                        sendMainMenuToUser($phoneNumberId, $accessToken, $from);
                    }
                    elseif (shouldSwitchToAdvisorEmailCapture($userText)) {
                        setSession($from, ['step' => 'ASK_ASESOR_EMAIL', 'data' => []]);
                        sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "Claro. Para que un asesor pueda contactarte, ¿cuál es tu *email*?");
                    } else {
                        $aiReply = getAiAssistantReply($userText);
                        if ($aiReply) {
                            sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, $aiReply);
                        } else {
                            $fallbackReply = buildGreenWashFallbackReply($userText);
                            sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, $fallbackReply);
                        }
                    }
                }
                elseif ($step === 'INFO_VIEWED') {
                    if (isReturnToMenuRequest($userText)) {
                        setSession($from, ['step' => 'IDLE', 'data' => []]);
                        sendMainMenuToUser($phoneNumberId, $accessToken, $from);
                    }
                    elseif (shouldSwitchToAdvisorEmailCapture($userText)) {
                        setSession($from, ['step' => 'ASK_ASESOR_EMAIL', 'data' => []]);
                        sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "Claro. Para que un asesor pueda contactarte, ¿cuál es tu *email*?");
                    }
                    elseif (preg_match('/\b(si|satisfecho|vale|ok|acepto|quiero|iniciar|solicitud)\b/u', mb_strtolower($userText, 'UTF-8'))) {
                        setSession($from, ['step' => 'ASK_NOMBRE', 'data' => []]);
                        sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "Perfecto. Vamos a tomar tus datos.\n\nEscribe tu *nombre*:");
                    }
                    else {
                        setSession($from, ['step' => 'AI_ASSISTANT', 'data' => []]);
                        sendTextMessageIfConfigured(
                            $phoneNumberId,
                            $accessToken,
                            $from,
                            "Perfecto. Ya estás hablando con el asistente IA de Green Wash.\n\nTe puedo explicar el modelo, las ventajas y el siguiente paso para valorar si te interesa.\n\nPrueba con:\n- *Quiero más información*\n- *¿Cómo funciona?*\n- *¿Qué ventajas tiene?*\n- *¿Hay franquicia?*\n- *Quiero que me contacten*\n- *Menú principal*\n\nSi al final necesitas ayuda humana, te la pondremos en contacto con un asesor."
                        );
                    }
                }
                elseif ($step === 'IDLE' || $textLower === 'hola' || $textLower === 'buenas' || $textLower === 'buenas tardes' || $textLower === 'buenos dias' || $textLower === 'buenos días' || $textLower === 'hey' || $textLower === 'hi') {
                    setSession($from, ['step' => 'IDLE', 'data' => []]);
                    
                    $buttonsPayload = [
                        'messaging_product' => 'whatsapp',
                        'recipient_type'    => 'individual',
                        'to'                => $from,
                        'type'              => 'interactive',
                        'interactive'        => [
                            'type' => 'button',
                            'body' => ['text' => "¡Hola! Gracias por comunicarte con Green Wash. ¿Cómo podemos ayudarte hoy?"],
                            'action' => [
                                'buttons' => [
                                    ['type' => 'reply', 'reply' => ['id' => 'btn_informacion', 'title' => 'Más información']],
                                    ['type' => 'reply', 'reply' => ['id' => 'btn_formulario', 'title' => 'Iniciar Solicitud']]
                                ]
                            ]
                        ]
                    ];
                    
                    if ($phoneNumberId && $accessToken) {
                        sendWhatsAppRequest($phoneNumberId, $accessToken, $buttonsPayload);
                    }
                } 
                // Flujo paso a paso del formulario
                else {
                    switch ($step) {
                        case 'ASK_NOMBRE':
                            $session['data']['nombre'] = $userText;
                            setSession($from, ['step' => 'ASK_APELLIDOS', 'data' => $session['data']]);
                            sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "¿Cuáles son tus *apellidos*?");
                            break;

                        case 'ASK_APELLIDOS':
                            $session['data']['apellidos'] = $userText;
                            setSession($from, ['step' => 'ASK_EMAIL', 'data' => $session['data']]);
                            sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "¿Cuál es tu *email*?");
                            break;

                        case 'ASK_EMAIL':
                            if (!isRealEmail($userText)) {
                                sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "Ese email no parece válido o su dominio no existe. Escríbelo de nuevo, por favor.");
                                break;
                            }
                            $session['data']['email'] = strtolower($userText);
                            setSession($from, ['step' => 'ASK_ZONA', 'data' => $session['data']]);
                            sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "¿En qué *zona o sector* estás? (España o Internacional)");
                            break;

                        case 'ASK_ZONA':
                            $session['data']['zona'] = $userText;
                            setSession($from, ['step' => 'ASK_PAIS', 'data' => $session['data']]);
                            sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "¿En qué *país* estás?");
                            break;

                        case 'ASK_PAIS':
                            $session['data']['pais'] = $userText;
                            setSession($from, ['step' => 'ASK_POBLACION', 'data' => $session['data']]);
                            sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "¿Cuál es tu *población o ciudad*?");
                            break;

                        case 'ASK_POBLACION':
                            $session['data']['poblacion'] = $userText;
                            setSession($from, ['step' => 'ASK_PROVINCIA', 'data' => $session['data']]);
                            sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "¿Cuál es tu *provincia*? Si no aplica, responde `sin provincia`.");
                            break;

                        case 'ASK_PROVINCIA':
                            $session['data']['provincia'] = strtolower($userText) === 'sin provincia' ? '' : $userText;
                            setSession($from, ['step' => 'ASK_MODALIDAD', 'data' => $session['data']]);
                            sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "¿Qué modalidad te interesa? Responde: *parking subterráneo*, *industrial/local*, *parking superficie* o *parking subterráneo Low Cost*.");
                            break;

                        case 'ASK_ASESOR_EMAIL':
                            if (!isRealEmail($userText)) {
                                sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "Necesito un email válido para que un asesor pueda contactarte. Escríbelo de nuevo, por favor.");
                                break;
                            }
                            $leadId = createWhatsappLead($from, [
                                'nombre' => 'Solicitud de asesor',
                                'email' => strtolower($userText),
                                'comentario' => 'Solicitud de asesor recibida por WhatsApp',
                            ]);
                            $logCrm = date('Y-m-d H:i:s') . " | CRM SOLICITUD ASESOR: ID: {$leadId} | Tel: {$from}\n";
                            file_put_contents(__DIR__ . '/webhook_debug.log', $logCrm, FILE_APPEND);
                            setSession($from, ['step' => 'IDLE', 'data' => []]);
                            sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "Gracias. Un asesor de Green Wash te contactará pronto.");
                            break;

                        case 'ASK_MODALIDAD':
                            $session['data']['modalidad'] = $userText;
                            $leadId = createWhatsappLead($from, $session['data']);
                            $logCrm = date('Y-m-d H:i:s') . " | CRM LEAD CREADO: ID: {$leadId} | Tel: {$from} | Nombre: {$session['data']['nombre']} | Zona: {$session['data']['zona']} | Población: {$session['data']['poblacion']}\n";
                            file_put_contents(__DIR__ . '/webhook_debug.log', $logCrm, FILE_APPEND);

                            // Reiniciar sesión y confirmar al usuario
                            setSession($from, ['step' => 'IDLE', 'data' => []]);
                            sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "¡Tus datos han sido registrados con éxito en GW Bot! Un asesor te contactará pronto.");
                            break;
                    }
                }
            }

            // -------------------------------------------------------
            // B. INTERACCIÓN DE BOTONES RECIBIDA
            // -------------------------------------------------------
            elseif ($type === 'interactive') {
                $interactiveType = $msg['interactive']['type'] ?? '';

                if ($interactiveType === 'button_reply') {
                    $buttonId = $msg['interactive']['button_reply']['id'] ?? '';

                    if ($buttonId === 'btn_formulario') {
                        setSession($from, ['step' => 'ASK_NOMBRE', 'data' => []]);
                        sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "¡Perfecto! Vamos a tomar tus datos.\n\nPor favor, escribe tu *nombre*:");
                    }
                    elseif ($buttonId === 'btn_informacion') {
                        setSession($from, ['step' => 'INFO_VIEWED', 'data' => []]);

                        sendTextMessageIfConfigured($phoneNumberId, $accessToken, $from, "Aquí tienes información breve acerca de nuestro modelo de negocio.");
                        usleep(800000);
                        sendImageMessageIfConfigured($phoneNumberId, $accessToken, $from, 'https://imgur.com/As6fSpq.jpg');
                        usleep(900000);
                        sendImageMessageIfConfigured($phoneNumberId, $accessToken, $from, 'https://imgur.com/EDMK4p7.jpg');

                        $decisionButtonsPayload = [
                            'messaging_product' => 'whatsapp',
                            'recipient_type'    => 'individual',
                            'to'                => $from,
                            'type'              => 'interactive',
                            'interactive'       => [
                                'type' => 'button',
                                'body' => ['text' => '¿Has quedado satisfecho con la información? Si no es así, puedes pedir hablar con un asesor y te contactará. Si estás satisfecho, puedes iniciar tu solicitud.'],
                                'action' => [
                                    'buttons' => [
                                        ['type' => 'reply', 'reply' => ['id' => 'btn_asesor', 'title' => 'Hablar con Asesor']],
                                        ['type' => 'reply', 'reply' => ['id' => 'btn_formulario', 'title' => 'Iniciar Solicitud']]
                                    ]
                                ]
                            ]
                        ];

                        if ($phoneNumberId && $accessToken) {
                            usleep(800000);
                            sendWhatsAppRequest($phoneNumberId, $accessToken, $decisionButtonsPayload);
                        }
                    }
                    elseif ($buttonId === 'btn_asesor' || mb_strtolower(trim((string)($msg['interactive']['button_reply']['title'] ?? '')), 'UTF-8') === 'hablar con asesor') {
                        setSession($from, ['step' => 'AI_ASSISTANT', 'data' => []]);
                        sendTextMessageIfConfigured(
                            $phoneNumberId,
                            $accessToken,
                            $from,
                            "Perfecto. Ya estás hablando con el asistente IA de Green Wash.\n\nTe puedo explicar el modelo, las ventajas y el siguiente paso para valorar si te interesa.\n\nPrueba con:\n- *Quiero más información*\n- *¿Cómo funciona?*\n- *¿Qué ventajas tiene?*\n- *¿Hay franquicia?*\n- *Quiero que me contacten*\n- *Menú principal*\n\nSi al final necesitas ayuda humana, te la pondremos en contacto con un asesor."
                        );
                    }
                }
            }
        }
    }

    http_response_code(200);
    echo "EVENT_RECEIVED";
    exit;
}

// ------------------------------------------------------------------
// INTEGRACIÓN DE ASISTENTES DE INTELIGENCIA ARTIFICIAL
// ------------------------------------------------------------------

function getAiAssistantReply($userText) {
    $cleanText = trim((string)$userText);
    if ($cleanText === '') {
        return null;
    }

    $siteContext = getGreenWashWebsiteContext();

    // 1. Intentar Gemini
    $geminiReply = tryGeminiAssistant($cleanText, $siteContext);
    if ($geminiReply !== null) {
        return $geminiReply;
    }

    // 2. Intentar otros proveedores de IA configurados
    $openAiReply = tryOpenAiAssistant($cleanText, $siteContext);
    if ($openAiReply !== null) {
        return $openAiReply;
    }

    $openRouterReply = tryOpenRouterAssistant($cleanText, $siteContext);
    if ($openRouterReply !== null) {
        return $openRouterReply;
    }

    $ollamaReply = tryOllamaAssistant($cleanText, $siteContext);
    if ($ollamaReply !== null) {
        return $ollamaReply;
    }

    // 3. Fallback inteligente
    return buildGreenWashFallbackReply($cleanText, $siteContext);
}

function tryGeminiAssistant($userText, $siteContext = null) {
    $apiKey = getEnvValue('GEMINI_API_KEY');
    if (!$apiKey) {
        return null;
    }

    $model = getEnvValue('GEMINI_MODEL', 'gemini-1.5-flash');
    $context = $siteContext ? "Información de Green Wash:\n" . substr($siteContext, 0, 3000) : '';

    $systemInstruction = "Eres un asesor comercial de Green Wash (lavado ecológico y franquicias).
Tu objetivo es responder por WhatsApp de forma natural, ágil, concisa y profesional (máximo 2 a 3 frases por mensaje).
Puntos clave de Green Wash:
- Ahorro de más de 150 litros de agua por lavado gracias a productos ecológicos y biodegradables.
- Modelos de franquicia rentables en parkings subterráneos, centros comerciales, naves y locales.
- Presencia en España y expansión internacional.

Reglas:
1. Sé conciso y conversacional. No hagas listas largas.
2. Termina siempre con una pregunta corta para guiar al usuario.
3. Si el usuario pide hablar con una persona, invítale a dejar su email para que un asesor le contacte.";

    $payload = [
        'system_instruction' => [
            'parts' => [['text' => $systemInstruction . "\n" . $context]]
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => $userText]]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.6,
            'maxOutputTokens' => 200,
        ]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . trim($apiKey);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode >= 400 || !$response) {
        $logErr = date('Y-m-d H:i:s') . " | ERROR GEMINI API (HTTP {$httpCode}): " . ($curlError ?: $response) . "\n";
        file_put_contents(__DIR__ . '/webhook_debug.log', $logErr, FILE_APPEND);
        return null;
    }

    $decoded = json_decode($response, true);
    $reply = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

    return $reply ? trim($reply) : null;
}

function tryOpenAiAssistant($userText, $siteContext = null) {
    $apiKey = getEnvValue('OPENAI_API_KEY', getEnvValue('OPENAI_KEY'));
    if (!$apiKey) {
        return null;
    }

    $contextText = $siteContext ? 'Base de datos: ' . substr($siteContext, 0, 3000) : '';

    $payload = [
        'model' => getEnvValue('OPENAI_MODEL', 'gpt-4o-mini'),
        'messages' => [
            ['role' => 'system', 'content' => "Eres un asesor comercial de Green Wash en WhatsApp. Responde conciso (2-3 líneas), directo y termina siempre con una pregunta corta para guiar al cliente.\n" . $contextText],
            ['role' => 'user', 'content' => $userText],
        ],
        'temperature' => 0.6,
        'max_tokens' => 200,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . trim($apiKey),
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode >= 400 || !$response) {
        return null;
    }

    $decoded = json_decode($response, true);
    $answer = $decoded['choices'][0]['message']['content'] ?? null;
    return $answer ? trim($answer) : null;
}

function tryOpenRouterAssistant($userText, $siteContext = null) {
    $apiKey = getEnvValue('OPENROUTER_API_KEY');
    if (!$apiKey) {
        return null;
    }

    $model = getEnvValue('OPENROUTER_MODEL', 'meta-llama/llama-3.1-8b-instruct:free');
    $siteUrl = getEnvValue('APP_BASE_URL', getEnvValue('BASE_URL', 'https://localhost'));
    $contextText = $siteContext ? 'Base de datos: ' . substr($siteContext, 0, 3000) : '';

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => "Eres un asesor de Green Wash en WhatsApp. Responde conciso (2-3 líneas), directo y termina con una pregunta corta.\n" . $contextText],
            ['role' => 'user', 'content' => $userText],
        ],
        'temperature' => 0.6,
        'max_tokens' => 200,
    ];

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . trim($apiKey),
            'HTTP-Referer: ' . rtrim($siteUrl, '/'),
            'X-Title: Green Wash Bot',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode >= 400 || !$response) {
        return null;
    }

    $decoded = json_decode($response, true);
    $answer = $decoded['choices'][0]['message']['content'] ?? null;
    return $answer ? trim($answer) : null;
}

function tryOllamaAssistant($userText, $siteContext = null) {
    $baseUrl = getEnvValue('OLLAMA_BASE_URL', 'http://localhost:11434');
    $model = getEnvValue('OLLAMA_MODEL', 'llama3.1:8b');

    if (!$baseUrl || !$model) {
        return null;
    }

    $contextPrompt = $siteContext ? "Base de datos Green Wash: " . substr($siteContext, 0, 3000) : '';

    $payload = [
        'model' => $model,
        'prompt' => "Eres un asesor comercial de Green Wash en WhatsApp. Responde breve (2-3 frases) y termina con una pregunta corta:\n\nCliente: " . $userText . "\n\n" . $contextPrompt,
        'stream' => false,
    ];

    $ch = curl_init(rtrim($baseUrl, '/') . '/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode >= 400 || !$response) {
        return null;
    }

    $decoded = json_decode($response, true);
    $answer = trim((string)($decoded['response'] ?? ''));
    return $answer !== '' ? $answer : null;
}

// ------------------------------------------------------------------
// RESPUESTAS FALLBACK ORGANIZADAS Y CONCISAS
// ------------------------------------------------------------------

function buildGreenWashFallbackReply($userText, $siteContext = null) {
    $text = mb_strtolower(trim((string)$userText), 'UTF-8');
    $text = strtr($text, ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u']);

    // 1. Franquicias, inversión, costes y rentabilidad
    if (preg_match('/\b(franquicia|invertir|inversion|negocio|coste|precio|cuanto cuesta|rentabilidad|abrir|montar)\b/u', $text)) {
        return "En Green Wash ofrecemos un modelo de franquicia probado y rentable, ideal para parkings o locales comerciales sin necesidad de obras complejas.\n\n¿Te interesaría abrir en España o en el extranjero?";
    }

    // 2. Sistema de lavado, productos y ahorro de agua
    if (preg_match('/\b(agua|lavado|limpieza|ecologico|producto|como funciona|sistema|coche|rayar|chapa)\b/u', $text)) {
        return "Lavamos vehículos a mano con productos ecológicos y biodegradables que protegen la pintura y ahorran más de 150 litros de agua por coche.\n\n¿Buscas información para tu propio vehículo o como oportunidad de negocio?";
    }

    // 3. Modalidades de implantación
    if (preg_match('/\b(parking|subterraneo|superficie|local|nave|industrial|modalidad|instalacion)\b/u', $text)) {
        return "Nos adaptamos a diferentes ubicaciones: parkings subterráneos, centros comerciales, naves industriales o locales a pie de calle.\n\n¿Tienes ya un local o ubicación en mente?";
    }

    // 4. Ubicación y presencia geográfica
    if (preg_match('/\b(donde|ubicacion|ciudades|poblacion|provincia|madrid|barcelona|colombia|mexico|estados unidos|eeuu)\b/u', $text)) {
        return "Contamos con centros en puntos estratégicos y parkings de España, y estamos en plena expansión internacional.\n\n¿En qué ciudad o país te gustaría consultar disponibilidad?";
    }

    // 5. Contacto con asesor humano
    if (preg_match('/\b(asesor|humano|persona|hablar|llamar|telefono|contacto|cita)\b/u', $text)) {
        return "Con gusto te comunico con un asesor de Green Wash para ver los números y resolver tus dudas.\n\nPor favor, facilítame tu correo electrónico para que te contacte.";
    }

    // 6. Saludos y peticiones generales
    if (preg_match('/\b(hola|buenas|informacion|info|que es|quienes son|ayuda)\b/u', $text)) {
        return "Green Wash combina sostenibilidad, innovación y una propuesta de negocio con potencial de crecimiento.\n\n¿Te interesa más información sobre el servicio o la franquicia?";
    }

    // 7. Fallback general
    return "Green Wash combina sostenibilidad, innovación y una propuesta de negocio con potencial de crecimiento.\n\n¿Te interesa más información sobre el servicio o la franquicia?";
}

// ------------------------------------------------------------------
// FUNCIONES AUXILIARES (BD, CONTEXTO WEB Y SESIONES)
// ------------------------------------------------------------------

function getGreenWashWebsiteContext() {
    $urls = [
        'https://greenwash.es',
        'https://www.greenwash.es',
    ];

    foreach ($urls as $url) {
        $html = fetchUrlContent($url);
        if ($html === null || trim($html) === '') {
            continue;
        }

        $text = htmlToPlainText($html);
        if (strlen($text) > 300) {
            return substr($text, 0, 12000);
        }
    }

    return null;
}

function fetchUrlContent($url) {
    $ch = curl_init(trim($url));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; GreenWashBot/1.0)',
    ]);

    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($content === false || $httpCode >= 400) {
        return null;
    }

    return $content;
}

function htmlToPlainText($html) {
    $text = preg_replace('/<script\b.*?<\/script>/is', ' ', $html);
    $text = preg_replace('/<style\b.*?<\/style>/is', ' ', $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function isRealEmail($email) {
    $email = trim((string)$email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $domain = substr(strrchr($email, '@'), 1);
    return $domain !== '' && (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A'));
}

function createWhatsappLead($phone, array $data) {
    $modalidadId = findWhatsappModality($data['modalidad'] ?? '');
    $email = trim((string)($data['email'] ?? ''));
    if (!isRealEmail($email)) {
        throw new InvalidArgumentException('El email no es válido o no tiene un dominio resoluble.');
    }
    $stmt = getDB()->prepare(
        'INSERT INTO trans_expedientes (
            NOMBRE, APELLIDOS, EMAIL, TELEFONO, ZONA, PAIS,
            POBLACION, PROVINCIA, ID_MODALIDAD, ID_ESTADO, ID_FASE,
            RECIBIDO, COMENTARIO, FECREACION, Activo
        ) VALUES (
            :nombre, :apellidos, :email, :telefono, :zona, :pais,
            :poblacion, :provincia, :modalidad, :estado, :fase,
            :recibido, :comentario, NOW(), b\'1\'
        )'
    );
    $stmt->execute([
        ':nombre' => trim((string)($data['nombre'] ?? '')),
        ':apellidos' => trim((string)($data['apellidos'] ?? '')),
        ':email' => $email,
        ':telefono' => $phone,
        ':zona' => normalizeWhatsappZone($data['zona'] ?? ''),
        ':pais' => trim((string)($data['pais'] ?? '')),
        ':poblacion' => trim((string)($data['poblacion'] ?? '')),
        ':provincia' => trim((string)($data['provincia'] ?? '')),
        ':modalidad' => $modalidadId,
        ':estado' => null,
        ':fase' => 88,
        ':recibido' => 'WhatsApp',
        ':comentario' => trim((string)($data['comentario'] ?? '')),
    ]);
    return (int)getDB()->lastInsertId();
}

function normalizeWhatsappZone($zone) {
    $zone = trim((string)$zone);
    $lowerZone = mb_strtolower($zone, 'UTF-8');
    if (in_array($lowerZone, ['españa', 'espana', 'nacional', 'spain'], true)) {
        return 'España';
    }
    if (in_array($lowerZone, ['internacional', 'international'], true)) {
        return 'Internacional';
    }
    return $zone;
}

function findWhatsappModality($modality) {
    $modality = mb_strtolower(trim((string)$modality), 'UTF-8');
    if ($modality === '') {
        return null;
    }
    $patterns = [];
    if (strpos($modality, 'subterr') !== false) {
        $patterns = ['%SUBTERR%'];
    } elseif (strpos($modality, 'industrial') !== false || strpos($modality, 'local') !== false) {
        $patterns = ['%INDUSTRIAL%', '%LOCAL%'];
    } elseif (strpos($modality, 'superficie') !== false) {
        $patterns = ['%SUPERFICIE%'];
    }
    foreach ($patterns as $pattern) {
        $stmt = getDB()->prepare(
            'SELECT IDMODALIDAD FROM m_modalidad_implantacion
             WHERE ACTIVO = 1 AND NOMBREMODALIDAD LIKE :nombre LIMIT 1'
        );
        $stmt->execute([':nombre' => $pattern]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }
    }
    return null;
}

function isAiActivationRequest($text) {
    $normalized = mb_strtolower(trim((string)$text), 'UTF-8');
    if ($normalized === '') {
        return false;
    }

    $normalized = preg_replace('/[\p{P}\p{S}]/u', ' ', $normalized);
    $normalized = preg_replace('/\s+/u', ' ', $normalized);

    $patterns = [
        '/\b(?:asistente(?:\s+de\s+ia)?|chatbot|chat|ia)\b/u',
        '/\b(?:quiero\s+hablar\s+con\s+(?:la\s+)?ia|quiero\s+hablar\s+con\s+un\s+asistente|asesor\s+virtual|quiero\s+asesor|hablar\s+con\s+asesor|solicitar\s+asesor|necesito\s+ayuda|quiero\s+ayuda)\b/u',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $normalized)) {
            return true;
        }
    }

    return false;
}

function isReturnToMenuRequest($text) {
    $normalized = mb_strtolower(trim((string)$text), 'UTF-8');
    if ($normalized === '') {
        return false;
    }

    $normalized = preg_replace('/[\p{P}\p{S}]/u', ' ', $normalized);
    $normalized = preg_replace('/\s+/u', ' ', $normalized);

    $keywords = [
        'menu principal', 'volver al menu', 'volver al menú',
        'volver al inicio', 'volver al principio', 'inicio',
        'empezar de nuevo', 'volver'
    ];

    foreach ($keywords as $keyword) {
        if (strpos($normalized, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

function shouldSwitchToAdvisorEmailCapture($text) {
    $normalized = mb_strtolower(trim((string)$text), 'UTF-8');
    if ($normalized === '') {
        return false;
    }

    $keywords = [
        'email', 'correo', 'quiero que me contacten', 'contactar', 'contactame',
        'hablar con asesor', 'solicitar asesor', 'asesor', 'llamarme'
    ];

    foreach ($keywords as $keyword) {
        if (strpos($normalized, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

function sendMainMenuToUser($phoneNumberId, $accessToken, $to) {
    $buttonsPayload = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
        'type'              => 'interactive',
        'interactive'        => [
            'type' => 'button',
            'body' => ['text' => "¡Hola! Gracias por comunicarte con Green Wash. ¿Cómo podemos ayudarte hoy?"],
            'action' => [
                'buttons' => [
                    ['type' => 'reply', 'reply' => ['id' => 'btn_informacion', 'title' => 'Más información']],
                    ['type' => 'reply', 'reply' => ['id' => 'btn_formulario', 'title' => 'Iniciar Solicitud']]
                ]
            ]
        ]
    ];

    if ($phoneNumberId && $accessToken) {
        sendWhatsAppRequest($phoneNumberId, $accessToken, $buttonsPayload);
    }
}

function sendTextMessageIfConfigured($phoneNumberId, $accessToken, $to, $messageText) {
    if (!$phoneNumberId || !$accessToken) {
        return null;
    }
    return sendTextMessage($phoneNumberId, $accessToken, $to, $messageText);
}

function sendImageMessageIfConfigured($phoneNumberId, $accessToken, $to, $imageUrl) {
    if (!$phoneNumberId || !$accessToken || !$imageUrl) {
        return null;
    }
    return sendImageMessage($phoneNumberId, $accessToken, $to, $imageUrl);
}

function sendTextMessage($phoneNumberId, $accessToken, $to, $messageText) {
    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
        'type'              => 'text',
        'text'              => [
            'preview_url' => false,
            'body'        => $messageText
        ]
    ];
    return sendWhatsAppRequest($phoneNumberId, $accessToken, $payload);
}

function sendImageMessage($phoneNumberId, $accessToken, $to, $imageUrl) {
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'image',
        'image'             => [
            'link' => $imageUrl,
        ]
    ];
    return sendWhatsAppRequest($phoneNumberId, $accessToken, $payload);
}

function getSession($phone) {
    $file = __DIR__ . "/sessions/{$phone}.json";
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return ['step' => 'IDLE', 'data' => [], 'updated_at' => time()];
}

function setSession($phone, $data) {
    $dir = __DIR__ . '/sessions';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    if (!isset($data['updated_at'])) {
        $data['updated_at'] = time();
    }

    file_put_contents("{$dir}/{$phone}.json", json_encode($data));
}

function shouldResetExpiredSession($phone, $session) {
    $updatedAt = (int)($session['updated_at'] ?? 0);
    if ($updatedAt <= 0) {
        return false;
    }

    $now = time();
    $sessionTimeoutSeconds = 600; // 10 minutos

    return ($now - $updatedAt) > $sessionTimeoutSeconds;
}

function normalizePhone($phone) {
    if (!is_string($phone) && !is_numeric($phone)) {
        return null;
    }

    $phone = trim((string)$phone);
    if ($phone === '') {
        return null;
    }

    return preg_replace('/[^0-9+]/', '', $phone);
}

function shouldIgnoreFloodMessage($phone, $messageId, $text) {
    $phone = normalizePhone($phone);
    if (!$phone) {
        return false;
    }

    $session = getSession($phone);
    $now = time();

    $floodBlockUntil = (int)($session['flood_block_until'] ?? 0);
    if ($floodBlockUntil > $now) {
        return true;
    }

    $lastMessageId = $session['last_message_id'] ?? null;
    $lastMessageAt = (int)($session['last_message_at'] ?? 0);
    $lastMessageText = trim((string)($session['last_message_text'] ?? ''));
    $messageWindowStartedAt = (int)($session['message_window_started_at'] ?? $now);
    $messageCount = (int)($session['message_count_30s'] ?? 0);

    if ($messageWindowStartedAt > 0 && ($now - $messageWindowStartedAt) > 30) {
        $messageWindowStartedAt = $now;
        $messageCount = 0;
    }

    $normalizedText = trim((string)$text);
    $normalizedText = mb_strtolower($normalizedText, 'UTF-8');
    $previousText = mb_strtolower($lastMessageText, 'UTF-8');

    if ($messageId && $lastMessageId === $messageId) {
        return true;
    }

    if ($lastMessageAt > 0 && ($now - $lastMessageAt) <= 12 && $normalizedText !== '' && $previousText !== '' && $normalizedText === $previousText) {
        return true;
    }

    if ($messageCount >= 5) {
        $session['flood_block_until'] = $now + 60;
        $session['message_count_30s'] = 0;
        $session['message_window_started_at'] = $now;
        setSession($phone, $session);
        return true;
    }

    $session['last_message_id'] = $messageId ?? $lastMessageId;
    $session['last_message_at'] = $now;
    $session['last_message_text'] = $normalizedText;
    $session['message_window_started_at'] = $messageWindowStartedAt;
    $session['message_count_30s'] = $messageCount + 1;
    setSession($phone, $session);

    return false;
}

function sendWhatsAppRequest($phoneNumberId, $accessToken, $payload) {
    $url = "https://graph.facebook.com/v20.0/{$phoneNumberId}/messages";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . trim($accessToken),
            'Content-Type: application/json'
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $apiResponse = curl_exec($ch);
    $curlError   = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        $logOutput = "ERROR cURL META: " . $curlError;
    } else {
        $logOutput = "RESPUESTA API META: " . $apiResponse;
    }

    file_put_contents(__DIR__ . '/webhook_debug.log', date('Y-m-d H:i:s') . " | " . $logOutput . "\n", FILE_APPEND);
    return $apiResponse;
}
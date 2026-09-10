<?php
/**
 * SmtpMailer — SMTP client ligero con soporte TLS/STARTTLS y autenticación LOGIN/PLAIN.
 * Compatible con Gmail, Outlook, y cualquier servidor SMTP estándar.
 * No requiere extensiones adicionales más allá de openssl (estándar en PHP).
 */
class SmtpMailer
{
    public string  $host       = '';
    public int     $port       = 587;
    public string  $username   = '';
    public string  $password   = '';
    public string  $encryption = 'tls';   // 'tls' (STARTTLS), 'ssl', ''
    public string  $from       = '';
    public string  $fromName   = '';
    public string  $replyTo    = '';
    public int     $timeout    = 30;
    public bool    $debug      = false;

    private $socket = null;
    private array $log = [];

    public function send(string $to, string $toName, string $subject, string $htmlBody): bool
    {
        try {
            $this->connect();
            $this->ehlo();
            if ($this->encryption === 'tls') {
                $this->starttls();
                $this->ehlo();
            }
            $this->auth();
            $this->mailFrom($this->from);
            $this->rcptTo($to);
            $this->data($to, $toName, $subject, $htmlBody);
            $this->quit();
            return true;
        } catch (\Exception $e) {
            $this->log[] = 'ERROR: ' . $e->getMessage();
            if ($this->socket) { @fclose($this->socket); $this->socket = null; }
            throw $e;
        }
    }

    public function getLog(): array { return $this->log; }

    private function connect(): void
    {
        $host = ($this->encryption === 'ssl') ? 'ssl://' . $this->host : $this->host;
        $this->socket = @stream_socket_client(
            $host . ':' . $this->port,
            $errno, $errstr, $this->timeout
        );
        if (!$this->socket) {
            throw new \Exception("No se pudo conectar a {$host}:{$this->port} — {$errstr} ({$errno})");
        }
        stream_set_timeout($this->socket, $this->timeout);
        $this->read(220, 'connect');
    }

    private function ehlo(): void
    {
        $host = gethostname() ?: 'localhost';
        $this->cmd("EHLO {$host}", 250);
    }

    private function starttls(): void
    {
        $this->cmd('STARTTLS', 220);
        if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new \Exception('No se pudo activar TLS');
        }
    }

    private function auth(): void
    {
        if (empty($this->username)) return;
        $this->cmd('AUTH LOGIN', 334);
        $this->cmd(base64_encode($this->username), 334);
        $this->cmd(base64_encode($this->password), 235);
    }

    private function mailFrom(string $email): void
    {
        $this->cmd("MAIL FROM:<{$email}>", 250);
    }

    private function rcptTo(string $email): void
    {
        $this->cmd("RCPT TO:<{$email}>", 250);
    }

    private function data(string $to, string $toName, string $subject, string $htmlBody): void
    {
        $this->cmd('DATA', 354);
        $from     = $this->fromName ? "\"{$this->fromName}\" <{$this->from}>" : $this->from;
        $toLine   = $toName ? "\"{$toName}\" <{$to}>" : $to;
        $replyTo  = $this->replyTo ?: $this->from;
        $msgId    = '<' . time() . '.' . uniqid() . '@' . ($this->host ?: 'nuvaistudio.com') . '>';
        $date     = date('r');
        $subject  = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $boundary = 'GW_' . md5(uniqid());
        $headers  = implode("\r\n", [
            "Date: {$date}",
            "From: {$from}",
            "Reply-To: {$replyTo}",
            "To: {$toLine}",
            "Subject: {$subject}",
            "Message-ID: {$msgId}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "X-Mailer: NuvaiCRM",
        ]);

        $plainText = strip_tags(str_replace(['<br', '<p', '</p', '<td', '</tr'], ["\n<br", "\n<p", "</p\n", "\n<td", "</tr\n"], $htmlBody));
        $plainText = preg_replace('/\n{3,}/', "\n\n", $plainText);

        $body = implode("\r\n", [
            "--{$boundary}",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "",
            chunk_split(base64_encode($plainText)),
            "--{$boundary}",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "",
            chunk_split(base64_encode($htmlBody)),
            "--{$boundary}--",
        ]);

        $message = $headers . "\r\n\r\n" . $body . "\r\n.";
        $this->write($message);
        $this->read(250, 'data end');
    }

    private function quit(): void
    {
        $this->write('QUIT');
        @fclose($this->socket);
        $this->socket = null;
    }

    private function cmd(string $cmd, int $expectedCode): string
    {
        $this->write($cmd);
        return $this->read($expectedCode, $cmd);
    }

    private function write(string $data): void
    {
        if ($this->debug) $this->log[] = ">>> {$data}";
        fwrite($this->socket, $data . "\r\n");
    }

    private function read(int $expectedCode, string $context): string
    {
        $response = '';
        while ($line = fgets($this->socket, 512)) {
            $response .= $line;
            if ($this->debug) $this->log[] = "<<< {$line}";
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \Exception("SMTP error en '{$context}': esperado {$expectedCode}, recibido {$code} — " . trim($response));
        }
        return $response;
    }
}

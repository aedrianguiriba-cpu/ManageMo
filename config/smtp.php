<?php
/**
 * Minimal dependency-free SMTP client.
 *
 * PHP's built-in mail() relies on a local MTA (sendmail/postfix) that XAMPP
 * doesn't have configured, so it silently fails on most Windows/XAMPP setups.
 * This talks directly to a real SMTP server (e.g. Gmail) over a socket with
 * STARTTLS + AUTH LOGIN — the same protocol PHPMailer uses, just without the
 * dependency (no composer/vendor directory in this project).
 */

class SmtpException extends Exception {}

class SmtpMailer {
    private string $host;
    private int $port;
    private string $encryption; // 'tls' (STARTTLS) or 'ssl' (implicit TLS)
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;

    /** @var resource|null */
    private $sock = null;

    public function __construct(string $host, int $port, string $encryption, string $username, string $password, string $fromEmail, string $fromName) {
        $this->host       = $host;
        $this->port       = $port;
        $this->encryption = $encryption;
        $this->username   = $username;
        $this->password   = $password;
        $this->fromEmail  = $fromEmail;
        $this->fromName   = $fromName;
    }

    public static function fromEnv(): ?self {
        $host = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? '');
        $user = getenv('SMTP_USERNAME') ?: ($_ENV['SMTP_USERNAME'] ?? '');
        $pass = getenv('SMTP_PASSWORD') ?: ($_ENV['SMTP_PASSWORD'] ?? '');
        if (!$host || !$user || !$pass) return null;

        return new self(
            $host,
            (int)(getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587)),
            strtolower(getenv('SMTP_ENCRYPTION') ?: ($_ENV['SMTP_ENCRYPTION'] ?? 'tls')),
            $user,
            $pass,
            getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? $user),
            getenv('SMTP_FROM_NAME') ?: ($_ENV['SMTP_FROM_NAME'] ?? 'ManageMo')
        );
    }

    /**
     * Send a plain-text email. Returns true on success.
     * Throws SmtpException with the server's own response text on failure —
     * catch it at the call site and log/ignore so a mail hiccup never blocks
     * the actual status-change action.
     */
    public function send(string $toEmail, string $toName, string $subject, string $body): bool {
        return $this->sendRaw($toEmail, $toName, $subject, $this->buildPlainPart($body));
    }

    /**
     * Send an HTML email with a plain-text fallback (multipart/alternative),
     * so clients that can't render HTML still get a readable message.
     */
    public function sendHtml(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): bool {
        $boundary = 'mm_' . bin2hex(random_bytes(12));
        $parts = "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n"
               . "--$boundary\r\n" . $this->buildPlainPart($textBody) . "\r\n"
               . "--$boundary\r\n" . $this->buildHtmlPart($htmlBody) . "\r\n"
               . "--$boundary--";
        return $this->sendRaw($toEmail, $toName, $subject, $parts);
    }

    private function buildPlainPart(string $body): string {
        return "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $body;
    }

    private function buildHtmlPart(string $body): string {
        return "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $body;
    }

    private function sendRaw(string $toEmail, string $toName, string $subject, string $mimeBody): bool {
        $this->connect();
        try {
            $this->hello();
            if ($this->encryption === 'tls') {
                $this->command("STARTTLS", 220);
                if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new SmtpException('STARTTLS negotiation failed.');
                }
                $this->hello(); // must re-EHLO after upgrading the connection
            }
            $this->authLogin();
            $this->command("MAIL FROM:<{$this->fromEmail}>", 250);
            $this->command("RCPT TO:<{$toEmail}>", 250);
            $this->command("DATA", 354);

            $headers = [
                'From: ' . $this->encodeHeader($this->fromName) . " <{$this->fromEmail}>",
                'To: ' . $this->encodeHeader($toName) . " <{$toEmail}>",
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Date: ' . date('r'),
                'X-Mailer: ManageMo',
            ];
            // Dot-stuff any line that starts with a lone "." per RFC 5321
            $escapedBody = preg_replace('/^\./m', '..', $mimeBody);
            $payload = implode("\r\n", $headers) . "\r\n" . $escapedBody . "\r\n.";
            $this->command($payload, 250);
            $this->command("QUIT", 221);
            return true;
        } finally {
            $this->close();
        }
    }

    private function connect(): void {
        $prefix = $this->encryption === 'ssl' ? 'ssl://' : '';
        $errno = 0; $errstr = '';
        $this->sock = @stream_socket_client(
            "{$prefix}{$this->host}:{$this->port}",
            $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
        );
        if (!$this->sock) {
            throw new SmtpException("Could not connect to {$this->host}:{$this->port} — $errstr ($errno)");
        }
        $this->readResponse(220);
    }

    private function hello(): void {
        $this->command("EHLO " . (gethostname() ?: 'managemo.local'), 250);
    }

    private function authLogin(): void {
        $this->command("AUTH LOGIN", 334);
        $this->command(base64_encode($this->username), 334);
        $this->command(base64_encode($this->password), 235);
    }

    private function command(string $cmd, int $expectedCode): string {
        fwrite($this->sock, $cmd . "\r\n");
        return $this->readResponse($expectedCode);
    }

    private function readResponse(int $expectedCode): string {
        $response = '';
        while (($line = fgets($this->sock, 515)) !== false) {
            $response .= $line;
            // Multi-line SMTP responses use "code-" until the final "code ".
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new SmtpException("SMTP error (expected $expectedCode): " . trim($response));
        }
        return $response;
    }

    private function encodeHeader(string $value): string {
        // MIME-encode subject/name fields so non-ASCII characters survive intact.
        return preg_match('/[^\x20-\x7E]/', $value)
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }

    private function close(): void {
        if ($this->sock) { fclose($this->sock); $this->sock = null; }
    }
}

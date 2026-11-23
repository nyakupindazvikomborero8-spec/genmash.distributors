<?php
/**
 * Email Service
 */

class EmailService
{
    private static ?PHPMailer $mailer = null;

    public static function getMailer(): PHPMailer
    {
        if (self::$mailer instanceof PHPMailer) {
            return self::$mailer;
        }

        require_once __DIR__ . '/../../vendor/autoload.php'; // Assuming PHPMailer is installed via Composer

        $mail = new PHPMailer(true);
        $config = Config::get('mail');

        try {
            $mailerConfig = $config['mailers'][$config['default']] ?? $config['mailers']['smtp'];
            
            $mail->isSMTP();
            $mail->Host = $mailerConfig['host'];
            $mail->Port = $mailerConfig['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $mailerConfig['username'];
            $mail->Password = $mailerConfig['password'];
            
            if ($mailerConfig['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($mailerConfig['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->Timeout = $mailerConfig['timeout'] ?? 30;
            
            $mail->setFrom(
                $config['from']['address'],
                $config['from']['name']
            );
            
            $mail->addReplyTo(
                $config['reply_to']['address'],
                $config['reply_to']['name']
            );
            
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log("Mailer configuration error: " . $e->getMessage());
            throw new RuntimeException('Email service not configured properly');
        }

        self::$mailer = $mail;
        return $mail;
    }

    public static function send(
        string $toEmail,
        string $subject,
        string $bodyHtml,
        string $toName = '',
        string $bodyText = '',
        array $options = []
    ): bool {
        $mail = self::getMailer();
        
        try {
            // Clear previous recipients
            $mail->clearAddresses();
            $mail->clearAttachments();
            $mail->clearCustomHeaders();
            
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body = $bodyHtml;
            $mail->AltBody = $bodyText ?: strip_tags($bodyHtml);
            
            // Optional CC/BCC
            if (!empty($options['cc'])) {
                foreach ($options['cc'] as $cc) {
                    $mail->addCC($cc['email'] ?? $cc, $cc['name'] ?? '');
                }
            }
            
            if (!empty($options['bcc'])) {
                foreach ($options['bcc'] as $bcc) {
                    $mail->addBCC($bcc['email'] ?? $bcc, $bcc['name'] ?? '');
                }
            }
            
            // Attachments
            if (!empty($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    if (isset($attachment['path'])) {
                        $mail->addAttachment(
                            $attachment['path'],
                            $attachment['name'] ?? basename($attachment['path']),
                            $attachment['encoding'] ?? 'base64',
                            $attachment['type'] ?? 'application/octet-stream'
                        );
                    } elseif (isset($attachment['content'])) {
                        $mail->addStringAttachment(
                            $attachment['content'],
                            $attachment['name'] ?? 'attachment',
                            $attachment['encoding'] ?? 'base64',
                            $attachment['type'] ?? 'application/octet-stream'
                        );
                    }
                }
            }
            
            // Custom headers
            if (!empty($options['headers'])) {
                foreach ($options['headers'] as $name => $value) {
                    $mail->addCustomHeader($name, $value);
                }
            }
            
            $mail->send();
            
            // Log success
            ActivityLog::log('email_sent', "Email sent to {$toEmail}: {$subject}");
            
            return true;
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            ActivityLog::log('email_failed', "Email failed to {$toEmail}: {$e->getMessage()}");
            return false;
        }
    }

    public static function processQueue(int $limit = 50): int
    {
        $sent = 0;
        $emails = EmailQueue::getPending($limit);
        
        foreach ($emails as $email) {
            $email->markAsProcessing();
            
            $success = self::send(
                $email->to_email,
                $email->subject,
                $email->body_html,
                $email->to_name,
                $email->body_text,
                [
                    'cc' => $email->cc ?? [],
                    'bcc' => $email->bcc ?? [],
                    'attachments' => $email->attachments ?? [],
                ]
            );
            
            if ($success) {
                $email->markAsSent();
                $sent++;
            } else {
                $email->markAsFailed('SMTP error or connection failed');
            }
        }
        
        return $sent;
    }

    public static function sendTemplated(
        string $template,
        array $data,
        string $toEmail,
        string $toName = '',
        array $options = []
    ): bool {
        $templatePath = __DIR__ . "/../../resources/emails/{$template}.php";
        
        if (!file_exists($templatePath)) {
            throw new Exception("Email template not found: {$template}");
        }

        // Extract data for template
        extract($data);
        ob_start();
        include $templatePath;
        $bodyHtml = ob_get_clean();

        return self::send($toEmail, $data['subject'] ?? 'Notification', $bodyHtml, $toName, '', $options);
    }
}
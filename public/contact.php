<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
/**
 * contact.php – E-Mail-Verarbeitung für Radiologie Baden-Baden
 * 
 * Ablage: /public/contact.php  (oder im Astro public-Ordner)
 * Das Formular sendet per POST hierher; nach der Verarbeitung
 * wird der Nutzer zurück auf /contact weitergeleitet.
 *
 * Voraussetzung: composer require phpmailer/phpmailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

// ── Konfiguration ────────────────────────────────────────────
define('EMPFAENGER_EMAIL', 'info@radiologie-baden-baden.de');
define('EMPFAENGER_NAME',  'Radiologie Baden-Baden');
define('ABSENDER_NAME',    'Kontaktformular Website');
define('BETREFF_PREFIX',   '[Website-Anfrage] ');
define('REDIRECT_BASE',    'http://localhost:4322/contact');

// ── Mailtrap SMTP ────────────────────────────────────────────
define('SMTP_HOST',     'sandbox.smtp.mailtrap.io');
define('SMTP_PORT',     2525);
define('SMTP_USER',     'e0d83db66a0290'); // ← aus Mailtrap-Settings kopieren
define('SMTP_PASS',     'cc90a4daf5f325'); // ← aus Mailtrap-Settings kopieren
// ─────────────────────────────────────────────────────────────

// ── JSON-Antwort Hilfsfunktion ───────────────────────────────
function jsonResponse(bool $success, string $message, int $httpCode = 200): never {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// Nur POST akzeptieren
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Methode nicht erlaubt.', 405);
}

// ── Honeypot-Check (Spam-Schutz) ─────────────────────────────
if (!empty($_POST['website'])) {
    jsonResponse(true, 'Vielen Dank! Ihre Nachricht wurde erfolgreich gesendet.');
}

// ── Felder einlesen und bereinigen ───────────────────────────
function clean(string $value): string {
    return htmlspecialchars(trim(strip_tags($value)), ENT_QUOTES, 'UTF-8');
}

$vorname    = clean($_POST['vorname']    ?? '');
$nachname   = clean($_POST['nachname']  ?? '');
$email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$telefon    = clean($_POST['telefon']   ?? '');
$betreff    = clean($_POST['betreff']   ?? '');
$nachricht  = clean($_POST['nachricht'] ?? '');


// ── Pflichtfeld-Validierung ──────────────────────────────────
if (empty($vorname)) {
    jsonResponse(false, 'Vorname fehlt.', 422);
}
if (empty($nachname)) {
    jsonResponse(false, 'Nachname fehlt.', 422);
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Bitte geben Sie eine gültige E-Mail-Adresse ein.', 422);
}

// ── E-Mail-Validierung: Blacklist + DNS + SMTP-Verify ────────
$emailDomain = strtolower(substr(strrchr($email, '@'), 1));

// Bekannte Fake/Wegwerf-Domains blockieren
$blacklist = [
    'gaga.com', 'mailinator.com', 'guerrillamail.com', 'tempmail.com',
    'throwaway.email', 'yopmail.com', 'sharklasers.com', 'guerrillamailblock.com',
    'grr.la', 'guerrillamail.info', 'spam4.me', 'trashmail.com', 'trashmail.me',
    'trashmail.net', 'dispostable.com', 'maildrop.cc', 'spamgourmet.com',
    'spamgourmet.net', 'spamgourmet.org', 'spamgourmet.com', 'mintemail.com',
    'tempr.email', 'fakeinbox.com', 'mailnull.com', 'spamfree24.org',
    'getonemail.com', 'mailnew.com', 'spamfree.eu', 'discard.email',
    'spamhereplease.com', 'spamoff.de', 'throwam.com', 'mytemp.email',
    'tempinbox.com', 'temp-mail.org', 'emailondeck.com', 'mailsac.com',
];
if (in_array($emailDomain, $blacklist, true)) {
    jsonResponse(false, 'Bitte verwenden Sie eine echte E-Mail-Adresse. Wegwerf-E-Mail-Adressen werden nicht akzeptiert.', 422);
}

// DNS-Prüfung: Existiert die Domain überhaupt?
if (!checkdnsrr($emailDomain, 'MX') && !checkdnsrr($emailDomain, 'A')) {
    jsonResponse(false, 'Die E-Mail-Domain existiert nicht. Bitte prüfen Sie Ihre E-Mail-Adresse.', 422);
}

// SMTP-Verify: Existiert das Postfach wirklich?
function smtpVerifyEmail(string $email, string $domain): bool {
    // MX-Record holen
    $mxHosts = [];
    if (!getmxrr($domain, $mxHosts)) {
        $mxHosts = [$domain]; // Fallback auf A-Record
    }

    $timeout = 5;
    $sock = @fsockopen($mxHosts[0], 25, $errno, $errstr, $timeout);
    if (!$sock) {
        // Wenn SMTP nicht erreichbar → im Zweifel durchlassen
        return true;
    }
    stream_set_timeout($sock, $timeout);

    $read = function() use ($sock): string {
        $response = '';
        while ($line = fgets($sock, 512)) {
            $response .= $line;
            if ($line[3] === ' ') break; // letzter Zeile der Antwort
        }
        return $response;
    };

    $read(); // 220 Begrüßung
    fwrite($sock, "HELO radiologie-baden-baden.de\r\n"); $read();
    fwrite($sock, "MAIL FROM: <noreply@radiologie-baden-baden.de>\r\n"); $read();
    fwrite($sock, "RCPT TO: <{$email}>\r\n");
    $rcptResponse = $read();
    fwrite($sock, "QUIT\r\n");
    fclose($sock);

    // 250 = Postfach existiert, 550/551/553 = existiert nicht
    $code = (int) substr(trim($rcptResponse), 0, 3);
    if (in_array($code, [550, 551, 552, 553, 554], true)) {
        return false;
    }
    return true; // Bei 250 oder unbekanntem Code → durchlassen
}

if (!smtpVerifyEmail($email, $emailDomain)) {
    jsonResponse(false, 'Diese E-Mail-Adresse existiert nicht. Bitte geben Sie eine gültige Adresse ein.', 422);
}

if (empty($betreff)) {
    jsonResponse(false, 'Betreff fehlt.', 422);
}
if (empty($nachricht) || mb_strlen($nachricht) < 10) {
    jsonResponse(false, 'Nachricht zu kurz (mindestens 10 Zeichen).', 422);
}

// ── E-Mail zusammenstellen ───────────────────────────────────
$vollname    = $vorname . ' ' . $nachname;
$mailBetreff = BETREFF_PREFIX . $betreff;

$mailBody  = "Neue Kontaktanfrage über das Website-Formular\n";
$mailBody .= str_repeat('─', 50) . "\n\n";
$mailBody .= "Name:      {$vollname}\n";
$mailBody .= "E-Mail:    {$email}\n";
if (!empty($telefon)) {
    $mailBody .= "Telefon:   {$telefon}\n";
}
$mailBody .= "Betreff:   {$betreff}\n\n";
$mailBody .= "Nachricht:\n";
$mailBody .= str_repeat('─', 50) . "\n";
$mailBody .= $nachricht . "\n\n";
$mailBody .= str_repeat('─', 50) . "\n";
$mailBody .= "Gesendet am: " . date('d.m.Y H:i:s') . " Uhr\n";

// ── Hilfsfunktion: PHPMailer-Instanz konfigurieren ──────────
function mailerInstanz(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug = 0;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];
    $mail->Host       = SMTP_HOST;
    $mail->Port       = SMTP_PORT;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet    = 'UTF-8';
    return $mail;
}

// ── E-Mail an Praxis senden ──────────────────────────────────
$gesendet = false;
try {
    $mail = mailerInstanz();
    $mail->setFrom('noreply@radiologie-baden-baden.de', ABSENDER_NAME);
    $mail->addAddress(EMPFAENGER_EMAIL, EMPFAENGER_NAME);
    $mail->addReplyTo($email, $vollname);
    $mail->Subject = $mailBetreff;
    $mail->Body    = $mailBody;
    $mail->send();
    $gesendet = true;
} catch (Exception $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Beim Senden ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.', 500);
}

// ── Bestätigungs-E-Mail an Absender ─────────────────────────
    try {
        $mailKonfirm = mailerInstanz();
        $mailKonfirm->setFrom(EMPFAENGER_EMAIL, EMPFAENGER_NAME);
        $mailKonfirm->addAddress($email, $vollname);
        $mailKonfirm->Subject = 'Ihre Anfrage bei ' . EMPFAENGER_NAME;
        $mailKonfirm->send();
    } catch (Exception $e) {
        // Bestätigungs-Mail optional – Fehler ignorieren
    }

// ── Erfolgsantwort ───────────────────────────────────────────
jsonResponse(true, 'Vielen Dank! Ihre Nachricht wurde erfolgreich gesendet.');

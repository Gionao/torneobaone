<?php
// includes/media_thumbnail.php
// ============================================================
//  Generazione miniature con GD (JPEG/PNG).
//  HEIC e video non hanno supporto GD: restano senza miniatura
//  e la UI mostra un segnaposto. Un errore non blocca il file.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/media_config.php';
require_once __DIR__ . '/media_storage.php';

function media_genera_miniatura(string $sorgente, string $relativaMiniatura, string $estensione): bool
{
    $estensione = strtolower($estensione);
    if (!in_array($estensione, ['jpg', 'jpeg', 'png'], true)) {
        return false;
    }
    if (!function_exists('imagecreatefromjpeg') || !is_file($sorgente)) {
        return false;
    }

    try {
        $info = @getimagesize($sorgente);
        if ($info === false) {
            return false;
        }
        [$larghezza, $altezza] = [(int) $info[0], (int) $info[1]];
        if ($larghezza < 1 || $altezza < 1) {
            return false;
        }
        // Limite di sicurezza: evita di saturare la memoria con immagini enormi.
        if ($larghezza * $altezza > 60_000_000) {
            return false;
        }
        $memoria = media_php_bytes(ini_get('memory_limit'));
        if ($memoria > 0) {
            // Stima prudente: bitmap a 32 bit + copia ridimensionata.
            $stima = $larghezza * $altezza * 4 * 3;
            if ($stima > ($memoria * 0.6)) {
                return false;
            }
        }

        $max = media_thumb_max_px();
        $scala = min(1, $max / max($larghezza, $altezza));
        $nuovaL = max(1, (int) round($larghezza * $scala));
        $nuovaA = max(1, (int) round($altezza * $scala));

        $immagine = $estensione === 'png'
            ? @imagecreatefrompng($sorgente)
            : @imagecreatefromjpeg($sorgente);

        if ($immagine === false) {
            return false;
        }

        // Orientamento EXIF per le foto da smartphone.
        if ($estensione !== 'png' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($sorgente);
            $orientamento = (int) ($exif['Orientation'] ?? 1);
            if ($orientamento !== 1) {
                $angolo = match ($orientamento) {
                    3 => 180,
                    6 => -90,
                    8 => 90,
                    default => 0,
                };
                if ($angolo !== 0) {
                    $ruotata = @imagerotate($immagine, $angolo, 0);
                    if ($ruotata !== false) {
                        imagedestroy($immagine);
                        $immagine = $ruotata;
                        [$larghezza, $altezza] = [imagesx($immagine), imagesy($immagine)];
                        $nuovaL = max(1, (int) round($larghezza * $scala));
                        $nuovaA = max(1, (int) round($altezza * $scala));
                    }
                }
            }
        }

        $miniatura = imagecreatetruecolor($nuovaL, $nuovaA);
        if ($miniatura === false) {
            imagedestroy($immagine);
            return false;
        }

        if ($estensione === 'png') {
            imagealphablending($miniatura, false);
            imagesavealpha($miniatura, true);
            $trasparente = imagecolorallocatealpha($miniatura, 0, 0, 0, 127);
            imagefilledrectangle($miniatura, 0, 0, $nuovaL, $nuovaA, $trasparente);
        }

        imagecopyresampled($miniatura, $immagine, 0, 0, 0, 0, $nuovaL, $nuovaA, $larghezza, $altezza);
        imagedestroy($immagine);

        $destinazione = media_storage_path($relativaMiniatura);
        $ok = @imagejpeg($miniatura, $destinazione, 82);
        imagedestroy($miniatura);

        return $ok && is_file($destinazione);
    } catch (Throwable $e) {
        return false;
    }
}

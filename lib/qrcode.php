<?php
// QR Code Generator Library
class QRCodeGenerator {
    private $api_url = 'https://api.qrserver.com/v1/create-qr-code/';
    
    /**
     * Generate QR code image from data
     * @param string $qr_code_id The QR code ID to encode
     * @param int $size Size of the QR code (default 200)
     * @return string URL to the generated QR code image
     */
    public static function generateQRCodeImage($qr_code_id, $size = 200) {
        $api_url = 'https://api.qrserver.com/v1/create-qr-code/';
        return $api_url . '?size=' . $size . 'x' . $size . '&data=' . urlencode($qr_code_id);
    }
    
    /**
     * Download and save QR code locally
     * @param string $qr_code_id The QR code ID
     * @param string $save_path Path to save the QR code image
     * @return bool Success status
     */
    public static function saveQRCodeLocally($qr_code_id, $save_path) {
        $image_url = self::generateQRCodeImage($qr_code_id, 300);
        
        // Create directory if not exists
        $directory = dirname($save_path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Download and save image
        $image_data = file_get_contents($image_url);
        if ($image_data !== false) {
            file_put_contents($save_path, $image_data);
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate multiple QR codes
     * @param array $qr_ids Array of QR code IDs
     * @return array Array of URLs for QR codes
     */
    public static function generateMultipleQRCodes($qr_ids) {
        $results = [];
        foreach ($qr_ids as $id) {
            $results[$id] = self::generateQRCodeImage($id);
        }
        return $results;
    }
}
?>

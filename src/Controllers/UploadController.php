<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Auth;
use App\Support\Env;
use App\Support\Request;
use App\Support\Response;

final class UploadController
{
    public static function signCloudinary(): void
    {
        Auth::requireAdmin();
        $cloudName = Env::get('CLOUDINARY_CLOUD_NAME');
        $apiKey = Env::get('CLOUDINARY_API_KEY');
        $apiSecret = Env::get('CLOUDINARY_API_SECRET');
        if (!$cloudName || !$apiKey || !$apiSecret) Response::error('Cloudinary não configurada.', 500);
        $data = Request::json();
        $timestamp = (int)($data['timestamp'] ?? time());
        $folder = trim((string)($data['folder'] ?? 'autentica/products'));
        $signature = sha1('folder=' . $folder . '&timestamp=' . $timestamp . $apiSecret);
        Response::json(['ok' => true, 'cloud_name' => $cloudName, 'api_key' => $apiKey, 'timestamp' => $timestamp, 'folder' => $folder, 'signature' => $signature]);
    }
}

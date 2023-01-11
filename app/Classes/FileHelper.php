<?php

namespace App\Classes;

use DirectoryIterator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class FileHelper
{
    /**
     * Create folder if not exists
     * @param string $folder
     * @return string
     */
    public static function createFolder(string $folder): string
    {
        // Check folder exists
        !is_dir($folder) && mkdir($folder, 0775, true);

        return $folder;
    }

    /**
     * Save $file to $path directory
     * @param UploadedFile $file
     * @param string $path
     * @param string|null $settings_key
     * @return string
     */
    public static function saveFile(UploadedFile $file, string $path, ?string $settings_key = null): string
    {
        $path = FileHelper::createFolder(public_path($path));
        $filename = $file->getClientOriginalName();
        $file_info = pathinfo($filename);

        $ext = match ($file->getClientMimeType()) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            default => $file_info['extension'],
        };

        $filename = sprintf('%s.%s', generateUrl($file_info['filename']), $ext);
        $file->move($path, $filename);

        return Str::finish(substr($path, strlen(public_path())), '/') . $filename;
    }

    /**
     * Remove file
     * @param string $file_path
     * @return void
     */
    public static function removeFile(string $file_path): void
    {
        $path = public_path($file_path);
        if (file_exists($path) && is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Recursive remove folder
     * @param string $path
     */
    public static function recursiveRemove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        } else {
            $files = new DirectoryIterator($path);

            foreach ($files as $file) {
                if (!$file->isDot()) {
                    if ($file->isDir()) {
                        self::recursiveRemove($file->getPathname());
                    } else {
                        unlink($file->getPathname());
                    }
                }
            }

            rmdir($path);
        }
    }
}
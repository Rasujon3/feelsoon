<?php

    namespace App\Services;

    use FFMpeg\FFMpeg;
    use FFMpeg\Format\Video\X264;
    use Illuminate\Support\Facades\Storage;

    class HLSConverter
    {
        public static function convertToHLS($inputPath, $outputFolder)
        {
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
                'timeout' => 3600, // Max execution time
            ]);

            $video = $ffmpeg->open($inputPath);

            // Ensure local temp folder exists
            Storage::disk('local')->makeDirectory("temp/$outputFolder");

            $outputPath = storage_path("app/temp/$outputFolder/output.m3u8");

            $video->filters()->resize(new \FFMpeg\Coordinate\Dimension(1280, 720));

            $format = new X264();
            $format->setKiloBitrate(1500);

            // Convert to HLS (.m3u8)
            $video->save($format, $outputPath);

            return storage_path("app/temp/$outputFolder");
        }
    }

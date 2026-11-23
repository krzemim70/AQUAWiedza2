<?php

namespace App\Domain\Advisor;

use App\Domain\Advisor\Actions\ExportCreatorBundle;
use Illuminate\Support\Facades\Storage;

/** Zarządza wersjonowanym plikiem JSON pakietu katalogu dla kreatora akwarium. */
class CreatorBundle
{
    private const DIRECTORY = 'creator';

    public function __construct(private ExportCreatorBundle $exporter) {}

    public function currentVersion(): string
    {
        return $this->exporter->version();
    }

    /** @return array{version: string, url: string} */
    public function ensureBuilt(): array
    {
        $version = $this->currentVersion();
        $disk = Storage::disk('local');
        if (! $disk->exists($this->path($version))) {
            return $this->write($version);
        }

        return [
            'version' => $version,
            'url' => route('creator.bundle', ['version' => $version]),
        ];
    }

    /** @return array{version: string, url: string} */
    public function rebuild(): array
    {
        return $this->write($this->currentVersion());
    }

    public function contents(): string
    {
        $built = $this->ensureBuilt();

        return Storage::disk('local')->get($this->path($built['version'])) ?? '{}';
    }

    public function path(string $version): string
    {
        return self::DIRECTORY.'/catalog-'.$version.'.json';
    }

    /** @return array{version: string, url: string} */
    private function write(string $version): array
    {
        $disk = Storage::disk('local');
        $path = $this->path($version);
        $payload = json_encode(
            $this->exporter->handle(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $disk->put($path, $payload);

        foreach ($disk->files(self::DIRECTORY) as $file) {
            $name = basename($file);
            if ($file !== $path && str_starts_with($name, 'catalog-') && str_ends_with($name, '.json')) {
                $disk->delete($file);
            }
        }

        return [
            'version' => $version,
            'url' => route('creator.bundle', ['version' => $version]),
        ];
    }
}

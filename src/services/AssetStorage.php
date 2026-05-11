<?php

namespace eseperio\aiagent\services;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Yii;
use yii\base\Component;
use yii\helpers\FileHelper;

class AssetStorage extends Component
{
    public ?FilesystemOperator $filesystem = null;
    public string $root = '@runtime/ai-agent-assets';
    public int $temporaryUrlTtl = 3600;

    public function init(): void
    {
        parent::init();
        if (!$this->filesystem instanceof FilesystemOperator) {
            $root = Yii::getAlias($this->root);
            FileHelper::createDirectory($root);
            $this->filesystem = new Filesystem(new LocalFilesystemAdapter($root));
        }
    }

    public function write(string $path, string $contents): void
    {
        $this->filesystem->write($path, $contents);
    }

    public function read(string $path): string
    {
        return $this->filesystem->read($path);
    }

    public function readStream(string $path)
    {
        return $this->filesystem->readStream($path);
    }

    public function delete(string $path): void
    {
        if ($this->filesystem->fileExists($path)) {
            $this->filesystem->delete($path);
        }
    }

    public function fileExists(string $path): bool
    {
        return $this->filesystem->fileExists($path);
    }

    public function temporaryPath(string $path, string $extension = 'bin'): string
    {
        $extension = preg_replace('/[^a-z0-9]/i', '', $extension) ?: 'bin';
        $tmp = tempnam(sys_get_temp_dir(), 'ai-agent-asset-');
        $target = $tmp . '.' . $extension;
        rename($tmp, $target);
        file_put_contents($target, $this->read($path));
        return $target;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'serve')]
class ServeCommand extends BaseServeCommand
{
    /**
     * Get the full server command with development upload limits applied to the spawned PHP worker.
     *
     * @return array<int, string>
     */
    protected function serverCommand()
    {
        /** @var array<int, string> $command */
        $command = parent::serverCommand();
        $binary = array_shift($command);

        if (! is_string($binary) || $binary === '') {
            return array_values($command);
        }

        return [
            $binary,
            '-d',
            'upload_max_filesize=8M',
            '-d',
            'post_max_size=10M',
            ...array_values($command),
        ];
    }
}

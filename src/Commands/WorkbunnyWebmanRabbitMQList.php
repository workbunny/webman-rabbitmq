<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Commands;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkbunnyWebmanRabbitMQList extends AbstractCommand
{
    protected static $defaultName        = 'workbunny:rabbitmq-list';
    protected static $defaultDescription = 'Show workbunny/webman-rabbitmq Builders list. ';

    protected function configure()
    {
        $this->setName('workbunny:rabbitmq-list')
            ->setDescription('Show workbunny/webman-rabbitmq Builders list. ');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $headers = ['name', 'file', 'handler', 'count', 'mode'];
        $rows = [];
        $basePath = base_path() . '/' . self::$baseProcessPath;
        $files = self::files($basePath);
        $configs = config('plugin.workbunny.webman-rabbitmq.process', []);
        foreach ($files as $file) {
            $key = str_replace(
                '/',
                '.',
                str_replace(base_path() . '/' , '', str_replace('.php', '', $file->getPathname()))
            );
            $name = str_replace("$basePath/", '', str_replace('.php', '', $file->getPathname()));
            $rows[] = [
                $name,
                $file->getRealPath(),
                $configs[$key]['handler'] ?? '--',
                $configs[$key]['count'] ?? '--',
                $configs[$key]['mode'] ?? '--'
            ];
        }

        $table = new Table($output);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->render();

        return self::SUCCESS;
    }

    /**
     * @param string $path
     * @return SplFileInfo[]
     */
    public static function files(string $path): array
    {
        $files = [];
        if (is_dir($path)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::FOLLOW_SYMLINKS));
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (
                    $file->isFile() and
                    preg_match('/.*Builder.*\.php$/', $file->getFilename())
                ) {
                    $files[] = $file;
                }
            }
        }
        return $files;
    }
}

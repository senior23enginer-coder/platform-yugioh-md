<?php
declare(strict_types=1);

namespace StationX\Yugioh\Infrastructure\Http\FormActions;

use PDO;
use StationX\Yugioh\Application\Commands\FormCommand;
use StationX\Yugioh\Application\Commands\FormCommandHandler;

/** Scopes PHP globals for legacy callees, including recursive offline synchronization. */
abstract class NativeFormHandler implements FormCommandHandler
{
    public function __construct(private readonly PDO $pdo) {}

    final public function execute(FormCommand $command): ?string
    {
        $hadPost = array_key_exists('_POST', $GLOBALS);
        $hadFiles = array_key_exists('_FILES', $GLOBALS);
        $post = $_POST ?? [];
        $files = $_FILES ?? [];
        $_POST = $command->input;
        $_FILES = $command->files;
        try {
            return $this->perform($this->pdo, $command->action, $command->input, $command->files);
        } finally {
            if ($hadPost) {
                $_POST = $post;
            } else {
                unset($_POST);
            }
            if ($hadFiles) {
                $_FILES = $files;
            } else {
                unset($_FILES);
            }
        }
    }

    abstract protected function perform(
        PDO $pdo,
        string $action,
        array $post,
        array $files,
    ): ?string;
}

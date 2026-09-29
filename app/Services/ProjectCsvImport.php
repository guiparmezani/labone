<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Lê a lista de peças do cliente: nome do projeto na primeira linha,
 * nomes das tarefas na terceira coluna a partir da terceira linha.
 */
class ProjectCsvImport
{
    /**
     * @return array{name: string, tasks: list<string>}
     */
    public function read(string $contents): array
    {
        $rows = $this->rows($contents);
        $name = trim((string) ($rows[0][2] ?? ''));

        if (mb_strlen($name) < 2) {
            throw new InvalidArgumentException('A primeira linha não tem o nome do projeto.');
        }

        if (mb_strlen($name) > 160) {
            throw new InvalidArgumentException('O nome do projeto pode ter no máximo 160 caracteres.');
        }

        $tasks = [];
        $vistos = [];

        foreach (array_slice($rows, 2) as $row) {
            $task = trim((string) ($row[2] ?? ''));

            if ($task === '') {
                continue;
            }

            if (mb_strlen($task) > 160) {
                throw new InvalidArgumentException('O nome da tarefa pode ter no máximo 160 caracteres.');
            }

            if (mb_strlen($task) < 2) {
                throw new InvalidArgumentException('O nome da tarefa precisa ter pelo menos 2 caracteres.');
            }

            $chave = mb_strtolower($task);

            if (isset($vistos[$chave])) {
                continue;
            }

            $vistos[$chave] = true;
            $tasks[] = $task;
        }

        return ['name' => $name, 'tasks' => $tasks];
    }

    /**
     * @return list<list<string|null>>
     */
    private function rows(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        if ($contents !== '' && ! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, null, ';', '"', '')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}

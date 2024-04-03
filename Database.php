<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * @throws Exception
     */
    public function buildQuery(string $query, array $args = []): string
    {
        $index = 0;
        while (($openBracePos = strpos($query, '{')) !== false) {
            $closeBracePos = strpos($query, '}', $openBracePos);
            if ($closeBracePos === false) {
                throw new Exception("Unmatched opening brace '{'.");
            }

            $block = substr($query, $openBracePos + 1, $closeBracePos - $openBracePos - 1);

            $insideBlock = false;

            foreach ($args as $arg) {
                if (str_contains($block, '?') && $arg == $this->skip()) {
                    $insideBlock = true;
                    break;
                }
            }

            if ($insideBlock) {
                $query = substr_replace($query, '', $openBracePos, $closeBracePos - $openBracePos + 1);
            } else {
                $query = substr_replace($query, '', $closeBracePos, 1);
                $query = substr_replace($query, '', $openBracePos, 1);
            }
        }

        while (($pos = strpos($query, '?', $index)) !== false) {
            $placeholder = substr($query, $pos, 2);

            $arg = $args[$index++] ?? null;
            $formattedArg = $this->formatArgumentByPlaceholder($arg, $placeholder);

            $query = substr_replace($query, $formattedArg, $pos, 2);
        }

        return $query;
    }

    private function formatArgumentByPlaceholder($arg, string $placeholder): string
    {
        switch ($placeholder) {
            case '?d':
                return (int)$arg;
            case '?f':
                return (float)$arg;
            case '?a':
                $formattedValues = [];
                foreach ($arg as $key => $item) {
                    if ($key === null || is_int($key)) {
                        $formattedValues[] = $this->formatArgument($item);
                    } else {
                        $formattedValues[] = '`' . $key . '` = ' . $this->formatArgument($item);
                    }
                }
                return implode(', ', $formattedValues);
            case '?#':
                if (!is_array($arg)) {
                    $arg = [$arg];
                }
                $formattedIdentifiers = [];
                foreach ($arg as $identifier) {
                    $formattedIdentifiers[] = $this->formatArgument($identifier, true);
                }
                return implode(', ', $formattedIdentifiers);
            default:
                return $this->formatArgument($arg) . ' ';
        }
    }

    private function formatArgument($arg, $isColumn = false): string
    {
        if ($arg === null) {
            return 'NULL';
        } elseif (is_bool($arg)) {
            return $arg ? '1' : '0';
        } elseif (is_int($arg)) {
            return (string)$arg;
        } elseif (is_float($arg)) {
            return sprintf('%F', $arg);
        } elseif (is_string($arg)) {
            $arg = $this->mysqli->real_escape_string($arg);
            if ($isColumn) {
                return "`" . $arg . "`";
            }
            return "'" . $arg . "'";
        } else {
            return $arg;
        }
    }


    public function skip(): string
    {
        return '?a';
    }
}

<?php
declare(strict_types=1);

function convertForJson(mixed $data): mixed
{
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = convertForJson($value);
        }
        return $data;
    } elseif (is_string($data)) {
        if (!mb_check_encoding($data, 'UTF-8')) {
            return 'hex->' . strtoupper(bin2hex($data));
        }
        return $data;
    }
    return $data;
}

<?php
declare(strict_types=1);

namespace ShengBot\Protobuf;

class Serializer
{
    const WIRE_TYPE_VARINT = 0;
    const WIRE_TYPE_64BIT = 1;
    const WIRE_TYPE_LENGTH_DELIMITED = 2;
    const WIRE_TYPE_32BIT = 5;

    public static function serializeJsonToProtobuf(array $jsonData): string
    {
        $data = '';

        foreach ($jsonData as $fieldNumber => $value) {
            $fieldNumber = intval($fieldNumber);
            $wireType = self::getWireType($value);

            if ($wireType === null) {
                throw new \Exception("Unsupported value type for field $fieldNumber");
            }

            if (is_array($value)) {
                if (self::isAssociativeArray($value)) {
                    $nestedData = self::serializeJsonToProtobuf($value);
                    $data .= self::encodeField($fieldNumber, self::WIRE_TYPE_LENGTH_DELIMITED);
                    $data .= self::encodeVarint(strlen($nestedData)) . $nestedData;
                } else {
                    foreach ($value as $item) {
                        $data .= self::encodeField($fieldNumber, self::WIRE_TYPE_LENGTH_DELIMITED);
                        $data .= self::encodeValue(self::WIRE_TYPE_LENGTH_DELIMITED, $item);
                    }
                }
            } else {
                $data .= self::encodeField($fieldNumber, $wireType);
                $data .= self::encodeValue($wireType, $value);
            }
        }

        return $data;
    }

    private static function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private static function getWireType(mixed $value): ?int
    {
        if (is_string($value) && ctype_digit($value)) {
            return self::WIRE_TYPE_VARINT;
        }
        if (is_int($value)) {
            return self::WIRE_TYPE_VARINT;
        } elseif (is_float($value)) {
            return (abs($value) > 16777216 || abs($value) < 1.0E-37) ? self::WIRE_TYPE_64BIT : self::WIRE_TYPE_32BIT;
        } elseif (is_string($value)) {
            return self::WIRE_TYPE_LENGTH_DELIMITED;
        } elseif (is_bool($value)) {
            return self::WIRE_TYPE_VARINT;
        } elseif (is_array($value)) {
            return self::WIRE_TYPE_LENGTH_DELIMITED;
        }

        return null;
    }

    private static function encodeField(int $fieldNumber, int $wireType): string
    {
        return self::encodeVarint(($fieldNumber << 3) | $wireType);
    }

    private static function encodeValue(int $wireType, mixed $value): string
    {
        switch ($wireType) {
            case self::WIRE_TYPE_VARINT:
                return self::encodeVarint($value);
            case self::WIRE_TYPE_64BIT:
                return self::encodeDouble($value);
            case self::WIRE_TYPE_32BIT:
                return self::encodeFloat($value);
            case self::WIRE_TYPE_LENGTH_DELIMITED:
                if (is_string($value)) {
                    return self::encodeString($value);
                } elseif (is_array($value)) {
                    if (self::isAssociativeArray($value)) {
                        $nestedData = self::serializeJsonToProtobuf($value);
                        return self::encodeVarint(strlen($nestedData)) . $nestedData;
                    } else {
                        return self::encodeRepeatedField($value);
                    }
                }
                break;
        }

        throw new \Exception("Unsupported wire type: $wireType");
    }

    private static function encodeVarint(int $value): string
    {
        $data = '';
        while ($value > 0x7F) {
            $data .= chr(($value & 0x7F) | 0x80);
            $value >>= 7;
        }
        $data .= chr($value);
        return $data;
    }

    private static function encodeString(string $value): string
    {
        $length = strlen($value);
        return self::encodeVarint($length) . $value;
    }

    private static function encodeFloat(float $value): string
    {
        return pack('G', $value);
    }

    private static function encodeDouble(float $value): string
    {
        return pack('E', $value);
    }

    private static function encodeRepeatedField(array $values): string
    {
        $data = '';
        foreach ($values as $value) {
            $wireType = self::getWireType($value);
            if ($wireType === null) {
                throw new \Exception("Unsupported value type in repeated field");
            }
            $data .= self::encodeValue($wireType, $value);
        }
        return self::encodeVarint(strlen($data)) . $data;
    }
}

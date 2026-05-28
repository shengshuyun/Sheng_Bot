<?php
declare(strict_types=1);

namespace ShengBot\Protobuf;

class Deserializer
{
    const WIRE_TYPE_VARINT = 0;
    const WIRE_TYPE_64BIT = 1;
    const WIRE_TYPE_LENGTH_DELIMITED = 2;
    const WIRE_TYPE_32BIT = 5;

    public static function deserialize(string $binaryData): array
    {
        $data = [];
        $offset = 0;
        $length = strlen($binaryData);

        while ($offset < $length) {
            if ($offset >= $length) {
                throw new \Exception("Unexpected end of data while reading tag");
            }

            [$fieldNumber, $wireType, $offset] = self::decodeTag($binaryData, $offset, $length);

            switch ($wireType) {
                case self::WIRE_TYPE_VARINT:
                    [$value, $offset] = self::decodeVarint($binaryData, $offset, $length);
                    break;
                case self::WIRE_TYPE_64BIT:
                    [$value, $offset] = self::decode64Bit($binaryData, $offset, $length);
                    break;
                case self::WIRE_TYPE_LENGTH_DELIMITED:
                    [$value, $offset] = self::decodeLengthDelimited($binaryData, $offset, $length);
                    break;
                case self::WIRE_TYPE_32BIT:
                    [$value, $offset] = self::decode32Bit($binaryData, $offset, $length);
                    break;
                default:
                    throw new \Exception("Unsupported wire type: $wireType");
            }

            if (isset($data[$fieldNumber])) {
                if (!is_array($data[$fieldNumber])) {
                    $data[$fieldNumber] = [$data[$fieldNumber]];
                }
                $data[$fieldNumber][] = $value;
            } else {
                $data[$fieldNumber] = $value;
            }
        }

        return $data;
    }

    private static function decodeTag(string $binaryData, int $offset, int $length): array
    {
        [$tag, $offset] = self::decodeVarint($binaryData, $offset, $length);
        $fieldNumber = $tag >> 3;
        $wireType = $tag & 0x07;
        return [$fieldNumber, $wireType, $offset];
    }

    private static function decodeVarint(string $binaryData, int $offset, int $length): array
    {
        $value = 0;
        $shift = 0;

        do {
            if ($offset >= $length) {
                throw new \Exception("Unexpected end of data while reading varint");
            }
            $byte = ord($binaryData[$offset++]);
            $value |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while (($byte & 0x80) !== 0);

        return [$value, $offset];
    }

    private static function decode64Bit(string $binaryData, int $offset, int $length): array
    {
        if ($offset + 8 > $length) {
            throw new \Exception("Not enough data for 64-bit value");
        }
        $value = unpack('P', substr($binaryData, $offset, 8))[1];
        return [$value, $offset + 8];
    }

    private static function decode32Bit(string $binaryData, int $offset, int $length): array
    {
        if ($offset + 4 > $length) {
            throw new \Exception("Not enough data for 32-bit value");
        }
        $value = unpack('V', substr($binaryData, $offset, 4))[1];
        return [$value, $offset + 4];
    }

    private static function decodeLengthDelimited(string $binaryData, int $offset, int $length): array
    {
        [$valueLength, $offset] = self::decodeVarint($binaryData, $offset, $length);

        if ($offset + $valueLength > $length) {
            throw new \Exception("Not enough data for length-delimited value");
        }

        $value = substr($binaryData, $offset, $valueLength);
        $offset += $valueLength;

        try {
            $nestedData = self::deserialize($value);
            return [$nestedData, $offset];
        } catch (\Exception $e) {
            return [$value, $offset];
        }
    }
}

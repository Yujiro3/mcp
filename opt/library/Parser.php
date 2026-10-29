<?php

namespace Library;

class Parser
{
    /**
     * JSON 形式から MCP スキーマ配列を取得
     *
     * @param string $json    JSON 形式のデータ
     * @return array
     */
    public static function jsonToMcpSchema(string $json): array
    {
        $json = trim($json);
        $json = str_replace(["\n", "\t", " "], '', $json);

        $parsed = self::_parseRawJson($json);

        $properties = [];
        if (is_array($parsed)) {
            foreach ($parsed as $key => $val) {
                if ($key === 'required') continue;
                $properties[$key] = self::_buildSchema($val);
            }
        }

        $res = [
            'type' => 'object',
            'properties' => $properties
        ];

        if (is_array($parsed) && isset($parsed['required'])) {
            $res['required'] = self::_parseRequired($parsed['required']);
        }

        return $res;
    }

    private static function _parseRawJson(string &$json): mixed
    {
        if ($json === '') return null;

        if ($json[0] === '{') {
            $json = substr($json, 1);
            $obj = [];
            while ($json !== '' && $json[0] !== '}') {
                $prevLength = strlen($json);

                if (preg_match('/^(?:"([^"]+)"|([a-zA-Z0-9_]+)):/', $json, $m)) {
                    $key = $m[1] !== '' ? $m[1] : $m[2];
                    $json = substr($json, strlen($m[0]));
                    $value = self::_parseRawJson($json);
                    if ($json !== '' && $json[0] === '=') {
                        $json = substr($json, 1);
                        $defaultVal = self::_parseRawJson($json);
                        if (is_array($value) && !isset($value['type'])) {
                            $value = [
                                'type' => $value,
                                'default' => $defaultVal
                            ];
                        } else {
                            $value = [
                                'type' => $value,
                                'default' => $defaultVal
                            ];
                        }
                    }
                    $obj[$key] = $value;
                }

                if ($json !== '' && $json[0] === ',') {
                    $json = substr($json, 1);
                }

                // Infinite loop protection inside object loop
                if (strlen($json) === $prevLength) {
                    $json = substr($json, 1);
                }
            }
            if ($json !== '' && $json[0] === '}') {
                $json = substr($json, 1);
            }
            return $obj;
        } elseif ($json[0] === '[') {
            $json = substr($json, 1);
            $arr = [];
            while ($json !== '' && $json[0] !== ']') {
                $prevLength = strlen($json);

                $item = self::_parseRawJson($json);
                $arr[] = $item;

                if ($json !== '' && $json[0] === ',') {
                    $json = substr($json, 1);
                }

                // Infinite loop protection inside array loop
                if (strlen($json) === $prevLength) {
                    $json = substr($json, 1);
                }
            }
            if ($json !== '' && $json[0] === ']') {
                $json = substr($json, 1);
            }
            return $arr;
        } else {
            $len = strlen($json);
            $token = '';
            $bracketLevel = 0;
            $braceLevel = 0;
            for ($i = 0; $i < $len; $i++) {
                $ch = $json[$i];
                if ($ch === '[') $bracketLevel++;
                elseif ($ch === ']') {
                    if ($bracketLevel > 0) $bracketLevel--;
                    else break;
                }
                elseif ($ch === '{') $braceLevel++;
                elseif ($ch === '}') {
                    if ($braceLevel > 0) $braceLevel--;
                    else break;
                }
                elseif ($ch === ',' && $bracketLevel === 0 && $braceLevel === 0) {
                    break;
                }
                $token .= $ch;
            }

            if ($token === '') {
                // Advance at least 1 character to prevent infinite loop
                $token = $json[0];
            }

            $json = substr($json, strlen($token));
            return $token;
        }
    }

    private static function _parseRequired(mixed $req): array
    {
        if (is_array($req)) {
            $res = [];
            foreach ($req as $item) {
                if (is_string($item)) {
                    $res[] = trim($item, "'\"");
                }
            }
            return $res;
        } elseif (is_string($req)) {
            return [trim($req, "'\"")];
        }
        return [];
    }

    private static function _parseValue(mixed $val): mixed
    {
        if (is_string($val)) {
            $val = trim($val, "'\"");
            if ($val === 'true') return true;
            if ($val === 'false') return false;
            if ($val === 'null') return null;
            if (is_numeric($val)) return str_contains($val, '.') ? (float)$val : (int)$val;
            if (str_starts_with($val, '[') && str_ends_with($val, ']')) {
                $inner = substr($val, 1, -1);
                $parts = explode(',', $inner);
                return array_map(fn($p) => self::_parseValue($p), $parts);
            }
        } elseif (is_array($val)) {
            $res = [];
            foreach ($val as $k => $v) {
                $res[$k] = self::_parseValue($v);
            }
            return $res;
        }
        return $val;
    }

    private static function _buildSchema(mixed $node): mixed
    {
        if (is_array($node)) {
            if (isset($node['type']) || isset($node['enum'])) {
                $schema = [];
                $typeVal = $node['type'] ?? null;
                $default = null;
                $hasDefault = false;

                if (isset($node['default'])) {
                    $hasDefault = true;
                    $default = self::_parseValue($node['default']);
                }

                if (is_string($typeVal)) {
                    if (str_contains($typeVal, '=')) {
                        list($typeVal, $defaultStr) = explode('=', $typeVal, 2);
                        if (!$hasDefault) {
                            $hasDefault = true;
                            $default = self::_parseValue($defaultStr);
                        }
                    }
                    $typeSchema = self::_buildSchema($typeVal);
                    if (isset($typeSchema['default']) && !$hasDefault) {
                        $hasDefault = true;
                        $default = $typeSchema['default'];
                        unset($typeSchema['default']);
                    }
                    $schema = array_merge($schema, $typeSchema);
                } elseif (is_array($typeVal)) {
                    $typeSchema = self::_buildSchema($typeVal);
                    if (isset($typeSchema['default']) && !$hasDefault) {
                        $hasDefault = true;
                        $default = $typeSchema['default'];
                        unset($typeSchema['default']);
                    }
                    $schema = array_merge($schema, $typeSchema);
                }

                if ($hasDefault) {
                    $schema['default'] = $default;
                }

                foreach ($node as $k => $v) {
                    if ($k === 'type' || $k === 'default') continue;
                    if ($k === 'enum') {
                        if (is_array($v)) {
                            $enumArr = [];
                            foreach ($v as $eItem) {
                                if (is_string($eItem)) {
                                    foreach (explode('|', $eItem) as $p) {
                                        $enumArr[] = self::_parseValue($p);
                                    }
                                } else {
                                    $enumArr[] = self::_parseValue($eItem);
                                }
                            }
                            $schema['enum'] = array_values(array_unique($enumArr));
                        } else if (is_string($v)) {
                            $enumArr = [];
                            foreach (explode('|', $v) as $p) {
                                $enumArr[] = self::_parseValue($p);
                            }
                            $schema['enum'] = array_values(array_unique($enumArr));
                        }
                    } elseif ($k === 'minItems' || $k === 'maxItems') {
                        $schema[$k] = self::_parseValue($v);
                    } elseif ($k === 'items') {
                        $schema['items'] = self::_buildSchema($v);
                    } else {
                        $schema[$k] = self::_parseValue($v);
                    }
                }

                return $schema;
            }

            if (array_keys($node) === range(0, count($node) - 1)) {
                if (count($node) === 1 && is_array($node[0])) {
                    return [
                        'type' => 'array',
                        'items' => self::_buildSchema($node[0])
                    ];
                } else {
                    $types = [];
                    foreach ($node as $item) {
                        if (is_string($item)) {
                            $parts = explode('|', $item);
                            foreach ($parts as $p) {
                                $types[] = trim($p, "'\"");
                            }
                        }
                    }
                    return [
                        'type' => 'array',
                        'items' => [
                            'type' => count($types) === 1 ? $types[0] : array_values(array_unique($types))
                        ]
                    ];
                }
            } else {
                $properties = [];
                foreach ($node as $key => $val) {
                    if ($key === 'required') continue;
                    $properties[$key] = self::_buildSchema($val);
                }
                $schema = [
                    'type' => 'object',
                    'properties' => $properties
                ];
                if (isset($node['required'])) {
                    $schema['required'] = self::_parseRequired($node['required']);
                }
                return $schema;
            }
        } elseif (is_string($node)) {
            $default = null;
            $hasDefault = false;

            if (str_contains($node, '=')) {
                list($node, $defaultStr) = explode('=', $node, 2);
                $hasDefault = true;
                $default = self::_parseValue($defaultStr);
            }

            if (str_starts_with($node, '[') && str_ends_with($node, ']')) {
                $inner = substr($node, 1, -1);
                $res = self::_buildSchema(explode(',', $inner));
                if ($hasDefault) {
                    $res['default'] = $default;
                }
                return $res;
            }

            $parts = explode('|', $node);
            $type = (count($parts) === 1) ? trim($parts[0], "'\"") : array_map(fn($p) => trim($p, "'\""), $parts);
            if (is_array($type)) {
                sort($type);
            }

            $res = ['type' => $type];
            if ($hasDefault) {
                $res['default'] = $default;
            }
            return $res;
        }

        return $node;
    }
}

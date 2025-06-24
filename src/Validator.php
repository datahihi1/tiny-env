<?php
namespace Datahihi1\TinyEnv;

use Exception;

/**
 * Validator class for validating environment variables.
 * 
 * This class provides methods to validate environment variables based on specified rules.
 */
final class Validator
{
    /**
     * Validate environment variables against specified rules.
     *
     * @param array<string, string|string[]> $rules Array of rules, e.g., ['DB_PORT' => 'required|int']
     * @throws Exception If validation fails
     * @return void
     */
    public static function validate(array $rules): void
    {
        foreach ($rules as $key => $rule) {
            $value = TinyEnv::env($key);
            $ruleParts = is_array($rule) ? $rule : explode('|', $rule);

            foreach ($ruleParts as $part) {
                $ruleComponents = explode(':', $part, 2);
                $ruleName = $ruleComponents[0];
                $ruleParam = $ruleComponents[1] ?? null;

                switch ($ruleName) {
                    case 'required':
                        if ($value === null || $value === '') {
                            throw new Exception("Environment variable '$key' is required but missing or empty");
                        }
                        break;

                    case 'int':
                    case 'integer':
                        if ($value !== null) {
                            if (is_int($value)) {
                            } elseif (is_numeric($value) && (int) $value == $value) {
                                $intValue = (int) $value;
                                $_ENV[$key] = $intValue;
                                TinyEnv::setCache($key, $intValue);
                                $value = $intValue;
                            } else {
                                throw new Exception("Environment variable '$key' must be an integer, got '" . var_export($value, true) . "'");
                            }
                        }
                        break;

                    case 'bool':
                    case 'boolean':
                        if ($value !== null) {
                            if (is_bool($value)) {
                            } elseif (is_scalar($value) && in_array(strtolower((string) $value), ['true', 'false', '1', '0'])) {
                                $boolValue = in_array(strtolower((string) $value), ['true', '1']);
                                $_ENV[$key] = $boolValue;
                                TinyEnv::setCache($key, $boolValue);
                                $value = $boolValue;
                            } else {
                                throw new Exception("Environment variable '$key' must be a boolean, got '" . var_export($value, true) . "'");
                            }
                        }
                        break;

                    case 'string':
                        if ($value !== null && !is_string($value)) {
                            throw new Exception("Environment variable '$key' must be a string, got '" . var_export($value, true) . "'");
                        }
                        break;

                    case 'url':
                        if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
                            throw new Exception("Environment variable '$key' must be a valid URL");
                        }
                        break;

                    case 'email':
                        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception("Environment variable '$key' must be a valid email address");
                        }
                        break;

                    case 'ip':
                        if ($value !== null && !filter_var($value, FILTER_VALIDATE_IP)) {
                            throw new Exception("Environment variable '$key' must be a valid IP address");
                        }
                        break;

                    case 'min':
                        if ($ruleParam === null) {
                            throw new Exception("Rule 'min' requires a parameter");
                        }
                        if ($value !== null) {
                            if (is_int($value) || is_float($value)) {
                                if ($value < $ruleParam) {
                                    throw new Exception("Environment variable '$key' must be at least $ruleParam");
                                }
                            } elseif (is_string($value)) {
                                if (strlen($value) < $ruleParam) {
                                    throw new Exception("Environment variable '$key' must be at least $ruleParam characters");
                                }
                            } else {
                                throw new Exception("Environment variable '$key' cannot be validated with 'min' rule");
                            }
                        }
                        break;

                    case 'max':
                        if ($ruleParam === null) {
                            throw new Exception("Rule 'max' requires a parameter");
                        }
                        if ($value !== null) {
                            if (is_int($value) || is_float($value)) {
                                if ($value > $ruleParam) {
                                    throw new Exception("Environment variable '$key' must not exceed $ruleParam");
                                }
                            } elseif (is_string($value)) {
                                if (strlen($value) > $ruleParam) {
                                    throw new Exception("Environment variable '$key' must not exceed $ruleParam characters");
                                }
                            } else {
                                throw new Exception("Environment variable '$key' cannot be validated with 'max' rule");
                            }
                        }
                        break;

                    case 'equal':
                        if ($ruleParam === null) {
                            throw new Exception("Rule 'equal' requires a parameter");
                        }
                        if (is_int($value)) {
                            if ($value !== (int) $ruleParam) {
                                throw new Exception("Environment variable '$key' must be equal to $ruleParam");
                            }
                        } elseif (is_bool($value)) {
                            $paramBool = in_array(strtolower($ruleParam), ['true', '1'], true);
                            if ($value !== $paramBool) {
                                throw new Exception("Environment variable '$key' must be equal to '$ruleParam'");
                            }
                        } else {
                            if ($value !== $ruleParam) {
                                throw new Exception("Environment variable '$key' must be equal to '$ruleParam'");
                            }
                        }
                        break;

                    default:
                        throw new Exception("Unknown validation rule: $ruleName");
                }
            }
        }
    }
}
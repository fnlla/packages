<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use Fnlla\Http\Request;
use Fnlla\Support\Validator;

final class ValidationHelper
{
    public static function errors(Request $request, array $rules, array $messages = []): array
    {
        if (method_exists($request, 'allInput')) {
            $input = $request->allInput();
        } else {
            $input = $request->all();
        }

        return self::errorsFromInput((array) $input, $rules, $messages);
    }

    public static function errorsFromInput(array $input, array $rules, array $messages = []): array
    {
        $validator = Validator::make($input, $rules, $messages);
        if ($validator->passes()) {
            return [];
        }

        return self::flattenErrors($validator->errors());
    }

    public static function formatAsHtmlList(array $errors): string
    {
        if ($errors === []) {
            return '';
        }

        return '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
    }

    private static function flattenErrors(array $errors): array
    {
        $flattened = [];
        foreach ($errors as $messages) {
            if (is_array($messages)) {
                foreach ($messages as $message) {
                    if (is_string($message) && $message !== '') {
                        $flattened[] = $message;
                    }
                }
                continue;
            }
            if (is_string($messages) && $messages !== '') {
                $flattened[] = $messages;
            }
        }

        return $flattened;
    }
}
